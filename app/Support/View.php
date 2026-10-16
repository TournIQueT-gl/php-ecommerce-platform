<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    public static function render(string $templateName, array $data = []): void
    {
        $templateName = ltrim(str_replace('\\', '/', $templateName), '/');
        if ($templateName === '' || str_contains($templateName, '..')) {
            throw new RuntimeException('Invalid view path.');
        }

        $root = dirname(__DIR__, 2);
        $path = $root . '/views/' . $templateName;
        if (!str_ends_with($path, '.php')) $path .= '.php';
        if (!is_file($path)) throw new RuntimeException('View not found: ' . $templateName);

        extract($data, EXTR_SKIP);
        require $path;
    }
}
