<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Request;
use App\Support\Response;
use PDO;

final class CustomerAdminController
{
    public static function index(): void
    {
        Auth::requireAdmin();
        $pdo = Database::connection();
        $page = Request::page();
        $perPage = Request::perPage();
        $offset = ($page - 1) * $perPage;
        $search = Request::query('search', '');
        $where = '1=1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (name LIKE :search OR email LIKE :search OR cpf LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $count = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $stmt = $pdo->prepare("SELECT id, name, cpf, email, phone, active, created_at FROM customers WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        Response::json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total]]);
    }

    public static function show(): void
    {
        Auth::requireAdmin();
        $id = Request::intQuery('id');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, cpf, email, phone, active, created_at FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) Response::error('Cliente não encontrado.', 404);
        $addresses = $pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id = :id ORDER BY is_default DESC, id DESC');
        $addresses->execute(['id' => $id]);
        $orders = $pdo->prepare('SELECT id, order_number, status, total_amount, created_at FROM orders WHERE customer_id = :id ORDER BY id DESC');
        $orders->execute(['id' => $id]);
        $customer['addresses'] = $addresses->fetchAll(PDO::FETCH_ASSOC);
        $customer['orders'] = $orders->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['ok' => true, 'data' => $customer]);
    }
}
