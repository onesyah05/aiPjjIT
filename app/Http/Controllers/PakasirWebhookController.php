<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Services\DonationPaymentConfirmer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PakasirWebhookController extends Controller
{
    public function __invoke(Request $request, DonationPaymentConfirmer $confirmer): JsonResponse
    {
        $secret = config('services.pakasir.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => 'Webhook unavailable.'], 503);
        }

        $receivedSecret = $request->header('X-Secret');
        if (! is_string($receivedSecret) || ! hash_equals($secret, $receivedSecret)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $input = $request->validate([
            'txn_id' => ['required', 'string', 'max:100'],
            'order_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer'],
            'is_sandbox' => ['required', 'boolean'],
            'status' => ['required', 'in:completed'],
        ]);

        $donation = Donation::query()->where('order_id', $input['order_id'])->first();
        if ($donation === null) {
            return response()->json(['message' => 'Donation not found.'], 404);
        }

        if ($donation->pakasir_txn_id !== (string) $input['txn_id']
            || $donation->amount !== (int) $input['amount']
            || $donation->is_sandbox !== (bool) $input['is_sandbox']) {
            return response()->json(['message' => 'Transaction mismatch.'], 422);
        }

        if (! $confirmer->confirm($donation)) {
            return response()->json(['message' => 'Payment is not confirmed.'], 409);
        }

        return response()->json(['ok' => true]);
    }
}
