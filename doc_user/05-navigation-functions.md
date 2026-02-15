# Функции навигации

Навигация в проекте строится через `core()->nav()`.

## Что такое `core()->nav()`
`core()->nav()` возвращает объект навигации, который знает:
- текущий сайт;
- текущий раздел;
- дерево разделов сайта;
- признаки активности пунктов меню.

Объект поддерживает режимы выдачи:
- `as_array()` — массивы;
- `as_object()` — объекты (по умолчанию).

## Базовые методы
- `get_by_level($level)` — элементы меню на конкретном уровне.
- `get_sub($parent_id)` — дочерние разделы для указанного родителя.
- `get_path($offset = 0, $length = null)` — цепочка текущего пути (breadcrumbs).
- `where(...)`, `or_where(...)` — фильтрация.
- `where_in(...)`, `or_where_in(...)` — фильтр по набору значений.
- `order_by($field, $dir)` — сортировка.

## Пример 1: меню верхнего уровня
```php
<?php
$menuItems = core()->nav()
    ->as_array()
    ->get_by_level(0);

foreach ($menuItems as $item) {
    echo '<a href="' . htmlspecialchars((string) ($item['url'] ?? '/'), ENT_QUOTES, 'UTF-8') . '">';
    echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    echo '</a>';
}
```

## Пример 2: дочерние пункты текущего раздела
```php
<?php
$children = core()->nav()
    ->as_array()
    ->get_sub(); // без аргумента — для текущего раздела

if ($children !== []) {
    echo '<ul>';
    foreach ($children as $child) {
        echo '<li><a href="' . htmlspecialchars((string) ($child['url'] ?? '/'), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars((string) ($child['name'] ?? ''), ENT_QUOTES, 'UTF-8')
            . '</a></li>';
    }
    echo '</ul>';
}
```

## Пример 3: breadcrumbs (путь)
```php
<?php
$path = core()->nav()->as_array()->get_path();

echo '<nav aria-label="breadcrumb"><ol>';
foreach ($path as $node) {
    echo '<li><a href="' . htmlspecialchars((string) ($node['url'] ?? '/'), ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars((string) ($node['name'] ?? ''), ENT_QUOTES, 'UTF-8')
        . '</a></li>';
}
echo '</ol></nav>';
```

## Пример 4: фильтрация и сортировка
```php
<?php
$items = core()->nav()
    ->as_array()
    ->where('parent_id', '=', 10)
    ->where_in('english_name', ['catalog', 'about', 'contacts'])
    ->order_by('title', 'asc')
    ->get();
```

## Альтернатива в макетах
Если не нужен гибкий конструктор, можно использовать готовый helper:
- `Layout::getMainMenuItems($ctx, $maxDepth)`

Он внутри тоже использует `core()->nav()`, но сразу возвращает структуру для рендера многоуровневого меню.
