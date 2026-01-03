# Архитектура cms.devel9.ru

## Корень сайта

Единственная публичная директория — `public_html`.

## Жизненный цикл запроса

Frontend-запрос
→ public_html/index.php
→ определение раздела (сегменты URL сопоставляются с english_name)
→ (опционально) объект
→ SEO
→ рендеринг

Admin-запрос
→ public_html/admin.php
→ Admin router
→ Action handler

## Рендеринг

Шаблоны:
templates/{component}/{view}.php

Шаблоны отвечают только за отображение.

## URL разделов

Актуальные URL разделов строятся из `english_name`. Наследованные поля `slug`/`path` больше не используются и удаляются миграцией.
