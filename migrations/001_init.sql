CREATE TABLE sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL
);

INSERT INTO sections (path, title)
VALUES ('/', 'Главная');
