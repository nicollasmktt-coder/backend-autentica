<?php

declare(strict_types=1);

namespace App\Support;

final class Jwt
{
    public static function encode(array $payload, string $secret, int $ttl): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;

        $segments = [
            self::b64(json_encode($header)),
            self::b64(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = self::b64($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $valid = self::b64(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
        if (!hash_equals($valid, $signature)) {
            return null;
        }

        $data = json_decode(self::unb64($payload), true);
        if (!is_array($data)) {
            return null;
        }

        if (($data['exp'] ?? 0) < time()) {
            return null;
        }

        return $data;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return base64_decode(strtr($data . str_repeat('=', (4 - strlen($data) % 4) % 4), '-_', '+/')) ?: '';
    }
}
