<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public function __construct(private readonly string $file) {}
    public function info(string $message, array $context = []): void { $this->write('INFO', $message, $context); }
    public function error(string $message, array $context = []): void { $this->write('ERROR', $message, $context); }
    private function write(string $level, string $message, array $context): void { $dir = dirname($this->file); if (!is_dir($dir)) mkdir($dir, 0775, true); error_log(sprintf("[%s] %s %s\n", $level, $message, json_encode($context, JSON_UNESCAPED_SLASHES)), 3, $this->file); }
}
