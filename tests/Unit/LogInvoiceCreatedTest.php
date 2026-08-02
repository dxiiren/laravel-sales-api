<?php

namespace Tests\Unit;

use App\Events\InvoiceCreated;
use App\Listeners\LogInvoiceCreated;
use App\Models\Sale;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The audit trail for every invoice is one line written by
 * `LogInvoiceCreated` to the dedicated `invoice` log channel
 * (`storage/logs/invoice.log`, config/logging.php). Writing it to the default
 * channel instead would scatter the audit trail into laravel.log unnoticed,
 * so the channel name is asserted, not just the message.
 */
class LogInvoiceCreatedTest extends TestCase
{
    public function testListenerWritesTheAuditLineToTheInvoiceChannel(): void
    {
        $sale = new Sale([
            'ref_num' => 'LOG-0001',
            'invoice_date' => '2026-01-15',
            'total' => '150.25',
        ]);

        Log::shouldReceive('channel')->once()->with('invoice')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('date: 2026-01-15, ref: LOG-0001, total: 150.25');

        (new LogInvoiceCreated)->handle(new InvoiceCreated($sale));
    }

    public function testListenerIsRegisteredForTheInvoiceCreatedEvent(): void
    {
        Event::fake();

        Event::assertListening(InvoiceCreated::class, LogInvoiceCreated::class);
    }
}
