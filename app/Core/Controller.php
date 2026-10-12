<?php
declare(strict_types=1);
namespace App\Core;
abstract class Controller {
    protected function view(string $template, array $data = [], int $status = 200): Response { return new Response(View::render($template, $data), $status); }
}
