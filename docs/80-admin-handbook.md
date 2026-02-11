# Руководство по админке

О чём документ: устройство админки, модель действий и правила безопасности.
Когда читать: перед добавлением новых действий админки и интеграций.
Кому полезно: разработчикам, расширяющим админку.
Связанные документы: `docs/81-admin-full-reload.md`, `docs/95-security.md`, `docs/90-database.md`.

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

## Логирование ошибок админки
`AdminRouter` фиксирует ошибки исполнения `action`‑файлов двумя путями:

- `AdminLog::log(...)` записывает ошибку в таблицу `admin_log` (если класс доступен).
- `ErrorLogger::log('admin_error', $data)` пишет JSON‑строки в файл `var/logs/admin_error.log`
  на случай, когда системная таблица недоступна или запись в неё падает.

Формат строки в файле:

```json
{"created_at":"2026-02-02T16:49:11+00:00","channel":"admin_error","data":{"action":"layouts","method":"GET","user_id":1,"request_uri":"/admin.php?action=layouts&tab=visual","message":"..."}}
```

Поле `data` содержит минимум: `action`, `method`, `user_id`, `request_uri`, `message`, `file`, `line`, `trace`.

## Форматы ответа
Действие может:
- сделать `redirectTo()`;
- вывести строку (`echo` или `return` строку).

## Просмотр ошибок в админских логах
Страница `/admin.php?action=logs` выводит колонку «Детали». Для записей с `entity_type = admin_error`
там показываются:

- текст ошибки (`message`);
- файл и строка (`file:line`);
- раскрываемый `trace`.

Источник данных — `admin_log.data_json`, который заполняется `AdminRouter::logActionError()`.
В `app/admin/actions/get/logs.php` за форматирование колонки отвечает функция `renderLogDetails()`,
она декодирует `data_json` и собирает краткое описание ошибки.

При открытии страницы логов выполняется обрезка таблицы `admin_log` до последних 500 записей
через `AdminLog::trimToLimit(500)` (самые старые строки удаляются).
Фильтры «Тип сущности» и «Действие» формируются как селекты на основе уникальных значений в БД.

## Подтверждение опасных действий
Формы с атрибутом `data-confirm` подтверждаются через модальное окно `adminConfirmModal`
вместо `window.confirm`. Модалка отображает текст подтверждения и выполняет отправку формы
только после нажатия кнопки подтверждения.

## Модальные формы и сообщения
Формы внутри модалок (`#adminModal`) отправляются через AJAX и отображают сообщения
в виде alert‑блоков внутри модалки (тосты в модалках не используются). За вывод отвечает
функция `showModalAlert()` в `public_html/assets/admin.js`. При успешной отправке страница
перезагружается, чтобы отобразить обновлённые данные.

Полезные хелперы (в `AdminHelpers.php`):
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

## `Layout` и пагинация в админке
- `Layout::listLayouts()` используется для списка макетов.
- `browse_messages()` применяется в списках объектов (см. `dashboard`).

### Управление макетами в админке
POST-действия для страницы макетов (`/admin.php?action=layouts`):
- `layout_create` — создаёт новый макет и, при наличии, `layout.nav.php`.
- `layout_update` — обновляет шаблон макета и опциональный шаблон навигации.
- `layout_delete` — удаляет файлы макета (кроме системных `default` и `home`).

`GET`-страница макетов рендерит сайдбар и контент только в полном режиме
с header и footer. Сайдбар обновляется исключительно при полной перезагрузке страницы.

Поле шаблона макета может быть пустым: обработчики сохраняют даже пустой файл, чтобы
можно было временно отключить разметку или подготовить файл под дальнейшую правку.

Для вкладки «Визуальные настройки» функция `renderLayoutsContentHtml()` принимает репозиторий
`VisualFieldRepo` параметром и использует его напрямую, чтобы избежать зависимости от глобальных
переменных при рендеринге списка визуальных полей.

### Компоненты: полная перезагрузка без частичных обновлений
Раздел «Компоненты» работает только через полные переходы (без `partial`‑обновлений и
`data-refresh-url-template`). Формы на странице отправляются обычным `POST → redirect → GET`
и используют `adminFlashSet(...)` для отображения тостов об успехе/ошибке.

### Врезки: типовая двухколоночная страница
Страница врезок повторяет шаблон «левая колонка / правая колонка», как у макетов и компонентов:
сайдбар рендерится `renderSnippetsSidebarHtml()`, форма редактирования — `renderSnippetsContentHtml()`.
Операции сохранения и удаления используют `POST → redirect → GET` и тосты через `adminFlashSet(...)`.

### Разделы: полный reload без частичных блоков
Страница разделов (`/admin.php`) больше не использует частичные `partial`‑обновления и `data-refresh-url`.
Все формы на странице работают через `POST → redirect → GET`, а результаты показываются тостами
через `adminFlashSet(...)`.

### Объекты: редактор CKEditor для текстовых полей
При создании и редактировании объектов `textarea`‑поля компонента получают класс `js-ckeditor`
и уникальный `id="editor-<field>"`, который инициализирует редактор CKEditor через
`initCkeditorEditors()` в `public_html/assets/admin.js`.
В админском layout подключаются стиль для скрытия зоны уведомлений CKEditor и скрипт:

```
<style>#cke_notifications_area_editor-text{display:none;}</style>
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
```

Инициализация выполняется при загрузке страницы и при открытии форм в модалке
через `openAdminModal()` → `initCkeditorEditors()` в `public_html/assets/admin.js`.

Настройки по умолчанию:

```
CKEDITOR.replace('editor-<field>', {
  extraPlugins: 'table,image',
  removePlugins: 'cloudservices'
});
```

