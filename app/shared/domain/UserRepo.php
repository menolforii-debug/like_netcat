<?php

final class UserRepo
{
    public function listAll(): array
    {
        if (DB::hasColumn('users', 'role')) {
            return DB::fetchAll('SELECT id, login, role FROM users ORDER BY id ASC');
        }

        return DB::fetchAll('SELECT id, login FROM users ORDER BY id ASC');
    }

    public function findById(int $id): ?array
    {
        return DB::fetchOne('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function findByLogin(string $login): ?array
    {
        return DB::fetchOne('SELECT * FROM users WHERE login = :login LIMIT 1', ['login' => $login]);
    }

    public function create(string $login, string $password, ?string $role = null): int
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

    public function updateRole(int $id, string $role): void
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

    public function updatePassword(int $id, string $password): void
    {
        $stmt = DB::pdo()->prepare('UPDATE users SET pass_hash = :pass_hash WHERE id = :id');
        $stmt->execute([
            'pass_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function countAdmins(): int
    {
        if (!DB::hasColumn('users', 'role')) {
            $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM users');
            return $row ? (int) $row['cnt'] : 0;
        }

        $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE role = :role', ['role' => 'admin']);

        return $row ? (int) $row['cnt'] : 0;
    }
}
