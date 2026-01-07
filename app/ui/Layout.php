<?php

final class Layout
{
    public static function renderDocumentStart(string $title, array $meta = []): void
    {
        $titleEscaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $description = isset($meta['description']) ? trim((string) $meta['description']) : '';
        $keywords = isset($meta['keywords']) ? trim((string) $meta['keywords']) : '';

        echo "<!doctype html>\n";
        echo "<html lang=\"ru\">\n";
        echo "<head>\n";
        echo "    <meta charset=\"utf-8\">\n";
        echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
        self::renderCss();
        echo "    <title>{$titleEscaped}</title>\n";
        if ($description !== '') {
            echo "    <meta name=\"description\" content=\"" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "\">\n";
        }
        if ($keywords !== '') {
            echo "    <meta name=\"keywords\" content=\"" . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . "\">\n";
        }
        echo "</head>\n";
        echo "<body class=\"bg-light\">\n";
        echo "<div class=\"page-wrapper d-flex flex-column min-vh-100\">\n";
        echo "<div class=\"content-wrapper flex-grow-1\">\n";
    }

    public static function renderDocumentEnd(): void
    {
        echo "</div>\n";
        echo "</div>\n";
        self::renderJs();
        echo "</body>\n";
        echo "</html>\n";
    }

    public static function renderNavbar(string $brand, array $links = []): void
    {
        $brandEscaped = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
        echo '<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">';
        echo '<div class="container">';
        echo '<a class="navbar-brand fw-semibold" href="/">' . $brandEscaped . '</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span>';
        echo '</button>';
        echo '<div class="collapse navbar-collapse" id="navbarMain">';
        echo '<ul class="navbar-nav ms-auto">';
        foreach ($links as $link) {
            $label = htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars((string) ($link['href'] ?? '#'), ENT_QUOTES, 'UTF-8');
            echo '<li class="nav-item"><a class="nav-link" href="' . $href . '">' . $label . '</a></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</nav>';
    }

    public static function render(string $layoutKey, array $ctx, callable $body): void
    {
        $layoutKey = trim($layoutKey) !== '' ? $layoutKey : 'default';
        $layoutPath = self::layoutPath($layoutKey);
        if (!is_file($layoutPath)) {
            $layoutKey = 'default';
            $layoutPath = self::layoutPath($layoutKey);
        }

        $ctx['title'] = (string) ($ctx['title'] ?? '');
        $ctx['meta'] = isset($ctx['meta']) && is_array($ctx['meta']) ? $ctx['meta'] : [];
        $ctx['site'] = isset($ctx['site']) && is_array($ctx['site']) ? $ctx['site'] : [];
        $ctx['section'] = $ctx['section'] ?? null;

        require $layoutPath;
    }

    public static function layoutExists(string $layoutKey): bool
    {
        return is_file(self::layoutPath($layoutKey));
    }

    private static function layoutPath(string $layoutKey): string
    {
        return __DIR__ . '/layouts/' . $layoutKey . '.php';
    }

    public static function sowAssetsAvailable(): bool
    {
        $root = dirname(__DIR__, 2);
        $files = [
            $root . '/public_html/assets/sow/css/core.min.css',
            $root . '/public_html/assets/sow/css/vendor_bundle.min.css',
            $root . '/public_html/assets/sow/js/core.min.js',
            $root . '/public_html/assets/sow/js/vendor_bundle.min.js',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                return false;
            }
        }

        return true;
    }

    private static function renderCss(): void
    {
        if (self::sowAssetsAvailable()) {
            echo "    <link href=\"/assets/sow/css/vendor_bundle.min.css\" rel=\"stylesheet\">\n";
            echo "    <link href=\"/assets/sow/css/core.min.css\" rel=\"stylesheet\">\n";
            return;
        }

        echo "    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n";
    }

    private static function renderJs(): void
    {
        if (self::sowAssetsAvailable()) {
            echo "<script src=\"/assets/sow/js/vendor_bundle.min.js\"></script>\n";
            echo "<script src=\"/assets/sow/js/core.min.js\"></script>\n";
            return;
        }

        echo "<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\"></script>\n";
    }
}
