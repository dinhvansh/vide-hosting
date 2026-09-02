<?php

namespace App\Services;

use App\Exceptions\PlatformException;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\Http;

class SePayGateway
{
    public function isConfigured(): bool
    {
        return collect(['merchant_id', 'secret_key', 'ipn_secret', 'checkout_url'])
            ->every(fn (string $key): bool => filled(config('services.sepay.'.$key)));
    }

    /** @return array{url: string, fields: array<string, string>} */
    public function checkout(PaymentOrder $order): array
    {
        if (! $this->isConfigured()) {
            throw new PlatformException('PAYMENT_UNAVAILABLE', 'Payment is not configured yet. Please contact support.', 503);
        }

        $returnBase = rtrim((string) config('app.frontend_url', 'http://localhost:3001'), '/').'/dashboard?payment_order='.$order->id;
        $fields = [
            'merchant' => (string) config('services.sepay.merchant_id'),
            'order_amount' => (string) $order->amount_vnd,
            'order_invoice_number' => $order->invoice_number,
            'customer_id' => (string) $order->user_id,
            'success_url' => $returnBase.'&payment_result=success',
            'error_url' => $returnBase.'&payment_result=error',
            'cancel_url' => $returnBase.'&payment_result=cancel',
        ];
        $fields['signature'] = hash_hmac('sha256', $this->signaturePayload($fields), (string) config('services.sepay.secret_key'));

        return ['url' => (string) config('services.sepay.checkout_url'), 'fields' => $fields];
    }

    public function validIpnSecret(?string $secret): bool
    {
        $expected = (string) config('services.sepay.ipn_secret');

        return $expected !== '' && $secret !== null && hash_equals($expected, $secret);
    }

    /** @return array<string, mixed>|null */
    public function query(PaymentOrder $order): ?array
    {
        if (! filled(config('services.sepay.api_url')) || ! filled(config('services.sepay.secret_key'))) {
            return null;
        }

        $response = Http::acceptJson()->withToken((string) config('services.sepay.secret_key'))
            ->timeout(8)->get(rtrim((string) config('services.sepay.api_url'), '/').'/orders/'.$order->invoice_number);

        return $response->successful() ? $response->json() : null;
    }

    /** @param array<string, string> $fields */
    private function signaturePayload(array $fields): string
    {
        ksort($fields);

        return http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    }
}
