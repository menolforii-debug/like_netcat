ALTER TABLE sections ADD COLUMN slug TEXT;
ALTER TABLE sections ADD COLUMN parent_id INTEGER NULL;

UPDATE sections
SET slug = CASE
    WHEN path = '/' THEN ''
    ELSE trim(path, '/')
END
WHERE slug IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_sections_parent_slug
    ON sections(parent_id, slug);
