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
        int $currentPage,
        int $totalPages,
        string $baseUrl,
        array $params = [],
        array $options = []
    ): void
    {
        $items = self::buildPagination($currentPage, $totalPages, $baseUrl, $params, $options);
        if (empty($items)) {
            return;
        }

        $navClass = isset($options['nav_class']) ? (string) $options['nav_class'] : '';
        $ulClass = isset($options['ul_class']) ? (string) $options['ul_class'] : 'pagination';
        $itemClass = isset($options['item_class']) ? (string) $options['item_class'] : 'page-item';
        $linkClass = isset($options['link_class']) ? (string) $options['link_class'] : 'page-link';
        $activeClass = isset($options['active_class']) ? (string) $options['active_class'] : 'active';
        $disabledClass = isset($options['disabled_class']) ? (string) $options['disabled_class'] : 'disabled';

        $navTemplate = isset($options['nav_template'])
            ? (string) $options['nav_template']
            : '<nav{nav_class} aria-label="Навигация по страницам">{list}</nav>';
        $listTemplate = isset($options['list_template'])
            ? (string) $options['list_template']
            : '<ul class="{ul_class}">{items}</ul>';
        $itemTemplate = isset($options['item_template'])
            ? (string) $options['item_template']
            : '<li class="{class}"><a class="{link_class}" href="{url}"{aria}>{label}</a></li>';
        $ellipsisTemplate = isset($options['ellipsis_template'])
            ? (string) $options['ellipsis_template']
            : '<li class="{class}"><span class="{link_class}">{label}</span></li>';

        $itemsHtml = '';
        foreach ($items as $item) {
            $isEllipsis = ($item['type'] ?? '') === 'ellipsis';
            $isActive = !$isEllipsis && !empty($item['active']);
            $isDisabled = !empty($item['disabled']) || $isEllipsis;
            $classes = [$itemClass];
            if ($isActive) {
                $classes[] = $activeClass;
            }
            if ($isDisabled) {
                $classes[] = $disabledClass;
            }
            $classAttr = htmlspecialchars(trim(implode(' ', array_filter($classes))), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $aria = isset($item['aria']) ? ' aria-label="' . htmlspecialchars((string) $item['aria'], ENT_QUOTES, 'UTF-8') . '"' : '';
            $url = htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8');

            if ($isEllipsis) {
                $itemsHtml .= strtr($ellipsisTemplate, [
                    '{class}' => $classAttr,
                    '{link_class}' => htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'),
                    '{label}' => $label,
                ]);
                continue;
            }

            $itemsHtml .= strtr($itemTemplate, [
                '{class}' => $classAttr,
                '{link_class}' => htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'),
                '{url}' => $url,
                '{label}' => $label,
                '{aria}' => $aria,
            ]);
        }

        $listHtml = strtr($listTemplate, [
            '{ul_class}' => htmlspecialchars($ulClass, ENT_QUOTES, 'UTF-8'),
            '{items}' => $itemsHtml,
        ]);
        $navClassAttr = $navClass !== '' ? ' class="' . htmlspecialchars($navClass, ENT_QUOTES, 'UTF-8') . '"' : '';
        $navHtml = strtr($navTemplate, [
            '{nav_class}' => $navClassAttr,
            '{list}' => $listHtml,
        ]);

        echo $navHtml;
    }

    public static function buildPagination(
        int $currentPage,
        int $totalPages,
        string $baseUrl,
        array $params = [],
        array $options = []
    ): array {
        if ($totalPages <= 1) {
            return [];
        }

        $currentPage = max(1, min($currentPage, $totalPages));
        $params = array_filter($params, static function ($value): bool {
            return $value !== null && $value !== '';
        });

        $pageParam = isset($options['page_param']) ? (string) $options['page_param'] : 'page';
        $window = isset($options['window']) ? (int) $options['window'] : 0;
        $edges = isset($options['edges']) ? max(0, (int) $options['edges']) : 1;
        $showPrevNext = !isset($options['show_prev_next']) || (bool) $options['show_prev_next'];
        $prevLabel = isset($options['prev_label']) ? (string) $options['prev_label'] : '«';
        $nextLabel = isset($options['next_label']) ? (string) $options['next_label'] : '»';
        $ellipsisLabel = isset($options['ellipsis_label']) ? (string) $options['ellipsis_label'] : '…';

        $items = [];
        if ($showPrevNext) {
            $items[] = [
                'type' => 'prev',
                'page' => max(1, $currentPage - 1),
                'url' => self::paginationUrl($baseUrl, $params, $currentPage - 1, $pageParam),
                'label' => $prevLabel,
                'aria' => 'Предыдущая',
                'disabled' => $currentPage <= 1,
            ];
        }

        $pagesToShow = [];
        if ($window <= 0 || $totalPages <= ($window * 2 + 1)) {
            $pagesToShow = range(1, $totalPages);
        } else {
            $start = max(1, $currentPage - $window);
            $end = min($totalPages, $currentPage + $window);
            $pagesToShow = array_merge(
                range(1, min($edges, $totalPages)),
                range($start, $end),
                range(max($totalPages - $edges + 1, 1), $totalPages)
            );
            $pagesToShow = array_values(array_unique($pagesToShow));
            sort($pagesToShow);
        }

        $lastPage = null;
        foreach ($pagesToShow as $page) {
            if ($lastPage !== null && $page > $lastPage + 1) {
                $items[] = [
                    'type' => 'ellipsis',
                    'label' => $ellipsisLabel,
                ];
            }

            $items[] = [
                'type' => 'page',
                'page' => $page,
                'url' => self::paginationUrl($baseUrl, $params, $page, $pageParam),
                'label' => (string) $page,
                'active' => $page === $currentPage,
                'disabled' => false,
            ];
            $lastPage = $page;
        }

        if ($showPrevNext) {
            $items[] = [
                'type' => 'next',
                'page' => min($totalPages, $currentPage + 1),
                'url' => self::paginationUrl($baseUrl, $params, $currentPage + 1, $pageParam),
                'label' => $nextLabel,
                'aria' => 'Следующая',
                'disabled' => $currentPage >= $totalPages,
            ];
        }

        return $items;
    }

    public static function getMainMenuItems(array $ctx, int $maxDepth = 2): array
    {
        $site = $ctx['site'] ?? [];
        $section = $ctx['section'] ?? [];
        $siteId = (int) ($site['id'] ?? 0);
        if ($siteId <= 0) {
            return [];
        }

        $currentId = (int) ($section['id'] ?? 0);
        $repo = new SectionRepo();
        return self::buildMenuItems($repo, $siteId, $siteId, $currentId, 1, $maxDepth);
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

        $navPath = self::layoutNavPath($layoutKey);
        if (is_file($navPath)) {
            require $navPath;
        }

        require $layoutPath;
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

    private static function buildMenuItems(SectionRepo $repo, int $siteId, int $parentId, int $currentId, int $depth, int $maxDepth): array
    {
        if ($depth > $maxDepth) {
            return [];
        }

        $children = $repo->listChildren($parentId);
        $items = [];
        foreach ($children as $child) {
            $englishName = (string) ($child['english_name'] ?? '');
            if ($englishName === '404') {
                continue;
            }

            $itemId = (int) ($child['id'] ?? 0);
            $name = (string) ($child['title'] ?? $englishName);
            if ($name === '') {
                continue;
            }

            $items[] = [
                'id' => $itemId,
                'name' => $name,
                'url' => $repo->buildPath($itemId),
                'active' => $itemId === $currentId || ($currentId > 0 && $repo->isDescendant($currentId, $itemId)),
                'children' => $depth < $maxDepth ? self::buildMenuItems($repo, $siteId, $itemId, $currentId, $depth + 1, $maxDepth) : [],
            ];
        }

        return $items;
    }

    private static function paginationUrl(string $baseUrl, array $params, int $page, string $pageParam = 'page'): string
    {
        $page = max(1, $page);
        $params[$pageParam] = $page;

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
