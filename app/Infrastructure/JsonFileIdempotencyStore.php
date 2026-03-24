<?php

namespace App\Infrastructure;

use App\Application\Results\EventResult;
use App\Domain\IdempotencyStoreInterface;

class JsonFileIdempotencyStore implements IdempotencyStoreInterface
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('app/ebanx_idempotency.json');
        $this->ensureDir();
    }

    public function clear(): void
    {
        $this->withLock(function (&$state) {
            $state['events'] = [];

            return true;
        });
    }

    public function execute(string $key, array $payload, callable $operation): EventResult
    {
        return $this->withLock(function (&$state) use ($key, $payload, $operation) {
            $hash = $this->payloadHash($payload);
            $stored = $state['events'][$key] ?? null;

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

            $result = $operation();

            $state['events'][$key] = [
                'payload_hash' => $hash,
                'status' => $result->status,
                'payload' => $result->payload,
            ];

            return $result;
        });
    }

    private function withLock(callable $fn)
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open idempotency file');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock idempotency file');
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

    private function readFromHandle($handle): array
    {
        rewind($handle);
        $raw = stream_get_contents($handle);
        if ($raw === false || $raw === '') {
            return ['events' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['events']) || !is_array($decoded['events'])) {
            return ['events' => []];
        }

        return $decoded;
    }

    private function writeToHandle($handle, array $state): void
    {
        $encoded = json_encode($state);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode idempotency state');
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $encoded);
        fflush($handle);
    }

    private function payloadHash(array $payload): string
    {
        $encoded = json_encode($payload);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode idempotency payload');
        }

        return hash('sha256', $encoded);
    }

    private function ensureDir(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
