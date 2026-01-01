<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(string $path): void
    {
        if (self::$pdo instanceof PDO) {
            return;
        }

        self::$pdo = new PDO('sqlite:' . $path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function connection(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('Database connection has not been initialized.');
        }

        return self::$pdo;
    }
}
