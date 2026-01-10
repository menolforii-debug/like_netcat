# Макеты и шаблоны: доступные переменные и функции

Этот документ описывает, какие переменные и функции доступны внутри файлов
макетов дизайна и шаблонов компонентов.

## Макеты дизайна (`templates/layouts/*.php`)

Макет подключается через `Layout::render($layoutKey, $ctx, $body)` и получает
доступ к двум переменным:

- `$ctx` — массив контекста.
- `$body` — callable для вывода контента страницы.

### Состав `$ctx`

Ключи заполняются в `Renderer::renderSitePath()` перед рендерингом:

- `title` — строка заголовка страницы (SEO).
- `meta` — массив метаданных (`description`, `keywords`).
- `site` — массив сайта из таблицы `sections` (корневой раздел).
- `section` — массив текущего раздела.
- `visual` — визуальные настройки, унаследованные по дереву разделов.
- `children` — массив дочерних разделов текущего раздела (каждый содержит `path` и `title`).

### Доступные функции/методы

В макете можно вызывать статические методы класса `Layout`:

- `Layout::renderCss()` — подключение CSS (локальные SOW‑ассеты или Bootstrap CDN).
- `Layout::renderJs()` — подключение JS (локальные SOW‑ассеты или Bootstrap CDN).
- `Layout::renderNavbar($brand, $links)` — быстрый вывод navbar.
- `Layout::renderDocumentStart($title, $meta)` / `Layout::renderDocumentEnd()` — готовые обёртки HTML.
- `Layout::renderSectionHeader($section, $children)` — вывод заголовка раздела и списка дочерних разделов.
- `Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options)` — вывод пагинации.
- `Layout::buildPagination($currentPage, $totalPages, $baseUrl, $params, $options)` — построение списка элементов пагинации для кастомной разметки.

Также можно вызывать `$body()` для вывода контентной части (в текущем рендеринге —
HTML инфоблоков раздела).

Пример использования пагинации:

```php
<?php
// Текущая страница (например, из $_GET['page']).
$currentPage = 1;
// Общее число страниц (например, на основе COUNT / perPage).
$totalPages = 10;
// Базовый URL без параметров.
$baseUrl = '/admin.php';
// Параметры, которые нужно сохранить в ссылках пагинации.
$params = [
    'section_id' => 1,
    'tab' => 'content',
    'content_infoblock_id' => 5,
];
// Вывод пагинации.
Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params);
?>
```

Пример кастомизации (уменьшенный диапазон и собственные классы):

```php
<?php
$options = [
    'window' => 2, // показывать по 2 страницы вокруг текущей
    'edges' => 1,  // первые/последние страницы
    'nav_class' => 'my-pagination',
    'ul_class' => 'pagination pagination-sm',
    'prev_label' => 'Назад',
    'next_label' => 'Вперёд',
];
Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options);
?>
```

Пример кастомной HTML-разметки через шаблоны:

```php
<?php
$options = [
    'window' => 1,
    'edges' => 1,
    'nav_template' => '<nav class="pager">{list}</nav>',
    'list_template' => '<div class="pager__list">{items}</div>',
    'item_template' => '<span class="{class}"><a class="{link_class}" href="{url}"{aria}>{label}</a></span>',
    'ellipsis_template' => '<span class="{class}"><span class="{link_class}">{label}</span></span>',
    'item_class' => 'pager__item',
    'link_class' => 'pager__link',
    'active_class' => 'is-active',
    'disabled_class' => 'is-disabled',
];
Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options);
?>
```

Доступные плейсхолдеры для шаблонов:

- `{nav_class}` — готовый атрибут `class="..."` для контейнера навигации (если задан `nav_class`).
- `{ul_class}` — класс контейнера списка.
- `{items}` — HTML элементов пагинации.
- `{class}` — итоговый класс элемента (учитывает `item_class`, `active_class`, `disabled_class`).
- `{link_class}` — класс ссылки.
- `{url}` — ссылка на страницу.
- `{label}` — текст ссылки/разделителя.
- `{aria}` — `aria-label` для ссылки (если задано).

Пример полного кастома разметки через `buildPagination`:

```php
<?php
$items = Layout::buildPagination($currentPage, $totalPages, $baseUrl, $params, [
    'window' => 1,
    'edges' => 1,
]);
?>
<?php if (!empty($items)) : ?>
    <ul class="pager">
        <?php foreach ($items as $item): ?>
            <?php if ($item['type'] === 'ellipsis'): ?>
                <li class="pager__ellipsis"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></li>
            <?php else: ?>
                <?php $isActive = !empty($item['active']); ?>
                <?php $isDisabled = !empty($item['disabled']); ?>
                <li class="pager__item<?= $isActive ? ' is-active' : '' ?><?= $isDisabled ? ' is-disabled' : '' ?>">
                    <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endif ?>
        <?php endforeach ?>
    </ul>
<?php endif ?>
```

### Файл навигации макета

Если существует `templates/layouts/<layout>.nav.php`, он подключается перед макетом
и работает в том же скоупе. Это значит, что:

- доступны `$ctx` и `$body`,
- можно объявлять helper‑функции или переменные для использования в основном макете.

Для меню по дереву разделов используйте helper `Layout::getMainMenuItems($ctx, $maxDepth)`
и выводите разметку прямо в макете. Пример разметки:

