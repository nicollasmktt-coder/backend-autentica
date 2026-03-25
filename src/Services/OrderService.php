<?php
declare(strict_types=1);
namespace App\Services;

use App\Config\Database;
use PDO;

final class OrderService
{
    public static function findByNumber(string $orderNumber): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :order_number LIMIT 1');
        $stmt->execute(['order_number' => $orderNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function tracking(int $orderId, bool $onlyVisible = false): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM order_tracking WHERE order_id = :order_id';
        if ($onlyVisible) $sql .= ' AND visible_to_customer = 1';
        $sql .= ' ORDER BY id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addTracking(int $orderId, string $label, ?string $description = null, bool $visible = true): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO order_tracking (order_id, status_label, description, visible_to_customer) VALUES (:order_id, :status_label, :description, :visible)');
        $stmt->execute([
            'order_id' => $orderId,
            'status_label' => $label,
            'description' => $description,
            'visible' => $visible ? 1 : 0,
        ]);
    }

    public static function attachItems(int $orderId, array $items): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, color_name, size_value, quantity, unit_price, total_price, price_mode, resale_kit_sizes) VALUES (:order_id,:product_id,:product_name,:sku,:color_name,:size_value,:quantity,:unit_price,:total_price,:price_mode,:resale_kit_sizes)');
        foreach ($items as $item) {
            $stmt->execute([
                'order_id' => $orderId,
                'product_id' => (int)$item['product_id'],
                'product_name' => (string)$item['product_name'],
                'sku' => (string)$item['sku'],
                'color_name' => $item['color_name'] ?? null,
                'size_value' => $item['size_value'] ?? null,
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['unit_price'],
                'total_price' => (float)$item['total_price'],
                'price_mode' => (string)$item['price_mode'],
                'resale_kit_sizes' => isset($item['resale_kit_sizes']) ? json_encode($item['resale_kit_sizes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);
        }
    }
}
