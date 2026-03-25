<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Env;
use App\Support\Jwt;
use App\Support\Request;
use App\Support\Response;
use PDO;

final class AuthController
{
    public static function register(): void
    {
        $data = Request::json();
        $name = trim((string) ($data['name'] ?? ''));
        $cpf = trim((string) ($data['cpf'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || $cpf === '' || $email === '' || $password === '') {
            Response::error('Preencha nome, CPF, e-mail e senha.');
        }

        $pdo = Database::getConnection();
        $check = $pdo->prepare('SELECT id FROM customers WHERE email = :email OR cpf = :cpf LIMIT 1');
        $check->execute(['email' => $email, 'cpf' => $cpf]);
        if ($check->fetch()) {
            Response::error('Cliente já cadastrado.', 409);
        }

        $stmt = $pdo->prepare('INSERT INTO customers (name, cpf, email, password_hash) VALUES (:name, :cpf, :email, :password_hash)');
        $stmt->execute([
            'name' => $name,
            'cpf' => $cpf,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $id = (int) $pdo->lastInsertId();
        self::issueToken($id, 'customer', $email, ['id' => $id, 'name' => $name, 'email' => $email]);
    }

    public static function login(): void
    {
        $data = Request::json();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            Response::error('Login inválido.', 401);
        }

        self::issueToken((int) $user['id'], 'customer', (string) $user['email'], [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ]);
    }

    public static function adminLogin(): void
    {
        $data = Request::json();
        $login = trim((string) ($data['login'] ?? $data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM admins WHERE username = :login OR email = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            Response::error('Login admin inválido.', 401);
        }

        self::issueToken((int) $admin['id'], 'admin', (string) ($admin['email'] ?: $admin['username']), [
            'id' => (int) $admin['id'],
            'username' => (string) $admin['username'],
            'email' => (string) $admin['email'],
        ]);
    }

    public static function me(): void
    {
        $user = Auth::user();
        Response::json(['ok' => true, 'user' => $user]);
    }

    private static function issueToken(int $id, string $role, string $email, array $user): void
    {
        $ttl = (int) Env::get('JWT_TTL', '604800');
        $token = Jwt::encode([
            'sub' => $id,
            'role' => $role,
            'email' => $email,
            'user' => $user,
        ], Env::get('JWT_SECRET', 'secret'), $ttl);

        Response::json(['ok' => true, 'token' => $token, 'user' => array_merge(['role' => $role], $user)]);
    }
}
