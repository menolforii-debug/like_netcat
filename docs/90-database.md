# База данных

О чём документ: структура `SQLite`‑схемы и назначение таблиц.
Когда читать: при работе с данными или отладке запросов.
Кому полезно: разработчикам, аналитикам, инженерам эксплуатации.
Связанные документы: `docs/20-bootstrap-and-install.md`, `docs/50-system-php-contract.md`.

## Где лежит схема
Схема — `app/shared/schema.sql`. Она применяется при первом запуске или если нет таблицы `sections`.

## Таблицы и назначение
### `sections`
Дерево разделов и сайтов.
- `parent_id = NULL` и `site_id = id` — это запись сайта.
- `english_name` участвует в построении `URL`.
- `extra_json` хранит настройки сайта/раздела (`layout`, `SEO`, визуальные поля).

### `components`
Описание компонентов контента.
- `fields_json` — набор полей (структура данных объектов).
- `views_json` — список доступных представлений (`view`).

### `infoblocks`
Связка раздела и компонента.
- `view_template` — выбранное представление (`view`).
- `per_page` — пагинация.
- `extra_json` — настройки и права (`permissions`).

### `objects`
Контент инфоблоков.
- `data_json` — данные объекта.
- `status` (`draft`/`published`).
- `is_deleted`/`deleted_at` — мягкое удаление.

### `users`
Пользователи админки (`login`, `pass_hash`, `role`).

### `admin_log`
Аудит действий в админке.
- `data_json` — доп. данные действия.

### `visual_fields`
Список визуальных полей (используются в настройках сайта/разделов).
- `options_json` — значения списка.

### `sql_history`
История выполненных SQL (используется админкой в инструменте SQL).

### `snippet`
Справочник врезок (имя + отображаемое название).

## `JSON`‑поля
- `sections.extra_json` — `site_domain`, `site_mirrors`, `site_enabled`, `site_offline_html`, `layout`, `seo_*`, `visual_settings`.
- `components.fields_json` — описание полей компонента.
- `components.views_json` — список view.
- `infoblocks.extra_json` — `before_html`, `after_html`, `permissions`.
- `objects.data_json` — данные объекта.
- `admin_log.data_json` — детали действий.
- `visual_fields.options_json` — список опций.

## Примеры `SQL` для отладки
Список сайтов:

```sql
SELECT id, title FROM sections WHERE parent_id IS NULL AND id = site_id;
```

Разделы конкретного сайта:

```sql
SELECT id, parent_id, english_name, title FROM sections WHERE site_id = 1 ORDER BY sort, id;
```

Инфоблоки раздела:

```sql
SELECT id, name, component_id, view_template FROM infoblocks WHERE section_id = 10 ORDER BY sort;
```

Последние объекты инфоблока:

```sql
SELECT id, status, created_at FROM objects WHERE infoblock_id = 5 ORDER BY id DESC LIMIT 10;
```

## Миграции
В репозитории есть `SQL`‑файлы в `migrations/`, но в коде нет мигратора.
Все изменения схемы применяются вручную.
