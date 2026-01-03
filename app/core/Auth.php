<?php

final class Auth
{
    public const ROLE_ADMIN  = 'admin';
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

        if (!$user || !password_verify($password, (string) $user['pass_hash'])) {
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

        // Нормализуем роль, но только если поле/колонка вообще используется
        if (DB::hasColumn('users', 'role')) {
            $user['role'] = self::normalizeRole(isset($user['role']) ? (string) $user['role'] : null);
        } else {
            // В старой схеме без role считаем админом по умолчанию
            $user['role'] = self::ROLE_ADMIN;
        }

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

        // Если role-колонки нет — все залогиненные считаются админом (backward compatibility)
        if (!DB::hasColumn('users', 'role')) {
            return true;
        }

        return (string) ($user['role'] ?? self::ROLE_ADMIN) === self::ROLE_ADMIN;
    }

    /**
     * Доступные роли + человеко-читаемые подписи (используется в админке/валидаторах).
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN  => 'Полный доступ',
            self::ROLE_EDITOR => 'Контент в инфоблоках и объекты',
        ];
    }

    public static function createUser(string $login, string $password, ?string $role = null): int
    {
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        if (DB::hasColumn('users', 'role')) {
            $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash, role) VALUES (:login, :pass_hash, :role)');
            $stmt->execute([
                'login'     => $login,
                'pass_hash' => $passHash,
                'role'      => $role !== null && trim($role) !== '' ? trim($role) : self::ROLE_ADMIN,
            ]);
        } else {
            $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash) VALUES (:login, :pass_hash)');
            $stmt->execute([
                'login'     => $login,
                'pass_hash' => $passHash,
            ]);
        }

        return (int) DB::pdo()->lastInsertId();
    }

    public static function updateUserPassword(int $id, string $password): void
    {
        $stmt = DB::pdo()->prepare('UPDATE users SET pass_hash = :pass_hash WHERE id = :id');
        $stmt->execute([
            'pass_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id'        => $id,
        ]);
    }

    public static function updateUserRole(int $id, string $role): void
    {
        if (!DB::hasColumn('users', 'role')) {
            return;
        }

        $stmt = DB::pdo()->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([
            'role' => self::normalizeRole($role),
            'id'   => $id,
        ]);
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
        if ($normalized === '') {
            return self::ROLE_ADMIN;
        }

        $roles = self::roles();
        if (!array_key_exists($normalized, $roles)) {
            return self::ROLE_ADMIN;
        }

        return $normalized;
    }
}
