CREATE TABLE IF NOT EXISTS sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NULL,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    FOREIGN KEY(parent_id) REFERENCES sections(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_sections_parent_slug
    ON sections(parent_id, slug);

INSERT INTO sections (parent_id, slug, title)
SELECT NULL, '', 'Главная'
WHERE NOT EXISTS (
    SELECT 1 FROM sections WHERE parent_id IS NULL AND slug = ''
);
