<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
