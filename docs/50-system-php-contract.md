# Контракт `system.php`

О чём документ: какие ключи реально поддерживает `system.php` и как они влияют на запрос.
Когда читать: при настройке выборки объектов и хелперов для шаблонов.
Кому полезно: разработчикам компонентов и тем, кто оптимизирует вывод.
Связанные документы: `docs/40-templates-and-components.md`, `docs/90-database.md`.

## Базовые правила
- `system.php` лежит в `templates/component/<keyword>/<view>/system.php`.
- Внутри доступны переменные: `$section`, `$site`, `$infoblock`, `$component`, `$isSingle`.
- Файл должен **возвращать массив**. Если ничего не вернуть — это считается пустыми настройками.
- Любой другой тип результата приводится к пустому массиву (с записью в лог).

## Поддерживаемые ключи (реально используемые)
`Renderer` нормализует только следующие ключи:

### Фильтры
- `ignore_sub` — не фильтровать по `infoblock_id`.
- `ignore_cc` — если `ignore_sub = 1`, также не фильтровать по `component_id`.
- `ignore_check` — отключить фильтр `status`.
- `ignore_all` — полностью отключить выборку (вернётся пустой список).
- `ignore_limit` — игнорировать пагинацию (`per_page/offset`).

### `SQL`‑фрагменты
Все части вставляются в `SQL` **как есть**:
- `query_select`
- `query_from`
- `query_join`
- `query_where`
- `query_group`
- `query_having`
- `query_order`
- `query_limit`

### DISTINCT
- `distinct` — если непустая строка, используется как есть (например `DISTINCT`).

### Хелперы
- `helpers` — произвольный массив, доступный в шаблоне как `$helpers`.

## Как устроен `SQL`‑шаблон
`ObjectRepo` строит запрос вида:

```
SELECT [DISTINCT] a.id, a.site_id, a.section_id, a.infoblock_id, a.component_id,
       a.data_json, a.created_at, a.updated_at, a.is_deleted, a.deleted_at,
       a.status, a.published_at
       [query_select]
FROM objects AS a
    [query_from]
    [query_join]
WHERE (системные условия)
  AND (query_where)
GROUP BY query_group
HAVING query_having
ORDER BY query_order
LIMIT query_limit
```

Имена полей в `query_*` используйте с алиасом `a`.

## Примеры (копипасто‑готовые)

### 1. Сортировка по дате публикации
```php
<?php
return [
    'query_order' => 'a.published_at DESC, a.id DESC',
];
```

### 2. Отбор по статусу без системной проверки
```php
<?php
return [
    'ignore_check' => true,
    'query_where' => "a.status IN ('draft','published')",
];
```

### 3. Отключить все объекты
```php
<?php
return [
    'ignore_all' => true,
];
```

### 4. Жёсткий лимит вне пагинации
```php
<?php
return [
    'ignore_limit' => true,
    'query_limit' => '5',
];
```

### 5. Разная логика для single‑режима
```php
<?php
if ($isSingle) {
    return [
        'query_order' => 'a.id DESC',
    ];
}
return [
    'query_order' => 'a.created_at DESC',
];
```

### 6. Использование `ignore_sub` для выборки по компоненту
```php
<?php
return [
    'ignore_sub' => true,
    // при ignore_sub компонент будет фильтроваться по component_id
];
```

### 7. Выборка без фильтра компонента и инфоблока
```php
<?php
return [
    'ignore_sub' => true,
    'ignore_cc' => true,
];
```

### 8. DISTINCT
```php
<?php
return [
    'distinct' => 'DISTINCT',
];
```

### 9. Helpers для шаблона
```php
<?php
return [
    'helpers' => [
        'formatDate' => static function (string $iso): string {
            $dt = new DateTimeImmutable($iso);
            return $dt->format('d.m.Y');
        },
    ],
];
```

### 10. Простой фильтр по JSON‑полю (строковый поиск)
```php
<?php
return [
    'query_where' => 'a.data_json LIKE "%\"category\":\"news\"%"',
];
```

> Важно: `query_*` не экранируются. Это прямые SQL‑фрагменты, используйте их аккуратно.
