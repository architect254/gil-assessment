<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MpesaTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class MpesaService
{
    /**
     * Normalize a Kenyan phone number into standard international format (254XXXXXXXXX).
     *
     * @throws InvalidArgumentException
     */
    public function normalizePhoneNumber(string $phone): string
    {
        // Remove spaces, hyphens, plus signs, parentheses
        $cleaned = preg_replace('/\D/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '254' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '1')) {
            if (strlen($cleaned) === 9) {
                $cleaned = '254' . $cleaned;
            }
        }

        if (! preg_match('/^254[17]\d{8}$/', $cleaned)) {
            throw new InvalidArgumentException("Invalid Kenyan phone number format: '{$phone}'. Must be a valid Safaricom/Airtel MSISDN (e.g., 0712345678 or 254712345678).");
        }

        return $cleaned;
    }

    /**
     * Get the Daraja API base URL based on configured environment.
     */
    public function getBaseUrl(): string
    {
        $env = config('mpesa.environment', 'sandbox');

        return $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Request an OAuth access token from Safaricom Daraja API.
     *
     * @throws RuntimeException
     */
    public function generateDarajaToken(): ?string
    {
        $consumerKey = config('mpesa.consumer_key', '');
        $consumerSecret = config('mpesa.consumer_secret', '');

        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new RuntimeException('Daraja API credentials are not configured. Please set MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET.');
        }

        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';

        try {
            $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            throw new RuntimeException('Failed to generate Daraja token: ' . $response->body());
        } catch (\Exception $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Error connecting to Daraja API: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Initiate a Lipa Na M-Pesa STK Push request.
     *
     * @return array<string, mixed>
     * @throws RuntimeException|InvalidArgumentException
     */
    public function sendStkPush(
        string $phone,
        float $amount,
        string $reference = 'Payment',
        string $description = 'Invoice Payment'
    ): array {
        $normalizedPhone = $this->normalizePhoneNumber($phone);
        $token = $this->generateDarajaToken();

        $shortcode = (string) config('mpesa.shortcode', '174379');
        $passkey = (string) config('mpesa.passkey', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);
        $callbackUrl = url('/api/mpesa/confirmation');

        $url = $this->getBaseUrl() . '/mpesa/stkpush/v1/processrequest';

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => max(1, (int) round($amount)),
            'PartyA' => $normalizedPhone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $normalizedPhone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => substr($reference, 0, 12),
            'TransactionDesc' => substr($description, 0, 13),
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $errorMsg = $response->json('errorMessage') ?? $response->body();
            throw new RuntimeException("STK Push failed ({$response->status()}): {$errorMsg}");
        } catch (\Exception $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('STK Push request error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find an invoice matching a bill reference number (e.g., "INV-1" or "1").
     */
    public function findInvoiceForReference(?string $reference): ?Invoice
    {
        if (blank($reference)) {
            return null;
        }

        $trimmed = trim($reference);

        // Direct numeric match or prefixed match like INV-123
        if (preg_match('/^(?:INV-?)?(\d+)$/i', $trimmed, $matches)) {
            $no = (int) $matches[1];
            return Invoice::query()->where('no', $no)->first();
        }

        return Invoice::query()->where('no', $trimmed)->first();
    }

    /**
     * Get all M-Pesa transactions linked to an invoice.
     *
     * @return Collection<int, MpesaTransaction>
     */
    public function getTransactionsForInvoice(Invoice|int $invoice): Collection
    {
        $no = $invoice instanceof Invoice ? $invoice->no : $invoice;
        $variants = [(string) $no, 'INV-' . $no, 'INV' . $no];

        return MpesaTransaction::query()
            ->where(function ($query) use ($variants) {
                $query->whereIn('bill_ref_number', $variants)
                    ->orWhereIn('invoice_number', $variants);
            })
            ->latest('id')
            ->get();
    }
}
