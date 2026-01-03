# cms.devel9.ru — Codex

This repository follows a strict Codex architecture.

## Core Invariants

- Web root is `public_html`
- Frontend HTTP requests enter via `public_html/index.php`
- No "site mode" switch; the entrypoint defines frontend vs admin flows
- Section is the primary routing unit
- Component defines schema only
- Infoblock is a component instance inside a section
- Objects are stored as JSON
- Rendering is template-driven
- Admin requests enter via `public_html/admin.php`
- SEO is resolved centrally
- All mutations emit events

## Events

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

## Forbidden

- ORM / ActiveRecord
- Logic or SQL in templates
- Framework dependencies
- New core entities
- Exposing `app/` or `templates/` to web
