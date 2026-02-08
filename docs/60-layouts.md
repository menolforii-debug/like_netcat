# Макеты (`layouts`)

О чём документ: как устроены макеты страниц и какие методы `Layout` доступны.
Когда читать: после шаблонов компонентов, перед настройкой визуального каркаса.
Кому полезно: разработчикам макетов и навигации.
Связанные документы: `docs/30-request-flow.md`, `docs/70-snippets.md`.

## Файлы макета
Макеты лежат в `templates/layouts/`:
- основной файл: `templates/layouts/<layout>.php`
- опциональный файл навигации/хелперов: `templates/layouts/<layout>.nav.php`

`Layout::render()` сначала подключает `<layout>.nav.php` (если есть), затем `<layout>.php`.

### Источники шаблона по умолчанию
Для первичной инициализации используются канонические шаблоны:
- `templates/layouts/default/default.php`
- `templates/layouts/default/default.nav.php`

При старте приложения они копируются в `templates/layouts/default.php` и
`templates/layouts/default.nav.php`, если эти файлы отсутствуют.

## Контекст `Layout::render()`
`Renderer` вызывает:

```php
Layout::render($layoutKey, $ctx, $body);
```

Внутри макета доступны:
- `$ctx` — массив контекста (`title`, `meta`, `site`, `section`, `h1`, `visual`, `children`).
- `$body` — вызываемый коллбек, который печатает `HTML` инфоблоков.

Дополнительно перед подключением макета `Layout::render()` подготавливает переменные:
- `$title` — строка из `$ctx['title']` или пустая строка;
- `$meta` — массив из `$ctx['meta']` или пустой массив;
- `$site` — массив из `$ctx['site']` или пустой массив.

В шаблоне макета эти переменные доступны напрямую без ручной подготовки.

## Полезные методы `Layout`
- `Layout::renderDocumentStart($title, $meta)` — печатает `<html>`, `<head>`, подключает `CSS`.
- `Layout::renderDocumentEnd()` — закрывает HTML и подключает `JS`.
- `Layout::renderCss()` и `Layout::renderJs()` — подключают ассеты `SOW` или `CDN` `Bootstrap`.
- `Layout::renderNavbar($brand, $links)` — простая навигационная панель.
- `Layout::renderSectionHeader($section, $children)` — заголовок раздела с дочерними ссылками.
- `Layout::getMainMenuItems($ctx, $maxDepth)` — получает меню на основе `core()->nav()`.

## Пример базового макета
`templates/layouts/default.php` (упрощённый):

```php
<?php
/** @var array $ctx */
/** @var callable $body */
Layout::renderDocumentStart((string) ($ctx['title'] ?? ''), $ctx['meta'] ?? []);
Layout::renderNavbar((string) ($ctx['site']['title'] ?? 'Сайт'), []);
?>
<main class="container py-4">
    <?php $body(); ?>
</main>
<?php Layout::renderDocumentEnd(); ?>
```

## Пример layout.nav.php с меню
`templates/layouts/default.nav.php`:

```php
<?php
/** @var array $ctx */
/** @var callable $body */

$menuItems = Layout::getMainMenuItems($ctx, 2);
```

И далее в `default.php`:

```php
<?php
/** @var array $ctx */
/** @var callable $body */
/** @var array $menuItems */
Layout::renderDocumentStart((string) ($ctx['title'] ?? ''), $ctx['meta'] ?? []);
?>
<nav class="navbar navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="/">Главная</a>
        <ul class="navbar-nav flex-row gap-3">
            <?php foreach ($menuItems as $item): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
<main class="container py-4"><?php $body(); ?></main>
<?php Layout::renderDocumentEnd(); ?>
```

## Пагинация — общая функция
`Layout` не содержит методов пагинации. Используйте `browse_messages()` из `app/shared/core/functions.php` прямо в шаблоне компонента.

Пример см. в `docs/40-templates-and-components.md` и `docs/30-request-flow.md`.
