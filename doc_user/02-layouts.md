# Макеты дизайна

Макеты располагаются в `templates/layouts/`:
- `templates/layouts/<layout>.php`
- `templates/layouts/<layout>.nav.php` (опционально)

## Дефолтные шаблоны
Канонические дефолты находятся в:
- `app/admin/resources/default_layouts_templates/default.php`
- `app/admin/resources/default_layouts_templates/default.nav.php`

## Что доступно в макете
`Layout::render()` передаёт в шаблон макета:
- `$ctx` — общий контекст;
- `$body` — callback рендера контента;
- `$title`, `$meta`, `$site` — подготовленные значения из `$ctx`.

Обычно в `$ctx` также доступны:
- `$ctx['section']`, `$ctx['h1']`, `$ctx['visual']`, `$ctx['children']`.

## Какие функции/методы использовать в макете
Основные методы:
- `Layout::renderDocumentStart(...)`
- `Layout::renderDocumentEnd()`
- `Layout::renderCss()` / `Layout::renderJs()`
- `Layout::renderNavbar(...)`
- `Layout::renderSectionHeader(...)`
- `Layout::getMainMenuItems($ctx, $maxDepth)`

Также доступны общие helper-функции публичного рантайма, например:
- `insert_snip(...)` — вставка врезок;
- `browse_messages(...)` — пагинация;
- `resolveTemplatePath(...)`.

## Визуальные поля в макете
Визуальные поля для текущего раздела передаются в `$ctx['visual']`.

Назначение:
- хранение настраиваемых параметров оформления/контента раздела;
- использование прямо в шаблоне макета через безопасный доступ (`isset`, `??`).

## Управление в админке
Раздел `Макеты дизайна`:
- создание и редактирование шаблона макета;
- редактирование nav-файла макета;
- удаление пользовательских макетов.
