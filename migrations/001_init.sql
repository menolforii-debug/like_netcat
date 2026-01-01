CREATE TABLE IF NOT EXISTS sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NULL,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    FOREIGN KEY(parent_id) REFERENCES sections(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_sections_parent_slug
    ON sections(parent_id, slug);

CREATE TABLE IF NOT EXISTS components (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    keyword TEXT NOT NULL UNIQUE,
    fields_json TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS infoblocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    component_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    settings_json TEXT NOT NULL,
    view_template TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(component_id) REFERENCES components(id)
);

CREATE TABLE IF NOT EXISTS objects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    infoblock_id INTEGER NOT NULL,
    component_id INTEGER NOT NULL,
    data_json TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(infoblock_id) REFERENCES infoblocks(id),
    FOREIGN KEY(component_id) REFERENCES components(id)
);

INSERT INTO sections (parent_id, slug, title)
SELECT NULL, '', 'Главная'
WHERE NOT EXISTS (
    SELECT 1 FROM sections WHERE parent_id IS NULL AND slug = ''
);

INSERT INTO sections (parent_id, slug, title)
SELECT
    (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1),
    'news',
    'Новости'
WHERE NOT EXISTS (
    SELECT 1 FROM sections
    WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
      AND slug = 'news'
);

INSERT INTO components (name, keyword, fields_json)
SELECT 'Новости', 'news', '{"fields": ["title", "text", "date"]}'
WHERE NOT EXISTS (
    SELECT 1 FROM components WHERE keyword = 'news'
);

INSERT INTO infoblocks (section_id, component_id, name, settings_json, view_template, sort, is_enabled)
SELECT
    (SELECT id FROM sections
     WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
       AND slug = 'news'
     LIMIT 1),
    (SELECT id FROM components WHERE keyword = 'news' LIMIT 1),
    'Новости',
    '{}',
    'list',
    100,
    1
WHERE NOT EXISTS (
    SELECT 1 FROM infoblocks
    WHERE section_id = (SELECT id FROM sections
                        WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
                          AND slug = 'news'
                        LIMIT 1)
);

INSERT INTO objects (section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted)
SELECT
    (SELECT section_id FROM infoblocks
     WHERE section_id = (SELECT id FROM sections
                         WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
                           AND slug = 'news'
                         LIMIT 1)
     LIMIT 1),
    (SELECT id FROM infoblocks
     WHERE section_id = (SELECT id FROM sections
                         WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
                           AND slug = 'news'
                         LIMIT 1)
     LIMIT 1),
    (SELECT id FROM components WHERE keyword = 'news' LIMIT 1),
    '{"title": "Первая новость", "text": "Демо-текст для первой новости.", "date": "2024-01-01"}',
    '2024-01-01T10:00:00+00:00',
    '2024-01-01T10:00:00+00:00',
    0
WHERE NOT EXISTS (
    SELECT 1 FROM objects
    WHERE infoblock_id = (SELECT id FROM infoblocks
                          WHERE section_id = (SELECT id FROM sections
                                              WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
                                                AND slug = 'news'
                                              LIMIT 1)
                          LIMIT 1)
      AND json_extract(data_json, '$.title') = 'Первая новость'
);

INSERT INTO objects (section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted)
SELECT
    (SELECT section_id FROM infoblocks
     WHERE section_id = (SELECT id FROM sections
                         WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
                           AND slug = 'news'
                         LIMIT 1)
     LIMIT 1),
    (SELECT id FROM infoblocks
     WHERE section_id = (SELECT id FROM sections
                         WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
                           AND slug = 'news'
                         LIMIT 1)
     LIMIT 1),
    (SELECT id FROM components WHERE keyword = 'news' LIMIT 1),
    '{"title": "Вторая новость", "text": "Демо-текст для второй новости.", "date": "2024-01-02"}',
    '2024-01-02T10:00:00+00:00',
    '2024-01-02T10:00:00+00:00',
    0
WHERE NOT EXISTS (
    SELECT 1 FROM objects
    WHERE infoblock_id = (SELECT id FROM infoblocks
                          WHERE section_id = (SELECT id FROM sections
                                              WHERE parent_id = (SELECT id FROM sections WHERE parent_id IS NULL AND slug = '' LIMIT 1)
                                                AND slug = 'news'
                                              LIMIT 1)
                          LIMIT 1)
      AND json_extract(data_json, '$.title') = 'Вторая новость'
);
