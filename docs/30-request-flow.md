# Поток запроса на фронтенде

О чём документ: как `Renderer` выбирает сайт, раздел, инфоблоки и шаблоны.
Когда читать: после инициализации и перед настройкой шаблонов.
Кому полезно: разработчикам, настраивающим компоненты и макеты.
Связанные документы: `docs/40-templates-and-components.md`, `docs/60-layouts.md`.

## Общая схема
`public_html/index.php` → `Renderer::renderSitePath()` → выбор сайта → выбор раздела → инфоблоки → объекты → шаблоны → макет.

## Выбор сайта по хосту
- Берётся `$_SERVER['HTTP_HOST']`.
- `SectionRepo::findSiteByHost()` ищет совпадение по `site_domain` или `site_mirrors` (в `sections.extra_json`).
- Если хост пустой или совпадений нет — возвращается первый сайт.

## Поиск раздела по пути
- Путь берётся из `$_SERVER['REQUEST_URI']`.
- `/index` редиректится на `/` (301).
- `SectionRepo::findByPath()` ищет раздел по сегментам пути в пределах сайта.
- Если раздел не найден — используется корневой раздел `404` (если он существует).

## Инфоблоки и компоненты
- `InfoblockRepo::listForSection()` возвращает инфоблоки раздела (только включённые).
- Для каждого инфоблока выбирается компонент (`ComponentRepo::findById()`).
- Имя шаблона инфоблока выбирается через `Renderer::resolveViewTemplate()`:
  - если в `infoblocks.view_template` указан шаблон и он существует — берётся он;
  - иначе выбирается первый доступный view из `components.views_json`, который реально существует;
  - если нет данных — `default`.

### Обёртки `before_html` / `after_html`
Если в `infoblock.extra_json` заданы `before_html` и/или `after_html`, эти фрагменты
оборачивают HTML инфоблока при рендере.

## Получение объектов
### Обычный режим
`ObjectRepo::listBySystemQuery()` вызывается с параметрами:
- `infoblock_id`, `component_id`, `status = published`;
- пагинация `per_page`, `offset` (если включена);
- параметры `system.php` (см. ниже).

### Режим `single` по `?object_id=`
Если задан `object_id`:
- объект должен существовать, принадлежать текущему разделу и не быть удалённым;
- если объект не опубликован, нужен корректный токен предпросмотра (см. ниже);
- в режиме `single` рендерится один объект и используется `single.php` (если есть).

Пример ссылки на режим `single`:

```
/novosti/?object_id=123
```

### Режим предпросмотра
Предпросмотр доступен, если выполнены условия:
- передан `preview_token` в адресе;
- пользователь залогинен (`Auth::user()`);
- `preview_token` совпадает с `$_SESSION['preview_token']`.

Пример адреса предпросмотра:

```
/novosti/?object_id=123&preview_token=SESSION_TOKEN
```

`SESSION_TOKEN` создаётся в админке через `ensurePreviewToken()`.

## Пагинация и `cc_env`
Если `infoblocks.per_page > 0` и не режим `single`, `Renderer` рассчитывает:
- `current_page`, `total_pages`, `total_items`;
- `base_url` (путь текущего раздела);
- `query_params` — текущие `$_GET` без `page`, `object_id`, `preview_token`.

Эти данные доступны в шаблоне как `$cc_env` и используются с `Pagination::render()` или `browse_messages()`.

## Выбор макета
`Renderer::resolveLayoutKey()`:
- по умолчанию `home` для корня сайта, иначе `default`;
- если в `site.extra_json.layout` указан существующий макет — используется он;
- если в `section.extra_json.layout` указан существующий макет — он перекрывает сайт.
- если выбранный макет не существует — возврат на `default`.

Подробно о макетах: `docs/60-layouts.md`.
