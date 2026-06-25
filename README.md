# Rate-API PHP Client

Official PHP client for the [Rate-API.com](https://rate-api.com) exchange-rate & crypto API.

## Install

```bash
composer require rate-api/rate-api-php
```

Or use it standalone — the package has no dependencies beyond `ext-curl` and `ext-json`:

```php
require __DIR__ . '/src/RateApiException.php';
require __DIR__ . '/src/RateLimitException.php';
require __DIR__ . '/src/RateApiTimeoutException.php';
require __DIR__ . '/src/RateApiClient.php';
```

## Usage

```php
use RateApi\RateApiClient;

$client = new RateApiClient('YOUR_API_KEY');

// Latest rates
$rates = $client->latest('USD', ['EUR', 'GBP', 'JPY']);
echo $rates['rates']['EUR'];

// Convert (Pro+)
$result = $client->convert('USD', 'EUR', 100);
echo $result['result'];

// Single pair
$pair = $client->pair('USD', 'EUR');

// Historical (Pro+)
$hist = $client->historical('2026-01-15', 'USD', ['EUR']);

// Crypto (Pro+)
$crypto = $client->crypto(['BTC', 'ETH']);

// Usage vs. quota for this key
$usage = $client->usage();

// Public health (no plan needed)
$health = $client->health();
```

Methods: `latest` · `convert` · `pair` · `historical` · `crypto` · `currencies` · `usage` · `quota` · `health`

## Errors

Any non-success response throws `RateApi\RateApiException` (the HTTP status is the
exception code). Two subclasses extend it for specific cases, so a single
`catch (RateApiException $e)` still handles everything:

| Exception | When | Extra |
|---|---|---|
| `RateApiException` | any API/HTTP error | `getCode()` (HTTP status), `$e->errorType` (stable `error.type` slug), `$e->requestId` (`X-Request-Id`, for support) |
| `RateLimitException` | HTTP 429 | `$e->retryAfter` (seconds to wait) |
| `RateApiTimeoutException` | request timed out | — |

```php
use RateApi\RateApiException;
use RateApi\RateLimitException;

try {
    $client->convert('USD', 'ZZZ', 100);
} catch (RateLimitException $e) {
    sleep($e->retryAfter);          // honour the server's backoff
} catch (RateApiException $e) {
    echo $e->getMessage();          // e.g. "Invalid target currency"
    echo $e->getCode();             // e.g. 400
    echo $e->errorType;             // e.g. "invalid_target_currency"
    echo $e->requestId;             // quote this when contacting support
}
```

## Authentication

The key is sent as an `X-API-Key` header (and in the URL path). You can also pass a v2 base URL:

```php
$client = new RateApiClient('YOUR_API_KEY', 'https://rate-api.com/api/v2');
```

## License

MIT
