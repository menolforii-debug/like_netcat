# 80. Карта проекта и рекомендации

## Зачем это существует

Короткая, практичная карта кода (архитектура + точки расширения), чтобы быстро ориентироваться
в шаблонах, системных настройках, меню и CRUD. В конце — список задач/рекомендаций для развития.

---

## 1) Архитектура: краткая схема

- `public_html/index.php`
  → `app/public/bootstrap.php`
  → `Renderer::renderSitePath()`
  → `SectionRepo::findByPath()`
  → инфоблоки/компоненты/objects
  → `templates/component/...`
  → `Layout::render()`
  → `templates/layouts/...`
- `public_html/admin.php`
  → `AdminRouter::run()`
  → admin actions (`app/admin/actions/*`)

---

## 1.1) Границы runtime (public/admin/shared)

- **Public runtime** стартует из `app/public/bootstrap.php` и подключает только `app/shared/*` + `app/public/*`.
- **Admin runtime** стартует из `app/admin/bootstrap.php` и подключает только `app/shared/*` + `app/admin/*`.
- **Shared** — общий код (core, domain, ui), который можно безопасно использовать в обоих runtime.
- Напрямую подключать public-файлы из admin и admin-файлы из public нельзя.
- Если логика нужна обеим сторонам — переносим её в `app/shared/*`, не меняя поведение.

---

## 2) Главные файлы и роли

| Файл | Роль | Когда трогать |
| --- | --- | --- |
| `app/shared/bootstrap.php` | Базовая инициализация (DB, Core, repo, events). | Общие зависимости/инициализация. |
| `app/public/bootstrap.php` | Публичные helpers и default-данные. | Публичные helper-функции, дефолты. |
| `app/public/render/Renderer.php` | Публичный рендер и системные выборки. | Менять системные запросы, пагинацию, контекст шаблонов. |
| `app/shared/nav/Nav.php` | Навигация `core()->nav()`. | Логика меню, active/current, фильтры. |
| `app/shared/ui/Layout.php` | Макеты, nav-файлы, helpers (меню/пагинация). | Развитие layout-ов и меню. |
| `app/shared/domain/*Repo.php` | Репозитории (CRUD/выборки). | Расширение CRUD или поиск. |
| `templates/layouts/*` | Макеты и nav-файлы. | UI/верстка, меню, общие блоки. |
| `templates/component/*` | Шаблоны компонентов. | Вывод list/single. |

---

## 3) Поток запроса (публичный рендер)

1. Определение сайта по `HTTP_HOST`.
2. По `REQUEST_URI` выбирается раздел через `SectionRepo::findByPath()`.
3. Загружаются инфоблоки и их компоненты.
4. Для каждого инфоблока подмешиваются системные настройки из `system.php` и выполняется выборка объектов.
5. Рендерятся шаблоны компонента (`list.php`/`single.php`).
6. Макет (`Layout::render()`) оборачивает HTML.

---

## 4) Данные и JSON-поля

- `sections.extra_json` — настройки сайта/раздела (layout, visual_settings, menu_title, show_in_menu).
- `infoblocks.extra_json` — обёртки before/after (HTML/изображения).
- `objects.data_json` — данные объекта компонента.
- `components.fields_json` — схема полей.
- `components.views_json` — список view-шаблонов.
- `templates/component/<keyword>/<view>/system.php` — системные настройки запросов (where/join/limit/ignore_*).

---

## 5) API для шаблонов (минимум)

### Глобальные функции

```php
core()->nav()->get_by_level(0);   // меню 1 уровня
insert_snip('footer');            // вставка из templates/snippets/footer.php
objects_list(['infoblock_id' => 5, 'query_order' => 'a.id DESC']);
```

### Контекст в шаблонах компонента

- `$objects`, `$object`, `$isSingle`
- `$settings`
- `$cc_env` (пагинация)
- `$message_select` (SQL последней выборки)

---

## 6) Навигация и меню

### core()->nav()

- `get_by_level(0)` — первый уровень.
- `get_sub($id)` — дочерние.
- `get_path()` — хлебные крошки.

### Поля раздела для меню

- `extra_json.menu_title` — альтернативное имя.
- `extra_json.show_in_menu` и `show_in_menu_inherit` — скрытие/наследование.

---

## 7) Пагинация

- `per_page` — берётся из инфоблока.
- `page` — из query-string.
- `$cc_env` передаётся в шаблон и может использоваться для пагинации через `Layout::renderPagination()` или `Layout::getPaginationItems()`.

---

## 8) Точки расширения (безопасные)

- Шаблоны компонентов: `templates/component/<keyword>/<view>/list.php` и `single.php`.
- Системные настройки: `templates/component/<keyword>/<view>/system.php` (возвращает массив).
- Макеты: `templates/layouts/<layout>.php` + `<layout>.nav.php`.
- Врезки: `templates/snippets/*.php` + `insert_snip()`.

---

## 9) Риски и заметки

- `eval` не используется: системные настройки возвращаются из `system.php`.
- Глобальные переменные `f_*` могут пересекаться и вести к конфликтам.
- `message_select` может раскрывать внутренние SQL запросы (не выводить публично).

---

## 10) Список задач/рекомендаций

### Документация

1. **Отдельно описать system settings**: перечисление всех `query_*` и `ignore_*` с примерами (формат `system.php`).
2. **Расширить справочник шаблонов**: полный список переменных в шаблонах компонентов.
3. **Добавить раздел по безопасности**: где используется `eval`, правила использования.

### Навигация/меню

1. Добавить флаг `show_in_menu` в UI в админке для “скрыть из меню” (уже есть, но нужна заметная подсказка).
2. В nav-рендере предусмотреть `menu_title` по умолчанию (уже есть) и документировать.

### Системные запросы

1. Добавить примеры `system.php` для:
   - списка объектов по фильтру `status`
   - сортировки по `published_at`
   - выборки “сквозь” подразделы (`ignore_sub`)

### CRUD/данные

1. Документировать работу soft-delete (`is_deleted`) и восстановление.
2. Добавить пример “single view” (`object_id` + `preview_token`).
