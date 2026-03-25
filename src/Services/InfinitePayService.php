<?php
declare(strict_types=1);
namespace App\Services;

final class InfinitePayService
{
    public static function createCheckout(array $payload): array
    {
        return self::post('https://api.infinitepay.io/invoices/public/checkout/links', $payload);
    }

    public static function paymentCheck(array $payload): array
    {
        return self::post('https://api.infinitepay.io/invoices/public/checkout/payment_check', $payload);
    }

    private static function post(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 500, 'message' => $error ?: 'Falha HTTP'];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $response];
        }

        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $decoded];
    }
}
