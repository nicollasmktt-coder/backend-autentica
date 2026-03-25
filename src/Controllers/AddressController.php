<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Request;
use App\Support\Response;
use PDO;

final class AddressController
{
    public static function index(): void
    {
        $auth = Auth::user();
        $customerId = (int) ($auth['sub'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id = :customer_id ORDER BY is_default DESC, id DESC');
        $stmt->execute(['customer_id' => $customerId]);
        Response::json(['ok' => true, 'addresses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function store(): void
    {
        $auth = Auth::user();
        $customerId = (int) ($auth['sub'] ?? 0);
        $data = Request::json();
        $pdo = Database::getConnection();

        if ((int) ($data['is_default'] ?? 0) === 1) {
            $pdo->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = :customer_id')->execute(['customer_id' => $customerId]);
        }

        $stmt = $pdo->prepare('INSERT INTO customer_addresses (customer_id, label, recipient_name, cep, street, number, complement, district, city, state, is_default) VALUES (:customer_id, :label, :recipient_name, :cep, :street, :number, :complement, :district, :city, :state, :is_default)');
        $stmt->execute([
            'customer_id' => $customerId,
            'label' => $data['label'] ?? null,
            'recipient_name' => (string) ($data['recipient_name'] ?? ''),
            'cep' => (string) ($data['cep'] ?? ''),
            'street' => (string) ($data['street'] ?? ''),
            'number' => (string) ($data['number'] ?? ''),
            'complement' => $data['complement'] ?? null,
            'district' => (string) ($data['district'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'state' => (string) ($data['state'] ?? ''),
            'is_default' => (int) ($data['is_default'] ?? 0),
        ]);

        Response::json(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }
}
