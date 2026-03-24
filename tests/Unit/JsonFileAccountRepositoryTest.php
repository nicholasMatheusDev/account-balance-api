<?php

namespace Tests\Unit;

use App\Infrastructure\JsonFileAccountRepository;
use PHPUnit\Framework\TestCase;

class JsonFileAccountRepositoryTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ebanx_state_test.json';
        if (file_exists($this->statePath)) {
            unlink($this->statePath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->statePath)) {
            unlink($this->statePath);
        }
        parent::tearDown();
    }

    private function freshRepo(): JsonFileAccountRepository
    {
        $repo = new JsonFileAccountRepository($this->statePath);
        $repo->reset();

        return $repo;
    }

    public function test_deposit_creates_an_account(): void
    {
        $repo = $this->freshRepo();

        $repo->deposit('100', 10);

        $this->assertSame(10, $repo->getBalance('100'));
    }

    public function test_deposit_accumulates_balance(): void
    {
        $repo = $this->freshRepo();

        $repo->deposit('100', 10);
        $repo->deposit('100', 15);

        $this->assertSame(25, $repo->getBalance('100'));
    }

    public function test_withdraw_reduces_balance(): void
    {
        $repo = $this->freshRepo();

        $repo->deposit('100', 20);

        $repo->withdraw('100', 5);

        $this->assertSame(15, $repo->getBalance('100'));
    }

    public function test_withdraw_missing_account_returns_null(): void
    {
        $repo = $this->freshRepo();

        $result = $repo->withdraw('missing', 10);

        $this->assertNull($result);
    }

    public function test_transfer_creates_destination_if_missing(): void
    {
        $repo = $this->freshRepo();

        $repo->deposit('100', 20);

        $repo->transfer('100', '200', 5);

        $this->assertSame(15, $repo->getBalance('100'));
        $this->assertSame(5, $repo->getBalance('200'));
    }

    public function test_transfer_fails_if_origin_missing(): void
    {
        $repo = $this->freshRepo();

        $result = $repo->transfer('missing', '200', 10);

        $this->assertNull($result);
        $this->assertNull($repo->getBalance('missing'));
        $this->assertNull($repo->getBalance('200'));
    }

    public function test_reset_clears_state(): void
    {
        $repo = $this->freshRepo();

        $repo->deposit('100', 10);

        $repo->reset();

        $this->assertNull($repo->getBalance('100'));
    }
}
