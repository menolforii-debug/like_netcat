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
- `Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options)` — вывод пагинации.
- `Layout::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, $options)` — массив элементов пагинации для кастомной разметки.
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

### Доступные переменные

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
