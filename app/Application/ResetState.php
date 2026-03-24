<?php

namespace App\Application;

use App\Domain\AccountRepositoryInterface;
use App\Domain\IdempotencyStoreInterface;

class ResetState
{
    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly IdempotencyStoreInterface $idempotency
    ) {
    }

    public function handle(): void
    {
        $this->accounts->reset();
        $this->idempotency->clear();
    }
}
