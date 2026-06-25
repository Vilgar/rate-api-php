# Changelog

All notable changes to `rate-api/rate-api-php` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.4] - 2026-06-25

### Added
- **Rate alerts** (Business+): `listAlerts()`, `createAlert()` and `deleteAlert()`
  for managing threshold alerts on a currency pair.

### Changed
- Aligned the client with the current API surface: added `usage()` and `quota()`,
  and removed endpoints no longer offered (timeseries/fluctuation/batch-convert).
  Brings the PHP client to parity with the JS, Python and MCP SDKs at 1.0.4.

## [1.0.2] - 2026-06-17

### Changed
- Updated the package author contact email.

## [1.0.1] - 2026-06-17

### Fixed
- **PSR-4 autoloading of exception subclasses.** `RateLimitException` and
  `RateApiTimeoutException` were declared inside `RateApiException.php`, so under the
  standard PSR-4 autoloader they failed to load standalone — a consumer catching
  `RateLimitException` on an HTTP 429 (or `RateApiTimeoutException` on a timeout) could
  hit a fatal "class not found". Each exception now lives in its own file.

### Changed
- Bounded the PHP requirement to `^8.0` (was `>=8.0`).
- Added `declare(strict_types=1)` across the source.
- Added an `authors` entry and a richer `Errors` section in the README documenting
  `retryAfter`, `errorType`, and `requestId`.

## [1.0.0] - 2026-06-17

### Added
- Initial release: v1/v2 exchange-rate, conversion, historical, timeseries,
  fluctuation, crypto, currencies, batch-convert, alerts, and health endpoints with
  retry/backoff and typed exceptions.
