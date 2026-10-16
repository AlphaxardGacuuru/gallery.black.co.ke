<?php

namespace App\Console\Commands;

use App\Http\Services\KopokopoTransferService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reconcile-kopokopo-transfers')]
#[Description('Poll Kopokopo for unfinished transfers and settle completed transfers')]
class ReconcileKopokopoTransfers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(KopokopoTransferService $service): int
    {
        $stats = $service->reconcileTransfers();

        $this->info(sprintf(
            'Checked %d transfers, polled %d, confirmed %d, errors %d.',
            $stats['checked'],
            $stats['polled'],
            $stats['confirmed'],
            $stats['errors'],
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
