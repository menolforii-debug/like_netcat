# 40. Компоненты и представления

## Зачем это существует

Компоненты описывают структуру данных, а представления (`component_views`) — как их выводить.

## Как это работает (кратко)

- Компонент задаёт поля (`fields_json`).
- Представление хранит шаблоны list/single и системный код (`system_tpl`).
- Инфоблок выбирает, какой view использовать.
- Шаблоны записываются в `templates/component/<component_key>/<view>.php`.

## Примеры

### Пример: минимальный list‑шаблон

```php
<?php foreach ($objects as $obj): ?>
  <?php $setFields($obj); ?>
  <h3><?= htmlspecialchars((string) $f_title, ENT_QUOTES, 'UTF-8') ?></h3>
<?php endforeach; ?>
```

### Типовой сценарий

- Создать компонент `news`.
- Создать view `list` и `single`.
- В инфоблоке выбрать view `list`.

### Где читать подробнее

- Шаблоны компонента и системные настройки: `docs/50-component-templates.md`.
- Макеты страниц: `docs/55-layouts.md`.
