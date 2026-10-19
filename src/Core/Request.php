<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(private readonly string $method, private readonly string $uri, private readonly array $query, private readonly array $body, private readonly array $server) {}
    public static function capture(): self { return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', $_GET, $_POST, $_SERVER); }
    public function method(): string { return $this->method; }
    public function path(): string { return $this->uri; }
    public function input(?string $key = null, mixed $default = null): mixed { $data = $this->method === 'GET' ? $this->query : $this->body; return $key === null ? $data : ($data[$key] ?? $default); }
    public function header(string $name, mixed $default = null): mixed { $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name)); return $this->server[$key] ?? $default; }
}
