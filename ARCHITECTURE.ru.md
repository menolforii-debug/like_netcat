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

## Админка

Админка — отдельный entrypoint (`public_html/admin.php`) со своим
маршрутизатором и набором action-обработчиков. Будущие изменения должны
сохранять эту модель: админский функционал оформляется как actions, а не как
frontend-маршруты.

## Рендеринг

Шаблоны:
templates/{component}/{view}.php

Шаблоны отвечают только за отображение.
