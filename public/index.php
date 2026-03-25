<?php
declare(strict_types=1);

use App\Controllers\AddressController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\CouponController;
use App\Controllers\CustomerAdminController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\ProductController;
use App\Controllers\UploadController;
use App\Core\Router;
use App\Support\Env;
use App\Support\Response;

$basePath = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $basePath . '/src/' . $relative . '.php';
    if (is_file($file)) require_once $file;
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
$router->add('PATCH', '/api/categories', [CategoryController::class, 'update']);
$router->add('DELETE', '/api/categories', [CategoryController::class, 'destroy']);
$router->add('POST', '/api/subcategories', [CategoryController::class, 'storeSubcategory']);
$router->add('PATCH', '/api/subcategories', [CategoryController::class, 'updateSubcategory']);
$router->add('DELETE', '/api/subcategories', [CategoryController::class, 'destroySubcategory']);
$router->add('GET', '/api/products', [ProductController::class, 'index']);
$router->add('GET', '/api/products/show', [ProductController::class, 'show']);
$router->add('POST', '/api/products', [ProductController::class, 'store']);
$router->add('PATCH', '/api/products', [ProductController::class, 'update']);
$router->add('DELETE', '/api/products', [ProductController::class, 'destroy']);
$router->add('POST', '/api/products/images/delete', [ProductController::class, 'deleteImage']);
$router->add('GET', '/api/coupons', [CouponController::class, 'index']);
$router->add('POST', '/api/coupons', [CouponController::class, 'store']);
$router->add('PATCH', '/api/coupons', [CouponController::class, 'update']);
$router->add('DELETE', '/api/coupons', [CouponController::class, 'destroy']);
$router->add('GET', '/api/admin/customers', [CustomerAdminController::class, 'index']);
$router->add('GET', '/api/admin/customers/show', [CustomerAdminController::class, 'show']);
$router->add('GET', '/api/customers/addresses', [AddressController::class, 'index']);
$router->add('POST', '/api/customers/addresses', [AddressController::class, 'store']);
$router->add('PATCH', '/api/customers/addresses', [AddressController::class, 'update']);
$router->add('DELETE', '/api/customers/addresses', [AddressController::class, 'destroy']);
$router->add('GET', '/api/orders', [OrderController::class, 'index']);
$router->add('GET', '/api/orders/show', [OrderController::class, 'show']);
$router->add('POST', '/api/orders', [OrderController::class, 'store']);
$router->add('PATCH', '/api/orders/status', [OrderController::class, 'updateStatus']);
$router->add('GET', '/api/orders/tracking', [OrderController::class, 'trackingIndex']);
$router->add('POST', '/api/orders/tracking', [OrderController::class, 'trackingStore']);
$router->add('POST', '/api/payments/infinitepay/checkout', [PaymentController::class, 'createInfinitePayCheckout']);
$router->add('POST', '/api/payments/infinitepay/confirm', [PaymentController::class, 'confirmInfinitePay']);
$router->add('POST', '/api/payments/infinitepay/webhook', [PaymentController::class, 'infinitePayWebhook']);
$router->add('POST', '/api/uploads/cloudinary/sign', [UploadController::class, 'signCloudinary']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    Response::error('Erro interno.', 500, ['details' => $e->getMessage()]);
}
