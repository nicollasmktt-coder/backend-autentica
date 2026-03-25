<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;

final class AddressController
{
    public static function index(): void
    {
        $user = Auth::requireCustomer();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id = :customer_id ORDER BY is_default DESC, id DESC');
        $stmt->execute(['customer_id' => (int)$user['id']]);
        Response::json(['ok' => true, 'data' => $stmt->fetchAll()]);
    }

    public static function store(): void
    {
        $user = Auth::requireCustomer();
        $data = Request::json();
        Validator::requireFields($data, ['recipient_name', 'cep', 'street', 'number', 'district', 'city', 'state']);
        $pdo = Database::connection();
        if ((int)($data['is_default'] ?? 0) === 1) {
            $pdo->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = :customer_id')->execute(['customer_id' => (int)$user['id']]);
        }
        $stmt = $pdo->prepare('INSERT INTO customer_addresses (customer_id, label, recipient_name, cep, street, number, complement, district, city, state, is_default) VALUES (:customer_id,:label,:recipient_name,:cep,:street,:number,:complement,:district,:city,:state,:is_default)');
        $stmt->execute([
            'customer_id' => (int)$user['id'],
            'label' => $data['label'] ?? null,
            'recipient_name' => trim((string)$data['recipient_name']),
            'cep' => trim((string)$data['cep']),
            'street' => trim((string)$data['street']),
            'number' => trim((string)$data['number']),
            'complement' => $data['complement'] ?? null,
            'district' => trim((string)$data['district']),
            'city' => trim((string)$data['city']),
            'state' => trim((string)$data['state']),
            'is_default' => (int)($data['is_default'] ?? 0),
        ]);
        Response::json(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    }

    public static function update(): void
    {
        $user = Auth::requireCustomer();
        $data = Request::json();
        Validator::requireFields($data, ['id', 'recipient_name', 'cep', 'street', 'number', 'district', 'city', 'state']);
        $pdo = Database::connection();
        if ((int)($data['is_default'] ?? 0) === 1) {
            $pdo->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = :customer_id')->execute(['customer_id' => (int)$user['id']]);
        }
        $stmt = $pdo->prepare('UPDATE customer_addresses SET label=:label, recipient_name=:recipient_name, cep=:cep, street=:street, number=:number, complement=:complement, district=:district, city=:city, state=:state, is_default=:is_default WHERE id=:id AND customer_id=:customer_id');
        $stmt->execute([
            'id' => (int)$data['id'],
            'customer_id' => (int)$user['id'],
            'label' => $data['label'] ?? null,
            'recipient_name' => trim((string)$data['recipient_name']),
            'cep' => trim((string)$data['cep']),
            'street' => trim((string)$data['street']),
            'number' => trim((string)$data['number']),
            'complement' => $data['complement'] ?? null,
            'district' => trim((string)$data['district']),
            'city' => trim((string)$data['city']),
            'state' => trim((string)$data['state']),
            'is_default' => (int)($data['is_default'] ?? 0),
        ]);
        Response::json(['ok' => true]);
    }

    public static function destroy(): void
    {
        $user = Auth::requireCustomer();
        $id = Request::intQuery('id');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM customer_addresses WHERE id = :id AND customer_id = :customer_id');
        $stmt->execute(['id' => $id, 'customer_id' => (int)$user['id']]);
        Response::json(['ok' => true]);
    }
}
