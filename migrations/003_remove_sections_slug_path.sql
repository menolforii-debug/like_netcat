PRAGMA foreign_keys=OFF;
BEGIN;

CREATE TABLE sections_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NULL,
    site_id INTEGER NOT NULL,
    english_name TEXT NULL,
    title TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    extra_json TEXT NOT NULL DEFAULT '{}',
    FOREIGN KEY(parent_id) REFERENCES sections_new(id)
);

INSERT INTO sections_new (id, parent_id, site_id, english_name, title, sort, extra_json)
SELECT id, parent_id, site_id, english_name, title, sort, extra_json
FROM sections;

DROP TABLE sections;
ALTER TABLE sections_new RENAME TO sections;

COMMIT;
PRAGMA foreign_keys=ON;
