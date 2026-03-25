<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Support\Response;
use Throwable;

final class HealthController
{
    public static function app(): void
    {
        Response::json(['ok' => true, 'message' => 'API online']);
    }

    public static function db(): void
    {
        try {
            $pdo = Database::getConnection();
            $pdo->query('SELECT 1');
            Response::json(['ok' => true, 'message' => 'Banco conectado']);
        } catch (Throwable $e) {
            Response::error('Falha na conexão com banco.', 500, ['details' => $e->getMessage()]);
        }
    }
}
