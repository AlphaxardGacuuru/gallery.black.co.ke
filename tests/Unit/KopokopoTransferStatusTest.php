<?php

namespace Tests\Unit;

use App\Models\KopokopoTransfer;
use PHPUnit\Framework\TestCase;

class KopokopoTransferStatusTest extends TestCase
{
    private function transfer(string $status, string $disbursementStatus): KopokopoTransfer
    {
        $transfer = new KopokopoTransfer;
        $transfer->status = $status;
        $transfer->transfer_batches = [[
            'status' => $disbursementStatus,
            'disbursements' => [['status' => $disbursementStatus]],
        ]];

        return $transfer;
    }

    public function test_scheduled_top_level_with_transferred_disbursements_is_success(): void
    {
        $transfer = $this->transfer('Scheduled', 'Transferred');

        $this->assertTrue($transfer->isConfirmedSuccess());
        $this->assertFalse($transfer->isConfirmedFailure());
    }

    public function test_processed_is_success(): void
    {
        $this->assertTrue($this->transfer('Processed', 'Transferred')->isConfirmedSuccess());
    }

    public function test_scheduled_with_pending_disbursement_is_neither(): void
    {
        $transfer = $this->transfer('Scheduled', 'Pending');

        $this->assertFalse($transfer->isConfirmedSuccess());
        $this->assertFalse($transfer->isConfirmedFailure());
    }

    public function test_failed_is_failure(): void
    {
        $transfer = $this->transfer('Failed', 'Failed');

        $this->assertFalse($transfer->isConfirmedSuccess());
        $this->assertTrue($transfer->isConfirmedFailure());
    }

    public function test_failed_top_level_with_pending_disbursement_is_not_final(): void
    {
        $transfer = $this->transfer('Failed', 'Pending');

        $this->assertFalse($transfer->isConfirmedSuccess());
        $this->assertFalse($transfer->isConfirmedFailure());
    }
}
