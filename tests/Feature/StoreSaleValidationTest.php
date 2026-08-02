<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every rule in `StoreSaleRequest::rules()` must reject the request AND leave
 * the `sales` table untouched — `StoreSaleController` calls `Sale::create()`
 * straight from `validated()`, so a rule that silently stops firing would
 * write a malformed invoice row (and dispatch `InvoiceCreated` for it).
 */
class StoreSaleValidationTest extends TestCase
{
    private const VALID = [
        'ref_num' => 'SSV-0001',
        'invoice_date' => '2026-01-15',
        'payee' => 'Valid Payee',
        'payee_id' => 42,
        'total' => 150.25,
        'currency_total' => 150.25,
        'paid' => 100.00,
        'due' => 50.25,
    ];

    public function testTheBaselinePayloadIsAccepted(): void
    {
        // Guards the negative tests below: they only prove a rule fired if the
        // very same payload minus/with one bad field would otherwise pass.
        $this->postJson('/api/sales', self::VALID)->assertStatus(200);

        $this->assertDatabaseCount('sales', 1);
    }

    public function testEveryRequiredFieldIsRequired(): void
    {
        foreach (array_keys(self::VALID) as $field) {
            $payload = self::VALID;
            unset($payload[$field]);

            $this->postJson('/api/sales', $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('sales', 0);
    }

    public function testRefNumLongerThanFiftyCharactersIsRejected(): void
    {
        $this->assertRejects(['ref_num' => str_repeat('X', 51)], 'ref_num');
    }

    public function testRefNumOfExactlyFiftyCharactersIsAccepted(): void
    {
        $this->postJson('/api/sales', array_merge(self::VALID, ['ref_num' => str_repeat('X', 50)]))
            ->assertStatus(200);
    }

    public function testNonStringRefNumIsRejected(): void
    {
        $this->assertRejects(['ref_num' => ['an', 'array']], 'ref_num');
    }

    public function testNonDateInvoiceDateIsRejected(): void
    {
        $this->assertRejects(['invoice_date' => 'not-a-date'], 'invoice_date');
    }

    public function testPayeeLongerThanTwoHundredFiftyFiveCharactersIsRejected(): void
    {
        $this->assertRejects(['payee' => str_repeat('Y', 256)], 'payee');
    }

    public function testNonIntegerPayeeIdIsRejected(): void
    {
        $this->assertRejects(['payee_id' => 'forty-two'], 'payee_id');
    }

    public function testNonNumericMoneyFieldsAreRejected(): void
    {
        foreach (['total', 'currency_total', 'paid', 'due'] as $field) {
            $this->assertRejects([$field => 'a lot of money'], $field);
        }
    }

    /**
     * Send the valid payload with one field replaced by a bad value and assert
     * the request is refused and nothing at all is written.
     */
    private function assertRejects(array $override, string $expectedErrorField): void
    {
        $this->postJson('/api/sales', array_merge(self::VALID, $override))
            ->assertStatus(422)
            ->assertJsonValidationErrors($expectedErrorField);

        $this->assertDatabaseCount('sales', 0);
    }
}
