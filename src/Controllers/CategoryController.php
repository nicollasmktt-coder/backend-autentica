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

final class CategoryController
{
    public static function index(): void
    {
        $pdo = Database::connection();
        $categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($categories as &$category) {
            $stmt = $pdo->prepare('SELECT * FROM subcategories WHERE category_id = :category_id ORDER BY name');
            $stmt->execute(['category_id' => $category['id']]);
            $category['subcategories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        Response::json(['ok' => true, 'data' => $categories]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['name']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
        $stmt->execute(['name' => trim((string)$data['name']), 'slug' => Helpers::slug((string)$data['name'])]);
        Response::json(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    }

    public static function update(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['id', 'name']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE categories SET name=:name, slug=:slug WHERE id=:id');
        $stmt->execute(['id' => (int)$data['id'], 'name' => trim((string)$data['name']), 'slug' => Helpers::slug((string)$data['name'])]);
        Response::json(['ok' => true]);
    }

    public static function destroy(): void
    {
        Auth::requireAdmin();
        $id = Request::intQuery('id');
        if ($id <= 0) Response::error('ID inválido.', 422);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }

    public static function storeSubcategory(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['category_id', 'name']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO subcategories (category_id, name, slug) VALUES (:category_id, :name, :slug)');
        $stmt->execute(['category_id' => (int)$data['category_id'], 'name' => trim((string)$data['name']), 'slug' => Helpers::slug((string)$data['name'])]);
        Response::json(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    }

    public static function updateSubcategory(): void
    {
        Auth::requireAdmin();
        $data = Request::json();
        Validator::requireFields($data, ['id', 'category_id', 'name']);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE subcategories SET category_id=:category_id, name=:name, slug=:slug WHERE id=:id');
        $stmt->execute(['id' => (int)$data['id'], 'category_id' => (int)$data['category_id'], 'name' => trim((string)$data['name']), 'slug' => Helpers::slug((string)$data['name'])]);
        Response::json(['ok' => true]);
    }

    public static function destroySubcategory(): void
    {
        Auth::requireAdmin();
        $id = Request::intQuery('id');
        if ($id <= 0) Response::error('ID inválido.', 422);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM subcategories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }
}
