# Architecture — cms.devel9.ru

## Web Root

Only `public_html` is web-accessible.

## Request Lifecycle

Frontend HTTP request
→ public_html/index.php
→ Section resolution (URL segments map to sections by english_name)
→ Optional object resolution (slug)
→ SEO resolution
→ Rendering (page or container)

Admin HTTP request
→ public_html/admin.php
→ Admin router
→ Action handler

## Rendering

Templates:
templates/{component}/{view}.php

Templates contain presentation only.

## Section URLs

Active section URLs are derived from `english_name`. Legacy `slug`/`path` columns are no longer used and are removed via migration.
