<?php

namespace Tests\Feature;

use App\Events\InvoiceCreated;
use App\Models\Sale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `Sale::$dispatchesEvents` fires `InvoiceCreated` on the model's `created`
 * event, and the event is queued + broadcast on the `invoice` channel. None of
 * that was asserted anywhere, so a dropped `$dispatchesEvents` entry or a
 * renamed channel would have been silent.
 */
class InvoiceCreatedEventTest extends TestCase
{
    private const PAYLOAD = [
        'ref_num' => 'EVT-0001',
        'invoice_date' => '2026-01-15',
        'payee' => 'Event Payee',
        'payee_id' => 42,
        'total' => 150.25,
        'currency_total' => 150.25,
        'paid' => 100.00,
        'due' => 50.25,
    ];

    public function testStoringASaleDispatchesInvoiceCreatedWithThatSale(): void
    {
        Event::fake([InvoiceCreated::class]);

        $this->postJson('/api/sales', self::PAYLOAD)->assertStatus(200);

        Event::assertDispatched(
            InvoiceCreated::class,
            fn (InvoiceCreated $event) => $event->sale->ref_num === 'EVT-0001'
                && (float) $event->sale->total === 150.25
        );
        Event::assertDispatchedTimes(InvoiceCreated::class, 1);

        // Faking the event must not stop the sale itself from being written.
        $this->assertDatabaseHas('sales', ['ref_num' => 'EVT-0001']);
    }

    public function testCreatingASaleThroughTheModelAlsoDispatchesTheEvent(): void
    {
        Event::fake([InvoiceCreated::class]);

        Sale::factory()->create(['ref_num' => 'EVT-MODEL']);

        Event::assertDispatched(
            InvoiceCreated::class,
            fn (InvoiceCreated $event) => $event->sale->ref_num === 'EVT-MODEL'
        );
    }

    public function testUpdatingASaleDoesNotRedispatchTheEvent(): void
    {
        $sale = Sale::factory()->create(['ref_num' => 'EVT-UPDATE']);

        Event::fake([InvoiceCreated::class]);

        $sale->update(['payee' => 'Renamed Payee']);

        Event::assertNotDispatched(InvoiceCreated::class);
    }

    public function testFailedValidationDispatchesNothingAndPersistsNothing(): void
    {
        Event::fake([InvoiceCreated::class]);

        $this->postJson('/api/sales', ['ref_num' => 'EVT-INVALID'])
            ->assertStatus(422);

        Event::assertNotDispatched(InvoiceCreated::class);
        $this->assertDatabaseMissing('sales', ['ref_num' => 'EVT-INVALID']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function testEventBroadcastsOnTheInvoiceChannel(): void
    {
        $sale = Sale::factory()->make(['ref_num' => 'EVT-CHANNEL']);

        $channels = (new InvoiceCreated($sale))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('invoice', $channels[0]->name);
    }

    public function testEventIsBothQueuedAndBroadcast(): void
    {
        $event = new InvoiceCreated(Sale::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $event);
        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }
}
