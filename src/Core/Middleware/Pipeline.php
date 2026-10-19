<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;

final class Pipeline
{
    public function __construct(private readonly array $middleware) {}
    public function handle(Request $request, callable $destination): Response { $next = array_reduce(array_reverse($this->middleware), fn(callable $next, mixed $middleware) => fn(Request $request): Response => $this->resolve($middleware)->process($request, $next), $destination); return $next($request); }
    private function resolve(mixed $middleware): MiddlewareInterface { return is_string($middleware) ? new $middleware() : $middleware; }
}
