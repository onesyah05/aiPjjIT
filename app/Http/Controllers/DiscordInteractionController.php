<?php

namespace App\Http\Controllers;

use App\Jobs\CreateDonationCheckout;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class DiscordInteractionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->validSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $interaction = $request->json()->all();

        if (($interaction['type'] ?? null) === 1) {
            return response()->json(['type' => 1]);
        }

        if (($interaction['guild_id'] ?? '') !== (string) config('services.discord.guild_id')
            || ($interaction['channel_id'] ?? '') !== (string) config('services.discord.donation_channel_id')) {
            return $this->ephemeral('Donasi hanya tersedia di channel leaderboard.');
        }

        if (($interaction['type'] ?? null) === 3 && ($interaction['data']['custom_id'] ?? '') === 'donation:open') {
            return response()->json(['type' => 9, 'data' => [
                'custom_id' => 'donation:submit',
                'title' => 'Donate',
                'components' => [
                    $this->label('Amount (IDR)', 'amount', 4, true, '50000', 3, 8),
                    $this->label('Message (optional)', 'message', 2, false, 'Ditampilkan setelah pembayaran', 0, 500),
                    $this->label('Image/GIF URL (Rp25.000+)', 'image_url', 4, false, 'https://...', 0, 255),
                ],
            ]]);
        }

        if (($interaction['type'] ?? null) !== 5 || ($interaction['data']['custom_id'] ?? '') !== 'donation:submit') {
            return $this->ephemeral('Interaksi tidak dikenal.');
        }

        $values = [];
        foreach (($interaction['data']['components'] ?? []) as $label) {
            $input = $label['component'] ?? $label['components'][0] ?? null;
            if (is_array($input) && isset($input['custom_id'])) {
                $values[$input['custom_id']] = $input['value'] ?? '';
            }
        }

        $validator = Validator::make($values, [
            'amount' => ['required', 'integer', 'between:500,50000000'],
            'message' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->ephemeral('Nominal harus Rp500–Rp50.000.000; periksa juga pesan dan URL gambar.');
        }

        $validated = $validator->validated();
        if (filled($validated['image_url'] ?? null) && (int) $validated['amount'] < 25000) {
            return $this->ephemeral('URL gambar hanya tersedia untuk donasi minimal Rp25.000.');
        }

        if (! filled(config('services.pakasir.slug')) || ! filled(config('services.pakasir.api_key')) || ! filled(config('services.pakasir.webhook_secret'))) {
            return $this->ephemeral('Pembayaran donasi belum tersedia. Silakan coba lagi nanti.');
        }

        $user = $interaction['member']['user'] ?? $interaction['user'] ?? [];
        if (! preg_match('/^\d{17,20}$/', (string) ($user['id'] ?? ''))) {
            return $this->ephemeral('Identitas Discord tidak valid.');
        }

        $donation = Donation::query()->firstOrCreate(
            ['discord_interaction_id' => (string) $interaction['id']],
            [
                'order_id' => 'don-'.Str::uuid(),
                'discord_user_id' => (string) $user['id'],
                'discord_username' => (string) ($user['global_name'] ?? $user['username'] ?? $user['id']),
                'guild_id' => (string) $interaction['guild_id'],
                'channel_id' => (string) $interaction['channel_id'],
                'amount' => (int) $validated['amount'],
                'message' => $validated['message'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
                'is_sandbox' => (bool) config('services.pakasir.sandbox'),
            ],
        );

        CreateDonationCheckout::dispatch($donation->id, (string) $interaction['application_id'], (string) $interaction['token']);

        return response()->json(['type' => 5, 'data' => ['flags' => 64]]);
    }

    private function validSignature(Request $request): bool
    {
        $publicKey = config('services.discord.public_key');
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');

        if (! is_string($publicKey) || ! is_string($signature) || ! is_string($timestamp)
            || ! ctype_xdigit($publicKey) || ! ctype_xdigit($signature)) {
            return false;
        }

        $key = hex2bin($publicKey);
        $bytes = hex2bin($signature);
        if ($key === false || $bytes === false || strlen($key) !== 32 || strlen($bytes) !== 64 || ! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($bytes, $timestamp.$request->getContent(), $key);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function label(string $title, string $id, int $style, bool $required, string $placeholder, int $minLength, int $maxLength): array
    {
        return ['type' => 18, 'label' => $title, 'component' => [
            'type' => 4,
            'custom_id' => $id,
            'style' => $style,
            'required' => $required,
            'placeholder' => $placeholder,
            'min_length' => $minLength,
            'max_length' => $maxLength,
        ]];
    }

    private function ephemeral(string $message): JsonResponse
    {
        return response()->json(['type' => 4, 'data' => ['content' => $message, 'flags' => 64]]);
    }
}
