<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response { if (!Session::has('user_id') && !Session::has('id') && !Session::has('username')) throw new HttpException(401, 'Authentication Required'); return $next($request); }
}
