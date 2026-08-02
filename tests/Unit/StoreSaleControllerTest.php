<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;

class StoreSaleControllerTest extends TestCase
{
    public function testStoresNewSale(): void
    {
        $requestData = [
            'ref_num' => 'ABC123',
            'invoice_date' => '2024-02-11',
            'payee' => 'John Doe',
            'payee_id' => 123,
            'total' => 500.00,
            'currency_total' => 500.00,
            'paid' => 500.00,
            'due' => 0.00,
        ];

        $response = $this->post('/api/sales', $requestData);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Sale created successfully']);

        $this->assertDatabaseHas('sales', $requestData);
    }
}
