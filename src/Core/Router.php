<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Response;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            Response::error('Rota não encontrada.', 404);
        }

        $handler();
    }
}
