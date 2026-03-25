<?php
declare(strict_types=1);
namespace App\Core;

use App\Support\Env;
use App\Support\Jwt;
use App\Support\Request;
use App\Support\Response;

final class Auth
{
    public static function user(): array
    {
        $token = Request::bearerToken();
        if (!$token) Response::error('Token ausente.', 401);
        $payload = Jwt::decode($token, Env::get('JWT_SECRET', 'secret'));
        if (!$payload) Response::error('Token inválido.', 401);
        return $payload;
    }

    public static function requireAdmin(): array
    {
        $user = self::user();
        if (($user['role'] ?? '') !== 'admin') {
            Response::error('Acesso negado.', 403);
        }
        return $user;
    }

    public static function requireCustomer(): array
    {
        $user = self::user();
        if (($user['role'] ?? '') !== 'customer') {
            Response::error('Acesso negado.', 403);
        }
        return $user;
    }
}
