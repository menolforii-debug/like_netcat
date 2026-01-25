# Функции / API

Этот документ описывает функции и хелперы, доступные в макетах, шаблонах компонентов и врезках.

## Глобальные функции

### `insert_snip(string $keyword, array $vars = [])`

Вставляет врезку по ключу и выполняет её PHP‑код.

- Если `$vars` не передан, врезка наследует переменные текущего шаблона.
- Функция **печатает** результат и **возвращает** его строкой.
- Содержимое не экранируется, поэтому отвечает за безопасность разработчик.

### `nc_objects_list(array $filters)`

Возвращает массив объектов по фильтрам (аналог старого `nc_objects_list`).
Полное описание и примеры — в `docs/objects-api.md`.

## Методы Layout (макеты дизайна)

Доступны в файлах `templates/layouts/*.php`:

- `Layout::renderCss()`
- `Layout::renderJs()`
- `Layout::renderNavbar($brand, $links)`
- `Layout::renderDocumentStart($title, $meta)` / `Layout::renderDocumentEnd()`
- `Layout::renderSectionHeader($section, $children)`
- `Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options)`
- `Layout::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, $options)`
- `Layout::getMainMenuItems($ctx, $maxDepth)`

Подробные примеры использования — в `docs/templates.md`.
