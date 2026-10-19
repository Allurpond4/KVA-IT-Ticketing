<?php
declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    public function __construct(protected readonly Database $database) {}
    protected function db(): \PDO { return $this->database->connection(); }
}
