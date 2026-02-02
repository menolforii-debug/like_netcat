# RFC: Админка like_netcat — только полный reload

Статус: обязательный стандарт
Область: все действия админки (`GET`/`POST`)
Версия: 1.0

## 1. Архитектурный принцип
Админка работает **ТОЛЬКО** в режиме:
- full page load
- POST → redirect → GET (PRG)

✅ РАЗРЕШЕНО:
- полная загрузка страницы
- редирект после `POST`

❌ ЗАПРЕЩЕНО:
- AJAX-навигация
- SPA / SPA-light
- selector-based обновления DOM

## 2. Категорический запрет AJAX
Любые механизмы ниже считаются legacy и **архитектурно запрещены**:

❌ ЗАПРЕЩЕНО:
- AJAX-навигация
- SPA / SPA-light
- `fetch` / `XMLHttpRequest` для `GET` или `POST`, **кроме** загрузки контента модалок по `data-modal-url`
- перехват кликов по ссылкам
- перехват `submit` форм
- `data-ajax`
- `ajax-admin`
- `partial=*`
- `refresh` / `focus` / selector-based DOM update
- `data-refresh-url`
- `data-refresh-url-template`

Использование любого из перечисленных механизмов считается **архитектурной ошибкой**.

## 3. Контракт GET-actions
Каждый `GET`-action админки:
- рендерит **полную** страницу:
  - header
  - sidebar
  - content
  - footer
- **не** поддерживает AJAX-режим
- **не** проверяет `isAjaxRequest()`
- **не** возвращает content-only HTML
- даже при наличии `X-Requested-With` отдает полный layout (например, страница «Макеты»)

✅ РАЗРЕШЕНО:
```php
AdminLayout::renderHeader('Title');
// ... полный контент страницы ...
AdminLayout::renderFooter();
```

❌ ЗАПРЕЩЕНО:
```php
if (isAjaxRequest()) {
    echo $contentOnly;
    return;
}
```

## 4. Контракт POST-actions
Каждый `POST`-action:
- обрабатывает данные
- устанавливает flash-сообщение:
  - `success` / `danger` / `info`
- **обязательно** делает `redirectTo(URL)`
- **никогда** не возвращает JSON для UI

Единственный допустимый паттерн:
**POST → redirect → GET**

✅ РАЗРЕШЕНО:
```php
adminFlashSet('success', 'Сохранено');
redirectTo('/admin.php?action=example');
```

❌ ЗАПРЕЩЕНО:
```php
fetch('/admin.php?action=save', { method: 'POST' });
jsonResponse(['ok' => true]);
adminOk('Сохранено');
```

## 5. Тосты / уведомления
Уведомления в админке реализуются **только** через server-side flash:
- `adminFlashSet(type, message)`
- `adminFlashConsume()`

Тосты:
- рендерятся в общем layout админки
- передаются через контейнер `<div id="admin-flash" data-flash="..."></div>`
- отображаются после redirect
- **не** зависят от AJAX
- работают одинаково для всех страниц, включая «Макеты»

✅ РАЗРЕШЕНО:
```php
adminFlashSet('info', 'Готово');
redirectTo('/admin.php?action=dashboard');
```

❌ ЗАПРЕЩЕНО:
```php
jsonResponse(['message' => 'Готово']);
```

## 6. Модальные окна (единственный разрешенный AJAX)
Единственное допустимое использование `fetch` в админке — загрузка контента модалок:
- триггер: элемент с `data-modal-url`
- запрос: `GET` с `X-Requested-With: XMLHttpRequest`
- полученный HTML вставляется **только** в тело модального окна

Формы внутри модалки:
- отправляются обычным `POST` (полная перезагрузка страницы)
- не используют `data-ajax`/`fetch`

## 7. Sidebar
Sidebar является частью layout и:
- **не** обновляется динамически
- **не** синхронизируется с состоянием страницы
- **не** содержит JS-логики
- обновляется **только** при полной загрузке страницы

✅ РАЗРЕШЕНО:
- sidebar как часть `AdminLayout::renderHeader(...)`

❌ ЗАПРЕЩЕНО:
- любые JS-обновления sidebar

## 8. Принцип простоты
Админка намеренно использует:
- простую навигацию
- предсказуемые перезагрузки
- минимальное количество JS

Это **осознанный архитектурный выбор**, а не временное ограничение.

## 9. Блок примеров (коротко)

✅ РАЗРЕШЕНО:
```html
<form method="POST" action="/admin.php?action=save">
  <?php echo csrf_token_field(); ?>
  <button type="submit">Сохранить</button>
</form>
```

❌ ЗАПРЕЩЕНО:
```js
form.addEventListener('submit', (e) => {
  e.preventDefault();
  fetch(form.action, { method: 'POST', body: new FormData(form) });
});
```
