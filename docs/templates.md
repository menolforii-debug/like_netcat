# Макеты и шаблоны: доступные переменные и функции

Этот документ описывает, какие переменные и функции доступны внутри файлов
макетов дизайна и шаблонов компонентов.

## Макеты дизайна (`templates/layouts/*.php`)

Макет подключается через `Layout::render($layoutKey, $ctx, $body)` и получает
доступ к двум переменным:

- `$ctx` — массив контекста.
- `$body` — callable для вывода контента страницы.

При создании нового макета админка использует шаблоны-заготовки:

- `templates/layouts/default/layout.tpl.php`
- `templates/layouts/default/nav.tpl.php`

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
 
- `Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, $options)` — вывод пагинации (если параметры `null`, просто ничего не выводит).
- `Layout::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, $options)` — получить массив элементов пагинации для кастомной разметки (при `null` вернёт пустой массив).
 
Также доступны глобальные helper‑функции:

- `insert_snip(string $keyword, array $vars = [])` — вставляет содержимое врезки по ключу и выполняет PHP‑код внутри неё (сырое HTML/JS/PHP из админки, без экранирования).
  - Если `$vars` не передан, врезка наследует переменные текущего шаблона.
  - Функция печатает результат и возвращает его строкой.


Также можно вызывать `$body()` для вывода контентной части (в текущем рендеринге —
HTML инфоблоков раздела).

Пример вставки врезки:

```php
<?php insert_snip('footer_banner'); ?>
```

Внутри врезок доступны те же переменные, что и в контексте шаблона:

- В макетах: `$ctx`, `$body`.
- В шаблонах компонентов: `$section`, `$site`, `$infoblock`, `$component`, `$settings`, `$items`, `$objects`, `$isSingle`, `$object`, `$core`, `$message_select`.

Полный список функций для макетов, компонентов и врезок см. в `docs/functions-api.md`.

Пример использования пагинации:

```php
<?php
$currentPage = 1;
$totalPages = 10;
$baseUrl = '/admin.php';
$params = [
    'section_id' => 1,
    'tab' => 'content',
    'content_infoblock_id' => 5,
];
Layout::renderPagination($currentPage, $totalPages, $baseUrl, $params, [
    // Опционально: показать пагинацию даже при totalPages = 1
    // 'show_single' => true,
]);
?>
```

Пример кастомной разметки с `getPaginationItems`:

```php
<?php

$items = Layout::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, [
    // 'show_single' => true,
]);

?>
<?php if (!empty($items)) : ?>
    <div class="pagination-custom">
        <?php foreach ($items as $item): ?>
            <?php
            $classes = ['page-link'];
            if (!empty($item['active'])) {
                $classes[] = 'is-active';
            }
            if (!empty($item['disabled'])) {
                $classes[] = 'is-disabled';
            }
            ?>
            <a class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>"
               href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8') ?>"
               <?php if (!empty($item['aria'])): ?>
                   aria-label="<?= htmlspecialchars((string) $item['aria'], ENT_QUOTES, 'UTF-8') ?>"
               <?php endif; ?>>
                <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif ?>
```

 
Пример подключения пагинации в публичном компоненте (шаблон `templates/component/<keyword>/list.php`):

