<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\TestCase;

class CountDailySalesTest extends TestCase
{
    /**
     * POST /api/daily-sale happy path — seed two sales, count them over a
     * range spanning today, no filters (empty-string keys, as the baseline
     * unit test sends them).
     */
    public function testCountsSeededSalesInRange(): void
    {
        Sale::factory()->create(['total' => 100.50]);
        Sale::factory()->create(['total' => 200.25]);

        $response = $this->postJson('/api/daily-sale', [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'payment_status' => '',
            'payee_id' => '',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sale successfully counted',
                'total_sale' => 'RM 300.75',
            ]);
    }

    /**
     * Regression test: omitting the nullable keys entirely used to 500 with
     * `Undefined array key "payment_status"`, because buildQuery() read the
     * keys straight out of validated() — which only contains keys present in
     * the request. Fixed with a null-coalescing read in
     * CountDailySalesController::buildQuery(); an omitted key now means
     * "no filter", exactly like sending it empty.
     */
    public function testOmittedNullableKeysAreTreatedAsNoFilter(): void
    {
        Sale::factory()->create(['total' => 75.00]);

        $response = $this->postJson('/api/daily-sale', [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            // payment_status and payee_id deliberately omitted
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sale successfully counted',
                'total_sale' => 'RM 75.00',
            ]);
    }

    /**
     * payment_status 0 (unpaid) is a legitimate filter value, not "no filter".
     * The REST controller used a truthy check, so requesting payment_status=0
     * silently dropped the filter and counted every sale in range — while the
     * GraphQL twin (DailyTotalSales, isset-based) filtered correctly. The two
     * endpoints must agree: only the unpaid sale may be counted here.
     */
    public function testPaymentStatusZeroIsAppliedAsARealFilter(): void
    {
        Sale::factory()->create(['total' => 40.00, 'payment_status' => 0]); // unpaid
        Sale::factory()->create(['total' => 60.00, 'payment_status' => 1]); // paid

        $response = $this->postJson('/api/daily-sale', [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'payment_status' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sale successfully counted',
                'total_sale' => 'RM 40.00',
            ]);
    }

    /**
     * The empty-string form value must keep meaning "no filter" (the baseline
     * happy-path test relies on it) even once 0 is honoured as a real value.
     */
    public function testEmptyStringPaymentStatusStillMeansNoFilter(): void
    {
        Sale::factory()->create(['total' => 40.00, 'payment_status' => 0]);
        Sale::factory()->create(['total' => 60.00, 'payment_status' => 1]);

        $response = $this->postJson('/api/daily-sale', [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'payment_status' => '',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sale successfully counted',
                'total_sale' => 'RM 100.00',
            ]);
    }
}
