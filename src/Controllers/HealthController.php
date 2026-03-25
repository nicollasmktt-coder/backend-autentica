<?php

declare(strict_types=1);

class HealthController
{
    public static function index(): void
    {
        echo json_encode([
            'ok' => true,
            'app' => 'AUTENTICA FASHIONF API',
            'timestamp' => gmdate('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function db(): void
    {
        try {
            $pdo = Database::connection();
            $version = $pdo->query('SELECT VERSION() as version')->fetch();
            echo json_encode([
                'ok' => true,
                'database' => 'connected',
                'version' => $version['version'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'database' => 'error',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
