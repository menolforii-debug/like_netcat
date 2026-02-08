# Админка: SPA-light навигация (устаревший документ)

> ВНИМАНИЕ: SPA-light навигация в админке отключена. Текущий стандарт — **полный reload + PRG** с единственным AJAX-исключением для загрузки модалок по `data-modal-url`. См. актуальный документ: `docs/81-admin-full-reload.md`.

## Статус

**Устаревший стандарт. Документ сохранен для истории и не должен применяться в новой разработке.**

## Архитектура

- Админка работает в режиме **SPA-light**.
- Единственная AJAX-навигация — через ссылки `<a class="ajax-admin">`.
- **URL — единственный источник истины** для состояния страницы (например, `layout`, `tab`).

## Контракт ссылок

✅ **Разрешено**

```html
<a class="ajax-admin" href="/admin.php?action=layouts&layout=home&tab=visual">Визуальные настройки</a>
```

❌ **Запрещено** (не будет перехвачено JS)

```html
<a href="/admin.php?action=layouts&layout=home&tab=visual">Визуальные настройки</a>
```

Правило: **только** `<a class="ajax-admin">` участвуют в AJAX-навигации. Любые ссылки без класса **никогда** не перехватываются и приводят к полной перезагрузке.

## Контракт GET-action

Каждый GET-action обязан поддерживать два режима:

### A) FULL PAGE (не AJAX)

- `renderHeader`
- `renderSidebar`
- `renderContent`
- `renderFooter`

### B) AJAX CONTENT (isAjaxRequest)

- рендер **только** content
- **запрещено** рендерить header/footer/sidebar
- **exit** сразу после content

✅ **Правильно**

```php
if (isAjaxRequest()) {
    renderExampleContentHtml($data);
    exit;
}

AdminLayout::renderHeader('Пример');
AdminLayout::openSidebar();
renderExampleSidebarHtml($data);
AdminLayout::closeSidebar();
AdminLayout::openContent();
echo '<div id="content">';
renderExampleContentHtml($data);
echo '</div>';
AdminLayout::closeContent();
AdminLayout::renderFooter();
```

❌ **Запрещено**

```php
// partial=content / partial=sidebar
if (isAjaxRequest() && $_GET['partial'] === 'content') { ... }
```

## Запрещено (legacy)

Любое использование следующих механизмов считается архитектурной ошибкой:

- `data-refresh-url`
- `data-refresh-url-template`
- selector-based refresh
- `refresh` в JSON-ответах
- `partial=sidebar` / `partial=content` / `partial=visual_fields`
- любые DOM-обновления, кроме **полной** замены `#content`

**Возврат к refresh/partial недопустим.** Новый код пишется только по SPA-light контракту.

## Контракт admin.js

Обязательные требования:

- Делегирование кликов **только** для `a.ajax-admin`.
- `history.pushState` / `popstate` обязательны.
- После каждой AJAX-подмены `#content` вызывается `initAdminUI(rootElement)`.
- Запрещены любые альтернативные AJAX-навигации.

### Актуальные методы

- `initAdminUI(rootElement)` — повторно инициализирует UI внутри `#content` (визуальные переключатели, инфоблоки, code editor и т.д.).
- `ajaxLoad(url, { push = true })` — загружает content-only HTML, заменяет `#content`, синхронизирует URL и вызывает `initAdminUI`.

## Контракт POST-действий (SPA-light)

Формы с `data-ajax="true"` отправляются через `fetch` и обрабатываются по единому правилу:

### A) Redirect (предпочтительно)

- Сервер возвращает `redirectTo(<url>)`.
- JS **не** делает `window.location`, а вызывает `ajaxLoad(<url>)`.
- Используйте для операций, меняющих набор данных (создание/удаление).

### B) JSON `{"ok": true}`

- Допустимо, если состояние страницы не меняется.
- JS вызывает `ajaxLoad(currentUrl)` для полной перерисовки `#content`.

### Ошибки

Возвращайте `jsonResponse(['ok' => false, 'error' => '...'], HTTP_STATUS)`.  
`refresh`, selector-based обновления и любые `partial`-механики запрещены.

## Стратегия миграции

- Миграция выполняется **постранично**.
- В любой момент времени action **либо полностью legacy (full reload)**, либо **полностью SPA-light**.
- Смешивание подходов внутри одного action запрещено.

## Напоминание

Legacy-механики удалены **сознательно**. Использовать refresh/partial запрещено — это нарушение стандарта.

## Известные отклонения (требуют устранения)

- `app/admin/actions/post/object_update.php` в AJAX-ветке возвращает `adminOk(..., ['refresh' => ['#content']])`,
  что нарушает запрет на `refresh` в JSON-ответах. Этот хвост нужно убрать при следующем рефакторинге
  и привести обработку к текущему контракту (redirect или `{"ok": true}` без selector-refresh).
