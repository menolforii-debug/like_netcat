# Codex проекта cms.devel9.ru

Проект следует строгой Codex-архитектуре.

## Инварианты

- Корень сайта — `public_html`
- Frontend-запросы входят через `public_html/index.php`
- Нет отдельного «режима сайта»; поток определяется entrypoint-ом (frontend или admin)
- Раздел — основная единица маршрутизации
- Компонент описывает схему данных
- Инфоблок — экземпляр компонента в разделе
- Контент хранится в JSON
- Рендеринг строго шаблонный
- Admin-запросы входят через `public_html/admin.php`
- SEO вычисляется централизованно
- Все изменения данных сопровождаются событиями

## События

- `section.created`
- `section.updated`
- `section.deleted`
- `infoblock.created`
- `infoblock.updated`
- `infoblock.deleted`
- `object.created`
- `object.updated`
- `object.published`
- `object.unpublished`
- `object.deleted`
- `component.created`
- `component.updated`

## Запрещено

- ORM / ActiveRecord
- Логика или SQL в шаблонах
- Фреймворки
- Новые core-сущности
- Публикация `app/` или `templates/`
