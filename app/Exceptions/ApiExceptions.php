<?php

namespace App\Exceptions;

enum ApiExceptions: int
{
    case UNKNOWN = 500_999;
    case INVALID_CREDENTIALS = 401_001;

    public function getMessage(): string
    {
        return match ($this) {
            self::UNKNOWN => 'An unknown error occurred.',
            self::INVALID_CREDENTIALS => 'Invalid credentials provided.',
        };
    }

    public function getStatusCode(): int
    {
        $value = $this->value;

        return match (true) {
            $value >= 500_000 => 500,
            $value >= 422_000 => 422,
            $value >= 429_000 => 429,
            $value >= 415_000 => 415,
            $value >= 405_000 => 405,
            $value >= 404_000 => 404,
            $value >= 403_000 => 403,
            $value >= 401_000 => 401,
            $value >= 400_000 => 400,

            default => 500
        };
    }

}
