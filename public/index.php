<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

$services = require dirname(__DIR__) . '/src/Bootstrap.php';
$router = $services['router'];
$router->get('/', fn() => Response::text('KVA IT MVC foundation is ready.'));
$router->get('/health', fn() => Response::text('OK'));
$request = Request::capture();
try { $router->dispatch($request)->send(); } catch (HttpException $exception) { $status = $exception->status(); http_response_code($status); require dirname(__DIR__) . '/public/errors/' . ($status === 401 || $status === 419 ? '403' : $status) . '.php'; } catch (Throwable $exception) { $services['logger']->error($exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]); http_response_code(500); require dirname(__DIR__) . '/public/errors/500.php'; }
