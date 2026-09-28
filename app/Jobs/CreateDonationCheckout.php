<?php

namespace App\Jobs;

use App\Models\Donation;
use App\Services\PakasirClient;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CreateDonationCheckout implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

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

        if (! filled($donation->qr_string)) {
            $transaction = $pakasir->createTransaction($donation);
            if (! filled($transaction['txn_id'] ?? null)
                || ! filled($transaction['qr_string'] ?? null)
                || ($transaction['payment_method'] ?? null) !== 'qris'
                || ($transaction['project'] ?? null) !== config('services.pakasir.slug')
                || ($transaction['status'] ?? null) !== 'pending'
                || (int) ($transaction['amount'] ?? 0) !== $donation->amount
                || ($transaction['order_id'] ?? null) !== $donation->order_id
                || ! is_bool($transaction['is_sandbox'] ?? null)
                || $transaction['is_sandbox'] !== $donation->is_sandbox
                || (int) ($transaction['total_payment'] ?? 0) < $donation->amount
                || ! filled($transaction['expired_at'] ?? null)) {
                throw new RuntimeException('Pakasir returned an incomplete QRIS transaction.');
            }

            $donation->update([
                'pakasir_txn_id' => (string) $transaction['txn_id'],
                'qr_string' => (string) $transaction['qr_string'],
                'total_payment' => (int) $transaction['total_payment'],
                'qris_expires_at' => $transaction['expired_at'],
            ]);
        }

        if ($donation->qris_expires_at->isPast()) {
            throw new RuntimeException('Pakasir QRIS transaction has expired.');
        }

        if (! filled($donation->dm_message_id)) {
            $this->sendQrCode($donation);
        }

        $this->editResponse('QRIS donasi telah dikirim ke DM Anda. Bayar sesuai total yang tertera sebelum kedaluwarsa; nama Anda masuk leaderboard setelah pembayaran terkonfirmasi.');
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Donation checkout failed', ['donation_id' => $this->donationId, 'error' => $exception?->getMessage()]);

        try {
            $sent = Donation::query()->whereKey($this->donationId)->whereNotNull('dm_message_id')->exists();
            $message = $sent
                ? 'QRIS donasi telah dikirim ke DM Anda. Nama Anda masuk leaderboard setelah pembayaran terkonfirmasi.'
                : 'Maaf, QRIS donasi belum berhasil dikirim. Pastikan DM dari anggota server diizinkan, lalu coba lagi. Belum ada donasi yang dihitung.';
            $this->editResponse($message);
        } catch (Throwable $notificationError) {
            Log::warning('Discord donation failure notification failed', ['donation_id' => $this->donationId, 'error' => $notificationError->getMessage()]);
        }
    }

    private function sendQrCode(Donation $donation): void
    {
        if (! filled(config('services.discord.bot_token'))) {
            throw new RuntimeException('Discord bot token is not configured.');
        }

        $channel = $this->discord()->post('https://discord.com/api/v10/users/@me/channels', [
            'recipient_id' => $donation->discord_user_id,
        ])->throw()->json();
        $channelId = (string) ($channel['id'] ?? '');
        if (! preg_match('/^\d{17,20}$/', $channelId)) {
            throw new RuntimeException('Discord did not return a DM channel.');
        }

        $qrCode = QrCode::create($donation->qr_string)->setSize(600)->setMargin(20);
        $image = (new PngWriter)->write($qrCode)->getString();
        $message = implode("\n", [
            '**QRIS Donasi**',
            'Nominal: **Rp'.number_format($donation->amount, 0, ',', '.').'**',
            'Total bayar (termasuk biaya): **Rp'.number_format($donation->total_payment, 0, ',', '.').'**',
            'Berlaku hingga: **'.$donation->qris_expires_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB**',
            'Pindai gambar QRIS terlampir dengan aplikasi pembayaran Anda. Nama Anda akan masuk leaderboard setelah pembayaran dikonfirmasi.',
        ]);

        $response = $this->discord()->attach('files[0]', $image, 'donasi-qris.png', ['Content-Type' => 'image/png'])
            ->post('https://discord.com/api/v10/channels/'.$channelId.'/messages', [
                'payload_json' => json_encode([
                    'content' => $message,
                    'allowed_mentions' => ['parse' => []],
                    'attachments' => [['id' => 0, 'filename' => 'donasi-qris.png']],
                    'nonce' => substr(hash('sha256', $donation->order_id), 0, 24),
                    'enforce_nonce' => true,
                ], JSON_THROW_ON_ERROR),
            ])->throw()->json();

        if (! filled($response['id'] ?? null)) {
            throw new RuntimeException('Discord did not confirm the QRIS DM.');
        }

        $donation->update(['dm_message_id' => (string) $response['id']]);
    }

    private function editResponse(string $message): void
    {
        Http::acceptJson()->connectTimeout(5)->timeout(10)->patch(
            'https://discord.com/api/v10/webhooks/'.$this->applicationId.'/'.$this->interactionToken.'/messages/@original',
            ['content' => $message, 'components' => [], 'allowed_mentions' => ['parse' => []]],
        )->throw();
    }

    private function discord(): PendingRequest
    {
        return Http::withToken((string) config('services.discord.bot_token'), 'Bot')->acceptJson()->connectTimeout(5)->timeout(15);
    }
}
