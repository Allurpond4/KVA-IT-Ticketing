<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(private readonly string $content = '', private readonly int $status = 200, private readonly array $headers = []) {}
    public static function text(string $content, int $status = 200, array $headers = []): self { return new self($content, $status, $headers); }
    public static function redirect(string $url, int $status = 302): self { return new self('', $status, ['Location' => $url]); }
    public function send(): void { http_response_code($this->status); foreach ($this->headers as $name => $value) header($name . ': ' . $value); echo $this->content; }
    public function status(): int { return $this->status; }
    public function content(): string { return $this->content; }
}
