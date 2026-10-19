<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function required(array $input, array $fields): array { $errors = []; foreach ($fields as $field) { if (!isset($input[$field]) || trim((string) $input[$field]) === '') $errors[$field] = 'This field is required.'; } return $errors; }
    public static function csrf(string $token): bool { return hash_equals((string) Session::get('_csrf', ''), $token); }
    public static function csrfToken(): string { $token = Session::get('_csrf'); if (!is_string($token) || $token === '') { $token = bin2hex(random_bytes(32)); Session::put('_csrf', $token); } return $token; }
}
