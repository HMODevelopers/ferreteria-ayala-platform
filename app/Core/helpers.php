<?php
declare(strict_types=1);
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function asset(string $path): string { return '/assets/' . ltrim($path, '/'); }
function component(string $name, array $data = []): void { echo App\Core\View::partial('components/public/' . $name, $data); }
function icon(string $name, string $class = ''): string {
    $paths = [
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        'list' => '<rect x="5" y="4" width="14" height="17" rx="1"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'truck' => '<path d="M3 6h11v11H3zM14 10h4l3 4v3h-7"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>',
    ];
    return '<svg class="icon ' . e($class) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['arrow']) . '</svg>';
}
