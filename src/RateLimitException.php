<?php

declare(strict_types=1);

namespace RateApi;

/** Thrown on HTTP 429; carries retryAfter (seconds). */
class RateLimitException extends RateApiException
{
    public int $retryAfter;

    public function __construct(string $message = '', int $code = 429, ?string $errorType = null, ?string $requestId = null, int $retryAfter = 60)
    {
        parent::__construct($message, $code, $errorType, $requestId);
        $this->retryAfter = $retryAfter;
    }
}
