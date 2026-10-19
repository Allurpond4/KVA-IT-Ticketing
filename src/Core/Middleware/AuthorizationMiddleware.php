<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly array $roles = []) {}
    public function process(Request $request, callable $next): Response { if ($this->roles !== []) { $role = Session::get('role', Session::get('permission')); if (!in_array($role, $this->roles, true)) throw new HttpException(403, 'Forbidden'); } return $next($request); }
}
