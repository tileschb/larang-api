<?php

namespace App\Exceptions;

class ApiException extends \Exception
{

    protected string $statusCode;
    protected ApiExceptions $type;
    protected ?array $details = null;

    public static function new(
        ApiExceptions $type = ApiExceptions::UNKNOWN,
        ?string $message = null,
        ?string $statusCode = null,
        ?array $details = []
    ): static
    {
        $e = new static(
            $message ?? $type->getMessage(),
            $statusCode ?? $type->getStatusCode(),
        );

        $e->type = $type;
        $e->details = $details;
        $e->statusCode = $statusCode ?? $type->getStatusCode();

        return $e;
    }

    public function getTypeName(): string
    {
        return $this->type->name;
    }

    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

}
