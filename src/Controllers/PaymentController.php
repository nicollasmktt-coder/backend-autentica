<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Services\InfinitePayService;
use App\Services\OrderService;
use App\Support\Env;
use App\Support\Helpers;
use App\Support\Request;
use App\Support\Response;
use Throwable;

final class PaymentController
{
    public static function createInfinitePayCheckout(): void
    {
        $user = Auth::requireCustomer();
        $data = Request::json();
        if (empty($data['order_number'])) Response::error('order_number obrigatório.', 422);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT o.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone FROM orders o INNER JOIN customers c ON c.id = o.customer_id WHERE o.order_number = :order_number AND o.customer_id = :customer_id LIMIT 1');
        $stmt->execute(['order_number' => (string)$data['order_number'], 'customer_id' => (int)$user['id']]);
        $order = $stmt->fetch();
        if (!$order) Response::error('Pedido não encontrado.', 404);
        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id');
        $itemsStmt->execute(['order_id' => $order['id']]);
        $items = $itemsStmt->fetchAll();
        if (!$items) Response::error('Pedido sem itens.', 422);
        $addressStmt = $pdo->prepare('SELECT * FROM customer_addresses WHERE id = :id LIMIT 1');
        $addressStmt->execute(['id' => $order['address_id']]);
        $address = $addressStmt->fetch() ?: [];

        $payload = [
            'handle' => Env::require('INFINITEPAY_HANDLE'),
            'redirect_url' => Env::get('INFINITEPAY_REDIRECT_URL'),
            'webhook_url' => Env::get('INFINITEPAY_WEBHOOK_URL'),
            'order_nsu' => $order['order_number'],
            'items' => array_map(static function (array $item): array {
                return [
                    'quantity' => 1,
                    'price' => Helpers::moneyToCents((float)$item['total_price']),
                    'description' => $item['price_mode'] === 'revenda' ? $item['product_name'] . ' - kit revenda' : $item['product_name'],
                ];
            }, $items),
            'customer' => [
                'name' => (string)$order['customer_name'],
                'email' => (string)$order['customer_email'],
                'phone_number' => (string)($order['customer_phone'] ?? ''),
            ],
            'address' => [
                'cep' => (string)($address['cep'] ?? ''),
                'street' => (string)($address['street'] ?? ''),
                'neighborhood' => (string)($address['district'] ?? ''),
                'number' => (string)($address['number'] ?? ''),
                'complement' => (string)($address['complement'] ?? ''),
            ],
        ];

        $result = InfinitePayService::createCheckout($payload);
        if (!$result['ok']) Response::error('Falha ao gerar checkout InfinitePay.', 502, ['provider' => $result]);
        $url = $result['data']['url'] ?? null;
        if (!$url) Response::error('Resposta da InfinitePay sem URL.', 502, ['provider' => $result['data']]);
        Response::json(['ok' => true, 'checkout_url' => $url, 'provider' => $result['data']]);
    }

    public static function confirmInfinitePay(): void
    {
        $data = Request::json();
        foreach (['order_nsu', 'transaction_nsu', 'slug'] as $field) {
            if (empty($data[$field])) Response::error('Campo obrigatório: ' . $field, 422);
        }
        $result = InfinitePayService::paymentCheck([
            'handle' => Env::require('INFINITEPAY_HANDLE'),
            'order_nsu' => (string)$data['order_nsu'],
            'transaction_nsu' => (string)$data['transaction_nsu'],
            'slug' => (string)$data['slug'],
        ]);
        if (!$result['ok']) Response::error('Falha ao confirmar pagamento.', 502, ['provider' => $result]);
        self::applyPaidStatus((string)$data['order_nsu'], (string)$data['transaction_nsu'], (string)$data['slug'], $data['receipt_url'] ?? null, $result['data']);
        Response::json(['ok' => true, 'payment' => $result['data']]);
    }

    public static function infinitePayWebhook(): void
    {
        $data = Request::json();
        if (empty($data['order_nsu']) || empty($data['transaction_nsu'])) Response::error('Payload inválido.', 400);
        self::applyPaidStatus((string)$data['order_nsu'], (string)$data['transaction_nsu'], (string)($data['invoice_slug'] ?? ''), $data['receipt_url'] ?? null, $data);
        Response::json(['ok' => true]);
    }

    private static function applyPaidStatus(string $orderNumber, string $transactionNsu, string $slug, ?string $receiptUrl, array $providerData): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $order = OrderService::findByNumber($orderNumber);
            if (!$order) Response::error('Pedido não encontrado.', 404);
            $status = !empty($providerData['paid']) || !empty($providerData['paid_amount']) ? 'paid' : 'pending';
            $orderStatus = $status === 'paid' ? 'pago' : 'aguardando_pagamento';
            $stmt = $pdo->prepare('UPDATE payments SET provider_reference=:provider_reference, transaction_nsu=:transaction_nsu, invoice_slug=:invoice_slug, receipt_url=:receipt_url, payment_status=:payment_status, paid_amount=:paid_amount, capture_method=:capture_method, raw_response=:raw_response WHERE order_id=:order_id');
            $stmt->execute([
                'provider_reference' => $slug ?: $transactionNsu,
                'transaction_nsu' => $transactionNsu,
                'invoice_slug' => $slug ?: null,
                'receipt_url' => $receiptUrl,
                'payment_status' => $status,
                'paid_amount' => isset($providerData['paid_amount']) ? ((float)$providerData['paid_amount'] / 100) : null,
                'capture_method' => $providerData['capture_method'] ?? null,
                'raw_response' => json_encode($providerData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'order_id' => (int)$order['id'],
            ]);
            $pdo->prepare('UPDATE orders SET status=:status, payment_method=:payment_method, receipt_url=:receipt_url WHERE id=:id')->execute([
                'status' => $orderStatus,
                'payment_method' => $providerData['capture_method'] ?? 'infinitepay',
                'receipt_url' => $receiptUrl,
                'id' => (int)$order['id'],
            ]);
            if ($status === 'paid') OrderService::addTracking((int)$order['id'], 'Pagamento aprovado', 'Pagamento confirmado pela InfinitePay.', true);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error('Falha ao aplicar status do pagamento.', 500, ['details' => $e->getMessage()]);
        }
    }
}
