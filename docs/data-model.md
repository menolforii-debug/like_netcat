# 20. Модель данных (SQLite)

## Зачем это существует

Документ описывает архитектурный слой БД: сущности, связи, индексы и JSON‑поля.

## Когда использовать

- Нужно понять, как связаны разделы, инфоблоки и объекты.
- Нужно писать диагностические SQL‑запросы.

## Когда НЕ использовать

- Когда нужна логика рендера — смотрите `docs/50-templates-layouts.md`.

## Как это работает (кратко)

Схема задаётся SQL‑миграциями (базовая — `migrations/001_init.sql`).
Данные читаются через репозитории (`app/domain/*Repo.php`).

## Карта сущностей и связей

```
sections (site/root)
  └─ infoblocks (экземпляры компонентов)
       └─ objects (контент)
components
  └─ component_views
snippets
users
visual_fields
admin_log
sql_history
```

## Таблицы

### sections

**Назначение:** дерево разделов и сайты (корневые разделы).

**Ключевые поля:** `id`, `parent_id`, `site_id`, `english_name`, `title`, `sort`.

**Внешние ключи и каскады:**
- `parent_id → sections.id` (CASCADE).

**JSON:** `extra_json` (SEO, визуальные настройки, макеты).

**Индексы:** `idx_sections_site_id`, `idx_sections_parent_id`.

**Пример (SQL):**
```sql
SELECT id, title FROM sections WHERE parent_id IS NULL;
```

**Типовой сценарий:**
```sql
-- Получить дочерние разделы
SELECT id, title FROM sections WHERE parent_id = 10 ORDER BY sort;
```

**Как НЕ надо:**
```sql
-- НЕ используйте english_name как уникальный ID глобально
SELECT * FROM sections WHERE english_name = 'news';
```

**Частые ошибки:**
- `parent_id` путают с `site_id`.

---

### components

**Назначение:** описание структуры данных.

**Ключевые поля:** `id`, `keyword`, `name`, `fields_json`, `views_json`.

**JSON:**
- `fields_json` — схема полей.
- `views_json` — список view‑шаблонов.

**Индексы:** уникальность по `keyword`.

**Пример:**
```sql
SELECT id, keyword, name FROM components;
```

**Типовой сценарий:**
```sql
SELECT fields_json FROM components WHERE keyword = 'news';
```

**Как НЕ надо:**
```sql
-- НЕ редактируйте JSON вручную без валидации структуры
UPDATE components SET fields_json = '{"bad":true}';
```

**Частые ошибки:**
- Меняют `keyword` — ломает путь к шаблонам.

---

### component_views

**Назначение:** пары list/single шаблонов и системный код.

**Ключевые поля:** `id`, `component_id`, `name`, `list_tpl`, `single_tpl`, `system_tpl`.

**Внешние ключи:** `component_id → components.id` (CASCADE).

**Индексы:** `idx_component_views_component_id`, уникальность `(component_id, name)`.

**Пример:**
```sql
SELECT name FROM component_views WHERE component_id = 3;
```

**Типовой сценарий:**
```sql
SELECT list_tpl FROM component_views WHERE component_id = 3 AND name = 'list';
```

**Как НЕ надо:**
```sql
-- НЕ удаляйте view без проверки зависимых инфоблоков
DELETE FROM component_views WHERE id = 1;
```

**Частые ошибки:**
- Создают view с тем же именем, что и файл в `templates/component/...` без синхронизации.

---

### infoblocks

**Назначение:** экземпляры компонента в разделе.

**Ключевые поля:** `id`, `section_id`, `component_id`, `key`, `name`, `view_template`, `per_page`, `sort`, `is_enabled`.

**Внешние ключи:**
- `section_id → sections.id` (CASCADE)
- `component_id → components.id` (CASCADE)

**JSON:** `extra_json` (обёртки before/after и прочее).

**Индексы:** `idx_infoblocks_site_id`, `idx_infoblocks_section_id`, `idx_infoblocks_component_id`, `idx_infoblocks_key`.

**Пример:**
```sql
SELECT id, name, key FROM infoblocks WHERE section_id = 10;
```

