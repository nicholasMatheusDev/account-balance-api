<?php

namespace App\Application;

use App\Application\Results\EventResult;
use App\Domain\AccountRepositoryInterface;
use App\Domain\IdempotencyStoreInterface;

class ProcessEvent
{
    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly IdempotencyStoreInterface $idempotency
    ) {
    }

    public function handle(array $payload, ?string $idempotencyKey = null): EventResult
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $this->process($payload);
        }

        return $this->idempotency->execute(
            $idempotencyKey,
            $payload,
            fn () => $this->process($payload)
        );
    }

    private function process(array $payload): EventResult
    {
        $type = $payload['type'] ?? null;

        return match ($type) {
            'deposit' => $this->handleDeposit(
                (string) $payload['destination'],
                (int) $payload['amount']
            ),
            'withdraw' => $this->handleWithdraw(
                (string) $payload['origin'],
                (int) $payload['amount']
            ),
            'transfer' => $this->handleTransfer(
                (string) $payload['origin'],
                (string) $payload['destination'],
                (int) $payload['amount']
            ),
            default => EventResult::notFound(),
        };
    }

    private function handleDeposit(string $destination, int $amount): EventResult
    {
        $balance = $this->accounts->deposit($destination, $amount);

        return EventResult::created([
            'destination' => [
                'id' => $destination,
                'balance' => $balance,
            ],
        ]);
    }

    private function handleWithdraw(string $origin, int $amount): EventResult
    {
        $balance = $this->accounts->withdraw($origin, $amount);

        if ($balance === null) {
            return EventResult::notFound();
        }

        return EventResult::created([
            'origin' => [
                'id' => $origin,
                'balance' => $balance,
            ],
        ]);
    }

    private function handleTransfer(string $origin, string $destination, int $amount): EventResult
    {
        $balances = $this->accounts->transfer($origin, $destination, $amount);

        if ($balances === null) {
            return EventResult::notFound();
        }

        return EventResult::created([
            'origin' => [
                'id' => $origin,
                'balance' => $balances['origin'],
            ],
            'destination' => [
                'id' => $destination,
                'balance' => $balances['destination'],
            ],
        ]);
    }
}
