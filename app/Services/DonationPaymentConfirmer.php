<?php

namespace App\Services;

use App\Jobs\RefreshDonationLeaderboard;
use App\Models\Donation;
use Illuminate\Support\Facades\DB;

class DonationPaymentConfirmer
{
    public function __construct(private readonly PakasirClient $pakasir) {}

    public function confirm(Donation $donation): bool
    {
        if ($donation->status === 'paid') {
            return true;
        }

        if (! filled($donation->pakasir_txn_id)) {
            return false;
        }

        $status = $this->pakasir->transactionStatus($donation->pakasir_txn_id);
        if (($status['status'] ?? null) !== 'completed'
            || ($status['txn_id'] ?? null) !== $donation->pakasir_txn_id
            || ($status['order_id'] ?? null) !== $donation->order_id
            || ($status['amount'] ?? null) !== $donation->amount
            || ($status['is_sandbox'] ?? null) !== $donation->is_sandbox) {
            return false;
        }

        $wasPaid = DB::transaction(function () use ($donation): bool {
            $locked = Donation::query()->lockForUpdate()->findOrFail($donation->id);
            if ($locked->status === 'paid') {
                return false;
            }

            $locked->update(['status' => 'paid', 'paid_at' => now()]);

            return true;
        });

        if ($wasPaid) {
            RefreshDonationLeaderboard::dispatch($donation->channel_id);
        }

        return true;
    }
}
