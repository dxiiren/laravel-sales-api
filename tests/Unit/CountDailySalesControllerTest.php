<?php

namespace Tests\Unit;

use App\Models\Sale;
use Tests\Concerns\QueriesDailyTotals;
use Tests\TestCase;

/**
 * `CountDailySalesController` (POST /api/daily-sale).
 *
 * This test used to assert `assertNotNull` on `total_sale` against an empty
 * database — a tautology, because the controller always returns the non-null
 * string "RM 0.00". It now seeds a known ledger and asserts the exact totals
 * each filter combination must produce.
 */
class CountDailySalesControllerTest extends TestCase
{
    use QueriesDailyTotals;

    /** Seeded by Tests\TestCase for the `exists:users,id` rule on payee_id. */
    private const PAYEE = 506;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUser(301, 'Other Payee');

        Sale::factory()->create([
            'ref_num' => 'CDS-1', 'total' => 200.00, 'payment_status' => 1,
            'payee_id' => self::PAYEE, 'created_at' => '2023-02-01 09:00:00',
        ]);
        Sale::factory()->create([
            'ref_num' => 'CDS-2', 'total' => 350.00, 'payment_status' => 1,
            'payee_id' => self::PAYEE, 'created_at' => '2023-05-20 09:00:00',
        ]);
        Sale::factory()->create([
            'ref_num' => 'CDS-3', 'total' => 75.00, 'payment_status' => 0,
            'payee_id' => self::PAYEE, 'created_at' => '2023-03-15 09:00:00',
        ]);
        Sale::factory()->create([
            'ref_num' => 'CDS-4', 'total' => 999.00, 'payment_status' => 1,
            'payee_id' => 301, 'created_at' => '2023-04-02 09:00:00',
        ]);
        // Outside the 2023-01-01..2023-06-30 window asserted below.
        Sale::factory()->create([
            'ref_num' => 'CDS-5', 'total' => 500.00, 'payment_status' => 1,
            'payee_id' => self::PAYEE, 'created_at' => '2023-11-01 09:00:00',
        ]);
    }

    public function testCountDailySales(): void
    {
        $response = $this->postJson('/api/daily-sale', [
            'start_date' => '2023-01-01',
            'end_date' => '2023-06-30',
            'payee_id' => '',
            'payment_status' => '',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'message' => 'Sale successfully counted',
                'total_sale' => 'RM 1,624.00', // everything but the November sale
            ]);
    }

    public function testFilteringByPayeeAndPaymentStatusNarrowsTheTotal(): void
    {
        $total = $this->restDailyTotal([
            'start_date' => '2023-01-01',
            'end_date' => '2023-06-30',
            'payee_id' => self::PAYEE,
            'payment_status' => 1,
        ]);

        $this->assertSame('RM 550.00', $total);
    }

    public function testSalesOutsideTheWindowAreExcluded(): void
    {
        $total = $this->restDailyTotal([
            'start_date' => '2023-10-01',
            'end_date' => '2023-12-31',
        ]);

        $this->assertSame('RM 500.00', $total);
    }

    public function testAWindowWithNoSalesTotalsZero(): void
    {
        $total = $this->restDailyTotal([
            'start_date' => '2022-01-01',
            'end_date' => '2022-12-31',
        ]);

        $this->assertSame('RM 0.00', $total);
    }
}
