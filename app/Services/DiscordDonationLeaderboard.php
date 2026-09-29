<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\DonationLeaderboard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordDonationLeaderboard
{
    /** @return array<string, mixed> */
    public function payload(): array
    {
        $query = Donation::query()->where('status', 'paid')
            ->where('is_sandbox', (bool) config('services.pakasir.sandbox'));
        $totals = (clone $query)->selectRaw('COUNT(*) AS donations_count, COALESCE(SUM(amount), 0) AS amount_total, COUNT(DISTINCT discord_user_id) AS donors_count')->first();
        $leaders = (clone $query)->select('discord_user_id')
            ->selectRaw('SUM(amount) AS amount_total')
            ->groupBy('discord_user_id')
            ->orderByDesc('amount_total')->limit(20)->get();
        $latestDonation = (clone $query)->orderByDesc('paid_at')->orderByDesc('id')->first();

        $lines = $leaders->map(fn ($donation, int $index): string => sprintf(
            '**#%d** <@%s> — **Rp%s**',
            $index + 1,
            $donation->discord_user_id,
            number_format((int) $donation->amount_total, 0, ',', '.'),
        ))->all();

        $embed = [
            'title' => '🏆 Top Donors'.(config('services.pakasir.sandbox') ? ' — SANDBOX / UJI COBA' : ''),
            'description' => implode("\n", $lines) ?: 'Belum ada donasi terkonfirmasi. Jadilah donatur pertama!',
            'color' => 16763955,
            'fields' => [
                ['name' => config('services.pakasir.sandbox') ? 'Total simulasi' : 'Total terkumpul', 'value' => 'Rp'.number_format((int) $totals->amount_total, 0, ',', '.'), 'inline' => true],
                ['name' => 'Donasi', 'value' => (string) $totals->donations_count, 'inline' => true],
                ['name' => 'Donatur', 'value' => (string) $totals->donors_count, 'inline' => true],
            ],
            'footer' => ['text' => config('services.pakasir.sandbox')
                ? 'Uji coba saja; tidak ada uang sungguhan yang diterima.'
                : 'Hanya pembayaran yang sudah dikonfirmasi yang dihitung.'],
        ];

        if ($latestDonation !== null && filled($latestDonation->message)) {
            $embed['fields'][] = [
                'name' => 'Pesan donatur terbaru',
                'value' => '<@'.$latestDonation->discord_user_id.'>: '.str_replace(['@everyone', '@here'], ['@ everyone', '@ here'], $latestDonation->message),
                'inline' => false,
            ];
        }

        if ($latestDonation !== null && $latestDonation->amount >= 25000 && filled($latestDonation->image_url)) {
            $embed['image'] = ['url' => $latestDonation->image_url];
        }

        return [
            'embeds' => [$embed],
            'components' => [[
                'type' => 1,
                'components' => [[
                    'type' => 2,
                    'style' => 3,
                    'label' => config('services.pakasir.sandbox') ? '🎁 Donate (Test)' : '🎁 Donate',
                    'custom_id' => 'donation:open',
                ]],
            ]],
            'allowed_mentions' => ['parse' => []],
        ];
    }

    public function publish(): DonationLeaderboard
    {
        $this->ensureBotConfigured();
        $channelId = (string) config('services.discord.donation_channel_id');
        $guildId = (string) config('services.discord.guild_id');
        $board = DonationLeaderboard::query()->where('channel_id', $channelId)->first();

        if ($board !== null) {
            $this->refresh($board);

            return $board;
        }

        $response = $this->discord()->post($this->messageUrl($channelId), $this->payload())->throw()->json();

        return DonationLeaderboard::query()->create([
            'guild_id' => $guildId,
            'channel_id' => $channelId,
            'message_id' => (string) $response['id'],
        ]);
    }

    public function refresh(DonationLeaderboard $board): void
    {
        $this->ensureBotConfigured();
        $this->discord()->patch($this->messageUrl($board->channel_id).'/'.$board->message_id, $this->payload())->throw();
    }

    private function ensureBotConfigured(): void
    {
        if (! filled(config('services.discord.bot_token')) || ! filled(config('services.discord.guild_id')) || ! filled(config('services.discord.donation_channel_id'))) {
            throw new RuntimeException('Discord donation leaderboard is not configured.');
        }
    }

    private function discord(): PendingRequest
    {
        return Http::withToken((string) config('services.discord.bot_token'), 'Bot')->acceptJson()->connectTimeout(5)->timeout(15);
    }

    private function messageUrl(string $channelId): string
    {
        return 'https://discord.com/api/v10/channels/'.$channelId.'/messages';
    }
}
