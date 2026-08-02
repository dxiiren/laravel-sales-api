<?php

namespace Tests\Feature;

use Tests\Concerns\QueriesDailyTotals;
use Tests\TestCase;

/**
 * The 422 contract of `CountDailySalesRequest::failedValidation` — it does NOT
 * use Laravel's default shape: it throws an `HttpResponseException` carrying
 * `{"errors": {...}}` with no top-level `message` key, and it ships custom
 * messages. Consumers depend on that shape, so it is pinned here alongside the
 * GraphQL twin's `@rules` equivalents.
 */
class DailySaleValidationTest extends TestCase
{
    use QueriesDailyTotals;

    public function testMissingStartDateIsRejected(): void
    {
        $response = $this->postJson('/api/daily-sale', ['end_date' => '2026-01-31']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', 'The start date is required.');
    }

    public function testMissingEndDateIsRejected(): void
    {
        $response = $this->postJson('/api/daily-sale', ['start_date' => '2026-01-01']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.end_date.0', 'The end date is required.');
    }

    public function testNonDateStartDateIsRejected(): void
    {
        $response = $this->postJson('/api/daily-sale', [
            'start_date' => 'not-a-date',
            'end_date' => '2026-01-31',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', 'The start date must be a valid date.');
    }

    public function testEndDateBeforeStartDateIsRejected(): void
    {
        $response = $this->postJson('/api/daily-sale', [
            'start_date' => '2026-01-31',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.end_date.0', 'The end date must be after or equal to the start date.');
    }

    public function testEndDateEqualToStartDateIsAccepted(): void
    {
        $response = $this->postJson('/api/daily-sale', [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('total_sale', 'RM 0.00');
    }

    public function testUnknownPayeeIdIsRejected(): void
    {
        $response = $this->postJson('/api/daily-sale', [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'payee_id' => 999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['payee_id']]);
    }

    public function testTheErrorPayloadOnlyContainsTheErrorsKey(): void
    {
        // failedValidation() replaces Laravel's default {message, errors} body
        // with a bare {errors} body — assert the contract, not just the status.
        $response = $this->postJson('/api/daily-sale', []);

        $response->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'start_date' => ['The start date is required.'],
                    'end_date' => ['The end date is required.'],
                ],
            ]);
    }

    public function testGraphqlTwinRejectsEndDateBeforeStartDate(): void
    {
        $response = $this->graphqlDailyTotalResponse([
            'start_date' => '2026-01-31',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['errors'])
            ->assertJsonPath('data.DailyTotalSales', null);

        $this->assertArrayHasKey('end_date', $response->json('errors.0.extensions.validation'));
    }

    public function testGraphqlTwinRejectsUnknownPayeeId(): void
    {
        $response = $this->graphqlDailyTotalResponse([
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'payee_id' => 999999,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['errors']);

        $this->assertArrayHasKey('payee_id', $response->json('errors.0.extensions.validation'));
    }

    public function testGraphqlTwinRejectsAMissingStartDate(): void
    {
        // start_date is Date! — the non-null variable is refused before the
        // resolver (and before @rules) ever runs.
        $response = $this->graphqlDailyTotalResponse(['end_date' => '2026-01-31']);

        $response->assertStatus(200)
            ->assertJsonStructure(['errors']);

        $this->assertStringContainsString('start_date', $response->json('errors.0.message'));
    }
}
