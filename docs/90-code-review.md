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
