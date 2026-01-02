<?php

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function authenticate(string $login, string $password): bool
    {
        if (!self::usersTableExists()) {
            return false;
        }

        $user = DB::fetchOne(
            'SELECT id, login, pass_hash FROM users WHERE login = :login LIMIT 1',
            ['login' => $login]
        );

        if (!$user || !password_verify($password, $user['pass_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'login' => $user['login'],
        ];

        return true;
    }

    public static function login(string $login, string $password): bool
    {
        return self::authenticate($login, $password);
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function canEdit(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return self::user() !== null;
    }

    public static function createUser(string $login, string $password): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash) VALUES (:login, :pass_hash)');
        $stmt->execute([
            'login' => $login,
            'pass_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return (int) DB::pdo()->lastInsertId();
    }

    private static function usersTableExists(): bool
    {
        $stmt = DB::pdo()->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'");
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }
}
