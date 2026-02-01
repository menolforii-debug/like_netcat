<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

AdminLayout::renderHeader('SQL');

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
echo csrf_token_field();
echo '<div class="mb-3"><label class="form-label">SQL запрос</label><textarea class="form-control font-monospace" id="sqlInput" name="sql" rows="6">' . htmlspecialchars($sqlValue, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
echo '<button class="btn btn-primary" type="submit">Выполнить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

$history = DB::hasTable('sql_history')
    ? DB::fetchAll('SELECT sql FROM sql_history ORDER BY id DESC LIMIT 15')
    : [];
if (!empty($history)) {
    echo '<div class="card shadow-sm mb-4">';
    echo '<div class="card-body">';
    echo '<h2 class="h6 mb-3">Последние запросы</h2>';
    echo '<ol class="mb-0 ps-3" id="sqlHistory">';
    foreach ($history as $item) {
        $sqlText = is_array($item) && isset($item['sql']) ? (string) $item['sql'] : '';
        if ($sqlText === '') {
            continue;
        }
        echo '<li><button class="btn btn-link p-0 text-start" type="button" data-sql="' . htmlspecialchars($sqlText, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($sqlText, ENT_QUOTES, 'UTF-8') . '</button></li>';
    }
    echo '</ol>';
    echo '</div>';
    echo '</div>';
}

if ($sqlError) {
    echo '<script>window.showGlobalSnackbar(' . json_encode((string) $sqlError, JSON_UNESCAPED_UNICODE) . ', "error");</script>';
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
        echo '<script>window.showGlobalSnackbar(' . json_encode((string) $message, JSON_UNESCAPED_UNICODE) . ', "success");</script>';
    }
}

echo '<script>';
echo 'document.querySelectorAll("#sqlHistory [data-sql]").forEach(function(button){';
echo 'button.addEventListener("click", function(){';
echo 'var input = document.getElementById("sqlInput");';
echo 'if (!input) return;';
echo 'input.value = button.getAttribute("data-sql") || "";';
echo 'input.focus();';
echo '});';
echo '});';
echo '</script>';

AdminLayout::renderFooter();
