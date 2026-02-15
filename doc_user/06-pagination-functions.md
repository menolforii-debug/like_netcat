# Функции пагинации

Для пагинации используется:
- `browse_messages(array $cc_env, int $range, $user_template = false): string`

Пример:
```php
<?php echo browse_messages($cc_env, 7); ?>
```

`$cc_env` формируется рендерером и содержит текущую страницу, общее число страниц и параметры URL.
