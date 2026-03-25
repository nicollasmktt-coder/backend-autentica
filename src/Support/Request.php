<?php
declare(strict_types=1);
namespace App\Support;

final class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? $default;
        return $value === null ? null : trim((string)$value);
    }

    public static function intQuery(string $key, int $default = 0): int
    {
        return (int)($_GET[$key] ?? $default);
    }

    public static function page(): int
    {
        return max(1, self::intQuery('page', 1));
    }

    public static function perPage(int $max = 100): int
    {
        return min($max, max(1, self::intQuery('per_page', 20)));
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
