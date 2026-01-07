<?php

AdminLayout::renderHeader('SQL');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

$sqlValue = '';
$sqlResult = $_SESSION['sql_result'] ?? null;
$sqlError = $_SESSION['sql_error'] ?? null;
unset($_SESSION['sql_result'], $_SESSION['sql_error']);

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">SQL</h1>';
echo '</div>';

echo '<div class="card shadow-sm mb-4">';
echo '<div class="card-body">';
echo '<form method="post" action="/admin.php?action=sql">';
echo csrfTokenField();
echo '<div class="mb-3"><label class="form-label">SQL запрос</label><textarea class="form-control font-monospace" name="sql" rows="6">' . htmlspecialchars($sqlValue, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
echo '<button class="btn btn-primary" type="submit">Выполнить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

if ($sqlError) {
    echo '<div class="alert alert-danger">' . htmlspecialchars((string) $sqlError, ENT_QUOTES, 'UTF-8') . '</div>';
}

if ($sqlResult && isset($sqlResult['type'])) {
    if ($sqlResult['type'] === 'select') {
        $rows = $sqlResult['rows'] ?? [];
        $columns = $sqlResult['columns'] ?? [];
        if (empty($rows)) {
            echo '<div class="alert alert-light border">Результатов нет.</div>';
        } else {
            echo '<div class="card shadow-sm">';
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-striped mb-0">';
            echo '<thead><tr>';
            foreach ($columns as $column) {
                echo '<th>' . htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    echo '<td>' . htmlspecialchars($value === null ? '' : (string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
    }

    if ($sqlResult['type'] === 'exec') {
        $message = $sqlResult['message'] ?? 'Запрос выполнен.';
        echo '<div class="alert alert-success">' . htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

AdminLayout::renderFooter();
