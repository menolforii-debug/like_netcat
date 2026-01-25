# Справка по функциям шаблонов

Этот документ перечисляет функции, которые можно использовать в макетах,
шаблонах компонентов и врезках (snippets).

## Макеты дизайна (`templates/layouts/*.php`)

### Класс `Layout`

- `Layout::renderCss()` — подключение CSS (локальные SOW‑ассеты или Bootstrap CDN).
- `Layout::renderJs()` — подключение JS (локальные SOW‑ассеты или Bootstrap CDN).
- `Layout::renderNavbar($brand, $links)` — быстрый вывод navbar.
- `Layout::renderDocumentStart($title, $meta)` / `Layout::renderDocumentEnd()` — готовые обёртки HTML.
- `Layout::renderSectionHeader($section, $children)` — вывод заголовка раздела и списка дочерних разделов.
- `Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options)` — вывод пагинации (если параметры `null`, ничего не выводит).
- `Layout::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, $options)` — массив элементов пагинации для кастомной разметки (при `null` вернёт пустой массив).
- `Layout::getMainMenuItems($ctx, $maxDepth)` — получить дерево меню по разделам.

### Глобальные функции

- `insert_snip($keyword)` — вставить врезку по ключу, выполняет PHP‑код внутри врезки.
- `nc_objects_list($filters)` — выборка объектов по фильтрам (см. `docs/objects-api.md`).

### Пагинация (пример)

```php
<?php
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalPages = 10;
$baseUrl = $section['path'] ?? '/';
$params = [];
Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params);
?>
```

### Пагинация (кастомная разметка)

```php
<?php
$items = Layout::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, [
    // 'show_single' => true,
]);
?>
<?php if (!empty($items)) : ?>
    <nav class="pagination-custom">
        <?php foreach ($items as $item): ?>
            <?php
            $classes = ['page-link'];
            if (!empty($item['active'])) {
                $classes[] = 'is-active';
            }
            if (!empty($item['disabled'])) {
                $classes[] = 'is-disabled';
            }
            ?>
            <a class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>"
               href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8') ?>"
               <?php if (!empty($item['aria'])): ?>
                   aria-label="<?= htmlspecialchars((string) $item['aria'], ENT_QUOTES, 'UTF-8') ?>"
               <?php endif; ?>>
                <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif ?>
```

### Доступные переменные

Состав `$ctx`:

- `title` — строка заголовка страницы (SEO).
- `meta` — массив метаданных (`description`, `keywords`).
- `site` — массив сайта из таблицы `sections` (корневой раздел).
- `section` — массив текущего раздела.
- `visual` — визуальные настройки, унаследованные по дереву разделов.
- `children` — массив дочерних разделов текущего раздела (каждый содержит `path` и `title`).

- `$ctx` — массив контекста (`title`, `meta`, `site`, `section`, `visual`, `children`).
- `$body` — callable для вывода контентной части страницы.

## Шаблоны компонентов (`templates/component/<keyword>/<view>.php`)

Доступны те же функции, что и в макетах, а также:

- `insert_snip($keyword)` — вставка врезок.
- `nc_objects_list($filters)` — выборка объектов.

### Доступные переменные

- `$section` — текущий раздел.
- `$site` — текущий сайт (корневой раздел).
- `$infoblock` — инфоблок с данными и настройками.
- `$component` — компонент.
- `$settings` — alias для `$infoblock['settings']`.
- `$items` — массив объектов инфоблока.
- `$objects` — alias для `$items`.
- `$isSingle` — флаг режима «одного объекта».
- `$object` — объект в режиме single (первый элемент `$objects`), либо `null`.
- `$core` — системный массив (в текущем коде пустой, но доступен для совместимости).
- `$message_select` — SQL‑запрос, которым получена выборка объектов (для отладки).

## Врезки (`snippets`)

Врезки выполняются как PHP‑код и наследуют контекст того шаблона, из которого они вызываются,
поэтому в них доступны все функции и переменные макета или компонента.
