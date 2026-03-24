<?php

namespace App\Services;

use App\Repositories\AccountRepositoryInterface;

class ResetStateService
{
    public function __construct(private readonly AccountRepositoryInterface $accounts)
    {
    }

    public function handle(): void
    {
        $this->accounts->reset();
    }
}
