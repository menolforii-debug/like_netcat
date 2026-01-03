# cms.devel9.ru — Codex

This repository follows a strict Codex architecture.

## Core Invariants

- Web root is `public_html`
- Frontend HTTP requests enter via `public_html/index.php`
- Section is the primary routing unit
- Component defines schema only
- Infoblock is a component instance inside a section
- Objects are stored as JSON
- Rendering is template-driven
- Admin requests enter via `public_html/admin.php`
- SEO is resolved centrally
- All mutations emit events

## Forbidden

- ORM / ActiveRecord
- Logic or SQL in templates
- Framework dependencies
- New core entities
- Exposing `app/` or `templates/` to web
