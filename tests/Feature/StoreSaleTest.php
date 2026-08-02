<?php

namespace Tests\Feature;

use Tests\TestCase;

class StoreSaleTest extends TestCase
{
    /**
     * POST /api/sales happy path — with the test-only schema scaffolding in
     * Tests\TestCase the endpoint is assertable end-to-end on sqlite :memory:
     * (200 + the row actually lands in `sales`).
     */
    public function testStoreSaleHappyPathPersistsARow(): void
    {
        $payload = [
            'ref_num' => 'FT-0001',
            'invoice_date' => '2026-01-15',
            'payee' => 'Feature Payee',
            'payee_id' => 42,
            'total' => 150.25,
            'currency_total' => 150.25,
            'paid' => 100.00,
            'due' => 50.25,
        ];

        $response = $this->postJson('/api/sales', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Sale created successfully'])
            ->assertJsonPath('sale.ref_num', 'FT-0001');

        $this->assertDatabaseHas('sales', [
            'ref_num' => 'FT-0001',
            'payee' => 'Feature Payee',
            'payee_id' => 42,
        ]);
    }
}
