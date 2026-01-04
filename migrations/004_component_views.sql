CREATE TABLE IF NOT EXISTS component_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    component_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    list_tpl TEXT NOT NULL,
    single_tpl TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(component_id, name),
    FOREIGN KEY(component_id) REFERENCES components(id)
);
