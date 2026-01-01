<?php

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login($login, $password): bool
    {
        $user = DB::fetchOne(
            'SELECT id, login, pass_hash, role FROM users WHERE login = :login LIMIT 1',
            ['login' => $login]
        );

        if (!$user || !password_verify($password, $user['pass_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => $user['id'],
            'login' => $user['login'],
            'role' => $user['role'],
        ];

        return true;
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
        $user = self::user();
        if (!$user) {
            return false;
        }

        return in_array($user['role'], ['admin', 'editor'], true);
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return $user['role'] === 'admin';
    }
}
