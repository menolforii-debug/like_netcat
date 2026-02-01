# Руководство по админке

О чём документ: устройство админки, модель действий и правила безопасности.
Когда читать: перед добавлением новых действий админки и интеграций.
Кому полезно: разработчикам, расширяющим админку.
Связанные документы: `docs/95-security.md`, `docs/90-database.md`.

## Архитектура действий админки
Админка использует файловую модель:

```
app/admin/actions/get/<action>.php
app/admin/actions/post/<action>.php
```

`AdminRouter::run()`:
1. Определяет `action` (по умолчанию `dashboard`).
2. Проверяет метод (`GET`/`POST`) и доступ по ролям.
3. Валидирует имя `action` по регулярному выражению `A-Za-z0-9_`.
4. Проверяет `CSRF` для `POST` (кроме `login`).
5. Находит файл `action` через `realpath` и выполняет его.

## Контекст, доступный в `action`‑файле
Перед выполнением `AdminRouter` делает `extract()` со следующими переменными:

- `$action`, `$isPost`
- `$user` (текущий пользователь)
- `$selectedId`, `$tab`
- репозитории: `$sectionRepo`, `$infoblockRepo`, `$componentRepo`, `$objectRepo`, `$userRepo`, `$visualFieldRepo`

## Форматы ответа
Действие может:
- сделать `redirectTo()`;
- вывести строку (`echo` или `return` строку);
- вернуть массив для `AJAX`‑запроса (при `X-Requested-With: XMLHttpRequest`).

Полезные хелперы (в `AdminHelpers.php`):
- `jsonResponse($payload, $status)`
- `redirectTo($url)`
- `csrf_token_field()`
- `adminFlashSet($type, $message)` и `adminFlashConsume()` — краткоживущие сообщения об успехе/ошибке между редиректами.

## `CSRF`
Для `POST`‑действий (кроме `login`) проверяется `csrf_token`:
- вставляйте `<?php echo csrf_token_field(); ?>` во все формы;
- валидатор — `is_valid_csrf_token()`.

## Роли и доступы
- Пользовательская роль хранится в `users.role`.
- `Auth::roles()` поддерживает `admin` и `editor`.
- `AdminRouter::actionPolicy()` задаёт базовые права для `action`‑файлов.

Права на уровне контента:
- `Permission::canAction($user, $infoblock, $action)` читает `infoblock.extra_json.permissions`.
- Если прав нет, действие блокируется в интерфейсе.

## `LayoutCatalog` и пагинация в админке
- `LayoutCatalog::listLayouts()` используется для списка макетов.
- `browse_messages()` применяется в списках объектов (см. `dashboard`).

### Управление макетами в админке
POST-действия для страницы макетов (`/admin.php?action=layouts`):
- `layout_create` — создаёт новый макет и, при наличии, `layout.nav.php`.
- `layout_update` — обновляет шаблон макета и опциональный шаблон навигации.
- `layout_delete` — удаляет файлы макета (кроме системных `default` и `home`).

Поле шаблона макета может быть пустым: обработчики сохраняют даже пустой файл, чтобы
можно было временно отключить разметку или подготовить файл под дальнейшую правку.

Во всех формах используйте `csrf_token_field()` и валидируйте ключ макета через `layoutKeyIsValid()`.

Для пользовательского фидбэка используйте флеш-сообщения:
- перед редиректом зовите `adminFlashSet('success'|'error', 'Сообщение')`;
- на странице `GET`‑формы выводите `adminFlashConsume()` и отображайте список сообщений.

`writeLayoutTemplate()` и `writeLayoutNavTemplate()` дополнительно проверяют доступность директории
`templates/layouts/` через пробную запись (файл `.write_probe`) и возвращают текст ошибки через
`$error`, если папка недоступна или блокируется настройками вроде `open_basedir`.

### Пример `browse_messages()` в админке

```php
<?php
$ccEnv = [
    'current_page' => $page,
    'total_pages' => $totalPages,
    'base_url' => '/admin.php',
    'query_params' => [
        'section_id' => $selectedId,
        'tab' => 'content',
    ],
    'per_page' => $perPage,
    'total_items' => $totalItems,
];
echo browse_messages($ccEnv, 10);
```

## Файловый менеджер и загрузки
Админка отображает файловый менеджер через `filemanager` и `filemanager_embed`:
- `filemanager_embed` требует роль `admin`.
- Подключается файл `vendor/filegator/index.php` (если он установлен).

Загрузки файлов объектов хранятся в:
- `public_html/files/component/<infoblock_id>`

Файлы визуальных полей — в:
- `public_html/files/layouts/<layout>/<fieldId>`

## Пример нового `action` (`GET` + `POST`)
### `GET`‑`action` `hello`
`app/admin/actions/get/hello.php`:

```php
<?php
AdminLayout::renderHeader('Hello');
echo '<div class="container py-4">Привет!</div>';
AdminLayout::renderFooter();
```

### `POST`‑`action` `hello_save`
`app/admin/actions/post/hello_save.php`:

```php
<?php
if (!Auth::isAdmin()) {
    jsonResponse(['ok' => false, 'error' => 'Недостаточно прав'], 403);
}
// ... обработка данных ...
jsonResponse(['ok' => true]);
```

### Ссылка в админке
```php
<a href="/admin.php?action=hello">Открыть</a>
```