**Типовой сценарий:**
```sql
SELECT view_template, per_page FROM infoblocks WHERE id = 42;
```

**Как НЕ надо:**
```sql
-- НЕ делайте отрицательный per_page
UPDATE infoblocks SET per_page = -10 WHERE id = 42;
```

**Частые ошибки:**
- `key` путают с `name` — `key` нужен для кода, `name` для UI.

---

### objects

**Назначение:** контент инфоблоков.

**Ключевые поля:** `id`, `infoblock_id`, `data_json`, `status`, `is_deleted`.

**Внешние ключи:**
- `section_id → sections.id` (CASCADE)
- `infoblock_id → infoblocks.id` (CASCADE)
- `component_id → components.id` (CASCADE)

**JSON:** `data_json` — поля объекта.

**Индексы:** `idx_objects_site_id`, `idx_objects_section_id`, `idx_objects_infoblock_id`, `idx_objects_component_id`, `idx_objects_status`.

**Пример:**
```sql
SELECT id, status FROM objects WHERE infoblock_id = 5;
```

**Типовой сценарий:**
```sql
SELECT id, data_json FROM objects WHERE infoblock_id = 5 AND status = 'published';
```

**Как НЕ надо:**
```sql
-- НЕ удаляйте объект, если используете soft-delete
DELETE FROM objects WHERE id = 10;
```

**Частые ошибки:**
- Путают `status` и `is_deleted`.

---

### users

**Назначение:** пользователи админки.

**Ключевые поля:** `id`, `login`, `pass_hash`, `role`.

**Пример:**
```sql
SELECT id, login, role FROM users;
```

**Типовой сценарий:**
```sql
SELECT COUNT(*) FROM users WHERE role = 'admin';
```

**Как НЕ надо:**
```sql
-- НЕ храните пароль в открытом виде
UPDATE users SET pass_hash = '12345';
```

**Частые ошибки:**
- Создают пользователя вручную без хэша.

---

### snippets

**Назначение:** врезки HTML/JS/PHP, вставляемые через `insert_snip()`.

**Ключевые поля:** `id`, `keyword`, `content`.

**Пример:**
```sql
SELECT keyword FROM snippets;
```

**Типовой сценарий:**
```sql
SELECT content FROM snippets WHERE keyword = 'footer';
```

**Как НЕ надо:**
```sql
-- НЕ вставляйте пользовательский ввод без проверки
UPDATE snippets SET content = '<script>alert(1)</script>';
```

**Частые ошибки:**
- Используют `keyword` с пробелами.

---

### visual_fields

**Назначение:** справочник визуальных настроек.

**Ключевые поля:** `id`, `name`, `label`, `type`, `options_json`.

**JSON:** `options_json` — набор опций.

**Пример:**
```sql
SELECT name, type FROM visual_fields ORDER BY sort;
```

**Типовой сценарий:**
```sql
SELECT options_json FROM visual_fields WHERE name = 'header_color';
```

**Как НЕ надо:**
```sql
-- НЕ указывайте тип, которого нет в UI
UPDATE visual_fields SET type = 'unknown';
```

**Частые ошибки:**
- Забывают обновить `sort` — поля “скачут” в UI.

---

### admin_log и sql_history

**Назначение:** аудит действий и история SQL‑консоли.

**Пример:**
```sql
SELECT action, created_at FROM admin_log ORDER BY id DESC LIMIT 10;
```

**Типовой сценарий:**
```sql
SELECT sql FROM sql_history ORDER BY id DESC LIMIT 5;
```

**Как НЕ надо:**
```sql
-- НЕ чистите логи без необходимости
DELETE FROM admin_log;
```

**Частые ошибки:**
- Путают логи админки и историю SQL.

## Типовые диагностические SQL‑запросы

```sql
-- Объекты инфоблока
SELECT id, data_json FROM objects WHERE infoblock_id = 10;

-- Только опубликованные
SELECT id, data_json FROM objects
WHERE infoblock_id = 10 AND status = 'published' AND is_deleted = 0;

-- Найти soft-deleted
SELECT id, deleted_at FROM objects WHERE is_deleted = 1;

-- Понять, какой шаблон используется
SELECT view_template FROM infoblocks WHERE id = 10;
```
