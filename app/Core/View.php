<?php
declare(strict_types=1);
namespace App\Core;
final class View {
    public static function partial(string $template, array $data = []): string {
        if (!preg_match('~^[a-zA-Z0-9_/-]+$~', $template) || str_contains($template, '..')) throw new \InvalidArgumentException('Invalid view.');
        $file = dirname(__DIR__) . '/Views/' . $template . '.php';
        if (!is_file($file)) throw new \RuntimeException('View not found.');
        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try { require $__file; return (string) ob_get_clean(); }
            catch (\Throwable $error) { ob_end_clean(); throw $error; }
        })($file, $data);
    }
    public static function render(string $template, array $data = [], string $layout = 'layouts/public'): string {
        return self::partial($layout, array_merge($data, ['content' => self::partial($template, $data)]));
    }
}
