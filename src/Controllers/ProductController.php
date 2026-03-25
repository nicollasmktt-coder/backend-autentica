<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Helpers;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validator;
use PDO;
use Throwable;

final class ProductController
{
    public static function index(): void
    {
        $pdo = Database::connection();
        $page = Request::page();
        $perPage = Request::perPage();
        $offset = ($page - 1) * $perPage;
        $search = Request::query('search', '');
        $categoryId = Request::intQuery('category_id');
        $active = Request::query('active');

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(p.name LIKE :search OR p.sku LIKE :search OR p.description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        if ($active !== null && $active !== '') {
            $where[] = 'p.active = :active';
            $params['active'] = (int)$active;
        }
        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $sql = "SELECT p.*, c.name AS category_name, s.name AS subcategory_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN subcategories s ON s.id = p.subcategory_id
                WHERE {$whereSql}
                ORDER BY p.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) self::hydrate($pdo, $row);
        Response::json(['ok' => true, 'data' => $rows, 'meta' => Helpers::paginateMeta($page, $perPage, $total)]);
    }

    public static function show(): void
    {
        $id = Request::intQuery('id');
        $slug = Request::query('slug');
        if ($id <= 0 && !$slug) Response::error('Informe id ou slug.', 422);
        $pdo = Database::connection();
        $stmt = $id > 0 ? $pdo->prepare('SELECT * FROM products WHERE id = :value LIMIT 1') : $pdo->prepare('SELECT * FROM products WHERE slug = :value LIMIT 1');
        $stmt->execute(['value' => $id > 0 ? $id : $slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) Response::error('Produto não encontrado.', 404);
        self::hydrate($pdo, $product);
        Response::json(['ok' => true, 'data' => $product]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['sku', 'name', 'product_type', 'price_normal', 'price_resale']);
        Validator::oneOf((string)$data['product_type'], ['roupa', 'sapato'], 'product_type');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO products (category_id, subcategory_id, sku, name, slug, description, product_type, price_normal, price_resale, stock, active) VALUES (:category_id,:subcategory_id,:sku,:name,:slug,:description,:product_type,:price_normal,:price_resale,:stock,:active)');
            $stmt->execute([
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'sku' => trim((string)$data['sku']),
                'name' => trim((string)$data['name']),
                'slug' => $data['slug'] ?? Helpers::slug((string)$data['name']) . '-' . strtolower(trim((string)$data['sku'])),
                'description' => $data['description'] ?? null,
                'product_type' => (string)$data['product_type'],
                'price_normal' => (float)$data['price_normal'],
                'price_resale' => (float)$data['price_resale'],
                'stock' => (int)($data['stock'] ?? 0),
                'active' => (int)($data['active'] ?? 1),
            ]);
            $productId = (int)$pdo->lastInsertId();
            self::syncChildren($pdo, $productId, $data);
            $pdo->commit();
            Response::json(['ok' => true, 'id' => $productId], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error('Falha ao criar produto.', 500, ['details' => $e->getMessage()]);
        }
    }

    public static function update(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['id', 'sku', 'name', 'product_type', 'price_normal', 'price_resale']);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE products SET category_id=:category_id, subcategory_id=:subcategory_id, sku=:sku, name=:name, slug=:slug, description=:description, product_type=:product_type, price_normal=:price_normal, price_resale=:price_resale, stock=:stock, active=:active WHERE id=:id');
            $stmt->execute([
                'id' => (int)$data['id'],
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'sku' => trim((string)$data['sku']),
                'name' => trim((string)$data['name']),
                'slug' => $data['slug'] ?? Helpers::slug((string)$data['name']) . '-' . strtolower(trim((string)$data['sku'])),
                'description' => $data['description'] ?? null,
                'product_type' => (string)$data['product_type'],
                'price_normal' => (float)$data['price_normal'],
                'price_resale' => (float)$data['price_resale'],
                'stock' => (int)($data['stock'] ?? 0),
                'active' => (int)($data['active'] ?? 1),
            ]);
            self::clearChildren($pdo, (int)$data['id']);
            self::syncChildren($pdo, (int)$data['id'], $data);
            $pdo->commit();
            Response::json(['ok' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error('Falha ao atualizar produto.', 500, ['details' => $e->getMessage()]);
        }
    }

    public static function destroy(): void
    {
        Auth::requireAdmin();
        $id = Request::intQuery('id');
        if ($id <= 0) Response::error('ID inválido.', 422);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }

    public static function deleteImage(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['id']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM product_images WHERE id = :id');
        $stmt->execute(['id' => (int)$data['id']]);
        Response::json(['ok' => true]);
    }

    private static function hydrate(PDO $pdo, array &$row): void
    {
        $id = (int)$row['id'];
        foreach ([['images','product_images','sort_order, id'], ['colors','product_colors','id'], ['sizes','product_sizes','id']] as $spec) {
            [$key, $table, $order] = $spec;
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE product_id = :id ORDER BY {$order}");
            $stmt->execute(['id' => $id]);
            $row[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    private static function clearChildren(PDO $pdo, int $id): void
    {
        foreach (['product_images','product_colors','product_sizes'] as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE product_id = :id");
            $stmt->execute(['id' => $id]);
        }
    }

    private static function syncChildren(PDO $pdo, int $productId, array $data): void
    {
        $imagesStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_url, public_id, sort_order) VALUES (:product_id,:image_url,:public_id,:sort_order)');
        foreach (($data['images'] ?? []) as $i => $image) {
            if (!is_array($image) || empty($image['image_url'])) continue;
            $imagesStmt->execute(['product_id' => $productId, 'image_url' => (string)$image['image_url'], 'public_id' => $image['public_id'] ?? null, 'sort_order' => (int)($image['sort_order'] ?? $i)]);
        }
        $colorsStmt = $pdo->prepare('INSERT INTO product_colors (product_id, color_name, color_hex) VALUES (:product_id,:color_name,:color_hex)');
        foreach (($data['colors'] ?? []) as $color) {
            if (is_string($color) && trim($color) !== '') $colorsStmt->execute(['product_id' => $productId, 'color_name' => trim($color), 'color_hex' => null]);
            if (is_array($color) && !empty($color['color_name'])) $colorsStmt->execute(['product_id' => $productId, 'color_name' => (string)$color['color_name'], 'color_hex' => $color['color_hex'] ?? null]);
        }
        $sizesStmt = $pdo->prepare('INSERT INTO product_sizes (product_id, size_value) VALUES (:product_id,:size_value)');
        foreach (($data['sizes'] ?? []) as $size) {
            $value = is_array($size) ? ($size['size_value'] ?? null) : $size;
            if ($value !== null && trim((string)$value) !== '') $sizesStmt->execute(['product_id' => $productId, 'size_value' => trim((string)$value)]);
        }
    }
}
