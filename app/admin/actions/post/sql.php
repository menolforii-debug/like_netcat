<?php

$sql = isset($_POST['sql']) ? trim((string) $_POST['sql']) : '';
if ($sql === '') {
    $msg = 'Введите SQL запрос';
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $msg]);
    }
    // fallback: без сообщений в URL
    $_SESSION['sql_error'] = $msg;
    redirectTo(buildAdminUrl(['action' => 'sql']));
}

function sqlAjaxOk(string $message): void
{
    if (isAjaxRequest()) {
        jsonResponse([
            'ok' => true,
            'message' => $message,
            // результат/таблица хранится в $_SESSION, поэтому просто ведём на страницу просмотра
            'redirect' => buildAdminUrl(['action' => 'sql']),
        ]);
    }
}

function sqlAjaxError(string $error): void
{
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error]);
    }
}

$createdAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
if (DB::hasTable('sql_history')) {
    $stmt = DB::pdo()->prepare(
        'INSERT INTO sql_history (user_id, sql, created_at) VALUES (:user_id, :sql, :created_at)'
    );
    $stmt->execute([
        'user_id' => $user ? (int) $user['id'] : null,
        'sql' => $sql,
        'created_at' => $createdAt,
    ]);
}

try {
    // чистим предыдущие результаты, чтобы не путаться при ошибках
    unset($_SESSION['sql_error'], $_SESSION['sql_result']);
    $pdo = DB::pdo();
    $lower = ltrim(strtolower($sql));
    $isSelect = str_starts_with($lower, 'select') || str_starts_with($lower, 'pragma');
    if ($isSelect) {
        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $columns = [];
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
        } elseif ($stmt !== false) {
            $count = $stmt->columnCount();
            for ($i = 0; $i < $count; $i++) {
                $meta = $stmt->getColumnMeta($i);
                if ($meta && isset($meta['name'])) {
                    $columns[] = $meta['name'];
                }
            }
        }
        $_SESSION['sql_result'] = [
            'type' => 'select',
            'columns' => $columns,
            'rows' => $rows,
        ];
        // Для AJAX показываем тост и делаем redirect на страницу результата
        sqlAjaxOk('Результат получен');
    } else {
        $affected = $pdo->exec($sql);
        $_SESSION['sql_result'] = [
            'type' => 'exec',
            'message' => 'Запрос выполнен. Изменено строк: ' . (int) $affected,
        ];
        sqlAjaxOk((string) $_SESSION['sql_result']['message']);
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
    $_SESSION['sql_error'] = $err;
    sqlAjaxError($err);
}

redirectTo(buildAdminUrl(['action' => 'sql']));
