<?php

$sql = isset($_POST['sql']) ? trim((string) $_POST['sql']) : '';
if ($sql === '') {
    redirectTo(buildAdminUrl(['action' => 'sql', 'error' => 'Введите SQL запрос']));
}

if (!isset($_SESSION['sql_history']) || !is_array($_SESSION['sql_history'])) {
    $_SESSION['sql_history'] = [];
}
array_unshift($_SESSION['sql_history'], [
    'sql' => $sql,
    'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
]);
$_SESSION['sql_history'] = array_slice($_SESSION['sql_history'], 0, 15);

try {
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
    } else {
        $affected = $pdo->exec($sql);
        $_SESSION['sql_result'] = [
            'type' => 'exec',
            'message' => 'Запрос выполнен. Изменено строк: ' . (int) $affected,
        ];
    }
} catch (Throwable $e) {
    $_SESSION['sql_error'] = $e->getMessage();
}

redirectTo(buildAdminUrl(['action' => 'sql']));
