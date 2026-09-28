<?php

namespace App\Jobs;

use App\Models\Donation;
use App\Services\PakasirClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateDonationCheckout implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $donationId,
        public readonly string $applicationId,
        public readonly string $interactionToken,
    ) {
        $this->onQueue('discord');
    }

    public function handle(PakasirClient $pakasir): void
    {
        $donation = Donation::query()->findOrFail($this->donationId);

        if (! filled($donation->payment_url)) {
            $transaction = $pakasir->createTransaction($donation);
            if (! filled($transaction['txn_id'] ?? null) || ! filled($transaction['payment_link'] ?? null)
                || parse_url((string) $transaction['payment_link'], PHP_URL_SCHEME) !== 'https'
                || parse_url((string) $transaction['payment_link'], PHP_URL_HOST) !== 'app.pakasir.com') {
                throw new \RuntimeException('Pakasir returned an incomplete transaction.');
            }

            $donation->update([
                'pakasir_txn_id' => (string) $transaction['txn_id'],
                'payment_url' => (string) $transaction['payment_link'],
            ]);
        }

        $this->editResponse('Pembayaran Rp'.number_format($donation->amount, 0, ',', '.').' dibuat. Lanjutkan melalui tautan berikut; nama Anda masuk leaderboard setelah pembayaran dikonfirmasi.', $donation->payment_url);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Donation checkout failed', ['donation_id' => $this->donationId, 'error' => $exception?->getMessage()]);

        try {
            $this->editResponse('Maaf, checkout donasi gagal dibuat. Silakan coba lagi nanti.');
        } catch (Throwable $notificationError) {
            Log::warning('Discord donation failure notification failed', ['donation_id' => $this->donationId, 'error' => $notificationError->getMessage()]);
        }
    }

    private function editResponse(string $message, ?string $paymentUrl = null): void
    {
        $components = $paymentUrl === null ? [] : [[
            'type' => 1,
            'components' => [['type' => 2, 'style' => 5, 'label' => 'Buka halaman pembayaran', 'url' => $paymentUrl]],
        ]];

        Http::acceptJson()->connectTimeout(5)->timeout(10)->patch(
            'https://discord.com/api/v10/webhooks/'.$this->applicationId.'/'.$this->interactionToken.'/messages/@original',
            ['content' => $message, 'components' => $components, 'allowed_mentions' => ['parse' => []]],
        )->throw();
    }
}
