<?php

declare(strict_types=1);

namespace RateApi;

/**
 * Thrown when the API returns an error or the request fails.
 * getCode() is the HTTP status. errorType is the stable error.type slug;
 * requestId is the X-Request-Id for support correlation.
 */
class RateApiException extends \RuntimeException
{
    public ?string $errorType;
    public ?string $requestId;

    public function __construct(string $message = '', int $code = 0, ?string $errorType = null, ?string $requestId = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
        $this->requestId = $requestId;
    }
}
