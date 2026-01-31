<?php
declare(strict_types=1);

final class MigrationRunner
{
    public static function run(PDO $pdo, string $migrationsDir): void
    {
        static $hasRun = false;
        if ($hasRun) {
            return;
        }
        $hasRun = true;

        if (DB::pdo() !== $pdo) {
            throw new RuntimeException('MigrationRunner::run must be called after DB::connect().');
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

        $files = glob(rtrim($migrationsDir, '/') . '/*.sql');
        if ($files === false) {
            return;
        }

        sort($files);

        if ($files === []) {
            return;
        }

        $applied = $pdo->query('SELECT name FROM migrations');
        $appliedNames = $applied ? $applied->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        $appliedLookup = array_fill_keys($appliedNames, true);

        $pendingFiles = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!isset($appliedLookup[$name])) {
                $pendingFiles[] = $file;
            }
        }

        if ($pendingFiles === []) {
            return;
        }

        try {
            foreach ($pendingFiles as $file) {
                $name = basename($file);
                $sql = file_get_contents($file);
                if ($sql === false) {
                    continue;
                }

                $manageTransaction = !$pdo->inTransaction() && !self::migrationManagesTransaction($sql);
                if ($manageTransaction) {
                    $pdo->beginTransaction();
                }

                $pdo->exec($sql);
                $stmt = $pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (:name, :applied_at)');
                $stmt->execute([
                    'name' => $name,
                    'applied_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
                ]);

                if ($manageTransaction) {
                    $pdo->commit();
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function migrationManagesTransaction(string $sql): bool
    {
        return preg_match('/\bBEGIN\b|\bCOMMIT\b|\bROLLBACK\b|\bPRAGMA\s+foreign_keys\b/i', $sql) === 1;
    }
}
