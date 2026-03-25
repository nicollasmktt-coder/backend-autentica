<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Services\OrderService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;
use PDO;
use Throwable;

final class OrderController
{
    public static function index(): void
    {
        $user = Auth::user();
        $pdo = Database::connection();
        $page = Request::page();
        $perPage = Request::perPage();
        $offset = ($page - 1) * $perPage;
        $where = '1=1';
        $params = [];
        if (($user['role'] ?? '') === 'customer') {
            $where .= ' AND o.customer_id = :customer_id';
            $params['customer_id'] = (int)$user['id'];
        }
        $status = Request::query('status');
        if ($status) {
            $where .= ' AND o.status = :status';
            $params['status'] = $status;
        }
        $count = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $sql = "SELECT o.*, c.name AS customer_name, c.email AS customer_email
                FROM orders o INNER JOIN customers c ON c.id = o.customer_id
                WHERE {$where}
                ORDER BY o.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as &$order) self::hydrateOrder($pdo, $order, ($user['role'] ?? '') === 'customer');
        Response::json(['ok' => true, 'data' => $orders, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total]]);
    }

    public static function show(): void
    {
        $user = Auth::user();
        $id = Request::intQuery('id');
        $pdo = Database::connection();
        $where = 'o.id = :id';
        $params = ['id' => $id];
        if (($user['role'] ?? '') === 'customer') {
            $where .= ' AND o.customer_id = :customer_id';
            $params['customer_id'] = (int)$user['id'];
        }
        $stmt = $pdo->prepare("SELECT o.*, c.name AS customer_name, c.email AS customer_email FROM orders o INNER JOIN customers c ON c.id = o.customer_id WHERE {$where} LIMIT 1");
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) Response::error('Pedido não encontrado.', 404);
        self::hydrateOrder($pdo, $order, ($user['role'] ?? '') === 'customer');
        Response::json(['ok' => true, 'data' => $order]);
    }

    public static function store(): void
    {
        $user = Auth::requireCustomer();
        $data = Request::json();
        Validator::requireFields($data, ['address_id', 'items', 'price_mode']);
        Validator::oneOf((string)$data['price_mode'], ['normal', 'revenda'], 'price_mode');
        if (!is_array($data['items']) || count($data['items']) === 0) Response::error('Itens obrigatórios.', 422);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $addressStmt = $pdo->prepare('SELECT * FROM customer_addresses WHERE id = :id AND customer_id = :customer_id LIMIT 1');
            $addressStmt->execute(['id' => (int)$data['address_id'], 'customer_id' => (int)$user['id']]);
            if (!$addressStmt->fetch()) Response::error('Endereço inválido.', 422);

            $subtotal = 0.0;
            $itemsForOrder = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item) || empty($item['product_id'])) Response::error('Item inválido.', 422);
                $productStmt = $pdo->prepare('SELECT id, sku, name, price_normal, price_resale, active FROM products WHERE id = :id LIMIT 1');
                $productStmt->execute(['id' => (int)$item['product_id']]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                if (!$product || !(int)$product['active']) Response::error('Produto inválido.', 422);
                $priceMode = (string)$data['price_mode'];
                $unitPrice = $priceMode === 'revenda' ? (float)$product['price_resale'] : (float)$product['price_normal'];
                $quantity = $priceMode === 'revenda' ? 10 : max(1, (int)($item['quantity'] ?? 1));
                $kitSizes = $item['resale_kit_sizes'] ?? null;
                if ($priceMode === 'revenda' && (!is_array($kitSizes) || count($kitSizes) !== 10)) {
                    Response::error('Revenda exige 10 tamanhos no kit.', 422);
                }
                $totalPrice = $unitPrice * ($priceMode === 'revenda' ? 1 : $quantity);
                $subtotal += $totalPrice;
                $itemsForOrder[] = [
                    'product_id' => (int)$product['id'],
                    'product_name' => (string)$product['name'],
                    'sku' => (string)$product['sku'],
                    'color_name' => $item['color_name'] ?? null,
                    'size_value' => $priceMode === 'normal' ? ($item['size_value'] ?? null) : null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'price_mode' => $priceMode,
                    'resale_kit_sizes' => $priceMode === 'revenda' ? array_values($kitSizes) : null,
                ];
            }
            $coupon = CouponController::validateForOrder($data['coupon_code'] ?? null, $subtotal);
            $discountAmount = 0.0;
            $couponId = null;
            if ($coupon) {
                $couponId = (int)$coupon['id'];
                $discountAmount = $coupon['discount_type'] === 'percent' ? round($subtotal * ((float)$coupon['discount_value'] / 100), 2) : min($subtotal, (float)$coupon['discount_value']);
            }
            $shippingAmount = (float)($data['shipping_amount'] ?? 0);
            $totalAmount = max(0, $subtotal + $shippingAmount - $discountAmount);
            $orderNumber = 'AF' . date('YmdHis') . random_int(100, 999);
            $stmt = $pdo->prepare('INSERT INTO orders (customer_id, address_id, coupon_id, order_number, status, price_mode, subtotal, shipping_amount, discount_amount, total_amount, payment_method, notes) VALUES (:customer_id,:address_id,:coupon_id,:order_number,:status,:price_mode,:subtotal,:shipping_amount,:discount_amount,:total_amount,:payment_method,:notes)');
            $stmt->execute([
                'customer_id' => (int)$user['id'],
                'address_id' => (int)$data['address_id'],
                'coupon_id' => $couponId,
                'order_number' => $orderNumber,
                'status' => 'aguardando_pagamento',
                'price_mode' => (string)$data['price_mode'],
                'subtotal' => $subtotal,
                'shipping_amount' => $shippingAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $data['payment_method'] ?? 'infinitepay',
                'notes' => $data['notes'] ?? null,
            ]);
            $orderId = (int)$pdo->lastInsertId();
            OrderService::attachItems($orderId, $itemsForOrder);
            OrderService::addTracking($orderId, 'Pedido criado', 'Pedido aguardando pagamento.', true);
            $pdo->prepare('INSERT INTO payments (order_id, provider, payment_status, amount) VALUES (:order_id, :provider, :payment_status, :amount)')->execute(['order_id' => $orderId, 'provider' => 'infinitepay', 'payment_status' => 'pending', 'amount' => $totalAmount]);
            if ($couponId) $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id')->execute(['id' => $couponId]);
            $pdo->commit();
            Response::json(['ok' => true, 'id' => $orderId, 'order_number' => $orderNumber, 'total_amount' => $totalAmount], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error('Falha ao criar pedido.', 500, ['details' => $e->getMessage()]);
        }
    }

    public static function updateStatus(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['id', 'status']);
        Validator::oneOf((string)$data['status'], ['pendente','aguardando_pagamento','pago','em_preparo','enviado','entregue','cancelado'], 'status');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE orders SET status = :status, tracking_code = :tracking_code WHERE id = :id');
        $stmt->execute(['id' => (int)$data['id'], 'status' => (string)$data['status'], 'tracking_code' => $data['tracking_code'] ?? null]);
        OrderService::addTracking((int)$data['id'], (string)$data['status'], $data['description'] ?? null, true);
        Response::json(['ok' => true]);
    }

    public static function trackingIndex(): void
    {
        $user = Auth::user();
        $orderId = Request::intQuery('order_id');
        $pdo = Database::connection();
        if (($user['role'] ?? '') === 'customer') {
            $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id AND customer_id = :customer_id LIMIT 1');
            $stmt->execute(['id' => $orderId, 'customer_id' => (int)$user['id']]);
            if (!$stmt->fetch()) Response::error('Pedido não encontrado.', 404);
            $tracking = OrderService::tracking($orderId, true);
        } else {
            $tracking = OrderService::tracking($orderId, false);
        }
        Response::json(['ok' => true, 'data' => $tracking]);
    }

    public static function trackingStore(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['order_id', 'status_label']);
        OrderService::addTracking((int)$data['order_id'], (string)$data['status_label'], $data['description'] ?? null, (bool)($data['visible_to_customer'] ?? true));
        Response::json(['ok' => true], 201);
    }

    private static function hydrateOrder(PDO $pdo, array &$order, bool $customerView): void
    {
        $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id');
        $items->execute(['order_id' => $order['id']]);
        $payment = $pdo->prepare('SELECT * FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
        $payment->execute(['order_id' => $order['id']]);
        $address = $pdo->prepare('SELECT * FROM customer_addresses WHERE id = :id LIMIT 1');
        $address->execute(['id' => $order['address_id']]);
        $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
        $order['payment'] = $payment->fetch(PDO::FETCH_ASSOC) ?: null;
        $order['address'] = $address->fetch(PDO::FETCH_ASSOC) ?: null;
        $order['tracking'] = OrderService::tracking((int)$order['id'], $customerView);
    }
}
