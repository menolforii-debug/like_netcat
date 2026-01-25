<?php

final class SnippetRepo
{
    public function listAll(): array
    {
        return DB::fetchAll(
            'SELECT id, keyword, content, created_at, updated_at
            FROM snippets
            ORDER BY keyword ASC'
        );
    }

    public function findById(int $id): ?array
    {
        return DB::fetchOne(
            'SELECT id, keyword, content, created_at, updated_at
            FROM snippets
            WHERE id = :id
            LIMIT 1',
            ['id' => $id]
        );
    }

    public function findByKeyword(string $keyword): ?array
    {
        return DB::fetchOne(
            'SELECT id, keyword, content, created_at, updated_at
            FROM snippets
            WHERE keyword = :keyword
            LIMIT 1',
            ['keyword' => $keyword]
        );
    }

    public function create(string $keyword, string $content): int
    {
        $now = $this->now();
        $stmt = DB::pdo()->prepare(
            'INSERT INTO snippets (keyword, content, created_at, updated_at)
            VALUES (:keyword, :content, :created_at, :updated_at)'
        );
        $stmt->execute([
            'keyword' => $keyword,
            'content' => $content,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::pdo()->lastInsertId();
    }

    public function update(int $id, string $content): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE snippets
            SET content = :content, updated_at = :updated_at
            WHERE id = :id'
        );
        $stmt->execute([
            'content' => $content,
            'updated_at' => $this->now(),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM snippets WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    }
}
