<?php

namespace App\Domain;

use App\Application\Results\EventResult;

interface IdempotencyStoreInterface
{
    public function clear(): void;

    public function execute(string $key, array $payload, callable $operation): EventResult;
}
