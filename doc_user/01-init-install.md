# Инициализация и установка

Кратко:
- публичная точка входа: `public_html/index.php`;
- админка: `public_html/admin.php`;
- база данных: `var/app.sqlite`.

## Минимальные требования
- PHP 8+
- права на запись: `var/`, `templates/`, `public_html/files/`.

## Быстрый запуск
```bash
php -S 127.0.0.1:8080 -t public_html
```

После запуска:
- сайт: `http://127.0.0.1:8080/`
- админка: `http://127.0.0.1:8080/admin.php`
