# Шаблоны компонентов

О чём документ: структура шаблонов компонентов, доступные переменные и примеры.
Когда читать: после понимания потока запроса и перед написанием своих шаблонов.
Кому полезно: разработчикам, которые пишут `list.php`/`single.php`/`system.php`.
Связанные документы: `docs/30-request-flow.md`, `docs/50-system-php-contract.md`.

## Структура каталогов
Шаблоны компонентов располагаются по пути:

```
templates/component/<keyword>/<view>/
```

Внутри папки представления (`view`) используются файлы:
- `list.php` — вывод списка объектов.
- `single.php` — вывод одного объекта.
- `system.php` — настройки выборки и `helpers` (опционально).

`<keyword>` — ключ компонента (только `A-Za-z0-9_-`).
`<view>` — имя представления (`view`, только `A-Za-z0-9_-`).

## Как выбирается файл шаблона
- Если включён `single`‑режим (`?object_id=`) и существует `single.php` — используется он.
- Иначе используется `list.php`.
- Если `list.php` отсутствует, но есть `single.php` — он используется и в списке.

## Доступные переменные в шаблоне
`Renderer` передаёт в `list.php`/`single.php` следующий контекст:

- `$section` — текущий раздел.
- `$site` — текущий сайт.
- `$infoblock` — инфоблок (в т.ч. `view_template`, `per_page`, `extra_json`).
- `$component` — компонент (в т.ч. `keyword`, `fields_json`, `views_json`).
- `$items` — список объектов (`['id','data','status','created_at','updated_at','controls']`).
- `$objects` — алиас `$items`.
- `$object` — первый объект в `single`‑режиме или `null`.
- `$isSingle` — режим отображения (`true/false`).
- `$editMode` — зарезервировано (по умолчанию `false`).
- `$settings` — `infoblock['settings']` или пустой массив.
- `$cc_env` — параметры пагинации (`current_page`, `total_pages`, `base_url`, `query_params`, `per_page`, `total_items`).
- `$message_select` — `SQL`‑текст последней выборки (для отладки).
- `$helpers` — массив `helpers` из `system.php`.
- `$setFields` — функция для назначения `$GLOBALS['f_*']` и `$GLOBALS['fullLink']`.

### Переменные `f_*`
Функция `$setFields($item)` записывает данные объекта в глобальные переменные `$f_<field>`.
В `list.php` её нужно вызывать для каждого элемента перед использованием `$f_*`, а в `single.php` `Renderer` вызывает `$setFields()` автоматически.
Данные через `$item['data']` или `$object['data']` в шаблонах не читаются — используйте только `$f_*`.
`Renderer` инициализирует переменные `$f_*` в области шаблона на основе списка полей компонента, чтобы они были объявлены до вызова `$setFields()`.

### Переменная `$fullLink` в `list.php`
`$fullLink` — строка со ссылкой на `single`‑режим текущего объекта. Её устанавливает `$setFields($item)` на каждой итерации списка (формируется в `Renderer` на основе `$cc_env`), вручную ссылку собирать не нужно.

### Хелпер `resolveTemplatePath()`
`resolveTemplatePath(string $componentKey = '', string $view = '', bool $isSingle = false): string` возвращает путь к шаблону компонента (`list.php` или `single.php`) и может использоваться в макетах/обёртках, где требуется подставить файл шаблона по ключу компонента и имени представления. При пустых аргументах возвращает пустую строку. Рендерер использует этот же хелпер для выбора шаблона.

## Пример компонента `news`: список (`list.php`)

```php
<?php
/** @var array $items */
/** @var callable $setFields */
?>
<div class="news-list">
    <?php foreach ($items as $item): ?>
        <?php $setFields($item); ?>
        <article class="news-item">
            <h3>
                <?php if (!empty($fullLink)): ?>
                    <a href="<?php echo (string) $fullLink; ?>">
                        <?php echo (string) ($f_title ?? ''); ?>
                    </a>
                <?php else: ?>
                    <?php echo (string) ($f_title ?? ''); ?>
                <?php endif; ?>
            </h3>
            <p><?php echo (string) ($f_text ?? ''); ?></p>
        </article>
    <?php endforeach; ?>
</div>
```

## Пример компонента `news`: `single.php`

```php
<?php
/** @var array|null $object */
if ($object === null) {
    return;
}
$title = (string) ($f_title ?? '');
$text = (string) ($f_text ?? '');
?>
<article class="news-single">
    <h1><?php echo $title; ?></h1>
    <div class="content"><?php echo $text; ?></div>
</article>
```

## Пример компонента `news`: `system.php` (минимальный)

```php
<?php
// Доступны переменные: $section, $site, $infoblock, $component, $isSingle
// Пример системных настроек:
// $query_order = 'a.created_at DESC';
$query_order = 'a.created_at DESC';
```

Подробный контракт `system.php` — в `docs/50-system-php-contract.md`.

## Пример пагинации через `browse_messages()`

```php
<?php
/** @var array $cc_env */
echo browse_messages($cc_env, 7);
```

### Кастомные шаблоны для пагинации

```php
<?php
/** @var array $cc_env */
echo browse_messages($cc_env, 7, [
    'prefix' => '<nav class="pagination">',
    'suffix' => '</nav>',
    'active' => '<span class="current">%PAGE</span>',
    'unactive' => '<a href="%URL">%PAGE</a>',
    'divider' => ' ',
    'first' => '<a href="%URL">«</a>',
    'last' => '<a href="%URL">»</a>',
    'prev' => '<a href="%URL">‹</a>',
    'next' => '<a href="%URL">›</a>',
    'ellipsis' => '...',
]);
```

## Хелпер `resize_image()`
Функция `resize_image()` из `app/shared/core/functions.php` уменьшает изображение до заданных габаритов.
Если исходник меньше лимитов, она возвращает исходный путь без изменений.

Пример:

```php
<?php
$publicPath = __DIR__ . '/uploads/photo.jpg';
$publicUrl = '/uploads/photo.jpg';
resize_image($publicPath, 1200, 1200);
echo '<img src="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" alt="">';
```

## Дополнительная точка расширения: `actions.php`
Если существует файл `templates/component/<keyword>/actions.php`, он будет выполнен
после создания объекта в админке. Контекст: `$message` (ID объекта), `$object` (запись объекта).

## Хелпер `objects_list()`
Функция `objects_list()` из `app/public/helpers.php` рендерит шаблоны компонента вручную.
Минимальные входные параметры — `infoblock_id` или `component_id`.
Нормализация записей объектов выполняется через `ObjectRepo::normalizeItems()` — это общий маппер, который используется и в `Renderer`.

Пример:

```php
<?php
$htmlItems = objects_list([
    'infoblock_id' => 5,
    'template' => 'default',
    'limit' => 3,
    'query_order' => 'a.id DESC',
]);
foreach ($htmlItems as $html) {
    echo $html;
}
```

## Безопасность и экранирование
- Всегда экранируйте пользовательский контент: `htmlspecialchars()`.
- Для многострочного текста используйте `nl2br(htmlspecialchars(...))`.
- Не включайте файлы динамически — путь шаблона проверяется через `realpath` и regex.
