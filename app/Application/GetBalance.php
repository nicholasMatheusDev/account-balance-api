<?php

namespace App\Application;

use App\Application\Results\BalanceResult;
use App\Domain\AccountRepositoryInterface;

class GetBalance
{
    public function __construct(private readonly AccountRepositoryInterface $accounts)
    {
    }

    public function handle(string $accountId): BalanceResult
    {
        $balance = $this->accounts->getBalance($accountId);

        if ($balance === null) {
            return BalanceResult::notFound();
        }

        return BalanceResult::found($balance);
    }
}
