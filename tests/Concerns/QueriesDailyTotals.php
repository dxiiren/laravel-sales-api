<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Shared plumbing for the two daily-total twins.
 *
 * `CountDailySalesController` (POST /api/daily-sale) and the Lighthouse
 * `DailyTotalSales` mutation (POST /graphql) are deliberate twins that must
 * return the same money string for the same filters, so every test that
 * compares them drives both through the same two helpers.
 */
trait QueriesDailyTotals
{
    /**
     * The GraphQL document used for every daily-total call. All four arguments
     * are declared as variables so a test can send an explicit null, an empty
     * string, or omit the key entirely — the three "no filter" spellings.
     */
    private string $dailyTotalsDocument = <<<'GRAPHQL'
    mutation DailyTotals($start_date: Date!, $end_date: Date!, $payment_status: Int, $payee_id: ID) {
        DailyTotalSales(
            start_date: $start_date
            end_date: $end_date
            payment_status: $payment_status
            payee_id: $payee_id
        ) {
            amount
            payment_status
            payee_id
        }
    }
    GRAPHQL;

    /**
     * POST /api/daily-sale and return the `total_sale` money string.
     */
    private function restDailyTotal(array $filters): string
    {
        $response = $this->postJson('/api/daily-sale', $filters);

        $response->assertStatus(200);

        return $response->json('total_sale');
    }

    /**
     * POST /graphql and return the `amount` money string, failing loudly on any
     * GraphQL error rather than letting a null amount look like a passing test.
     */
    private function graphqlDailyTotal(array $variables): string
    {
        $response = $this->graphqlDailyTotalResponse($variables);

        $response->assertStatus(200);
        $this->assertNull(
            $response->json('errors'),
            'GraphQL returned errors: '.json_encode($response->json('errors'))
        );

        return $response->json('data.DailyTotalSales.amount');
    }

    /**
     * Raw GraphQL response, for tests that assert on the error payload itself.
     */
    private function graphqlDailyTotalResponse(array $variables): TestResponse
    {
        return $this->postJson('/graphql', [
            'query' => $this->dailyTotalsDocument,
            'variables' => $variables,
        ]);
    }

    /**
     * The `exists:users,id` rule guards `payee_id` on both twins, so any payee a
     * test filters by needs a matching users row. (TestCase already seeds 506.)
     */
    private function seedUser(int $id, string $name = 'Parity Payee'): void
    {
        if (DB::table('users')->where('id', $id)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'password' => 'not-a-real-password',
        ]);
    }
}
