<?php
declare(strict_types=1);
namespace App\Support;

final class Helpers
{
    public static function slug(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(trim($value))) ?: '';
        return trim($value, '-');
    }

    public static function moneyToCents(float $value): int
    {
        return (int) round($value * 100);
    }

    public static function paginateMeta(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) max(1, ceil($total / max(1, $perPage))),
        ];
    }
}
