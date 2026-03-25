<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Support\Request;
use App\Support\Response;

final class CategoryController
{
    public static function index(): void
    {
        $pdo = Database::getConnection();
        $categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
        $subcategories = $pdo->query('SELECT * FROM subcategories ORDER BY name ASC')->fetchAll();
        Response::json(['ok' => true, 'categories' => $categories, 'subcategories' => $subcategories]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? self::slug($name)));
        if ($name === '') {
            Response::error('Nome da categoria é obrigatório.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        Response::json(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    public static function storeSubcategory(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        $categoryId = (int) ($data['category_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? self::slug($name)));
        if ($categoryId <= 0 || $name === '') {
            Response::error('Categoria e nome são obrigatórios.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO subcategories (category_id, name, slug) VALUES (:category_id, :name, :slug)');
        $stmt->execute(['category_id' => $categoryId, 'name' => $name, 'slug' => $slug]);
        Response::json(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    private static function slug(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(trim($value))) ?: '';
        return trim($value, '-');
    }
}
