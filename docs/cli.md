# CLI утилита

В проекте есть вспомогательная CLI‑утилита `bin/cms`. Она предназначена для разработки/администрирования
и не является обязательной частью работы сайта.

## Запуск

```bash
php bin/cms <command>
```

## Команды

- `migrate` — выполнить миграции (фактически подтверждение, миграции запускаются в `bootstrap.php`).
- `sections:list` — список разделов.
- `sections:add --parent-id=1 --english-name=news --title="Новости" [--sort=0]` — добавить раздел.
- `sections:add --title="Новый сайт"` — создать сайт (корневой раздел).
- `users:list` — список пользователей.
- `users:add --login=admin --pass=admin [--role=admin]` — создать пользователя.
- `users:passwd --id=1 --pass=newpass` — сменить пароль.
- `users:role --id=1 --role=editor` — изменить роль пользователя.
- `components:list` — список компонентов.
