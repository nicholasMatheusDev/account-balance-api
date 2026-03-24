<?php

namespace App\Repositories;

use App\Services\Results\EventResult;

interface AccountRepositoryInterface
{
    public function reset(): void;

    public function getBalance(string $accountId): ?int;

    public function processEvent(array $payload, ?string $idempotencyKey = null): EventResult;
}
