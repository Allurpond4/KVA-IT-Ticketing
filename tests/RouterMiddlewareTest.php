<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Middleware\Pipeline;

final class HeaderMiddleware implements MiddlewareInterface { public function process(Request $request, callable $next): Response { return $next($request); } }
$router = new Router();
$router->get('/tickets/{id}', fn(Request $request, string $id) => Response::text('ticket:' . $id), [new HeaderMiddleware()]);
$response = $router->dispatch(new Request('GET', '/tickets/42', [], [], []));
if ($response->content() !== 'ticket:42' || $response->status() !== 200) exit(1);
echo "Router and middleware tests passed\n";
