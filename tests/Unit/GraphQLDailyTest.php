<?php

namespace Tests\Unit;

use App\Models\Sale;
use Tests\Concerns\QueriesDailyTotals;
use Tests\TestCase;

/**
 * The Lighthouse `DailyTotalSales` mutation.
 *
 * This test used to POST to the hard-coded absolute URL
 * `http://biztory.test/graphql` and assert `assertNotNull` on the amount —
 * which is the string "RM 0.00" against an empty database, so it could never
 * fail. It now posts to the relative `/graphql` route the app actually
 * registers and asserts real, seeded totals.
 */
class GraphQLDailyTest extends TestCase
{
    use QueriesDailyTotals;

    /** Seeded by Tests\TestCase for the `exists:users,id` rule on payee_id. */
    private const PAYEE = 506;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUser(301, 'Other Payee');

        Sale::factory()->create([
            'ref_num' => 'GQL-1', 'total' => 200.00, 'payment_status' => 1,
            'payee_id' => self::PAYEE, 'created_at' => '2023-02-01 09:00:00',
        ]);
        Sale::factory()->create([
            'ref_num' => 'GQL-2', 'total' => 350.00, 'payment_status' => 1,
            'payee_id' => self::PAYEE, 'created_at' => '2023-05-20 09:00:00',
        ]);
        Sale::factory()->create([
            'ref_num' => 'GQL-3', 'total' => 75.00, 'payment_status' => 0,
            'payee_id' => self::PAYEE, 'created_at' => '2023-03-15 09:00:00',
        ]);
        Sale::factory()->create([
            'ref_num' => 'GQL-4', 'total' => 999.00, 'payment_status' => 1,
            'payee_id' => 301, 'created_at' => '2023-04-02 09:00:00',
        ]);
        // Outside the 2023-01-01..2023-06-30 window asserted below.
        Sale::factory()->create([
            'ref_num' => 'GQL-5', 'total' => 500.00, 'payment_status' => 1,
            'payee_id' => self::PAYEE, 'created_at' => '2023-11-01 09:00:00',
        ]);
    }

    public function testDailyTotalSalesMutation(): void
    {
        $response = $this->graphqlDailyTotalResponse([
            'start_date' => '2023-01-01',
            'end_date' => '2023-06-30',
            'payment_status' => 1,
            'payee_id' => self::PAYEE,
        ]);

        // Only GQL-1 + GQL-2 qualify: paid, payee 506, inside the window.
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'DailyTotalSales' => [
                        'amount' => 'RM 550.00',
                        'payment_status' => 1,
                        'payee_id' => '506',
                    ],
                ],
            ]);
    }

    public function testTotalCoversEveryRowInTheWindowWhenNoFilterIsGiven(): void
    {
        $amount = $this->graphqlDailyTotal([
            'start_date' => '2023-01-01',
            'end_date' => '2023-06-30',
        ]);

        // Everything but the November sale.
        $this->assertSame('RM 1,624.00', $amount);
    }

    public function testUnpaidFilterTotalsOnlyUnpaidSales(): void
    {
        $amount = $this->graphqlDailyTotal([
            'start_date' => '2023-01-01',
            'end_date' => '2023-06-30',
            'payment_status' => 0,
        ]);

        $this->assertSame('RM 75.00', $amount);
    }

    public function testAWindowWithNoSalesTotalsZero(): void
    {
        $amount = $this->graphqlDailyTotal([
            'start_date' => '2022-01-01',
            'end_date' => '2022-12-31',
        ]);

        $this->assertSame('RM 0.00', $amount);
    }
}
