<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(private readonly int $status, string $message) { parent::__construct($message, $status); }
    public function status(): int { return $this->status; }
}
