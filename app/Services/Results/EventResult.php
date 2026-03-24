<?php

namespace App\Services\Results;

class EventResult
{
    public const STATUS_CREATED = 'created';
    public const STATUS_NOT_FOUND = 'not_found';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_INSUFFICIENT_FUNDS = 'insufficient_funds';

    public function __construct(
        public readonly string $status,
        public readonly array $payload
    ) {
    }

    public static function created(array $payload): self
    {
        return new self(self::STATUS_CREATED, $payload);
    }

    public static function notFound(): self
    {
        return new self(self::STATUS_NOT_FOUND, []);
    }

    public static function conflict(array $payload): self
    {
        return new self(self::STATUS_CONFLICT, $payload);
    }

    public static function insufficientFunds(): self
    {
        return new self(self::STATUS_INSUFFICIENT_FUNDS, [
            'error' => 'insufficient_funds',
        ]);
    }

    public static function fromStored(string $status, array $payload): self
    {
        return new self($status, $payload);
    }
}
