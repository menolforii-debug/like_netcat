# Архитектура и поток выполнения

## Входные точки

- `public_html/index.php` — фронтенд.
- `public_html/admin.php` — админ‑панель.

Обе точки подключают `app/bootstrap.php`, где:

- настраивается окружение,
- создаётся `var/`,
- подключается SQLite и запускаются миграции (только для CLI и админки),
- создаются дефолтные макеты и системные разделы, если БД пустая,
- инициализируется шина событий.

## Разрешение сайта и URL

1. `SectionRepo::findSiteByHost()` ищет сайт по домену или зеркалу.
2. Настройки сайта (`site_domain`, `site_mirrors`, `site_enabled`, `site_offline_html`) читаются из `sections.extra_json`.
3. Если сайт выключен (`site_enabled = false`), возвращается HTML из `site_offline_html` со статусом 503.
4. Путь URL разбирается на сегменты и сопоставляется с `english_name` разделов.
5. Если раздел не найден, используется системный корневой раздел `404`.

## Рендеринг

Рендер выполняется в `app/render/Renderer.php`:

- Для раздела собираются дочерние разделы и инфоблоки.
- Из инфоблоков извлекаются объекты со статусом `published`.
- Для предпросмотра объекта используется `?object_id=...&preview_token=...`.
- Инфоблоки рендерятся через шаблон `templates/component/<keyword>/<view>.php`.
- Обёртка страницы задаётся макетом `templates/layouts/<layout>.php`.

### Выбор макета

- По умолчанию используется `home` для корня и `default` для остальных путей.
- `sections.extra_json.layout` и `site.extra_json.layout` переопределяют макет, если файл существует.

### SEO

`app/core/Seo.php` заполняет title/description/keywords из:

- данных объекта (`seo_title`, `seo_description`, `seo_keywords`),
- затем из `sections.extra_json`,
- затем из заголовка раздела или fallback‑значений.

## События

`app/core/EventBus.php` используется как шина событий.
События объявлены в `app/events.php`, обработчики по умолчанию пустые,
но все операции создают события (`section.created`, `object.updated` и т.д.).
