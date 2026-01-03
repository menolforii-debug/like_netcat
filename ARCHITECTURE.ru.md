# Архитектура cms.devel9.ru

## Корень сайта

Единственная публичная директория — `public_html`.

## Жизненный цикл запроса

Frontend-запрос
→ public_html/index.php
→ определение раздела
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
