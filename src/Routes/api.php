<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && ($path === '/' || $path === '/api' || $path === '/api/health')) {
    HealthController::index();
    return;
}

if ($method === 'GET' && $path === '/api/health/db') {
    HealthController::db();
    return;
}

http_response_code(404);
echo json_encode([
    'ok' => false,
    'message' => 'Route not found',
    'path' => $path,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
