PRAGMA foreign_keys = OFF;

ALTER TABLE admin_log RENAME TO admin_log_old;
ALTER TABLE component_views RENAME TO component_views_old;
ALTER TABLE objects RENAME TO objects_old;
ALTER TABLE infoblocks RENAME TO infoblocks_old;
ALTER TABLE components RENAME TO components_old;
ALTER TABLE sections RENAME TO sections_old;
ALTER TABLE users RENAME TO users_old;

CREATE TABLE sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NULL,
    site_id INTEGER NOT NULL,
    english_name TEXT NULL,
    title TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    extra_json TEXT NOT NULL DEFAULT '{}',
    FOREIGN KEY(parent_id) REFERENCES sections(id) ON DELETE CASCADE
);

CREATE TABLE components (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    keyword TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    fields_json TEXT NOT NULL,
    views_json TEXT NOT NULL DEFAULT '[]'
);

CREATE TABLE infoblocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    section_id INTEGER NOT NULL,
    component_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    view_template TEXT NOT NULL,
    settings_json TEXT NOT NULL DEFAULT '{}',
    extra_json TEXT NOT NULL DEFAULT '{}',
    sort INTEGER NOT NULL DEFAULT 0,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY(section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY(component_id) REFERENCES components(id) ON DELETE CASCADE
);

CREATE TABLE objects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    section_id INTEGER NOT NULL,
    infoblock_id INTEGER NOT NULL,
    component_id INTEGER NOT NULL,
    data_json TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    published_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    deleted_at TEXT NULL,
    FOREIGN KEY(section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY(infoblock_id) REFERENCES infoblocks(id) ON DELETE CASCADE,
    FOREIGN KEY(component_id) REFERENCES components(id) ON DELETE CASCADE
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT NOT NULL UNIQUE,
    pass_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin'
);

CREATE TABLE admin_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NULL,
    data_json TEXT NOT NULL DEFAULT '{}',
    ip TEXT NULL,
    user_agent TEXT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE component_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    component_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    list_tpl TEXT NOT NULL,
    single_tpl TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(component_id, name),
    FOREIGN KEY(component_id) REFERENCES components(id) ON DELETE CASCADE
);

INSERT INTO sections (id, parent_id, site_id, english_name, title, sort, extra_json)
SELECT
    s.id,
    CASE
        WHEN s.parent_id IS NOT NULL AND EXISTS (SELECT 1 FROM sections_old p WHERE p.id = s.parent_id)
            THEN s.parent_id
        ELSE NULL
    END,
    s.site_id,
    s.english_name,
    s.title,
    s.sort,
    s.extra_json
FROM sections_old s;

INSERT INTO components (id, keyword, name, fields_json, views_json)
SELECT id, keyword, name, fields_json, views_json
FROM components_old;

INSERT INTO infoblocks (id, site_id, section_id, component_id, name, view_template, settings_json, extra_json, sort, is_enabled)
SELECT
    i.id,
    i.site_id,
    i.section_id,
    i.component_id,
    i.name,
    i.view_template,
    i.settings_json,
    i.extra_json,
    i.sort,
    i.is_enabled
FROM infoblocks_old i
JOIN sections s ON s.id = i.section_id
JOIN components c ON c.id = i.component_id;

INSERT INTO objects (id, site_id, section_id, infoblock_id, component_id, data_json, status, published_at, created_at, updated_at, is_deleted, deleted_at)
SELECT
    o.id,
    o.site_id,
    o.section_id,
    o.infoblock_id,
    o.component_id,
    o.data_json,
    o.status,
    o.published_at,
    o.created_at,
    o.updated_at,
    o.is_deleted,
    o.deleted_at
FROM objects_old o
JOIN sections s ON s.id = o.section_id
JOIN infoblocks i ON i.id = o.infoblock_id
JOIN components c ON c.id = o.component_id;

INSERT INTO users (id, login, pass_hash, role)
SELECT id, login, pass_hash, role
FROM users_old;

INSERT INTO admin_log (id, created_at, user_id, action, entity_type, entity_id, data_json, ip, user_agent)
SELECT l.id, l.created_at, l.user_id, l.action, l.entity_type, l.entity_id, l.data_json, l.ip, l.user_agent
FROM admin_log_old l
JOIN users u ON u.id = l.user_id;

INSERT INTO component_views (id, component_id, name, list_tpl, single_tpl, created_at, updated_at)
SELECT v.id, v.component_id, v.name, v.list_tpl, v.single_tpl, v.created_at, v.updated_at
FROM component_views_old v
JOIN components c ON c.id = v.component_id;

DROP TABLE admin_log_old;
DROP TABLE component_views_old;
DROP TABLE objects_old;
DROP TABLE infoblocks_old;
DROP TABLE components_old;
DROP TABLE sections_old;
DROP TABLE users_old;

PRAGMA foreign_keys = ON;
