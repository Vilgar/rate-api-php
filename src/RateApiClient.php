<?php

declare(strict_types=1);

namespace RateApi;

/**
 * Official PHP client for the Rate-API.com API.
 *
 *   $rates = (new RateApiClient('YOUR_API_KEY'))->latest('USD', ['EUR', 'GBP']);
 */
class RateApiClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $maxRetries;

    public function __construct(string $apiKey, string $baseUrl = 'https://rate-api.com/api/v1', int $timeout = 15, int $maxRetries = 2)
    {
        if ($apiKey === '') {
            throw new RateApiException('An API key is required.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /** Latest exchange rates for a base currency. */
    public function latest(string $base = 'USD', array $symbols = []): array
    {
        return $this->get('latest', $this->symbolsQuery(['base' => $base], $symbols));
    }

    /** Convert an amount between two currencies (Pro+). */
    public function convert(string $from, string $to, float $amount): array
    {
        return $this->get('convert', ['from' => $from, 'to' => $to, 'amount' => $amount]);
    }

    /** Historical rates for a YYYY-MM-DD date (Pro+). */
    public function historical(string $date, string $base = 'USD', array $symbols = []): array
    {
        return $this->get('historical', $this->symbolsQuery(['date' => $date, 'base' => $base], $symbols));
    }

    /** Single pair rate, e.g. pair('USD', 'EUR'). */
    public function pair(string $from, string $to): array
    {
        return $this->get('pair/' . rawurlencode($from) . '/' . rawurlencode($to));
    }

    /** Top cryptocurrency prices in USD (Pro+). */
    public function crypto(array $symbols = []): array
    {
        $query = $symbols ? ['symbols' => implode(',', $symbols)] : [];
        return $this->get('crypto', $query);
    }

    /** List supported currencies. */
    public function currencies(): array
    {
        return $this->get('currencies');
    }

    /** This key's current-month usage vs. its plan quota. */
    public function usage(): array
    {
        return $this->get('usage');
    }

    /** Lean plan-quota / remaining-requests view. */
    public function quota(): array
    {
        return $this->get('quota');
    }

    /** List rate alerts on this key (Business+). */
    public function listAlerts(): array
    {
        return $this->get('alerts');
    }

    /** Create a rate alert (Business+). $notifyUrl is an optional signed webhook target. */
    public function createAlert(string $from, string $to, string $direction, float $threshold, ?string $notifyUrl = null): array
    {
        $payload = ['from' => $from, 'to' => $to, 'direction' => $direction, 'threshold' => $threshold];
        if ($notifyUrl !== null) {
            $payload['notify_url'] = $notifyUrl;
        }
        return $this->request($this->baseUrl . '/' . $this->apiKey . '/alerts', [], 'POST', $payload);
    }

    /** Delete a rate alert by id (Business+). */
    public function deleteAlert(int $id): array
    {
        return $this->request($this->baseUrl . '/' . $this->apiKey . '/alerts/' . rawurlencode((string) $id), [], 'DELETE');
    }

    /** Public service health (no key needed, but sent anyway). */
    public function health(): array
    {
        return $this->getPublic('health');
    }

    private function symbolsQuery(array $query, array $symbols): array
    {
        if ($symbols) {
            $query['symbols'] = implode(',', $symbols);
        }
        return $query;
    }

    private function get(string $endpoint, array $query = []): array
    {
        return $this->request($this->baseUrl . '/' . $this->apiKey . '/' . $endpoint, $query);
    }

    private function getPublic(string $endpoint, array $query = []): array
    {
        return $this->request($this->baseUrl . '/' . $endpoint, $query);
    }

    private function backoffMs(int $attempt, array $headers): int
    {
        if (isset($headers['retry-after']) && is_numeric($headers['retry-after'])) {
            return min((int) $headers['retry-after'], 30) * 1000;
        }
        return (int) min(1000 * (2 ** ($attempt - 1)), 8000);
    }

    private function request(string $url, array $query, string $method = 'GET', ?array $payload = null): array
    {
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
            'User-Agent: rate-api-php/1.0',
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $attempt = 0;
        while (true) {
            $respHeaders = [];
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                    $p = explode(':', $line, 2);
                    if (count($p) === 2) {
                        $respHeaders[strtolower(trim($p[0]))] = trim($p[1]);
                    }
                    return strlen($line);
                },
            ];
            if ($method !== 'GET') {
                $opts[CURLOPT_CUSTOMREQUEST] = $method;
            }
            if ($payload !== null) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            curl_close($ch);

            // Network/timeout error: retry, then throw (timeout vs generic).
            if ($body === false) {
                if ($attempt < $this->maxRetries) {
                    $attempt++;
                    usleep($this->backoffMs($attempt, $respHeaders) * 1000);
                    continue;
                }
                if ($errno === CURLE_OPERATION_TIMEDOUT) {
                    throw new RateApiTimeoutException('Request timed out after ' . $this->timeout . 's');
                }
                throw new RateApiException('Request failed: ' . $err);
            }

            // Retry transient statuses, honoring Retry-After.
            if (($status === 429 || $status === 503) && $attempt < $this->maxRetries) {
                $attempt++;
                usleep($this->backoffMs($attempt, $respHeaders) * 1000);
                continue;
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                throw new RateApiException('Invalid JSON response (HTTP ' . $status . ').', $status);
            }

            if (($data['success'] ?? false) === false) {
                $message = $data['error']['message'] ?? ($data['message'] ?? 'Unknown API error');
                $type = $data['error']['type'] ?? null;
                $rid = $data['request_id'] ?? ($respHeaders['x-request-id'] ?? null);
                if ($status === 429) {
                    throw new RateLimitException($message, $status, $type, $rid, (int) ($respHeaders['retry-after'] ?? 60));
                }
                throw new RateApiException($message, $status, $type, $rid);
            }

            return $data;
        }
    }
}
