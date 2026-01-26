# 60. Доступные классы и функции

## Зачем это существует

Единый справочник API, доступного в макетах, шаблонах компонентов и врезках.

## Когда использовать

- Нужна сигнатура и пример вызова.
- Нужно понять, где доступна функция или класс.

## Когда НЕ использовать

- Для логики админки — это не фронтенд‑API.

## Как это работает (кратко)

Все функции и классы загружаются через `app/bootstrap.php` и доступны в шаблонах.

## Примеры

### Типовой сценарий

```php
$items = objects_list(['infoblock_id' => 10, 'status' => 'published']);
```

### Как делать НЕ надо

```php
// НЕ вызывайте insert_snip() с пользовательским ключом без проверки
insert_snip($_GET['snip']);
```

## Частые ошибки

- Путают контекст: макеты используют Layout, компоненты — $objects/$object.

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
- **Типичная ошибка:** вызывать события без проверки данных

### `users_count(): int`

- **Где использовать:** layouts / components / snippets (обычно admin)
- **Что делает:** возвращает количество пользователей
- **Возвращает:** `int`
- **Пример:**
  ```php
  if (users_count() === 0) { /* ... */ }
  ```
- **Типичная ошибка:** использовать на фронтенде без необходимости

### `objects_list(array $filters): array`

- **Где использовать:** layouts / components / snippets
- **Что делает:** возвращает список объектов по фильтрам
- **Возвращает:** массив объектов
- **Пример:**
  ```php
  $items = objects_list(['infoblock_id' => 5, 'status' => 'published']);
  ```
- **Типичная ошибка:** вызывать без фильтров (слишком большая выборка)

### `insert_snip(string $keyword, array $vars = []): string`

- **Где использовать:** layouts / components / snippets
- **Что делает:** вставляет врезку по ключу и выполняет её код
- **Возвращает:** строку HTML (и сразу печатает)
- **Пример:**
  ```php
  insert_snip('footer');
  ```
- **Типичная ошибка:** передавать пользовательский ввод как ключ

### `resize_image(string $sourcePath, int $maxWidth, int $maxHeight, ?string $destPath = null, int $quality = 85): string`

- **Где использовать:** layouts / components / snippets
- **Что делает:** уменьшает изображение и возвращает путь результата
- **Возвращает:** путь к файлу
- **Пример:**
  ```php
  $thumb = resize_image($path, 800, 600);
  ```
- **Типичная ошибка:** вызывать для несуществующего файла

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

**Типичная ошибка:** вызывать методы Layout в snippets без нужного контекста.

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

**Типичная ошибка:** выполнять запросы без параметров (SQL‑инъекции).

### `Core`

**Где использовать:** layouts / components / snippets

- `Core::db(): PDO`
- `Core::events(): EventBus`

**Пример:**
```php
$core = core();
$pdo = $core->db();
```

**Типичная ошибка:** обращаться к `events()` без подписчиков.

### `Utils`

**Где использовать:** layouts / components / snippets

- `Utils::normalizeHost(string $host): string`
- `Utils::isUrlSafe(string $value): bool`
- `Utils::decodeExtra(array $row): array`

**Пример:**
```php
if (!Utils::isUrlSafe($key)) { /* ... */ }
```

**Типичная ошибка:** использовать `decodeExtra()` для `data_json` объектов.
