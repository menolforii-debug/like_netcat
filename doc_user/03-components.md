# Компоненты

Шаблоны компонентов лежат в:
`templates/component/<keyword>/<view>/`

Файлы представления:
- `list.php`
- `single.php`
- `system.php`

## Что доступно в `list.php` / `single.php`
- `$section`, `$site`, `$infoblock`, `$component`
- `$items`, `$objects`, `$object`
- `$isSingle`, `$editMode`
- `$settings`
- `$cc_env` (контекст пагинации)
- `$message_select` (диагностический SQL)
- `$helpers` (из `system.php`)
- `$setFields($item)`
- `$f_<field>` переменные полей объекта
- `$fullLink` ссылка на single-режим

## Системные настройки (`system.php`)
`system.php` задаёт параметры выборки и поведение рендера.

Основные переменные:
- фильтры: `$ignore_sub`, `$ignore_cc`, `$ignore_check`, `$ignore_all`, `$ignore_limit`
- SQL-фрагменты: `$query_select`, `$query_from`, `$query_join`, `$query_where`, `$query_group`, `$query_having`, `$query_order`, `$query_limit`
- `$distinct`
- `$helpers` (массив хелперов для шаблона)

Дополнительно доступны входные переменные:
- `$section`, `$site`, `$infoblock`, `$component`, `$isSingle`.

## Где хранится структура компонента
В базе:
- `components.fields_json` — описание полей;
- `components.views_json` — список view;
- `infoblocks.view_template` — выбранный view для конкретного инфоблока.

## В админке
Раздел `Компоненты` позволяет:
- создавать компонент;
- добавлять/удалять представления (`view`);
- редактировать `list.php`, `single.php`, `system.php`;
- редактировать поля компонента.
