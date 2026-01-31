CREATE TABLE IF NOT EXISTS sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NULL,
    site_id INTEGER NOT NULL,
    english_name TEXT NULL,
    title TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    extra_json TEXT NOT NULL DEFAULT '{}',
    FOREIGN KEY(parent_id) REFERENCES sections(id) ON DELETE CASCADE
);

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
    `key` TEXT NOT NULL DEFAULT '',
    name TEXT NOT NULL,
    view_template TEXT NOT NULL,
    per_page INTEGER NOT NULL DEFAULT 0,
    extra_json TEXT NOT NULL DEFAULT '{}',
    sort INTEGER NOT NULL DEFAULT 0,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY(section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY(component_id) REFERENCES components(id) ON DELETE CASCADE
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
    FOREIGN KEY(section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY(infoblock_id) REFERENCES infoblocks(id) ON DELETE CASCADE,
    FOREIGN KEY(component_id) REFERENCES components(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT NOT NULL UNIQUE,
    pass_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin'
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
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS visual_fields (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'text',
    options_json TEXT NOT NULL DEFAULT '[]',
    sort INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sql_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    sql TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS snippet (
    keyword TEXT PRIMARY KEY,
    name TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_sections_site_id ON sections(site_id);
CREATE INDEX IF NOT EXISTS idx_sections_parent_id ON sections(parent_id);
CREATE INDEX IF NOT EXISTS idx_infoblocks_site_id ON infoblocks(site_id);
CREATE INDEX IF NOT EXISTS idx_infoblocks_section_id ON infoblocks(section_id);
CREATE INDEX IF NOT EXISTS idx_infoblocks_component_id ON infoblocks(component_id);
CREATE INDEX IF NOT EXISTS idx_infoblocks_key ON infoblocks(key);
CREATE INDEX IF NOT EXISTS idx_objects_site_id ON objects(site_id);
CREATE INDEX IF NOT EXISTS idx_objects_section_id ON objects(section_id);
CREATE INDEX IF NOT EXISTS idx_objects_infoblock_id ON objects(infoblock_id);
CREATE INDEX IF NOT EXISTS idx_objects_component_id ON objects(component_id);
CREATE INDEX IF NOT EXISTS idx_objects_status ON objects(status);
CREATE INDEX IF NOT EXISTS idx_admin_log_user_id ON admin_log(user_id);
CREATE INDEX IF NOT EXISTS idx_sql_history_user_id ON sql_history(user_id);
