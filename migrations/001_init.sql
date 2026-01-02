CREATE TABLE IF NOT EXISTS sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NULL,
    site_id INTEGER NOT NULL,
    english_name TEXT NULL,
    slug TEXT NULL,
    path TEXT NULL,
    title TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    extra_json TEXT NOT NULL DEFAULT '{}',
    FOREIGN KEY(parent_id) REFERENCES sections(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_sections_parent_slug
    ON sections(parent_id, slug);

CREATE TABLE IF NOT EXISTS components (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    keyword TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    fields_json TEXT NOT NULL,
    views_json TEXT NOT NULL DEFAULT '[]'
);

CREATE TABLE IF NOT EXISTS infoblocks (
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
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(component_id) REFERENCES components(id)
);

CREATE TABLE IF NOT EXISTS objects (
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
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(infoblock_id) REFERENCES infoblocks(id),
    FOREIGN KEY(component_id) REFERENCES components(id)
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT NOT NULL UNIQUE,
    pass_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS admin_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NULL,
    data_json TEXT NOT NULL DEFAULT '{}',
    ip TEXT NULL,
    user_agent TEXT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
