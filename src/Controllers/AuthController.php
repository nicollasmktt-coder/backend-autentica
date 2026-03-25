<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Env;
use App\Support\Jwt;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;
use PDO;

final class AuthController
{
    public static function register(): void
    {
        $data = Request::json();
        Validator::requireFields($data, ['name', 'cpf', 'email', 'password']);
        Validator::email((string)$data['email']);

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT id FROM customers WHERE email = :email OR cpf = :cpf LIMIT 1');
        $exists->execute(['email' => strtolower(trim((string)$data['email'])), 'cpf' => preg_replace('/\D+/', '', (string)$data['cpf'])]);
        if ($exists->fetch()) Response::error('E-mail ou CPF já cadastrado.', 409);

        $stmt = $pdo->prepare('INSERT INTO customers (name, cpf, email, password_hash, phone) VALUES (:name,:cpf,:email,:password_hash,:phone)');
        $stmt->execute([
            'name' => trim((string)$data['name']),
            'cpf' => preg_replace('/\D+/', '', (string)$data['cpf']),
            'email' => strtolower(trim((string)$data['email'])),
            'password_hash' => password_hash((string)$data['password'], PASSWORD_DEFAULT),
            'phone' => $data['phone'] ?? null,
        ]);

        $user = ['id' => (int)$pdo->lastInsertId(), 'name' => trim((string)$data['name']), 'email' => strtolower(trim((string)$data['email'])), 'role' => 'customer'];
        Response::json(['ok' => true, 'token' => self::token($user), 'user' => $user], 201);
    }

    public static function login(): void
    {
        $data = Request::json();
        Validator::requireFields($data, ['email', 'password']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, active FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim((string)$data['email']))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int)$user['active'] || !password_verify((string)$data['password'], (string)$user['password_hash'])) {
            Response::error('Login inválido.', 401);
        }
        $payload = ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => 'customer'];
        Response::json(['ok' => true, 'token' => self::token($payload), 'user' => $payload]);
    }

    public static function adminLogin(): void
    {
        $data = Request::json();
        Validator::requireFields($data, ['login', 'password']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, active FROM admins WHERE username = :login OR email = :login LIMIT 1');
        $stmt->execute(['login' => trim((string)$data['login'])]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin || !(int)$admin['active'] || !password_verify((string)$data['password'], (string)$admin['password_hash'])) {
            Response::error('Login admin inválido.', 401);
        }
        $payload = ['id' => (int)$admin['id'], 'name' => $admin['username'], 'email' => $admin['email'], 'role' => 'admin'];
        Response::json(['ok' => true, 'token' => self::token($payload), 'user' => $payload]);
    }

    public static function me(): void
    {
        $user = Auth::user();
        if (($user['role'] ?? '') === 'admin') {
            Response::json(['ok' => true, 'user' => $user]);
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, cpf, email, phone, active, created_at FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$user['id']]);
        Response::json(['ok' => true, 'user' => $stmt->fetch(PDO::FETCH_ASSOC) ?: $user]);
    }

    private static function token(array $payload): string
    {
        return Jwt::encode($payload, Env::get('JWT_SECRET', 'secret'), (int) Env::get('JWT_TTL', '604800'));
    }
}
