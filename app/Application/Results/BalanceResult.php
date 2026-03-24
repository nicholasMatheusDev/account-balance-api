<?php

namespace App\Application\Results;

class BalanceResult
{
    public function __construct(
        public readonly bool $found,
        public readonly int $balance
    ) {
    }

    public static function notFound(): self
    {
        return new self(false, 0);
    }

    public static function found(int $balance): self
    {
        return new self(true, $balance);
    }
}
