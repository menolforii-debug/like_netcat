# 90. Обзор кода проекта (кратко и по делу)

## Цель документа

Краткий обзор архитектуры и ключевых точек проекта, чтобы быстро войти в контекст дальнейшей разработки.

## Архитектура и поток запросов

1. Вход: `public_html/index.php` подключает `app/bootstrap.php` и запускает рендеринг через `Renderer::renderSitePath()`.
2. `Renderer` выбирает сайт по `HTTP_HOST`, определяет раздел по пути, загружает инфоблоки, выполняет системную выборку объектов и рендерит шаблоны компонентов.
3. Макет страницы определяется по `sections.extra_json.layout` (раздел), затем по `site` и с откатом на `default`.

## Слой данных (SQLite)

Схема хранится в `app/schema.sql`. Основные сущности: `sections`, `components`, `infoblocks`, `objects`, `users`, `admin_log`, `visual_fields`, `sql_history`, `snippet`. Данные читаются через репозитории в `app/domain/*Repo.php`.

## Шаблоны и макеты

- Компоненты: `templates/component/<component_key>/<view>/list.php`, `single.php`, `system.php`.
- Системные настройки выборки задаются в `system.php` через `ignore_*` и `query_*`.
- Макеты: `templates/layouts/<layout>.php` и опционально `<layout>.nav.php`.
- Дефолтный view при пустом `views_json` — `default`.

## Админка

- Точка входа: `public_html/admin.php`, маршрутизация через `AdminRouter`.
- CSRF‑проверка для POST‑запросов.
- Роли: `admin` и `editor` (ограничения определены в `AdminRouter` и `Permission`).

## Точки расширения

- Шаблоны компонентов (`templates/component/...`).
- Макеты и nav‑файлы (`templates/layouts/...`).
- Врезки (`templates/snippets/*.php`) через `insert_snip()`.
- События домена в `app/events.php`.

## Рекомендации для дальнейшей работы

1. Расширить примеры `system.php` (фильтры, сортировки, выборка через `ignore_sub`) и зафиксировать лучшие практики в документации.
2. Сформировать рабочий гайд по контент‑моделям (типы полей, валидация, рекомендации по заполнению) на базе `FieldValidator` и форм админки.
3. Добавить чек‑лист по безопасной работе с шаблонами (особенно при выводе `message_select` в публичной части).

## Проверка соответствия заявлений по проекту

### 1) Default view

- Единый дефолтный view — `default` (админка и публичный рендер используют один и тот же fallback).
- При пустом `views_json` или пустом имени view система ожидает папку `templates/component/<component_key>/default/`.

### 2) Безопасность путей в публичном Renderer

- В публичном рендере добавлена проверка ключей и представлений через `preg_match('/^[A-Za-z0-9_-]+$/')`.
- Также добавлен `realpath`‑guard относительно `templates/component`, чтобы исключить выход за пределы каталога шаблонов.

## Дублирующиеся механизмы и функции

### Меню

- `Layout::getMainMenuItems()` использует `Nav` как единый источник данных меню и строит дерево на основе `core()->nav()` вместо прямого обращения к `SectionRepo`.【F:app/ui/Layout.php†L196-L310】【F:app/nav/Nav.php†L40-L114】

### Пагинация

- Для пагинации используется `Layout::renderPagination()` и `Layout::getPaginationItems()` как единый механизм.【F:app/ui/Layout.php†L87-L194】

### Парсинг шаблонных тегов

- Используется единая реализация `Utils::stripSystemTemplateTags()` без дублирующих функций в админке.【F:app/core/Utils.php†L38-L48】【F:app/admin/AdminHelpers.php†L620-L673】

### decodeExtra и построение пути раздела

- Для декодирования `extra_json` используется `Utils::decodeExtra()` без промежуточных обёрток в `AdminHelpers`.【F:app/core/Utils.php†L24-L35】
- Построение пути раздела выполняется через `SectionRepo::buildPath()` напрямую.【F:app/domain/SectionRepo.php†L356-L382】

## Предложения по удалению дублей

### 1) Меню: единый источник (Nav)

Меню унифицировано через `Nav`: `Layout::getMainMenuItems()` использует `core()->nav()` и больше не строит дерево через `SectionRepo`. Это устраняет дублирование источников данных для меню.【F:app/ui/Layout.php†L196-L310】【F:app/nav/Nav.php†L40-L114】

### 2) Пагинация: единый механизм

Постраничная навигация унифицирована через `Layout::renderPagination()` и `Layout::getPaginationItems()` (без альтернативных функций).【F:app/ui/Layout.php†L87-L194】

### 3) `stripSystemTemplateTags`: единая реализация

Единственным источником остаётся `Utils::stripSystemTemplateTags()`, локальная функция в `AdminHelpers` удалена, вызовы переведены на `Utils` напрямую.【F:app/core/Utils.php†L38-L48】【F:app/admin/AdminHelpers.php†L620-L673】

### 4) `decodeExtra` и `buildSectionPathFromId`: обёртки удалены

Вызовы переведены на `Utils::decodeExtra()` и `SectionRepo::buildPath()` напрямую, обёртки в `AdminHelpers` больше не используются.【F:app/core/Utils.php†L24-L35】【F:app/domain/SectionRepo.php†L356-L382】

