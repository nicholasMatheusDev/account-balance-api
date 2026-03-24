<?php

namespace App\Domain;

interface AccountRepositoryInterface
{
    public function reset(): void;

    public function getBalance(string $accountId): ?int;

    public function deposit(string $accountId, int $amount): int;

    public function withdraw(string $accountId, int $amount): ?int;

    public function transfer(string $origin, string $destination, int $amount): ?array;
}
