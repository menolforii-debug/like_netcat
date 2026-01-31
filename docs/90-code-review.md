# 90. Обзор кода проекта (кратко и по делу)

## Цель документа

Краткий обзор архитектуры и ключевых точек проекта, чтобы быстро войти в контекст дальнейшей разработки.

## Архитектура и поток запросов

1. Вход: `public_html/index.php` подключает `app/public/bootstrap.php` и запускает рендеринг через `Renderer::renderSitePath()`.
2. `Renderer` выбирает сайт по `HTTP_HOST`, определяет раздел по пути, загружает инфоблоки, выполняет системную выборку объектов и рендерит шаблоны компонентов.
3. Макет страницы определяется по `sections.extra_json.layout` (раздел), затем по `site` и с откатом на `default`.

## Слой данных (SQLite)

Схема хранится в `app/shared/schema.sql`. Основные сущности: `sections`, `components`, `infoblocks`, `objects`, `users`, `admin_log`, `visual_fields`, `sql_history`, `snippet`. Данные читаются через репозитории в `app/shared/domain/*Repo.php`.

## Шаблоны и макеты

- Компоненты: `templates/component/<component_key>/<view>/list.php`, `single.php`, `system.php`.
- Системные настройки выборки задаются в `system.php` через `ignore_*` и `query_*`.
- `system.php` может быть пустым (без `return`), тогда настройки считаются пустыми; любые не-массивы логируются и игнорируются в публичном рендере.
- Макеты: `templates/layouts/<layout>.php` и опционально `<layout>.nav.php`.
- Дефолтный view при пустом `views_json` — `default`.

## Админка

- Точка входа: `public_html/admin.php`, маршрутизация через `AdminRouter`.
- CSRF‑проверка для POST‑запросов.
- Роли: `admin` и `editor` (ограничения определены в `AdminRouter` и `Permission`).
- Action‑файлы исполняются через единый executor в `AdminRouter` с централизованным обработчиком ошибок, а нужный контекст (репозитории, user, selectedId, tab и т.п.) передаётся внутрь файла.
- AJAX определяем только по заголовку `X-Requested-With`, параметры `ajax=1` больше не учитываются.
- Для POST‑actions (кроме `login`) ведётся минимальный audit‑лог (action + method), но запись пропускается, если action уже отправил заголовки или редирект.

## Точки расширения

- Шаблоны компонентов (`templates/component/...`).
- Макеты и nav‑файлы (`templates/layouts/...`).
- Врезки (`templates/snippets/*.php`) через `insert_snip()`.
- События домена в `app/shared/events.php`.

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

- `Layout::getMainMenuItems()` использует `Nav` как единый источник данных меню и строит дерево на основе `core()->nav()` вместо прямого обращения к `SectionRepo`.【F:app/shared/ui/Layout.php†L196-L310】【F:app/shared/nav/Nav.php†L40-L114】

### Пагинация

- Для пагинации используется `Layout::renderPagination()` и `Layout::getPaginationItems()` как единый механизм.【F:app/shared/ui/Layout.php†L87-L194】

### Парсинг шаблонных тегов

- Используется единая реализация `Utils::stripSystemTemplateTags()` без дублирующих функций в админке.【F:app/shared/core/Utils.php†L38-L48】【F:app/admin/AdminHelpers.php†L620-L673】

### decodeExtra и построение пути раздела

- Для декодирования `extra_json` используется `Utils::decodeExtra()` без промежуточных обёрток в `AdminHelpers`.【F:app/shared/core/Utils.php†L24-L35】
- Построение пути раздела выполняется через `SectionRepo::buildPath()` напрямую.【F:app/shared/domain/SectionRepo.php†L356-L382】

## Предложения по удалению дублей

### 1) Меню: единый источник (Nav)

Меню унифицировано через `Nav`: `Layout::getMainMenuItems()` использует `core()->nav()` и больше не строит дерево через `SectionRepo`. Это устраняет дублирование источников данных для меню.【F:app/shared/ui/Layout.php†L196-L310】【F:app/shared/nav/Nav.php†L40-L114】

### 2) Пагинация: единый механизм

Постраничная навигация унифицирована через `Layout::renderPagination()` и `Layout::getPaginationItems()` (без альтернативных функций).【F:app/shared/ui/Layout.php†L87-L194】

### 3) `stripSystemTemplateTags`: единая реализация

Единственным источником остаётся `Utils::stripSystemTemplateTags()`, локальная функция в `AdminHelpers` удалена, вызовы переведены на `Utils` напрямую.【F:app/shared/core/Utils.php†L38-L48】【F:app/admin/AdminHelpers.php†L620-L673】

### 4) `decodeExtra` и `buildSectionPathFromId`: обёртки удалены

Вызовы переведены на `Utils::decodeExtra()` и `SectionRepo::buildPath()` напрямую, обёртки в `AdminHelpers` больше не используются.【F:app/shared/core/Utils.php†L24-L35】【F:app/shared/domain/SectionRepo.php†L356-L382】
