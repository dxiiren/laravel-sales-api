<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\Concerns\QueriesDailyTotals;
use Tests\TestCase;

/**
 * `Sale` uses SoftDeletes, so a deleted invoice keeps its row (`deleted_at`
 * set) but must stop counting toward money totals — on BOTH the REST endpoint
 * and its GraphQL twin, since each builds its own query. Restoring must put
 * the amount back; force-deleting must actually remove the row.
 */
class SaleSoftDeleteTest extends TestCase
{
    use QueriesDailyTotals;

    private const START = '2026-01-01';

    private const END = '2026-01-31';

    private Sale $kept;

    private Sale $doomed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kept = Sale::factory()->create([
            'ref_num' => 'SD-KEPT', 'total' => 100.00,
            'created_at' => '2026-01-10 12:00:00', 'payee_id' => 506,
        ]);

        $this->doomed = Sale::factory()->create([
            'ref_num' => 'SD-DOOMED', 'total' => 250.00,
            'created_at' => '2026-01-11 12:00:00', 'payee_id' => 506,
        ]);
    }

    public function testSoftDeletedSaleDropsOutOfBothTotals(): void
    {
        $this->assertSame('RM 350.00', $this->restTotal());
        $this->assertSame('RM 350.00', $this->graphqlTotal());

        $this->doomed->delete();

        $this->assertSoftDeleted('sales', ['ref_num' => 'SD-DOOMED']);
        $this->assertSame('RM 100.00', $this->restTotal(), 'A soft-deleted sale still counts toward the REST total.');
        $this->assertSame('RM 100.00', $this->graphqlTotal(), 'A soft-deleted sale still counts toward the GraphQL total.');
    }

    public function testRestoringASalePutsItBackInBothTotals(): void
    {
        $this->doomed->delete();
        $this->assertSame('RM 100.00', $this->restTotal());

        $this->doomed->restore();

        $this->assertDatabaseHas('sales', ['ref_num' => 'SD-DOOMED', 'deleted_at' => null]);
        $this->assertSame('RM 350.00', $this->restTotal());
        $this->assertSame('RM 350.00', $this->graphqlTotal());
    }

    public function testSoftDeletedSaleIsStillReachableThroughTrashedScopes(): void
    {
        $this->doomed->delete();

        $this->assertCount(1, Sale::onlyTrashed()->get());
        $this->assertCount(1, Sale::query()->get(), 'The default scope must hide trashed sales.');
        $this->assertCount(2, Sale::withTrashed()->get());
    }

    public function testForceDeleteRemovesTheRowEntirely(): void
    {
        $this->doomed->delete();

        // Guard against a vacuous assertDatabaseMissing: the row is provably
        // there (trashed) before the force delete removes it.
        $this->assertTrue(Sale::onlyTrashed()->where('ref_num', 'SD-DOOMED')->exists());

        $this->doomed->forceDelete();

        $this->assertDatabaseMissing('sales', ['ref_num' => 'SD-DOOMED']);
        $this->assertCount(0, Sale::withTrashed()->where('ref_num', 'SD-DOOMED')->get());
        $this->assertSame('RM 100.00', $this->restTotal());
        $this->assertSame('RM 100.00', $this->graphqlTotal());
    }

    private function restTotal(): string
    {
        return $this->restDailyTotal(['start_date' => self::START, 'end_date' => self::END]);
    }

    private function graphqlTotal(): string
    {
        return $this->graphqlDailyTotal(['start_date' => self::START, 'end_date' => self::END]);
    }
}
