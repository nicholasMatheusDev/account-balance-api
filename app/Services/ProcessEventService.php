<?php

namespace App\Services;

use App\Repositories\AccountRepositoryInterface;
use App\Services\Results\EventResult;

class ProcessEventService
{
    public function __construct(private readonly AccountRepositoryInterface $accounts)
    {
    }

    public function handle(array $payload, ?string $idempotencyKey = null): EventResult
    {
        return $this->accounts->processEvent($payload, $idempotencyKey);
    }
}
