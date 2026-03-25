<?php

declare(strict_types=1);

use App\Controllers\AddressController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\OrderController;
use App\Controllers\ProductController;
use App\Controllers\UploadController;
use App\Core\Router;
use App\Support\Env;
use App\Support\Response;

$basePath = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $basePath . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

Env::load($basePath);

$origins = array_filter(array_map('trim', explode(',', Env::get('CORS_ORIGINS', '*'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
if (in_array('*', $origins, true) || in_array($origin, $origins, true)) {
    header('Access-Control-Allow-Origin: ' . ($origin === '' ? '*' : $origin));
}
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router = new Router();
$router->add('GET', '/api/health', [HealthController::class, 'app']);
$router->add('GET', '/api/health/db', [HealthController::class, 'db']);
$router->add('POST', '/api/auth/register', [AuthController::class, 'register']);
$router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
$router->add('POST', '/api/auth/admin/login', [AuthController::class, 'adminLogin']);
$router->add('GET', '/api/auth/me', [AuthController::class, 'me']);
$router->add('GET', '/api/dashboard/summary', [DashboardController::class, 'summary']);
$router->add('GET', '/api/categories', [CategoryController::class, 'index']);
$router->add('POST', '/api/categories', [CategoryController::class, 'store']);
$router->add('POST', '/api/subcategories', [CategoryController::class, 'storeSubcategory']);
$router->add('GET', '/api/products', [ProductController::class, 'index']);
$router->add('GET', '/api/products/show', [ProductController::class, 'show']);
$router->add('POST', '/api/products', [ProductController::class, 'store']);
$router->add('GET', '/api/customers/addresses', [AddressController::class, 'index']);
$router->add('POST', '/api/customers/addresses', [AddressController::class, 'store']);
$router->add('GET', '/api/orders', [OrderController::class, 'index']);
$router->add('POST', '/api/orders', [OrderController::class, 'store']);
$router->add('PATCH', '/api/orders/status', [OrderController::class, 'updateStatus']);
$router->add('POST', '/api/uploads/cloudinary/sign', [UploadController::class, 'signCloudinary']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    Response::error('Erro interno.', 500, ['details' => $e->getMessage()]);
}