```php
<?php $menuItems = Layout::getMainMenuItems($ctx, 2); ?>
<?php if (!empty($menuItems)) : ?>
    <ul class="navbar-nav">
        <?php foreach ($menuItems as $sd): ?>
            <?php $submenu = $sd['children'] ?? []; ?>
            <?php if (!empty($submenu)): ?>
                <li class="nav-item dropdown">
                    <a href="#"
                       class="nav-link dropdown-toggle<?= !empty($sd['active']) ? ' active' : '' ?>"
                       data-bs-toggle="dropdown"
                       aria-haspopup="true"
                       aria-expanded="false">
                        <?= htmlspecialchars((string) $sd['name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-hover dropdown-menu-clean dropdown-fadeindown rounded-xl">
                        <ul class="list-unstyled m-0 p-0">
                            <?php foreach ($submenu as $ch): ?>
                                <li class="dropdown-item">
                                    <?php if (!empty($ch['active'])): ?>
                                        <span class="dropdown-link active"><?= htmlspecialchars((string) $ch['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars((string) $ch['url'], ENT_QUOTES, 'UTF-8') ?>" class="dropdown-link">
                                            <?= htmlspecialchars((string) $ch['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                </li>
            <?php else: ?>
                <li class="nav-item dropdown">
                    <?php if (!empty($sd['active'])): ?>
                        <span class="nav-link active"><?= htmlspecialchars((string) $sd['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars((string) $sd['url'], ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                            <?= htmlspecialchars((string) $sd['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endif ?>
                </li>
            <?php endif ?>
        <?php endforeach ?>
    </ul>
<?php endif ?>
```

Пример использования в макете:

```php
<div class="collapse navbar-collapse" id="navbarMain">
    <?php $menuItems = Layout::getMainMenuItems($ctx, 2); ?>
    <?php if (!empty($menuItems)) : ?>
        <!-- Здесь ваша кастомная разметка меню -->
    <?php endif ?>
</div>
```

## Шаблоны компонентов (`templates/component/<keyword>/<view>.php`)

Шаблоны компонентов подключаются в `Renderer::renderInfoblock()`.
Доступный набор переменных:

- `$section` — текущий раздел (массив из `sections`).
- `$site` — текущий сайт (корневой раздел).
- `$infoblock` — инфоблок (из `infoblocks`), включая:
  - `view_template` — выбранный view.
  - `settings` — массив настроек (распарсенный `settings_json`).
- `$component` — компонент (из `components`).
- `$settings` — alias для `$infoblock['settings']`.
- `$items` — массив объектов инфоблока, каждый элемент:
  - `id`, `data`, `status`, `created_at`, `updated_at`, `controls`.
- `$objects` — alias для `$items`.
- `$isSingle` — флаг режима «одного объекта».
- `$object` — объект в режиме single (первый элемент `$objects`), либо `null`.
- `$core` — системный массив (в текущем коде пустой, но доступен для совместимости).
- `$message_select` — SQL‑запрос, которым была получена выборка объектов (для отладки).

### Системные настройки шаблона

В админке для представления компонента доступно поле **«Системные настройки»** (`component_views.system_tpl`).
Код из этого поля вставляется в конец сгенерированного шаблона и оборачивается в `<?php ... ?>`,
чтобы можно было объявлять вспомогательные функции, переменные или переопределять данные перед выводом list/single.

Переменная `$ignore_sub` может использоваться для управления выборкой объектов: если она равна `1`,
то запрос игнорирует текущий раздел (см. `query_json`).

### Настройки SQL‑запроса

Поле **«Настройки запроса (JSON)»** (`component_views.query_json`) позволяет управлять выборкой объектов.
Поддерживаемые ключи:

- `mode`: `extend` (добавить условия к базовому запросу инфоблока) или `replace` (полная замена SQL).
- `where`: массив условий SQL, которые будут добавлены к базовому `WHERE`.
- `order`: строка `ORDER BY` без ключевого слова.
- `limit`: числовое ограничение `LIMIT`.
- `params`: параметры для плейсхолдеров (ассоциативный массив).
- `sql`: полный SQL‑текст (используется при `mode = replace`).
- `ignore_sub`: `1`, если нужно игнорировать текущий раздел при выборке (используется вместе с `$ignore_sub`).

Пример:

```json
{
  "mode": "extend",
  "where": ["status = :status"],
  "order": "created_at DESC",
  "limit": 20,
  "params": {
    "status": "published"
  },
  "ignore_sub": 0
}
```

### Режим single/list

Сгенерированные шаблоны используют `$isSingle` для выбора разметки:

- `list`‑часть получает массив `$objects`.
- `single`‑часть получает `$object`.

### Дополнительные обёртки инфоблока

До и после шаблона инфоблока могут быть выведены данные из `infoblocks.extra_json`:

- `before_html`, `after_html` — HTML‑вставки.

Эти вставки применяются автоматически и не требуют переменных в шаблоне.

## Список дочерних разделов

Отображение заголовка раздела и списка дочерних разделов вынесено в helper
`Layout::renderSectionHeader($section, $children)` и вызывается из макета.
Для собственных макетов используйте переменные `$ctx['section']` и `$ctx['children']`.
