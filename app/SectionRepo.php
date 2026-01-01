<?php
declare(strict_types=1);

final class SectionRepo
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByPath(string $path): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, path, title FROM sections WHERE path = :path LIMIT 1');
        $stmt->execute(['path' => $path]);
        $section = $stmt->fetch();

        return $section ?: null;
    }
}
