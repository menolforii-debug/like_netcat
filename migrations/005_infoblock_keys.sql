BEGIN;

ALTER TABLE infoblocks RENAME TO infoblocks_old;

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

INSERT INTO infoblocks (id, site_id, section_id, component_id, `key`, name, view_template, per_page, extra_json, sort, is_enabled)
SELECT id, site_id, section_id, component_id, '', name, view_template, 0, extra_json, sort, is_enabled
FROM infoblocks_old;

DROP TABLE infoblocks_old;

CREATE INDEX IF NOT EXISTS idx_infoblocks_site_id ON infoblocks(site_id);
CREATE INDEX IF NOT EXISTS idx_infoblocks_section_id ON infoblocks(section_id);
CREATE INDEX IF NOT EXISTS idx_infoblocks_component_id ON infoblocks(component_id);
CREATE INDEX IF NOT EXISTS idx_infoblocks_key ON infoblocks(key);

COMMIT;