Другие редакторы (MediumEditor, Summernote, Quill, MarkdownEditor из пакета SOW) в админке
не используются и их локальные ассеты удалены.

### Бэкапы: одна колонка, список, загрузка и восстановление
Страница `/admin.php?action=backups` работает в одну колонку в стандартном admin layout.
На странице доступны:
- создание нового бэкапа (`POST /admin.php?action=backups_create`),
- загрузка архива `.tar.gz` (`POST /admin.php?action=backups_upload`),
- список архивов из `var/backups` с кнопкой скачивания (`GET /admin.php?action=backups_download&file=...`),
- восстановление выбранного архива (`POST /admin.php?action=backups_restore`).

Восстановление перед распаковкой полностью очищает целевые пути бэкапа (`var/app.sqlite`, `templates/layouts`,
`templates/component`, `templates/snippets`, `public_html/files`), затем распаковывает архив в корень проекта.
Все новые данные в этих путях будут удалены, поэтому используйте подтверждение действия
и выполняйте операцию только администратором.

### Пользователи: full-reload и тосты
Раздел пользователей работает без `partial`‑блоков и `data-ajax`. Все операции обновления роли,
смены пароля и удаления выполняются через `POST → redirect → GET` и показывают тосты через
`adminFlashSet(...)`. Страница создаётся в одну колонку без сайдбара.

Во всех формах используйте `csrf_token_field()` и валидируйте ключ макета через `layoutKeyIsValid()`.
Все `POST`-операции работают по схеме `POST → redirect → GET` и используют
`adminFlashSet(...)` для сообщений.

### Единая проверка AJAX‑запросов
В админке используйте только `isAjaxRequest()` из `app/admin/AdminHelpers.php`.
`AdminRouter` опирается на эту функцию при обработке массивов, возвращённых из action‑файлов.
Другие реализации проверки не используйте, чтобы избежать рассинхронизации поведения.
Смысла хранить две функции с одинаковой логикой нет: это повышает риск расхождения правил.

### Каталоги макетов и nav‑шаблонов
### Валидация ключей компонентов, врезок и макетов
`componentKeyIsValid()`, `snippetKeyIsValid()` и `layoutKeyIsValid()` теперь используют общий
хелпер `adminKeyIsValid()` с единым набором проверок (regex + защита от `..`, `/`, `\`).
Если нужно менять правила валидации, правьте только `adminKeyIsValid()` и не дублируйте логику
в отдельных функциях.
На практике в действиях по макетам проверяется только `layoutKeyIsValid()`, а
`componentKeyIsValid()` используется лишь в `component_delete.php`, `snippetKeyIsValid()` в
текущих action-файлах не вызывается — это нужно учитывать при аудите маршрутов и форм.
`readDefaultLayoutNavTemplateFile()` не является «мертвой» функцией: она используется в
`app/admin/actions/get/layouts.php` при создании нового макета, чтобы подставлять дефолтный
шаблон навигации. Функция читает файл `templates/layouts/default/default.nav.php` и возвращает
его содержимое, либо `null`, если файл отсутствует или не читается.

`layoutTemplatesDir()` и `layoutNavTemplatesDir()` оба указывают на `templates/layouts`.
Это намеренно: обычные макеты и `*.nav.php` лежат рядом, а различие задаётся суффиксом файла.
`readLayoutNavTemplate()` ищет `layoutKey.nav.php` в том же каталоге, поэтому дублирование пути
здесь не является ошибкой, но требует ясной документации.

### Рекурсивный обход дерева разделов
`collectSections()` теперь делегирует обход в `collectSectionTree()` и просто удаляет поле
`depth` из результата, поэтому реальная рекурсия живёт в одном месте. В `collectSectionTree()`
добавляется поле `depth` в каждый узел; если оно не нужно, используйте `collectSections()`.

### Единый рендер file input + preview
Разметка file input, превью, кнопки очистки и чекбокса удаления вынесена в `renderFileInput()`,
её используют и `renderFieldInput()`, и `app/admin/actions/get/dashboard.php` для визуальных
настроек. Новые сценарии с файлами следует собирать через этот хелпер, чтобы не плодить копии.

### Единый helper для textarea-содержимого
`renderTextareaValue()` вынесен в `app/admin/AdminHelpers.php` и используется во всех формах,
где нужно безопасно вставлять шаблоны в `<textarea>` (экранирование `</textarea>`). Не дублируйте
эту функцию в action-файлах.

### UI-рендеры должны жить в app/admin/ui
Рендеринг списков и карточек (например, список врезок и список пользователей) вынесен в
`app/admin/ui` (`SnippetListView`, `UsersListView`). Новую HTML-логику списка/карточки
размещайте там, а action-файлы оставляйте для подготовки данных.

`writeLayoutTemplate()` и `writeLayoutNavTemplate()` дополнительно проверяют доступность директории
`templates/layouts/` через пробную запись (файл `.write_probe`) и возвращают текст ошибки через
`$error`, если папка недоступна или блокируется настройками вроде `open_basedir`.

`layoutTemplatesDir()` и `layoutNavTemplatesDir()` возвращают путь к каталогу
`templates/layouts/`, определённый относительно `app/` (на два уровня вверх от `app/admin`).

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
- Запросы `POST` к `filemanager_embed` не проходят проверку CSRF админки: Filegator использует собственный токен.

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
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo('/admin.php?action=hello');
}
// ... обработка данных ...
adminFlashSet('success', 'Сохранено');
redirectTo('/admin.php?action=hello');
```

### Ссылка в админке
```php
<a href="/admin.php?action=hello">Открыть</a>
```
