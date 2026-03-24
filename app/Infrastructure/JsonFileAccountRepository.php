<?php

namespace App\Infrastructure;

use App\Domain\AccountRepositoryInterface;

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
            return true;
        });
    }

    public function getBalance(string $accountId): ?int
    {
        $state = $this->readWithLock();
        return $state['accounts'][$accountId] ?? null;
    }

    public function deposit(string $accountId, int $amount): int
    {
        return $this->withLock(function (&$state) use ($accountId, $amount) {
            $state['accounts'][$accountId] = ($state['accounts'][$accountId] ?? 0) + $amount;
            return $state['accounts'][$accountId];
        });
    }

    public function withdraw(string $accountId, int $amount): ?int
    {
        return $this->withLock(function (&$state) use ($accountId, $amount) {
            if (!array_key_exists($accountId, $state['accounts'])) {
                return null;
            }
            $state['accounts'][$accountId] -= $amount;
            return $state['accounts'][$accountId];
        });
    }

    public function transfer(string $origin, string $destination, int $amount): ?array
    {
        return $this->withLock(function (&$state) use ($origin, $destination, $amount) {
            if (!array_key_exists($origin, $state['accounts'])) {
                return null;
            }

            $state['accounts'][$origin] -= $amount;
            $state['accounts'][$destination] = ($state['accounts'][$destination] ?? 0) + $amount;

            return [
                'origin' => $state['accounts'][$origin],
                'destination' => $state['accounts'][$destination],
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
            return ['accounts' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['accounts']) || !is_array($decoded['accounts'])) {
            return ['accounts' => []];
        }

        return $decoded;
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
}
