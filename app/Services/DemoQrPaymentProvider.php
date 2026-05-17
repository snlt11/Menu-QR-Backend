<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoQrPaymentProvider
{
    /**
     * Generate a fake QR payment session. Returns provider_reference, qr_payload, expires_at.
     *
     * @return array{provider_reference: string, qr_payload: string, expires_at: Carbon}
     */
    public function createSession(string $orderNumber, float $amount, string $currency = 'MMK'): array
    {
        $reference = 'QR-'.strtoupper(Str::random(10));
        $payload = "PAY|ORDER={$orderNumber}|AMOUNT={$amount}|CURRENCY={$currency}|REF={$reference}";

        return [
            'provider_reference' => $reference,
            'qr_payload' => $payload,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
