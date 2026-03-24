<?php

namespace App\Repositories;

use App\Services\Results\EventResult;

interface AccountRepositoryInterface
{
    public function reset(): void;

    public function getBalance(string $accountId): ?int;

    public function processEvent(array $payload, ?string $idempotencyKey = null): EventResult;

    public function deposit(string $accountId, int $amount): int;

    public function withdraw(string $accountId, int $amount): ?int;

    public function transfer(string $origin, string $destination, int $amount): ?array;
}
