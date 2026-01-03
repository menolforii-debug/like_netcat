<?php

final class Auth
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';

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
        $_SESSION['user_id'] = (int) $user['id'];

        return true;
    }

    public static function login(string $login, string $password): bool
    {
        return self::authenticate($login, $password);
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return null;
        }

        if (!self::usersTableExists()) {
            return null;
        }

        $user = DB::fetchOne(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            ['id' => (int) $userId]
        );
        if ($user === null) {
            return null;
        }

        $user['role'] = self::normalizeRole($user['role'] ?? null);

        return $user;
    }

    public static function canEdit(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        return (string) $user['role'] === self::ROLE_ADMIN;
    }

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => 'Полный доступ',
            self::ROLE_EDITOR => 'Контент в инфоблоках и объекты',
        ];
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

    private static function normalizeRole(?string $role): string
    {
        $normalized = $role !== null ? trim($role) : '';
        if ($normalized === '' || !array_key_exists($normalized, self::roles())) {
            return self::ROLE_ADMIN;
        }

        return $normalized;
    }
}
