<?php

namespace App\Services;

use App\Models\Donation;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PakasirClient
{
    public function configured(): bool
    {
        return filled(config('services.pakasir.slug')) && filled(config('services.pakasir.api_key'));
    }

    /** @return array<string, mixed> */
    public function createTransaction(Donation $donation): array
    {
        $this->ensureConfigured();

        return Http::withHeaders(['X-Api-Key' => config('services.pakasir.api_key')])
            ->acceptJson()->connectTimeout(5)->timeout(15)
            ->post($this->url('create-transaction', $donation->order_id), [
                'method' => 'payment_link',
                'amount' => $donation->amount,
            ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function transactionStatus(string $transactionId): array
    {
        $this->ensureConfigured();

        return Http::withHeaders(['X-Api-Key' => config('services.pakasir.api_key')])
            ->acceptJson()->connectTimeout(5)->timeout(15)
            ->get($this->url('transaction-status', $transactionId))
            ->throw()->json();
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Pakasir is not configured.');
        }
    }

    private function url(string $endpoint, string $identifier): string
    {
        return 'https://app.pakasir.com/api/v2/'.$endpoint.'/'.rawurlencode((string) config('services.pakasir.slug')).'/'.rawurlencode($identifier);
    }
}
