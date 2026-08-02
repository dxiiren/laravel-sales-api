<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\Concerns\QueriesDailyTotals;
use Tests\TestCase;

/**
 * REST/GraphQL twin parity.
 *
 * `CountDailySalesController::buildQuery` and
 * `App\GraphQL\Mutations\DailyTotalSales::buildQuery` are hand-maintained
 * copies of the same aggregation. They have already drifted once (the REST
 * twin used a truthy check and silently dropped `payment_status=0`), so every
 * filter shape is asserted against BOTH endpoints here: the same seeded rows,
 * the same filters, and an assertSame() between the two money strings.
 *
 * Each scenario that should match rows also asserts the total is NOT the
 * zero string, so an accidentally-empty result set cannot make parity pass
 * tautologically.
 */
class RestGraphqlParityTest extends TestCase
{
    use QueriesDailyTotals;

    /** Payee that owns the two January sales (seeded by Tests\TestCase). */
    private const PAYEE_A = 506;

    /** Payee that owns the February and March sales. */
    private const PAYEE_B = 777;

    private const ZERO = 'RM 0.00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUser(self::PAYEE_B, 'Parity Payee B');

        // A fixed, fully deterministic ledger: two payees, both payment
        // statuses, three different months.
        Sale::factory()->create([
            'ref_num' => 'PAR-A', 'total' => 100.00, 'created_at' => '2026-01-10 12:00:00',
            'payee_id' => self::PAYEE_A, 'payment_status' => 0,
        ]);
        Sale::factory()->create([
            'ref_num' => 'PAR-B', 'total' => 250.50, 'created_at' => '2026-01-20 12:00:00',
            'payee_id' => self::PAYEE_A, 'payment_status' => 1,
        ]);
        Sale::factory()->create([
            'ref_num' => 'PAR-C', 'total' => 49.50, 'created_at' => '2026-02-15 12:00:00',
            'payee_id' => self::PAYEE_B, 'payment_status' => 0,
        ]);
        Sale::factory()->create([
            'ref_num' => 'PAR-D', 'total' => 1200.00, 'created_at' => '2026-03-05 12:00:00',
            'payee_id' => self::PAYEE_B, 'payment_status' => 1,
        ]);
    }

    public function testNoFilterTotalsMatch(): void
    {
        // Neither optional key is sent at all — REST sees them missing from
        // validated(), GraphQL never adds them to the argument map.
        $this->assertTwinsAgree(
            ['start_date' => '2026-01-01', 'end_date' => '2026-03-31'],
            'RM 1,600.00'
        );
    }

    public function testExplicitNullFiltersMeanNoFilterOnBothTwins(): void
    {
        $this->assertTwinsAgree(
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'payment_status' => null,
                'payee_id' => null,
            ],
            'RM 1,600.00'
        );
    }

    public function testEmptyStringFiltersMeanNoFilterOnBothTwins(): void
    {
        // The HTML-form spelling of "no filter". REST has always honoured it;
        // the GraphQL twin rejected it until `nullable` was added to the
        // payee_id @rules and the `!== ''` guard to its buildQuery().
        $this->assertTwinsAgree(
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'payment_status' => null,
                'payee_id' => '',
            ],
            'RM 1,600.00'
        );
    }

    public function testPaymentStatusZeroTotalsMatch(): void
    {
        // The exact drift of bug 0afc055: 0 is a real filter, not "no filter".
        $this->assertTwinsAgree(
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'payment_status' => 0,
            ],
            'RM 149.50'
        );
    }

    public function testPaymentStatusOneTotalsMatch(): void
    {
        $this->assertTwinsAgree(
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'payment_status' => 1,
            ],
            'RM 1,450.50'
        );
    }

    public function testPayeeIdFilterTotalsMatch(): void
    {
        $this->assertTwinsAgree(
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'payee_id' => self::PAYEE_A,
            ],
            'RM 350.50'
        );
    }

    public function testCombinedPayeeAndPaymentStatusTotalsMatch(): void
    {
        $this->assertTwinsAgree(
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'payment_status' => 1,
                'payee_id' => self::PAYEE_B,
            ],
            'RM 1,200.00'
        );
    }

    public function testDateRangeMatchingSomeRowsTotalsMatch(): void
    {
        $this->assertTwinsAgree(
            ['start_date' => '2026-01-01', 'end_date' => '2026-01-31'],
            'RM 350.50'
        );
    }

    public function testDateRangeMatchingNothingTotalsMatch(): void
    {
        // The one scenario where the zero string is the correct answer; asserted
        // explicitly rather than through assertTwinsAgree's non-zero guard.
        $rest = $this->restDailyTotal(['start_date' => '2020-01-01', 'end_date' => '2020-12-31']);
        $graphql = $this->graphqlDailyTotal(['start_date' => '2020-01-01', 'end_date' => '2020-12-31']);

        $this->assertSame(self::ZERO, $rest);
        $this->assertSame($rest, $graphql);
    }

    /**
     * Drive both twins with the same filters and assert they return the same
     * money string — and that the string is the expected non-zero total, so an
     * empty result set cannot satisfy the parity assertion by accident.
     */
    private function assertTwinsAgree(array $filters, string $expected): void
    {
        $rest = $this->restDailyTotal($filters);
        $graphql = $this->graphqlDailyTotal($filters);

        $this->assertNotSame(self::ZERO, $rest, 'Scenario matched no rows — the parity assertion would be vacuous.');
        $this->assertSame($expected, $rest, 'REST total_sale is wrong.');
        $this->assertSame($rest, $graphql, 'REST and GraphQL daily totals have drifted apart.');
    }
}
