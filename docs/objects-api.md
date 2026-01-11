# API выборки объектов (`nc_objects_list`)

Функция предназначена для выборки объектов из таблицы `objects` с фильтрами,
аналогичными старому `nc_objects_list`, и доступна в любом месте, где загружен
`app/bootstrap.php` (в том числе в шаблонах компонентов).

## Сигнатура

```php
/**
 * @param array $filters
 * @return array
 */
$objects = nc_objects_list($filters);
```

## Входные параметры

Обязателен хотя бы один идентификатор:

- `infoblock_id` (int) — выборка по инфоблоку.
- `component_id` (int) — выборка по компоненту.

Дополнительные фильтры:

- `status` (string|null) — статус объекта (`published` или `draft`).
- `is_deleted` (bool|int|null) — удалённость.
  - `null`/не задано → выбираем только `is_deleted = 0`.
  - `0` → выбираем только `is_deleted = 0`.
  - `1` → выбираем только `is_deleted = 1`.
- `section_id` (int|null) — ограничение по разделу.
- `site_id` (int|null) — ограничение по сайту.

Управление выборкой:

- `order` или `sort` (string) — выражение `ORDER BY` без ключевого слова.
- `limit` (int) — `LIMIT`.
- `offset` (int) — `OFFSET` (если задан без `limit`, используется `LIMIT -1`).
- `where` (array) — дополнительные SQL‑условия (строки), добавляются в `WHERE`.
- `params` (array) — параметры для плейсхолдеров в `where` или `sql`.

Дополнительные режимы:

- `ignore_sub` (bool|int) — использовать `component_id` как базовый фильтр,
  игнорируя `infoblock_id` (по сути «развязать» выборку от инфоблока).
- `sql` (string) — полный SQL‑текст. Если задан, остальные фильтры не применяются.

## Выход

Возвращается массив строк объектов из таблицы `objects` (включая поля
`id`, `site_id`, `section_id`, `infoblock_id`, `component_id`, `data_json`,
`status`, `published_at`, `created_at`, `updated_at`, `is_deleted`, `deleted_at`).

## Примеры

### Опубликованные объекты инфоблока

```php
$items = nc_objects_list([
    'infoblock_id' => 12,
    'status' => 'published',
    'order' => 'id DESC',
    'limit' => 10,
]);
```

### Фильтр по разделу и компоненту

```php
$items = nc_objects_list([
    'infoblock_id' => 12,
    'component_id' => 3,
    'section_id' => 7,
    'status' => 'draft',
    'order' => 'created_at DESC',
]);
```

### Выборка удалённых объектов компонента (ignore_sub)

```php
$items = nc_objects_list([
    'component_id' => 3,
    'ignore_sub' => 1,
    'is_deleted' => 1,
    'order' => 'deleted_at DESC',
]);
```

### Полный SQL (replace‑режим)

```php
$items = nc_objects_list([
    'sql' => 'SELECT * FROM objects WHERE status = :status ORDER BY id DESC',
    'params' => ['status' => 'published'],
]);
```
