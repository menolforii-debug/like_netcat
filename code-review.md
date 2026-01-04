Ниже — отчёт по обзору. Критические ошибки вынесены в начало, далее строго по требуемой структуре. Все выводы основаны на просмотре файлов и команд (см. ссылки).
Критические ошибки (сразу исправлять после согласования)

    Публичный SQLite‑браузер с фиксированным паролем.

        Где: public_html/sqlite.php (пароль 111, Auth не подключён, файл лежит в публичной директории).

        Почему критично: при доступе к /sqlite.php на боевом домене возможно чтение/изменение БД, обход админки и утечка данных.

        Риск: высокий.

        Источник: public_html/sqlite.php.

1. Краткое описание проекта

    Тип системы: лёгкая CMS с Codex‑архитектурой в духе NetCat, с отдельной админ‑панелью и рендерингом через PHP‑шаблоны. Вся публичная часть находится в public_html, админка — отдельный entrypoint public_html/admin.php. (Источник: README.ru.md, ARCHITECTURE.ru.md, public_html/index.php, public_html/admin.php.)

    Основные сущности:

        Сайт/Раздел (таблица sections, у сайта parent_id = NULL, у разделов — ссылка на site_id),

        Компонент (таблица components, описывает поля/схему),

        Инфоблок (экземпляр компонента в конкретном разделе),

        Объект (контент, JSON‑данные внутри инфоблока),

        Пользователь и Админ‑лог. (Источник: migrations/001_init.sql, репозитории в app/domain/*.)

    Как работают пользователь и администратор:

        Пользователь: попадает через public_html/index.php, CMS определяет сайт по домену, затем раздел по english_name, рендерит шаблон раздела и инфоблоки. (Источник: public_html/index.php, app/render/Renderer.php.)

        Администратор: работает через public_html/admin.php, который запускает роутер AdminRouter и обработчики app/admin/actions/*. (Источник: public_html/admin.php, app/admin/AdminRouter.php.)

2. Структура проекта

    Ключевые директории:

        public_html/ — публичный вход, ассеты, admin.php, index.php, сторонний sqlite.php. (Источник: команды ls, ls public_html.)

        app/ — ядро, домен, рендер, админка, UI/layout. (Источник: ls app.)

        templates/ — шаблоны компонентов/разделов. (Источник: find templates -maxdepth 2 -type f -print.)

        migrations/ — SQL‑миграции. (Источник: ls migrations.)

        var/ — хранение SQLite (var/app.sqlite создаётся при старте). (Источник: app/bootstrap.php.)

        bin/ — CLI утилита bin/cms. (Источник: bin/cms.)

    Роль основных файлов:

        public_html/index.php — frontend entrypoint.

        public_html/admin.php — admin entrypoint.

        app/bootstrap.php — инициализация, подключение классов, миграции, создание default‑site.

        app/render/Renderer.php — основной фронтенд‑рендеринг.

        app/ui/Layout.php + app/ui/layouts/*.php — layout‑обёртки.

        app/ui/AdminLayout.php — общий каркас админки.

        app/admin/actions/* — экраны и действия админки.

        app/domain/* — репозитории для работы с БД.

        migrations/*.sql — схема.
        (Источники: public_html/index.php, public_html/admin.php, app/bootstrap.php, app/render/Renderer.php, app/ui/Layout.php, app/ui/AdminLayout.php, app/admin/actions/*, app/domain/*, migrations/*.sql.)

3. Поток выполнения
3.1. Frontend: от запроса до рендера страницы

    Запрос → public_html/index.php.

    bootstrap.php: подключение классов, Auth::start(), создание var/, подключение SQLite, миграции, запуск событий, ensureDefaultSite().

    Определение сайта по домену (SectionRepo::findSiteByHost).

    Проверка флага site_enabled.

    Парсинг URL → Renderer::renderSitePath($site, $path).

    Renderer:

        ищет раздел по english_name (разбор сегментов URL),

        если не найден — берёт системный 404 раздел,

        собирает путь, детей, инфоблоки, объекты, применяет SEO,

        выбирает layout и отрисовывает.
        (Источники: public_html/index.php, app/bootstrap.php, app/domain/SectionRepo.php, app/render/Renderer.php, app/core/Seo.php, app/ui/Layout.php.)

3.2. Backend (admin): от клика до сохранения данных

    public_html/admin.php → bootstrap.php → AdminRouter::run().

    AdminRouter определяет action, проверяет логин, CSRF (для POST), права, подключает нужный файл app/admin/actions/{get|post}/{action}.php.

    В action‑файле выполняется логика: работа с репозиториями (SectionRepo, ComponentRepo, InfoblockRepo, ObjectRepo, UserRepo), затем редирект.

    UI: AdminLayout + конкретный action‑экран.
    (Источники: public_html/admin.php, app/bootstrap.php, app/admin/AdminRouter.php, app/admin/actions/*, app/ui/AdminLayout.php.)

4. Найденные проблемы и несостыковки

(каждая проблема: описание → где → почему → риск)

    Публичный SQLite‑браузер с паролем по умолчанию.

        Где: public_html/sqlite.php.

        Почему: не интегрирован с Auth, пароль жёстко задан (111), файл лежит в public_html.

        Риск: высокий.

        Источник: public_html/sqlite.php.

    Несогласованность URL для корневого index.

        Где: Renderer::buildSectionPath() пропускает index на корне, а buildSectionPathFromId() (админ) — нет.

        Почему: админ‑ссылки и превью могут указывать на /index/..., хотя канонический URL — /.... Возможны дубли страниц и SEO‑проблемы.

        Риск: средний.

        Источник: app/render/Renderer.php, app/admin/AdminHelpers.php, app/ui/SectionTree.php, app/admin/actions/get/dashboard.php.

    Права инфоблоков в модели есть, но UI/Frontend почти не учитывают.

        Где: Permission смотрит infoblock.extra.permissions, но админка не даёт настраивать эти права, а фронтенд рендерит все инфоблоки без проверки.

        Почему: модель прав не задействована в интерфейсе; возникает риск несогласованности ожиданий по доступу.

        Риск: средний.

        Источник: app/core/Permission.php, app/admin/actions/get/infoblock_new.php, app/admin/actions/get/dashboard.php, app/render/Renderer.php.

    Валидация полей компонентов неполная/рассинхронизирована.

        Где: FieldValidator существует, но не используется в формах объектов/компонентов.

        Почему: данные сохраняются без нормальной типизации/валидации типов; UI проверяет только required.

        Риск: средний.

        Источник: app/core/FieldValidator.php, app/admin/actions/post/object_create.php, app/admin/actions/post/object_update.php, app/admin/AdminHelpers.php.

    view_template инфоблока можно сохранить некорректным.

        Где: infoblock_update принимает значение без проверки на существование в компоненте.

        Почему: на фронте Renderer возвращает пустую строку, если файл шаблона не найден — визуально «пропадает» блок.

        Риск: средний.

        Источник: app/admin/actions/post/infoblock_update.php, app/render/Renderer.php.

    Короткие PHP‑теги в сгенерированных шаблонах.

        Где: templates/0780780870/w1.php, templates/news1/list.php используют <?/<? echo ... ?>.

        Почему: если short_open_tag отключён (часто на проде), шаблон не выполнится.

        Риск: средний.

        Источник: templates/0780780870/w1.php, templates/news1/list.php.

    Дублирование источника правды для view‑шаблонов.

        Где: component_views таблица + components.views_json.

        Почему: список видов дублируется; если изменения идут «не через админку», можно получить рассинхрон.

        Риск: низкий/средний.

        Источник: migrations/004_component_views.sql, app/domain/ComponentViewRepo.php, app/admin/AdminHelpers.php, app/domain/ComponentRepo.php.

Временно/костыльно (отдельно)

    public_html/sqlite.php выглядит как временный dev‑инструмент, не интегрирован с системой ролей. (Источник: public_html/sqlite.php.)

    Сгенерированные шаблоны с маркером /** GENERATED FILE */ явно предполагают «быстрые правки» и могут ломаться из‑за short tags. (Источник: templates/45345/list.php, templates/0780780870/w1.php, templates/news1/list.php.)

5. Технический долг

    В первую очередь:

        Убрать/закрыть public_html/sqlite.php или строго завязать на Auth::isAdmin() и убрать дефолтный пароль. (Источник: public_html/sqlite.php.)

        Привести URL‑логики (index) к единой канонической модели в админке/фронте. (Источник: app/render/Renderer.php, app/admin/AdminHelpers.php.)

        Привести шаблоны к единообразному <?php без кортких тегов. (Источник: templates/*.)

    Можно отложить:

        Использование FieldValidator для строгой типизации данных объектов. (Источник: app/core/FieldValidator.php.)

        Синхронизацию component_views ↔ views_json через сервисную утилиту (сейчас синхронизируется в админке). (Источник: app/admin/AdminHelpers.php, app/domain/ComponentViewRepo.php.)

    Опасно трогать без тестов:

        Renderer и роутинг по english_name + special index/404: это влияет на URL‑схему и 404‑поведение. (Источник: app/render/Renderer.php, app/bootstrap.php.)

        Админский роутер и схема прав (если поменять — можно сломать доступы). (Источник: app/admin/AdminRouter.php, app/core/Auth.php, app/core/Permission.php.)

6. Архитектурная карта (словами)

    Центр системы: bootstrap.php + Renderer + доменные репозитории.

    Ключевые модули:

        app/core/* — инфраструктура (DB, Auth, Permission, SEO, EventBus).

        app/domain/* — доступ к данным (sections/components/infoblocks/objects/users).

        app/render/Renderer.php — связывает разделы, инфоблоки, объекты и шаблоны.

        app/ui/* — layout‑слой frontend/admin.

        app/admin/* — админский роутер и экраны.

    Зависимости:

        UI/рендер зависит от доменных репозиториев и Auth/Seo.

        Админка зависит от AdminRouter + AdminHelpers + репозиториев.

        События (EventBus) подключены, но пока пустые слушатели.
        (Источники: app/bootstrap.php, app/render/Renderer.php, app/core/*, app/domain/*, app/admin/*, app/ui/*, app/events.php.)

7. Рекомендации для дальнейшей разработки

    Безопасное добавление фич:

        Новые сущности лучше отражать в app/domain/* (репозитории) и создавать миграции.

        Не добавлять ORM — архитектура предполагает чистый SQL. (Источник: CODEX.ru.md, app/domain/*.)

    Где вводить новые сущности/данные:

        Схемные изменения: migrations/*.sql, затем репозиторий.

        Для контента: через компоненты → инфоблоки → объекты (JSON‑данные). (Источник: migrations/001_init.sql, app/domain/*.)

    Паттерны проекта:

        Entry‑points — index.php/admin.php, логика — в репозиториях и action‑файлах, шаблоны — только отображение. (Источник: ARCHITECTURE.ru.md, app/render/Renderer.php, app/admin/actions/*.)

        Сохранять english_name как единственный источник URL. (Источник: ARCHITECTURE.ru.md, app/render/Renderer.php.)

8. Шаблоны компонентов: карта доступного API

Где шаблоны:

    templates/{component}/{view}.php — для инфоблоков/объектов.

    templates/section/default.php — для раздела.
    (Источник: app/render/Renderer.php, templates/*.)

Переменные, доступные в templates/{component}/{view}.php:

    $section — массив раздела.

    $site — массив сайта.

    $infoblock — массив инфоблока.

    $component — массив компонента (ключ, name, fields_json, views_json).

    $items — массив объектов (каждый: id, data, status, created_at, updated_at, controls).

    $objects — алиас $items.

    $object — объект, если включён одиночный режим.

    $isSingle — флаг одиночного режима.

    $core — пустой массив (зарезервирован).

    $editMode — всегда false.
    (Источник: app/render/Renderer.php.)

Переменные в templates/section/default.php:

    $section — текущий раздел.

    $items — дочерние разделы, дополнены полем path.

    $core['infoblocks_html'] — HTML‑вставка всех инфоблоков.
    (Источник: app/render/Renderer.php, templates/section/default.php.)

Откуда берутся переменные и этап:

    Формируются в Renderer::renderSitePath() и renderInfoblock(), затем шаблон подключается через require. (Источник: app/render/Renderer.php.)

Как передаются данные:

    Данные объектов берутся из objects.data_json и декодируются перед передачей в шаблон (decodeItems).

    Для SEO передаются title, meta в layout‑шаблон (Layout::render).
    (Источник: app/render/Renderer.php, app/core/Seo.php, app/ui/Layout.php.)

Зависимость от layout/renderer:

    Выбор layout задаётся либо путём (home для /), либо в section.extra.layout. (Источник: app/render/Renderer.php, app/ui/Layout.php.)

Примеры безопасных точек расширения:

    Добавить новый layout: app/ui/layouts/*.php, затем выбрать его в настройках раздела. (Источник: app/ui/Layout.php, app/admin/actions/post/section_update.php.)

    Добавить новый вид шаблона компонента через админку (формирует файл в templates/{component}/{view}.php). (Источник: app/admin/actions/post/component_view_create.php, app/admin/AdminHelpers.php.)

9. Админка: список файлов для редизайна

(файл → роль → что будет при изменении → зависимости)

Layout / header/footer

    app/ui/AdminLayout.php — общий каркас админки, top‑nav, подключение CSS/JS; изменение влияет на весь UI. Зависит от public_html/assets/*.

    app/ui/SectionTree.php — рендер бокового дерева разделов. Изменение влияет на левую навигацию.

Меню/навигация

    app/ui/AdminLayout.php — массив пунктов верхнего меню (dashboard, logs, users, components, sql).

    app/ui/SectionTree.php — ссылки на разделы и кнопки действий.

Роутинг и actions

    public_html/admin.php — entrypoint.

    app/admin/AdminRouter.php — маппинг action → файлы actions/get|post.

Ассеты (CSS/JS)

    public_html/assets/admin.css — кастомный CSS админки.

    public_html/assets/admin.js — улучшения UX (в т.ч. табы/редактор).

    public_html/assets/sow/* — сторонний UI‑набор.

Экраны (actions/get/*) — что отвечает за UI:

    app/admin/actions/get/dashboard.php — основной экран (сайты/разделы, табы раздела, контент).

    app/admin/actions/get/login.php — экран логина.

    app/admin/actions/get/components.php — список и редактирование компонентов + видов.

    app/admin/actions/get/component_new.php — форма создания компонента.

    app/admin/actions/get/components_list.php / components_create.php — вспомогательные экраны/формы для компонента (см. файлы).

    app/admin/actions/get/infoblock_new.php — создание инфоблока.

    app/admin/actions/get/object_form.php — форма объекта.

    app/admin/actions/get/section_new.php / site_new.php — создание раздела/сайта.

    app/admin/actions/get/users_list.php / users_create.php — пользователи.

    app/admin/actions/get/logs.php — лог‑экран.

    app/admin/actions/get/sql.php — SQL‑панель.
    (Источники: ls app/admin/actions/get, соответствующие файлы в app/admin/actions/get/*.)
