<?php

namespace Tests\Feature;

use Tests\TestCase;

class FileStateTest extends TestCase
{
    public function test_state_persists_within_process(): void
    {
        $this->post('/reset')->assertOk()->assertSeeText('OK');

        $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ])->assertCreated()->assertJson([
            'destination' => [
                'id' => '100',
                'balance' => 10,
            ],
        ]);

        $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ])->assertCreated()->assertJson([
            'destination' => [
                'id' => '100',
                'balance' => 20,
            ],
        ]);

        $this->get('/balance?account_id=100')
            ->assertOk()
            ->assertSeeText('20');
    }

    public function test_reset_clears_state(): void
    {
        $this->post('/reset')->assertOk()->assertSeeText('OK');

        $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ])->assertCreated();

        $this->post('/reset')->assertOk()->assertSeeText('OK');

        $this->get('/balance?account_id=100')
            ->assertStatus(404)
            ->assertSeeText('0');
    }

    public function test_event_with_same_idempotency_key_is_applied_once(): void
    {
        $this->post('/reset')->assertOk();

        $payload = [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ];

        $this->withHeaders([
            'Idempotency-Key' => 'deposit-100-1',
        ])->postJson('/event', $payload)
            ->assertCreated()
            ->assertJson([
                'destination' => [
                    'id' => '100',
                    'balance' => 10,
                ],
            ]);

        $this->withHeaders([
            'Idempotency-Key' => 'deposit-100-1',
        ])->postJson('/event', $payload)
            ->assertCreated()
            ->assertJson([
                'destination' => [
                    'id' => '100',
                    'balance' => 10,
                ],
            ]);

        $this->get('/balance?account_id=100')
            ->assertOk()
            ->assertSeeText('10');
    }

    public function test_event_with_same_idempotency_key_and_different_payload_returns_conflict(): void
    {
        $this->post('/reset')->assertOk();

        $this->withHeaders([
            'Idempotency-Key' => 'deposit-100-conflict',
        ])->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ])->assertCreated();

        $this->withHeaders([
            'Idempotency-Key' => 'deposit-100-conflict',
        ])->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 25,
        ])->assertStatus(409)
            ->assertJson([
                'error' => 'idempotency_key_conflict',
            ]);

        $this->get('/balance?account_id=100')
            ->assertOk()
            ->assertSeeText('10');
    }

    public function test_withdraw_with_insufficient_funds_returns_error_and_keeps_balance(): void
    {
        $this->post('/reset')->assertOk();

        $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ])->assertCreated();

        $this->postJson('/event', [
            'type' => 'withdraw',
            'origin' => '100',
            'amount' => 20,
        ])->assertStatus(422)
            ->assertJson([
                'error' => 'insufficient_funds',
            ]);

        $this->get('/balance?account_id=100')
            ->assertOk()
            ->assertSeeText('10');
    }

    public function test_transfer_with_insufficient_funds_returns_error_and_keeps_balances(): void
    {
        $this->post('/reset')->assertOk();

        $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ])->assertCreated();

        $this->postJson('/event', [
            'type' => 'transfer',
            'origin' => '100',
            'destination' => '200',
            'amount' => 20,
        ])->assertStatus(422)
            ->assertJson([
                'error' => 'insufficient_funds',
            ]);

        $this->get('/balance?account_id=100')
            ->assertOk()
            ->assertSeeText('10');

        $this->get('/balance?account_id=200')
            ->assertStatus(404)
            ->assertSeeText('0');
    }

    public function test_idempotent_withdraw_with_insufficient_funds_is_replayed_without_changing_balance(): void
    {
        $this->post('/reset')->assertOk();

        $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ])->assertCreated();

        $headers = [
            'Idempotency-Key' => 'withdraw-insufficient-1',
        ];

        $payload = [
            'type' => 'withdraw',
            'origin' => '100',
            'amount' => 20,
        ];

        $this->withHeaders($headers)->postJson('/event', $payload)
            ->assertStatus(422)
            ->assertJson([
                'error' => 'insufficient_funds',
            ]);

        $this->withHeaders($headers)->postJson('/event', $payload)
            ->assertStatus(422)
            ->assertJson([
                'error' => 'insufficient_funds',
            ]);

        $this->get('/balance?account_id=100')
            ->assertOk()
            ->assertSeeText('10');
    }
}
