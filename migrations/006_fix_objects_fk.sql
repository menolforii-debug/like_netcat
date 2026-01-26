BEGIN;

ALTER TABLE objects RENAME TO objects_old;

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

INSERT INTO objects (id, site_id, section_id, infoblock_id, component_id, data_json, status, published_at, created_at, updated_at, is_deleted, deleted_at)
SELECT id, site_id, section_id, infoblock_id, component_id, data_json, status, published_at, created_at, updated_at, is_deleted, deleted_at
FROM objects_old;

DROP TABLE objects_old;

CREATE INDEX IF NOT EXISTS idx_objects_site_id ON objects(site_id);
CREATE INDEX IF NOT EXISTS idx_objects_section_id ON objects(section_id);
CREATE INDEX IF NOT EXISTS idx_objects_infoblock_id ON objects(infoblock_id);
CREATE INDEX IF NOT EXISTS idx_objects_component_id ON objects(component_id);
CREATE INDEX IF NOT EXISTS idx_objects_status ON objects(status);

COMMIT;
