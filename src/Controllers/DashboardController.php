<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Response;
use PDO;

final class DashboardController
{
    public static function summary(): void
    {
        Auth::requireAdmin();
        $pdo = Database::connection();
        $summary = [
            'revenue_total' => (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status IN ('pago','em_preparo','enviado','entregue')")->fetchColumn(),
            'orders_total' => (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'orders_paid' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pago','em_preparo','enviado','entregue')")->fetchColumn(),
            'orders_preparing' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'em_preparo'")->fetchColumn(),
            'orders_sent' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'enviado'")->fetchColumn(),
            'products_total' => (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
            'customers_total' => (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
            'coupons_total' => (int)$pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn(),
        ];
        $summary['average_ticket'] = $summary['orders_paid'] > 0 ? round($summary['revenue_total'] / $summary['orders_paid'], 2) : 0.0;
        $latest = $pdo->query("SELECT id, order_number, status, total_amount, created_at FROM orders ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['ok' => true, 'data' => array_merge($summary, ['latest_orders' => $latest])]);
    }
}
