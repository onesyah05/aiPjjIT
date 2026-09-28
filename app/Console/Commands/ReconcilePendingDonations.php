<?php

namespace App\Console\Commands;

use App\Models\Donation;
use App\Services\DonationPaymentConfirmer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('donations:reconcile-pending')]
#[Description('Confirm pending donations against Pakasir when a webhook is delayed or unavailable')]
class ReconcilePendingDonations extends Command
{
    public function handle(DonationPaymentConfirmer $confirmer): int
    {
        if (! filled(config('services.pakasir.slug')) || ! filled(config('services.pakasir.api_key'))) {
            return self::SUCCESS;
        }

        $donations = Donation::query()
            ->where('status', 'pending')
            ->whereNotNull('pakasir_txn_id')
            ->where('created_at', '>=', now()->subDay())
            ->where('updated_at', '<=', now()->subMinute())
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($donations as $donation) {
            try {
                $confirmer->confirm($donation);
            } catch (Throwable $exception) {
                Log::warning('Donation reconciliation failed', [
                    'donation_id' => $donation->id,
                    'exception' => $exception,
                ]);
            } finally {
                if ($donation->fresh()?->status === 'pending') {
                    $donation->touch();
                }
            }
        }

        return self::SUCCESS;
    }
}
