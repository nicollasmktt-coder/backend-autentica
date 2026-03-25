<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Request;
use App\Support\Response;
use PDO;
use Throwable;

final class OrderController
{
    public static function index(): void
    {
        $auth = Auth::user();
        $pdo = Database::getConnection();

        if (($auth['role'] ?? '') === 'admin') {
            $stmt = $pdo->query('SELECT * FROM orders ORDER BY id DESC');
            Response::json(['ok' => true, 'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE customer_id = :customer_id ORDER BY id DESC');
        $stmt->execute(['customer_id' => (int) ($auth['sub'] ?? 0)]);
        Response::json(['ok' => true, 'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function store(): void
    {
        $auth = Auth::user();
        $customerId = (int) ($auth['sub'] ?? 0);
        $data = Request::json();
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if ($customerId <= 0 || $items === []) {
            Response::error('Pedido inválido.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += ((float) ($item['unit_price'] ?? 0)) * ((int) ($item['quantity'] ?? 1));
            }
            $total = $subtotal - (float) ($data['discount_amount'] ?? 0);
            $orderNumber = 'AF' . date('YmdHis') . random_int(10, 99);

            $stmt = $pdo->prepare('INSERT INTO orders (customer_id, address_id, coupon_id, order_number, status, price_mode, subtotal, discount_amount, total_amount, tracking_code, notes) VALUES (:customer_id, :address_id, :coupon_id, :order_number, :status, :price_mode, :subtotal, :discount_amount, :total_amount, :tracking_code, :notes)');
            $stmt->execute([
                'customer_id' => $customerId,
                'address_id' => $data['address_id'] ?? null,
                'coupon_id' => $data['coupon_id'] ?? null,
                'order_number' => $orderNumber,
                'status' => (string) ($data['status'] ?? 'pendente'),
                'price_mode' => (string) ($data['price_mode'] ?? 'normal'),
                'subtotal' => $subtotal,
                'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                'total_amount' => $total,
                'tracking_code' => $data['tracking_code'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, color_name, size_value, quantity, unit_price, total_price, price_mode, resale_kit_sizes) VALUES (:order_id, :product_id, :product_name, :sku, :color_name, :size_value, :quantity, :unit_price, :total_price, :price_mode, :resale_kit_sizes)');
            foreach ($items as $item) {
                $quantity = (int) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'product_name' => (string) ($item['product_name'] ?? ''),
                    'sku' => (string) ($item['sku'] ?? ''),
                    'color_name' => $item['color_name'] ?? null,
                    'size_value' => $item['size_value'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                    'price_mode' => (string) ($item['price_mode'] ?? 'normal'),
                    'resale_kit_sizes' => isset($item['resale_kit_sizes']) ? json_encode($item['resale_kit_sizes'], JSON_UNESCAPED_UNICODE) : null,
                ]);
            }

            $pdo->prepare('INSERT INTO order_tracking (order_id, status_label, description) VALUES (:order_id, :status_label, :description)')->execute([
                'order_id' => $orderId,
                'status_label' => 'Pedido criado',
                'description' => 'Pedido criado com sucesso.',
            ]);

            $pdo->commit();
            Response::json(['ok' => true, 'order_id' => $orderId, 'order_number' => $orderNumber]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Falha ao criar pedido.', 500, ['details' => $e->getMessage()]);
        }
    }

    public static function updateStatus(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        $orderId = (int) ($data['order_id'] ?? 0);
        if ($orderId <= 0) {
            Response::error('Pedido inválido.');
        }
        $pdo = Database::getConnection();
        $pdo->prepare('UPDATE orders SET status = :status, tracking_code = :tracking_code WHERE id = :id')->execute([
            'status' => (string) ($data['status'] ?? 'pendente'),
            'tracking_code' => $data['tracking_code'] ?? null,
            'id' => $orderId,
        ]);
        $pdo->prepare('INSERT INTO order_tracking (order_id, status_label, description) VALUES (:order_id, :status_label, :description)')->execute([
            'order_id' => $orderId,
            'status_label' => (string) ($data['status_label'] ?? $data['status'] ?? 'Atualizado'),
            'description' => (string) ($data['description'] ?? 'Status atualizado pelo painel admin.'),
        ]);
        Response::json(['ok' => true]);
    }
}
