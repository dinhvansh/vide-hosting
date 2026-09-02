<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:cancel-stale')]
#[Description('Cancel pending payment orders that passed their checkout expiry')]
class CancelStalePaymentOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BillingService $billing): int
    {
        $this->info($billing->cancelStale().' stale payment order(s) cancelled.');

        return self::SUCCESS;
    }
}
