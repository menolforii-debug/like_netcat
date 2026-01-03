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

        if (DB::hasColumn('users', 'role') && isset($user['role'])) {
            return (string) $user['role'] === 'admin';
        }

        return true;
    }

    public static function createUser(string $login, string $password, ?string $role = null): int
    {
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        if (DB::hasColumn('users', 'role')) {
            $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash, role) VALUES (:login, :pass_hash, :role)');
            $stmt->execute([
                'login' => $login,
                'pass_hash' => $passHash,
                'role' => $role !== null && $role !== '' ? $role : 'admin',
            ]);
        } else {
            $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash) VALUES (:login, :pass_hash)');
            $stmt->execute([
                'login' => $login,
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
            'id' => $id,
        ]);
    }

    public static function updateUserRole(int $id, string $role): void
    {
        if (!DB::hasColumn('users', 'role')) {
            return;
        }

        $stmt = DB::pdo()->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([
            'role' => $role,
            'id' => $id,
        ]);
    }

    private static function usersTableExists(): bool
    {
        $stmt = DB::pdo()->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'");
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }
}
