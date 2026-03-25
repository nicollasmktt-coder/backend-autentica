<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;
use PDO;

final class CouponController
{
    public static function index(): void
    {
        Auth::requireAdmin();
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM coupons ORDER BY id DESC');
        Response::json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['code', 'discount_type', 'discount_value']);
        Validator::oneOf((string)$data['discount_type'], ['fixed', 'percent'], 'discount_type');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO coupons (code, discount_type, discount_value, usage_limit, expires_at, active) VALUES (:code,:discount_type,:discount_value,:usage_limit,:expires_at,:active)');
        $stmt->execute([
            'code' => strtoupper(trim((string)$data['code'])),
            'discount_type' => (string)$data['discount_type'],
            'discount_value' => (float)$data['discount_value'],
            'usage_limit' => $data['usage_limit'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => (int)($data['active'] ?? 1),
        ]);
        Response::json(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    }

    public static function update(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['id', 'code', 'discount_type', 'discount_value']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE coupons SET code=:code, discount_type=:discount_type, discount_value=:discount_value, usage_limit=:usage_limit, expires_at=:expires_at, active=:active WHERE id=:id');
        $stmt->execute([
            'id' => (int)$data['id'],
            'code' => strtoupper(trim((string)$data['code'])),
            'discount_type' => (string)$data['discount_type'],
            'discount_value' => (float)$data['discount_value'],
            'usage_limit' => $data['usage_limit'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => (int)($data['active'] ?? 1),
        ]);
        Response::json(['ok' => true]);
    }

    public static function destroy(): void
    {
        Auth::requireAdmin();
        $id = Request::intQuery('id');
        if ($id <= 0) Response::error('ID inválido.', 422);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }

    public static function validateForOrder(?string $code, float $subtotal): ?array
    {
        if (!$code) return null;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = :code AND active = 1 LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($code))]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) Response::error('Cupom inválido.', 422);
        if ($coupon['expires_at'] && strtotime((string)$coupon['expires_at']) < time()) Response::error('Cupom expirado.', 422);
        if ((int)$coupon['usage_limit'] > 0 && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) Response::error('Limite de uso do cupom atingido.', 422);
        return $coupon;
    }
}