```php
<?php
/** @var array $items */
/** @var array $infoblock */

$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;
$totalItems = $items ? count($items) : 0;
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$baseUrl = $section['path'] ?? '/';
$params = [];

$pagination = Layout::getPaginationItems($currentPage, $totalPages, $baseUrl, $params, [
    // 'show_single' => true,
]);
$pageItems = array_slice($items, ($currentPage - 1) * $perPage, $perPage);
?>

<?php foreach ($pageItems as $item): ?>
    <article class="news-item">
        <h2><?= htmlspecialchars((string) ($item['data']['title'] ?? 'Без заголовка'), ENT_QUOTES, 'UTF-8') ?></h2>
    </article>
<?php endforeach; ?>

<?php if (!empty($pagination)) : ?>
    <nav class="pagination-custom">
        <?php foreach ($pagination as $link): ?>
            <?php
            $classes = ['page-link'];
            if (!empty($link['active'])) {
                $classes[] = 'is-active';
            }
            if (!empty($link['disabled'])) {
                $classes[] = 'is-disabled';
            }
            ?>
            <a class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>"
               href="<?= htmlspecialchars((string) $link['url'], ENT_QUOTES, 'UTF-8') ?>"
               <?php if (!empty($link['aria'])): ?>
                   aria-label="<?= htmlspecialchars((string) $link['aria'], ENT_QUOTES, 'UTF-8') ?>"
               <?php endif; ?>>
                <?= htmlspecialchars((string) $link['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>
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

## Дефолтные шаблоны для нового макета

Админка подставляет стартовую разметку для новых макетов из файлов:

- `templates/layouts/default/layout.tpl.php` — шаблон макета по умолчанию.
- `templates/layouts/default/nav.tpl.php` — шаблон навигации по умолчанию.

При создании нового макета эти файлы читаются через `readDefaultLayoutTemplateFile()` и
`readDefaultLayoutNavTemplateFile()` (см. `app/admin/AdminHelpers.php`).【F:app/admin/AdminHelpers.php†L743-L780】

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
  - `key` — ключ инфоблока для различения одинаковых блоков.
  - `per_page` — лимит объектов на странице (0 = без лимита).
- `$component` — компонент (из `components`).
- `$items` — массив объектов инфоблока, каждый элемент:
  - `id`, `data`, `status`, `created_at`, `updated_at`, `controls`.
- `$objects` — alias для `$items`.
- `$isSingle` — флаг режима «одного объекта».
- `$object` — объект в режиме single (первый элемент `$objects`), либо `null`.
- `$core` — системный массив (в текущем коде пустой, но доступен для совместимости).
- `$message_select` — SQL‑запрос, которым была получена выборка объектов (для отладки).
- `$setFields` — helper для установки переменных `$f_*` по данным объекта (для list‑шаблонов).
- Поля объекта доступны как в массиве `data`, так и в виде отдельных переменных с префиксом `f_`
  (например, поле `big_text` можно использовать как `$object['data']['big_text']` и как `$f_big_text`).

### Дополнительные helper‑функции

В шаблонах компонентов можно вызывать `nc_objects_list()` для выборки объектов по фильтрам
(см. подробное описание в `docs/objects-api.md`).

Также доступна функция `nc_resize_image($sourcePath, $maxWidth, $maxHeight, $destPath = null, $quality = 85)`,
которая уменьшает изображение и возвращает путь сохранённого файла.

### Пример доступа к полям объекта

Внутри шаблонов компонентов можно приводить поля объекта к переменным с префиксом `f_`,
чтобы избежать повторения длинных выражений. В режиме list можно вызвать `$setFields($obj)`
внутри цикла.

```php
<?php
/** @var array $object */
$f_title = (string) ($object['data']['title'] ?? 'Без заголовка');
$f_big_text = (string) ($object['data']['big_text'] ?? '');
?>

<article class="news-item">
    <h2><?= htmlspecialchars($f_title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if ($f_big_text !== ''): ?>
        <p><?= htmlspecialchars($f_big_text, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</article>
```

### Системные настройки шаблона

В админке для представления компонента доступно поле **«Системные настройки»** (`component_views.system_tpl`).
При сохранении представления админка формирует единый PHP‑файл:

1) выбирает `list` или `single` часть;  
2) добавляет содержимое `system_tpl` в конец файла.

Это позволяет объявлять вспомогательные функции, переменные и выполнять дополнительную логику
после выбора режима list/single. Обёртки `<?php ... ?>` добавляются автоматически, поэтому теги можно не писать.

#### Переменные, доступные в `system_tpl`

`system_tpl` выполняется в том же контексте, что и шаблон компонента, поэтому в нём доступны
все переменные из списка выше:

- `$section`, `$site`, `$component` — текущие сущности.
- `$infoblock` — массив инфоблока (включая `key`, `per_page`, `view_template`).
- `$items`/`$objects` — массив объектов инфоблока.
- `$isSingle`, `$object` — режим single/list и объект single.
- `$core` — системный массив (сейчас пустой).
- `$message_select` — SQL‑запрос, которым была получена выборка объектов (влияние на запрос):
  можно анализировать/логировать текущий запрос или принять решение о дополнительной выборке.

Если требуется влиять на выборку, используйте собственные запросы или `nc_objects_list()` прямо
в `list`/`single` и/или `system_tpl`. При этом системный запрос уже выполнен до выполнения `system_tpl`,
а `$message_select` показывает его итоговый SQL.

### Шаблоны действий компонента

Для компонента можно задать «шаблон действий» — PHP‑код, который выполняется **после создания нового объекта**
в админке. Код хранится в `templates/component/<keyword>/actions.php`.

Доступные переменные:

- `$message` — ID созданного объекта.
- `$object` — объект (строка из `objects`, уже сохранённая).
- `$infoblock` — инфоблок, в котором создан объект.
- `$component` — компонент объекта.

#### Предлагаемые правила использования

- настройки инфоблока (лимит «выводить по N объектов», HTML до/после) трактуются как параметры вывода,
  не как место для SQL или сложной логики.
- `system_tpl` можно использовать для продвинутых сценариев: собственных запросов,
  подготовки данных, полной переопределённой разметки. Если нужно управлять выборкой,
  делайте это в `list`/`single`, а `system_tpl` используйте для объявления функций и констант.
- При необходимости «полностью своего» вывода рекомендуется оставлять `list`/`single`
  минимальными (например, вызов функции), а остальную логику хранить в `system_tpl`.

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
