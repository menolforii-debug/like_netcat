# Инициализация и установка

О чём документ: как стартует проект, где создаётся БД, когда накатывается схема и сиды.
Когда читать: после архитектуры рантаймов и перед описанием запроса.
Кому полезно: разработчикам и инженерам эксплуатации, которые разворачивают проект.
Связанные документы: `docs/10-runtime-architecture.md`, `docs/90-database.md`.

## Как стартует проект
1. `public_html/index.php` включает `app/public/bootstrap.php`.
2. `app/public/bootstrap.php`:
   - задаёт `APP_RUNTIME = public` и включает проверку рантайма;
   - определяет путь к БД `var/app.sqlite` и флаг `isDbNew` (файл отсутствует);
   - подключает `app/shared/bootstrap.php` и зависимости публичного рантайма;
   - стартует сессию (`Auth::start()`);
   - если БД новая, выполняет сидинг (см. ниже).

3. `app/shared/bootstrap.php`:
   - создаёт папку `var/` при необходимости;
   - подключает core и доменные классы;
   - соединяется с `SQLite` (`DB::connect()`);
   - если БД новая **или** нет таблицы `sections`, загружает `app/shared/schema.sql`.

## Где создаётся `SQLite`
- Файл базы данных: `var/app.sqlite`.
- Папка `var/` создаётся автоматически, если отсутствует.

## Когда накатывается schema.sql
`schema.sql` применяется при одном из условий:
- файл БД отсутствует;
- таблица `sections` не существует.

Это происходит в `app/shared/bootstrap.php`.

## Когда выполняются дефолты
Сиды выполняются **только при первом запуске**, когда файла БД ещё не было:
- `ensureDefaultLayoutTemplates()` — создаёт `templates/layouts/default.php` и `templates/layouts/default.nav.php` на основе шаблонов `templates/layouts/default/*.tpl.php`.
- `ensureDefaultSite()` — создаёт сайт и системные разделы `index` и `404`.
- `ensureDefaultVisualFields()` — заполняет `visual_fields` дефолтными полями.

Логика сидов находится в `app/public/bootstrap.php` и привязана к условию «файл БД отсутствовал».

## Права на запись
Для корректной работы нужны права на запись:
- `var/` — файл БД и бэкапы.
- `templates/layouts/` — создание/редактирование макетов в админке.
- `templates/snippets/` — создание/редактирование врезок в админке.
- `templates/component/` — редактирование шаблонов компонентов в админке.
- `public_html/files/` — загрузки файлов (объекты, визуальные поля).

## Быстрый запуск локально
Пример для локального `PHP`‑сервера:

```bash
php -S 127.0.0.1:8080 -t public_html
```

После запуска:
- фронтенд: `http://127.0.0.1:8080/`
- админка: `http://127.0.0.1:8080/admin.php`

## Командная утилита
В `bin/cms` есть простая утилита для разработчиков. Примеры:

```bash
php bin/cms sections:list
php bin/cms sections:add --title="Новый сайт"
php bin/cms users:add --login=admin --pass=admin --role=admin
php bin/cms components:list
php bin/cms backup:create
php bin/cms backup:restore --in=var/backups/cms-backup-YYYYmmdd-HHMMSS.tar.gz --force=1
```

Команды `backup:create` и `backup:restore` реализуют технический экспорт/импорт «Вариант 1»:
- `backup:create` собирает архив `tar.gz` из рабочих данных: `var/app.sqlite`, `templates/layouts`, `templates/component`, `templates/snippets`, `public_html/files`;
- `backup:restore` перед распаковкой очищает целевые пути бэкапа и затем восстанавливает архив в корень проекта (требует явный флаг `--force=1`).

Утилита подключает `app/shared/bootstrap.php` и работает напрямую с `SQLite`.
