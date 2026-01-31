<?php

final class LayoutCatalog
{
    public static function layoutExists(string $layoutKey): bool
    {
        return is_file(self::layoutPath($layoutKey));
    }

    public static function listLayouts(): array
    {
        $path = dirname(__DIR__, 3) . '/templates/layouts/*.php';
        $files = glob($path) ?: [];
        $layouts = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (str_ends_with($name, '.nav')) {
                continue;
            }
            if ($name !== '') {
                $layouts[] = $name;
            }
        }
        sort($layouts);

        return $layouts;
    }

    private static function layoutPath(string $layoutKey): string
    {
        return dirname(__DIR__, 3) . '/templates/layouts/' . $layoutKey . '.php';
    }
}
