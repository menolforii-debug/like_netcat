<?php

final class Pagination
{
    public static function render(
        ?int $currentPage,
        ?int $totalPages,
        ?string $baseUrl,
        array $params = [],
        array $options = []
    ): void {
        if ($currentPage === null || $totalPages === null || $baseUrl === null) {
            return;
        }

        $items = self::items($currentPage, $totalPages, $baseUrl, $params, $options);

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
    public static function items(
        ?int $currentPage,
        ?int $totalPages,
        ?string $baseUrl,
        array $params = [],
        array $options = []
    ): array {
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
}
