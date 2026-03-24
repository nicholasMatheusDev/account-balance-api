<?php

namespace App\Repositories;

use App\Services\Results\EventResult;

class JsonFileAccountRepository implements AccountRepositoryInterface
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('app/ebanx_state.json');
        $this->ensureDir();
    }

    public function reset(): void
    {
        $this->withLock(function (&$state) {
            $state['accounts'] = [];
            $state['events'] = [];

            return true;
        });
    }

    public function getBalance(string $accountId): ?int
    {
        $state = $this->readWithLock();
        return $state['accounts'][$accountId] ?? null;
    }

    public function processEvent(array $payload, ?string $idempotencyKey = null): EventResult
    {
        return $this->withLock(function (&$state) use ($payload, $idempotencyKey) {
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $hash = $this->payloadHash($payload);
                $stored = $state['events'][$idempotencyKey] ?? null;

                if (is_array($stored)) {
                    if (($stored['payload_hash'] ?? null) !== $hash) {
                        return EventResult::conflict([
                            'error' => 'idempotency_key_conflict',
                        ]);
                    }

                    return EventResult::fromStored(
                        (string) ($stored['status'] ?? EventResult::STATUS_CREATED),
                        is_array($stored['payload'] ?? null) ? $stored['payload'] : []
                    );
                }
            }

            $result = $this->processEventState($state, $payload);

            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $state['events'][$idempotencyKey] = [
                    'payload_hash' => $this->payloadHash($payload),
                    'status' => $result->status,
                    'payload' => $result->payload,
                ];
            }

            return $result;
        });
    }

    public function deposit(string $accountId, int $amount): int
    {
        return $this->withLock(function (&$state) use ($accountId, $amount) {
            return $this->applyDeposit($state, $accountId, $amount);
        });
    }

    public function withdraw(string $accountId, int $amount): ?int
    {
        return $this->withLock(function (&$state) use ($accountId, $amount) {
            $result = $this->applyWithdraw($state, $accountId, $amount);

            if ($result->status !== EventResult::STATUS_CREATED) {
                return null;
            }

            return $result->payload['origin']['balance'];
        });
    }

    public function transfer(string $origin, string $destination, int $amount): ?array
    {
        return $this->withLock(function (&$state) use ($origin, $destination, $amount) {
            $result = $this->applyTransfer($state, $origin, $destination, $amount);

            if ($result->status !== EventResult::STATUS_CREATED) {
                return null;
            }

            return [
                'origin' => $result->payload['origin']['balance'],
                'destination' => $result->payload['destination']['balance'],
            ];
        });
    }

    private function withLock(callable $fn)
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open state file');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock state file');
            }

            $state = $this->readFromHandle($handle);
            $result = $fn($state);

            if ($result !== null) {
                $this->writeToHandle($handle, $state);
            }

            flock($handle, LOCK_UN);
            return $result;
        } finally {
            fclose($handle);
        }
    }

    private function readWithLock(): array
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open state file');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new \RuntimeException('Unable to lock state file');
            }

            $state = $this->readFromHandle($handle);

            flock($handle, LOCK_UN);
            return $state;
        } finally {
            fclose($handle);
        }
    }

    private function readFromHandle($handle): array
    {
        rewind($handle);
        $raw = stream_get_contents($handle);
        if ($raw === false || $raw === '') {
            return $this->emptyState();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->emptyState();
        }

        $accounts = $decoded['accounts'] ?? null;
        $events = $decoded['events'] ?? null;

        return [
            'accounts' => is_array($accounts) ? $accounts : [],
            'events' => is_array($events) ? $events : [],
        ];
    }

    private function writeToHandle($handle, array $state): void
    {
        $encoded = json_encode($state);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode state');
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $encoded);
        fflush($handle);
    }

    private function ensureDir(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    private function processEventState(array &$state, array $payload): EventResult
    {
        $type = $payload['type'] ?? null;

        return match ($type) {
            'deposit' => EventResult::created([
                'destination' => [
                    'id' => (string) $payload['destination'],
                    'balance' => $this->applyDeposit($state, (string) $payload['destination'], (int) $payload['amount']),
                ],
            ]),
            'withdraw' => $this->applyWithdraw($state, (string) $payload['origin'], (int) $payload['amount']),
            'transfer' => $this->applyTransfer(
                $state,
                (string) $payload['origin'],
                (string) $payload['destination'],
                (int) $payload['amount']
            ),
            default => EventResult::notFound(),
        };
    }

    private function applyDeposit(array &$state, string $accountId, int $amount): int
    {
        $state['accounts'][$accountId] = ($state['accounts'][$accountId] ?? 0) + $amount;

        return $state['accounts'][$accountId];
    }

    private function applyWithdraw(array &$state, string $accountId, int $amount): EventResult
    {
        if (!array_key_exists($accountId, $state['accounts'])) {
            return EventResult::notFound();
        }

        if ($state['accounts'][$accountId] < $amount) {
            return EventResult::insufficientFunds();
        }

        $state['accounts'][$accountId] -= $amount;

        return EventResult::created([
            'origin' => [
                'id' => $accountId,
                'balance' => $state['accounts'][$accountId],
            ],
        ]);
    }

    private function applyTransfer(array &$state, string $origin, string $destination, int $amount): EventResult
    {
        if (!array_key_exists($origin, $state['accounts'])) {
            return EventResult::notFound();
        }

        if ($state['accounts'][$origin] < $amount) {
            return EventResult::insufficientFunds();
        }

        $state['accounts'][$origin] -= $amount;
        $state['accounts'][$destination] = ($state['accounts'][$destination] ?? 0) + $amount;

        return EventResult::created([
            'origin' => [
                'id' => $origin,
                'balance' => $state['accounts'][$origin],
            ],
            'destination' => [
                'id' => $destination,
                'balance' => $state['accounts'][$destination],
            ],
        ]);
    }

    private function payloadHash(array $payload): string
    {
        $encoded = json_encode($payload);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode idempotency payload');
        }

        return hash('sha256', $encoded);
    }

    private function emptyState(): array
    {
        return [
            'accounts' => [],
            'events' => [],
        ];
    }
}
