# Врезки (`snippets`)

О чём документ: как работают врезки и как их подключать в шаблонах.
Когда читать: после шаблонов компонентов и макетов.
Кому полезно: разработчикам, выносящим повторяющиеся блоки.
Связанные документы: `docs/40-templates-and-components.md`, `docs/60-layouts.md`.

## Где лежат врезки
Врезки — это `PHP`‑файлы в каталоге:

```
templates/snippets/<name>.php
```

Имя `<name>` должно соответствовать шаблону `A-Za-z0-9_-`.

## Как подключать
Используйте функцию `insert_snip()` из `app/public/helpers.php`:

```php
<?php echo insert_snip('footer', ['year' => date('Y')]); ?>
```

## Наследование области видимости
`insert_snip()` умеет наследовать переменные из текущего шаблона:
- если второй параметр пустой, берётся `$GLOBALS['_snip_scope']`;
- `_snip_scope` устанавливается при рендеринге компонента и макета.

Это позволяет использовать переменные шаблона внутри врезки без явной передачи.

## Безопасность путей
`insert_snip()` защищает путь:
- проверяет имя на регулярное выражение;
- проверяет `realpath` и принадлежность к `templates/snippets`.

## Управление в админке
В админке доступно создание и удаление врезок:
- создание в двухколоночном режиме открывается через `/admin.php?action=snippet_list&new=1`;
- редактирование выполняется на странице списка `/admin.php?action=snippet_list`;
- сохранение выполняет `/admin.php?action=snippet_save`;
- удаление выполняет `/admin.php?action=snippet_delete`.
При создании проверяется уникальность ключа: если врезка с таким ключом уже есть, сохранение будет отклонено.

Удаление удаляет файл `templates/snippets/<keyword>.php` и запись в таблице `snippet` (если таблица существует).
Для безопасности действие проверяет ключ по шаблону `A-Za-z0-9_-` и путь через `realpath`.
Все операции работают по схеме `POST → redirect → GET` и используют `adminFlashSet(...)`
для тостов об успехе/ошибке.

`GET`-страница списка врезок (`/admin.php?action=snippet_list`) рендерит две колонки:
- левая колонка — список врезок;
- правая колонка — форма редактирования выбранной врезки.

## Пример: врезка футера
`templates/snippets/footer.php`:

```php
<footer class="py-4 text-center text-muted">
    &copy; <?php echo htmlspecialchars((string) ($year ?? date('Y')), ENT_QUOTES, 'UTF-8'); ?>
</footer>
```

Использование в макете:

```php
<?php echo insert_snip('footer', ['year' => 2025]); ?>
```

## Пример: повторяемый блок
`templates/snippets/alert.php`:

```php
<div class="alert alert-warning">
    <?php echo htmlspecialchars((string) ($message ?? ''), ENT_QUOTES, 'UTF-8'); ?>
</div>
```

В шаблоне компонента:

```php
<?php echo insert_snip('alert', ['message' => 'Нет данных']); ?>
```
