# 60. Доступные классы и функции

## Зачем это существует

Единый справочник API, доступного в макетах, шаблонах компонентов и врезках.

## Как это работает (кратко)

Все функции и классы загружаются через `app/bootstrap.php` и доступны в шаблонах.

## Примеры

### Типовой сценарий

```php
$itemsHtml = objects_list(['infoblock_id' => 10, 'query_order' => 'a.id DESC']);
```

---

## A) Глобальные функции

### `core(): Core`

- **Где использовать:** layouts / components / snippets
- **Что делает:** возвращает Core (доступ к PDO и EventBus)
- **Возвращает:** `Core`
- **Пример:**
  ```php
  $pdo = core()->db();
  ```

### `users_count(): int`

- **Где использовать:** layouts / components / snippets (обычно admin)
- **Что делает:** возвращает количество пользователей
- **Возвращает:** `int`
- **Пример:**
  ```php
  if (users_count() === 0) { /* ... */ }
  ```

### `objects_list(array $filters): array`

- **Где использовать:** layouts / components / snippets
- **Что делает:** возвращает массив HTML‑строк (item‑template для каждого объекта)
- **Возвращает:** массив строк
- **Пример:**
  ```php
  $itemsHtml = objects_list([
    'infoblock_id' => 5,
    'query_order' => 'a.published_at DESC, a.id DESC',
  ]);
  echo implode('', $itemsHtml);
  ```

### `insert_snip(string $keyword, array $vars = []): string`

- **Где использовать:** layouts / components / snippets
- **Что делает:** вставляет врезку по ключу и выполняет её код
- **Возвращает:** строку HTML (и сразу печатает)
- **Пример:**
  ```php
  insert_snip('footer');
  ```

### `resize_image(string $sourcePath, int $maxWidth, int $maxHeight, ?string $destPath = null, int $quality = 85): string`

- **Где использовать:** layouts / components / snippets
- **Что делает:** уменьшает изображение и возвращает путь результата
- **Возвращает:** путь к файлу
- **Пример:**
  ```php
  $thumb = resize_image($path, 800, 600);
  ```

---

## B) Классы и методы

### `Layout` (макеты)

**Где использовать:** layouts

- `Layout::renderCss()` — вывод CSS.
- `Layout::renderJs()` — вывод JS.
- `Layout::renderNavbar($brand, $links)` — шапка навигации.
- `Layout::renderDocumentStart($title, $meta)` / `Layout::renderDocumentEnd()` — HTML‑каркас.
- `Layout::renderSectionHeader($section, $children)` — заголовок и список дочерних разделов.
- `Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options)` — пагинация.
- `Layout::getPaginationItems(...)` — список элементов пагинации.
- `Layout::getMainMenuItems($ctx, $maxDepth)` — данные меню.

**Пример:**
```php
<?= Layout::renderSectionHeader($ctx['section'], $ctx['children']) ?>
```


### `DB`

**Где использовать:** layouts / components / snippets (лучше через репозитории)

- `DB::pdo(): PDO`
- `DB::fetchOne(string $sql, array $params = []): ?array`
- `DB::fetchAll(string $sql, array $params = []): array`
- `DB::hasTable(string $table): bool`
- `DB::hasColumn(string $table, string $column): bool`

**Пример:**
```php
$row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM objects');
```


### `Core`

**Где использовать:** layouts / components / snippets

- `Core::db(): PDO`
- `Core::events(): EventBus`
- `Core::nav(): Nav`

**Пример:**
```php
$core = core();
$pdo = $core->db();
```

### Навигация (core()->nav())

**Где использовать:** layouts / components / snippets

- `core()->nav()->get_by_level($level)` — разделы указанного уровня.
- `core()->nav()->get_sub($parent_id = 0)` — подразделы (по умолчанию текущего раздела).
- `core()->nav()->get_path($offset = 0, $length = null)` — хлебные крошки.
- `core()->nav()->where(...)` / `or_where(...)` / `where_in(...)` / `or_where_in(...)` — фильтры.
- `core()->nav()->order_by(...)` — сортировка.
- `core()->nav()->as_object()` / `as_array()` — формат результата.

**Примеры:**

Главное меню (первый уровень):
```php
foreach (core()->nav()->get_by_level(0) as $sd) {
  $cls = $sd->active ? 'active' : '';
  echo "<a class='{$cls}' href='{$sd->url}'>" . htmlspecialchars($sd->name) . "</a>";
}
```

Подменю текущего раздела:
```php
foreach (core()->nav()->get_sub() as $sd) {
  echo "<a href='{$sd->url}'>" . htmlspecialchars($sd->name) . "</a>";
}
```

Хлебные крошки:
```php
foreach (core()->nav()->get_path(0) as $sd) {
  echo "<a href='{$sd->url}'>" . htmlspecialchars($sd->name) . "</a>";
}
```

Фильтр + сортировка:
```php
core()->nav()
  ->where('english_name', '!=', 'index')
  ->order_by('title', 'asc')
  ->get_by_level(0);
```


### `Utils`

**Где использовать:** layouts / components / snippets

- `Utils::normalizeHost(string $host): string`
- `Utils::isUrlSafe(string $value): bool`
- `Utils::decodeExtra(array $row): array`

**Пример:**
```php
if (!Utils::isUrlSafe($key)) { /* ... */ }
```
