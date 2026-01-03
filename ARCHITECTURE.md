# Architecture — cms.devel9.ru

## Web Root

Only `public_html` is web-accessible.

## Request Lifecycle

Frontend HTTP request
→ public_html/index.php
→ Section resolution
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
