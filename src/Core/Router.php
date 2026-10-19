<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

final class Router
{
    private array $routes = [];
    public function get(string $pattern, callable $handler, array $middleware = []): void { $this->add('GET', $pattern, $handler, $middleware); }
    public function post(string $pattern, callable $handler, array $middleware = []): void { $this->add('POST', $pattern, $handler, $middleware); }
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void { $this->routes[] = [strtoupper($method), $pattern, $handler, $middleware]; }
    public function dispatch(Request $request): Response { foreach ($this->routes as [$method, $pattern, $handler, $middleware]) { $matches = $this->match($pattern, $request->path()); if ($matches === null) continue; if ($method !== $request->method()) throw new HttpException(405, 'Method Not Allowed'); return (new Middleware\Pipeline($middleware))->handle($request, fn(Request $req) => $this->call($handler, $req, $matches)); } throw new HttpException(404, 'Not Found'); }
    private function call(callable $handler, Request $request, array $params): Response { $result = $handler($request, ...array_values($params)); return $result instanceof Response ? $result : Response::text((string) $result); }
    private function match(string $pattern, string $path): ?array { $names = []; $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function($m) use (&$names) { $names[] = $m[1]; return '([^/]+)'; }, rtrim($pattern, '/') ?: '/'); if (!preg_match('#^' . $regex . '/?$#', $path, $matches)) return null; array_shift($matches); return array_combine($names, $matches) ?: []; }
}
