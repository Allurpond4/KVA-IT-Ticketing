<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response { if ($request->method() === 'POST' && !Validator::csrf((string) $request->input('_csrf', $request->header('X-CSRF-TOKEN', '')))) throw new HttpException(419, 'CSRF Token Mismatch'); return $next($request); }
}
