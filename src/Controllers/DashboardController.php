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
        $pdo = Database::getConnection();

        $faturamento = (float) ($pdo->query('SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status <> "cancelado"')->fetchColumn() ?: 0);
        $orders = (int) ($pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() ?: 0);
        $products = (int) ($pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() ?: 0);
        $customers = (int) ($pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn() ?: 0);
        $ticket = $orders > 0 ? $faturamento / $orders : 0;
        $enviados = (int) ($pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'enviado'")->fetchColumn() ?: 0);
        $preparo = (int) ($pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'em_preparo'")->fetchColumn() ?: 0);

        Response::json(['ok' => true, 'summary' => [
            'faturamento' => $faturamento,
            'orders' => $orders,
            'products' => $products,
            'customers' => $customers,
            'ticket_medio' => $ticket,
            'enviados' => $enviados,
            'em_preparo' => $preparo,
        ]]);
    }
}
