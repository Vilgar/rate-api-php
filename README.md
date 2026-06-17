# Rate-API PHP Client

Official PHP client for the [Rate-API.com](https://rate-api.com) exchange-rate & crypto API.

## Install

```bash
composer require rate-api/rate-api-php
```

Or use it standalone — the package has no dependencies beyond `ext-curl` and `ext-json`:

```php
require 'src/RateApiException.php';
require 'src/RateApiClient.php';
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

// Time series & fluctuation (Business+)
$series = $client->timeseries('2026-01-01', '2026-01-31', 'USD', ['EUR']);
$flux   = $client->fluctuation('2026-01-01', '2026-01-31');

// Crypto (Pro+)
$crypto = $client->crypto(['BTC', 'ETH']);

// Public health (no plan needed)
$health = $client->health();
```

## Errors

Any non-success response throws `RateApi\RateApiException` (the HTTP status is the exception code):

```php
use RateApi\RateApiException;

try {
    $client->timeseries('2026-01-01', '2026-12-31');
} catch (RateApiException $e) {
    echo $e->getMessage();  // e.g. "Date range too large. Maximum is 366 days."
    echo $e->getCode();     // e.g. 400
}
```

## v2 features

The client exposes the v2 endpoints directly (they resolve to `/api/v2` regardless of base URL):

```php
// Latest with 24h change, metadata and precision
$r = $client->latestV2('USD', ['EUR', 'GBP'], ['include_change' => true, 'include_metadata' => true, 'precision' => 4]);
echo $r['changes_pct']['EUR'];

// Historical comparison between two dates (Pro+)
$cmp = $client->historicalCompare('2026-01-15', '2026-01-01', 'USD', ['EUR']);

// Batch conversion — up to 100 pairs in one call (Pro+)
$batch = $client->batchConvert([
    ['from' => 'USD', 'to' => 'EUR', 'amount' => 100],
    ['from' => 'GBP', 'to' => 'JPY', 'amount' => 50],
]);

// Your configured rate alerts (Business+)
$alerts = $client->alerts();
```

## Authentication

The key is sent as an `X-API-Key` header (and in the URL path). You can also pass a v2 base URL:

```php
$client = new RateApiClient('YOUR_API_KEY', 'https://rate-api.com/api/v2');
```

## License

MIT
