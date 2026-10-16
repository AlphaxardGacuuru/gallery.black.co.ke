<?php

namespace Tests\Feature;

use App\Events\KopokopoTransferRecordedEvent;
use App\Models\KopokopoTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class KopokopoTransferReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_webhooks_update_the_existing_transfer(): void
    {
        $payload = $this->webhook('Scheduled');
        $this->postJson('/api/kopokopo-transfers', $payload)->assertOk();

        $payload['data']['attributes']['status'] = 'Processed';
        $payload['data']['attributes']['transfer_batches'][0]['status'] = 'Transferred';
        $payload['data']['attributes']['transfer_batches'][0]['disbursements'][0]['status'] = 'Transferred';
        $this->postJson('/api/kopokopo-transfers', $payload)->assertOk();

        $this->assertDatabaseCount('kopokopo_transfers', 1);
        $this->assertDatabaseHas('kopokopo_transfers', [
            'kopokopo_id' => 'transfer-123',
            'status' => 'Transferred',
        ]);
    }

    public function test_reconciler_dispatches_settlement_for_previously_saved_transferred_disbursements(): void
    {
        Event::fake([KopokopoTransferRecordedEvent::class]);

        $transfer = new KopokopoTransfer;
        $transfer->kopokopo_id = 'transfer-123';
        $transfer->status = 'Scheduled';
        $transfer->transfer_batches = [[
            'status' => 'Transferred',
            'disbursements' => [['status' => 'Transferred']],
        ]];
        $transfer->save();

        $this->artisan('app:reconcile-kopokopo-transfers')
            ->expectsOutput('Checked 1 transfers, polled 0, confirmed 1, errors 0.')
            ->assertExitCode(0);

        Event::assertDispatched(KopokopoTransferRecordedEvent::class, fn ($event) => $event->kopokopoTransfer->is($transfer));
        $this->assertDatabaseHas('kopokopo_transfers', [
            'id' => $transfer->id,
            'status' => 'Transferred',
        ]);
    }

    private function webhook(string $status): array
    {
        return [
            'data' => [
                'id' => 'transfer-123',
                'attributes' => [
                    'status' => $status,
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'metadata' => [],
                    'destinations' => [['amount' => '500.0', 'nickname' => 'Jane', 'phone_number' => '254700123456']],
                    'transfer_batches' => [[
                        'status' => 'Scheduled',
                        'disbursements' => [['status' => 'Pending']],
                    ]],
                ],
            ],
        ];
    }
}
