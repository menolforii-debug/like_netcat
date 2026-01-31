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

    public static function renderSectionHeader(array $section, array $children = []): void
    {
        $title = isset($section['title']) ? (string) $section['title'] : '';
        echo '<div class="card shadow-sm mb-4">';
        echo '<div class="card-header bg-white">';
        echo '<h1 class="h4 mb-0">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '</div>';
        echo '<div class="card-body">';
        if (!empty($children)) {
            echo '<div class="list-group list-group-flush">';
            foreach ($children as $child) {
                $path = isset($child['path']) ? (string) $child['path'] : '/';
                $childTitle = isset($child['title']) ? (string) $child['title'] : '';
                echo '<a class="list-group-item list-group-item-action" href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '">';
                echo htmlspecialchars($childTitle, ENT_QUOTES, 'UTF-8');
                echo '</a>';
            }
            echo '</div>';
        } else {
            echo '<div class="text-muted">Разделов нет.</div>';
        }
        echo '</div>';
        echo '</div>';
    }

 
    public static function renderPagination(
        ?int $currentPage,
        ?int $totalPages,
        ?string $baseUrl,
        array $params = [],
        array $options = []
    ): void
    {
        if ($currentPage === null || $totalPages === null || $baseUrl === null) {
            return;
        }

        $items = self::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, $options);
 
        if (empty($items)) {
            return;
        }

        echo '<nav aria-label="Навигация по страницам">';
        echo '<ul class="pagination">';

        foreach ($items as $item) {
            $classes = ['page-item'];
            if (!empty($item['active'])) {
                $classes[] = 'active';
            }
            if (!empty($item['disabled'])) {
                $classes[] = 'disabled';
            }
            $label = (string) ($item['label'] ?? '');
            $url = (string) ($item['url'] ?? '#');
            $aria = (string) ($item['aria'] ?? '');

            echo '<li class="' . implode(' ', $classes) . '">';
            echo '<a class="page-link" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
            if ($aria !== '') {
                echo ' aria-label="' . htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') . '"';
            }
            echo '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</nav>';
    }

    /**
     * Возвращает список элементов пагинации для кастомной разметки.
     */
 
    public static function getPaginationItems(
        ?int $currentPage,
        ?int $totalPages,
        ?string $baseUrl,
        array $params = [],
        array $options = []
    ): array
    {
        if ($currentPage === null || $totalPages === null || $baseUrl === null) {
            return [];
        }

        $showSingle = !empty($options['show_single']);
        if ($totalPages <= 1 && !$showSingle) {
 
            return [];
        }

        $currentPage = max(1, min($currentPage, $totalPages));
        $params = array_filter($params, static function ($value): bool {
            return $value !== null && $value !== '';
        });

        $items = [];
        $items[] = [
            'type' => 'prev',
            'page' => max(1, $currentPage - 1),
            'label' => '«',
            'aria' => 'Предыдущая',
            'url' => self::paginationUrl($baseUrl, $params, $currentPage - 1),
            'active' => false,
            'disabled' => $currentPage <= 1,
        ];

        for ($page = 1; $page <= $totalPages; $page++) {
            $items[] = [
                'type' => 'page',
                'page' => $page,
                'label' => (string) $page,
                'aria' => '',
                'url' => self::paginationUrl($baseUrl, $params, $page),
                'active' => $page === $currentPage,
                'disabled' => false,
            ];
        }

        $items[] = [
            'type' => 'next',
            'page' => min($totalPages, $currentPage + 1),
            'label' => '»',
            'aria' => 'Следующая',
            'url' => self::paginationUrl($baseUrl, $params, $currentPage + 1),
            'active' => false,
            'disabled' => $currentPage >= $totalPages,
        ];

        return $items;
    }

    public static function getMainMenuItems(array $ctx, int $maxDepth = 2): array
    {
        $nav = core()->nav()->as_array();
        $items = $nav->get_by_level(0);
        if ($items === []) {
            return [];
        }

        $currentId = (int) (($ctx['section']['id'] ?? 0));
        return self::buildMenuItemsFromNav($nav, $items, $currentId, 1, $maxDepth);
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

        $previousScope = $GLOBALS['_snip_scope'] ?? null;
        $GLOBALS['_snip_scope'] = get_defined_vars();

        $navPath = self::layoutNavPath($layoutKey);
        if (is_file($navPath)) {
            require $navPath;
        }

        require $layoutPath;

        if ($previousScope !== null) {
            $GLOBALS['_snip_scope'] = $previousScope;
        } else {
            unset($GLOBALS['_snip_scope']);
        }
    }

    public static function layoutExists(string $layoutKey): bool
    {
        return is_file(self::layoutPath($layoutKey));
    }

    public static function listLayouts(): array
    {
        $path = dirname(__DIR__, 2) . '/templates/layouts/*.php';
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
        return dirname(__DIR__, 2) . '/templates/layouts/' . $layoutKey . '.php';
    }

    private static function layoutNavPath(string $layoutKey): string
    {
        return dirname(__DIR__, 2) . '/templates/layouts/' . $layoutKey . '.nav.php';
    }

    private static function buildMenuItemsFromNav(Nav $nav, array $items, int $currentId, int $depth, int $maxDepth): array
    {
        if ($depth > $maxDepth) {
            return [];
        }

        $result = [];
        foreach ($items as $child) {
            $englishName = (string) ($child['english_name'] ?? '');
            if ($englishName === '404') {
                continue;
            }

            $itemId = (int) ($child['id'] ?? 0);
            $name = (string) ($child['name'] ?? $child['title'] ?? $englishName);
            if ($name === '') {
                continue;
            }

            $children = [];
            if ($depth < $maxDepth) {
                $children = $nav->as_array()->get_sub($itemId);
                $children = self::buildMenuItemsFromNav($nav, $children, $currentId, $depth + 1, $maxDepth);
            }

            $result[] = [
                'id' => $itemId,
                'name' => $name,
                'url' => (string) ($child['url'] ?? ''),
                'active' => !empty($child['active']),
                'children' => $children,
            ];
        }

        return $result;
    }

    private static function paginationUrl(string $baseUrl, array $params, int $page): string
    {
        $page = max(1, $page);
        $params['page'] = $page;

        $query = http_build_query($params);
        if ($query === '') {
            return $baseUrl;
        }

        return $baseUrl . '?' . $query;
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

    public static function renderCss(): void
    {
        if (self::sowAssetsAvailable()) {
            echo "    <link href=\"/assets/sow/css/vendor_bundle.min.css\" rel=\"stylesheet\">\n";
            echo "    <link href=\"/assets/sow/css/core.min.css\" rel=\"stylesheet\">\n";
            return;
        }

        echo "    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n";
    }

    public static function renderJs(): void
    {
        if (self::sowAssetsAvailable()) {
            echo "<script src=\"/assets/sow/js/vendor_bundle.min.js\"></script>\n";
            echo "<script src=\"/assets/sow/js/core.min.js\"></script>\n";
            return;
        }

        echo "<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\"></script>\n";
    }
}
