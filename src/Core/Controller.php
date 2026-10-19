<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function response(string $content, int $status = 200, array $headers = []): Response { return new Response($content, $status, $headers); }
    protected function redirect(string $url): Response { return Response::redirect($url); }
}
