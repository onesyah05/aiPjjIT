<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeleteDonationInteractionResponse implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(
        public readonly string $applicationId,
        public readonly string $interactionToken,
    ) {
        $this->onQueue('discord');
    }

    public function handle(): void
    {
        $response = Http::connectTimeout(5)->timeout(10)->delete(
            'https://discord.com/api/v10/webhooks/'.$this->applicationId.'/'.$this->interactionToken.'/messages/@original',
        );

        if (! $response->successful() && ! in_array($response->status(), [401, 404], true)) {
            throw new RuntimeException('Discord could not delete the temporary donation response (HTTP '.$response->status().').');
        }
    }
}
