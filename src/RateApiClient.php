<?php

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

    /** Time series between two dates (Business+). Max 366 days. */
    public function timeseries(string $startDate, string $endDate, string $base = 'USD', array $symbols = []): array
    {
        return $this->get('timeseries', $this->symbolsQuery([
            'start_date' => $startDate, 'end_date' => $endDate, 'base' => $base,
        ], $symbols));
    }

    /** Fluctuation between two dates (Business+). */
    public function fluctuation(string $startDate, string $endDate, string $base = 'USD', array $symbols = []): array
    {
        return $this->get('fluctuation', $this->symbolsQuery([
            'start_date' => $startDate, 'end_date' => $endDate, 'base' => $base,
        ], $symbols));
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

    /** Public service health (no key needed, but sent anyway). */
    public function health(): array
    {
        return $this->getPublic('health');
    }

    // ---- v2 endpoints (resolve to /api/v2 regardless of the configured base) ----

    private function v2Base(): string
    {
        if (str_ends_with($this->baseUrl, '/v1')) {
            return substr($this->baseUrl, 0, -3) . '/v2';
        }
        return str_replace('/v1/', '/v2/', $this->baseUrl);
    }

    /** v2 latest with metadata / 24h change / precision options (Pro+ for change). */
    public function latestV2(string $base = 'USD', array $symbols = [], array $opts = []): array
    {
        $query = $this->symbolsQuery(['base' => $base], $symbols);
        if (!empty($opts['include_metadata'])) {
            $query['include_metadata'] = 'true';
        }
        if (!empty($opts['include_change'])) {
            $query['include_change'] = 'true';
        }
        if (isset($opts['precision'])) {
            $query['precision'] = $opts['precision'];
        }
        return $this->request($this->v2Base() . '/' . $this->apiKey . '/latest', $query);
    }

    /** v2 historical with an optional compareDate -> per-currency deltas (Pro+). */
    public function historicalCompare(string $date, ?string $compareDate = null, string $base = 'USD', array $symbols = []): array
    {
        $query = $this->symbolsQuery(['date' => $date, 'base' => $base], $symbols);
        if ($compareDate) {
            $query['compare_date'] = $compareDate;
        }
        return $this->request($this->v2Base() . '/' . $this->apiKey . '/historical', $query);
    }

    /** v2 batch conversion: array of ['from'=>,'to'=>,'amount'=>] (Pro+). Max 100. */
    public function batchConvert(array $conversions): array
    {
        return $this->request($this->v2Base() . '/' . $this->apiKey . '/batch-convert', [], 'POST', $conversions);
    }

    /** List your configured rate alerts (Business+). Manage them in the dashboard. */
    public function alerts(): array
    {
        return $this->request($this->v2Base() . '/' . $this->apiKey . '/alerts', []);
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
