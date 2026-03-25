<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Request;
use App\Support\Response;
use PDO;
use Throwable;

final class ProductController
{
    public static function index(): void
    {
        $pdo = Database::getConnection();
        $rows = $pdo->query('SELECT p.*, c.name AS category_name, s.name AS subcategory_name FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN subcategories s ON s.id = p.subcategory_id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['images'] = self::fetchMany($pdo, 'SELECT id, image_url, public_id, sort_order FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC', (int) $row['id']);
            $row['colors'] = self::fetchMany($pdo, 'SELECT id, color_name, color_hex FROM product_colors WHERE product_id = :id ORDER BY id ASC', (int) $row['id']);
            $row['sizes'] = self::fetchMany($pdo, 'SELECT id, size_value FROM product_sizes WHERE product_id = :id ORDER BY id ASC', (int) $row['id']);
        }

        Response::json(['ok' => true, 'products' => $rows]);
    }

    public static function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Response::error('ID inválido.');
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            Response::error('Produto não encontrado.', 404);
        }
        $product['images'] = self::fetchMany($pdo, 'SELECT id, image_url, public_id, sort_order FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC', $id);
        $product['colors'] = self::fetchMany($pdo, 'SELECT id, color_name, color_hex FROM product_colors WHERE product_id = :id ORDER BY id ASC', $id);
        $product['sizes'] = self::fetchMany($pdo, 'SELECT id, size_value FROM product_sizes WHERE product_id = :id ORDER BY id ASC', $id);
        Response::json(['ok' => true, 'product' => $product]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $name = trim((string) ($data['name'] ?? ''));
            $sku = trim((string) ($data['sku'] ?? ''));
            if ($name === '' || $sku === '') {
                Response::error('Nome e SKU são obrigatórios.');
            }

            $stmt = $pdo->prepare('INSERT INTO products (category_id, subcategory_id, sku, name, slug, description, product_type, price_normal, price_resale, stock, active) VALUES (:category_id, :subcategory_id, :sku, :name, :slug, :description, :product_type, :price_normal, :price_resale, :stock, :active)');
            $stmt->execute([
                'category_id' => $data['category_id'] ?: null,
                'subcategory_id' => $data['subcategory_id'] ?: null,
                'sku' => $sku,
                'name' => $name,
                'slug' => self::slug((string) ($data['slug'] ?? $name)),
                'description' => (string) ($data['description'] ?? ''),
                'product_type' => (string) ($data['product_type'] ?? 'sapato'),
                'price_normal' => (float) ($data['price_normal'] ?? 0),
                'price_resale' => (float) ($data['price_resale'] ?? 0),
                'stock' => (int) ($data['stock'] ?? 0),
                'active' => (int) ($data['active'] ?? 1),
            ]);

            $productId = (int) $pdo->lastInsertId();
            self::syncImages($pdo, $productId, $data['images'] ?? []);
            self::syncColors($pdo, $productId, $data['colors'] ?? []);
            self::syncSizes($pdo, $productId, $data['sizes'] ?? []);

            $pdo->commit();
            Response::json(['ok' => true, 'id' => $productId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Falha ao criar produto.', 500, ['details' => $e->getMessage()]);
        }
    }

    private static function syncImages(PDO $pdo, int $productId, array $images): void
    {
        $stmt = $pdo->prepare('INSERT INTO product_images (product_id, image_url, public_id, sort_order) VALUES (:product_id, :image_url, :public_id, :sort_order)');
        foreach ($images as $i => $image) {
            if (!is_array($image) || empty($image['image_url'])) {
                continue;
            }
            $stmt->execute([
                'product_id' => $productId,
                'image_url' => (string) $image['image_url'],
                'public_id' => $image['public_id'] ?? null,
                'sort_order' => (int) ($image['sort_order'] ?? $i),
            ]);
        }
    }

    private static function syncColors(PDO $pdo, int $productId, array $colors): void
    {
        $stmt = $pdo->prepare('INSERT INTO product_colors (product_id, color_name, color_hex) VALUES (:product_id, :color_name, :color_hex)');
        foreach ($colors as $color) {
            if (is_string($color) && trim($color) !== '') {
                $stmt->execute(['product_id' => $productId, 'color_name' => trim($color), 'color_hex' => null]);
                continue;
            }
            if (is_array($color) && !empty($color['color_name'])) {
                $stmt->execute(['product_id' => $productId, 'color_name' => (string) $color['color_name'], 'color_hex' => $color['color_hex'] ?? null]);
            }
        }
    }

    private static function syncSizes(PDO $pdo, int $productId, array $sizes): void
    {
        $stmt = $pdo->prepare('INSERT INTO product_sizes (product_id, size_value) VALUES (:product_id, :size_value)');
        foreach ($sizes as $size) {
            $value = is_array($size) ? ($size['size_value'] ?? null) : $size;
            if (!$value) {
                continue;
            }
            $stmt->execute(['product_id' => $productId, 'size_value' => (string) $value]);
        }
    }

    private static function fetchMany(PDO $pdo, string $sql, int $id): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function slug(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(trim($value))) ?: '';
        return trim($value, '-');
    }
}
