# 20. Модель данных (SQLite)

## Зачем это существует

Документ описывает архитектурный слой БД: сущности, связи, индексы и JSON‑поля.

## Как это работает (кратко)

Схема задаётся файлом `app/shared/schema.sql`.
Данные читаются через репозитории (`app/shared/domain/*Repo.php`).

## Карта сущностей и связей

```
sections (site/root)
  └─ infoblocks (экземпляры компонентов)
       └─ objects (контент)
components
users
visual_fields
admin_log
sql_history
snippet
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

---

### snippet

**Назначение:** метаданные врезок (название для `templates/snippets/*.php`).

**Ключевые поля:** `keyword`, `name`.

**Пример:**
```sql
SELECT keyword, name FROM snippet ORDER BY keyword;
```

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