## Методы Layout, доступные из макета дизайна, и их аналоги

Ниже перечислены методы `Layout`, которые обычно используются прямо в макетах (`templates/layouts/*.php`), и указаны их возможные аналоги/дубли.

### 1) `Layout::renderDocumentStart()` / `Layout::renderDocumentEnd()`

- **Назначение:** базовый HTML‑каркас (doctype, `<head>`, `<body>`, meta и т.п.).【F:app/ui/Layout.php†L5-L37】
- **Аналоги:** прямой HTML в самом макете. Это не дублирование в коде, а альтернативный способ верстки.

### 2) `Layout::renderNavbar()`

- **Назначение:** вывод стандартной шапки‑навигации с брендом и ссылками.【F:app/ui/Layout.php†L39-L59】
- **Аналоги/дубли:** ручная верстка навигации в макете; данных меню можно добиться через `core()->nav()` или `Layout::getMainMenuItems()`, но это не дубликат самой разметки, а альтернативный источник данных.

### 3) `Layout::renderSectionHeader()`

- **Назначение:** заголовок раздела + список дочерних разделов.【F:app/ui/Layout.php†L61-L84】
- **Аналоги/дубли:** ручная верстка в макете и получение данных через `core()->nav()` или `$ctx['children']`. Прямого дубля в коде нет, это лишь альтернативный способ рендера.

### 4) `Layout::renderPagination()` / `Layout::getPaginationItems()`

- **Назначение:** стандартная пагинация и набор элементов для кастомной разметки.【F:app/ui/Layout.php†L87-L194】
- **Аналоги/дубли:** явного дубля в коде нет; это единственный встроенный механизм пагинации (после удаления `browse_messages()`).

### 5) `Layout::getMainMenuItems()`

- **Назначение:** получение дерева меню для макета дизайна.【F:app/ui/Layout.php†L196-L308】
- **Аналоги/дубли:** прямые вызовы `core()->nav()->get_by_level()` / `get_sub()` в макете, если нужна своя структура данных. Это альтернативный источник, а не дублирующая функция.

### 6) `Layout::renderCss()` / `Layout::renderJs()`

- **Назначение:** подключение CSS/JS из набора ассетов проекта.【F:app/ui/Layout.php†L342-L395】
- **Аналоги/дубли:** ручные `<link>`/`<script>` в макете. Явных кодовых дублей нет.

## План удаления методов Layout и риски при чистом старте

Ниже — что потребуется сделать, если целиться в удаление перечисленных методов, и сломает ли это проект при старте «с нуля».

### Методы, которые реально задействованы при чистом старте

- `Layout::renderCss()` / `Layout::renderJs()` используются в `templates/layouts/default/layout.tpl.php`, который копируется в `templates/layouts/default.php` при первом запуске (bootstrap). Если удалить эти методы без замены, дефолтный макет будет обращаться к несуществующим функциям и сломается стартовый рендер.【F:templates/layouts/default/layout.tpl.php†L1-L44】【F:app/bootstrap.php†L319-L347】
- `Layout::renderPagination()` используется в админке на странице `dashboard` — удаление без замены сломает пагинацию в админке.【F:app/admin/actions/get/dashboard.php†L556-L571】

Итог: при старте с «нуля» удалять эти методы без замен **нельзя** — проект сломается.

### Методы, которые не критичны при старте с нуля

- `Layout::renderDocumentStart()` / `Layout::renderDocumentEnd()` / `Layout::renderNavbar()` / `Layout::renderSectionHeader()` используются только в служебных макетах внутри `app/ui/layouts/*`, которые не участвуют в публичном рендере по умолчанию. Их можно убрать, если параллельно удалить или заменить эти внутренние шаблоны (чтобы не осталось ссылок).【F:app/ui/layouts/default.php†L1-L20】【F:app/ui/layouts/home.php†L1-L33】
- `Layout::getMainMenuItems()` не обязателен, если в макетах используется `core()->nav()` напрямую. Однако его удаление безопасно только после обновления всех макетов, которые могут на него ссылаться (включая будущие кастомные шаблоны).【F:app/ui/Layout.php†L196-L308】

### Что делать, чтобы удалить методы без поломок

1. Для `renderCss/renderJs`: заменить их вызовы в `templates/layouts/default/layout.tpl.php` на прямые `<link>`/`<script>` или инлайн‑подключение ассетов, затем пересоздать `templates/layouts/default.php` (или обновить его вручную).【F:templates/layouts/default/layout.tpl.php†L1-L44】【F:app/bootstrap.php†L319-L347】
2. Для `renderPagination/getPaginationItems`: заменить использование в админке на локальную разметку пагинации или вынести альтернативный helper именно для админки. Иначе сломается список разделов/объектов в `dashboard`.【F:app/admin/actions/get/dashboard.php†L556-L571】
3. Для `renderDocumentStart/renderDocumentEnd/renderNavbar/renderSectionHeader`: убрать или переписать файлы `app/ui/layouts/*`, чтобы в них не было вызовов этих методов, затем можно удалить методы безопасно.【F:app/ui/layouts/default.php†L1-L20】【F:app/ui/layouts/home.php†L1-L33】
4. Для `getMainMenuItems`: сначала проверить/обновить все пользовательские макеты, затем удалить метод.
