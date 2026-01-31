<?php

// Важно: requireLogin() уже сработал в AdminRouter (кроме login/logout),
// но embed должен быть строго admin-only.
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo 'Недостаточно прав';
    return;
}

$managerPath = __DIR__ . '/../../../vendor/filegator/index.php';
if (!is_file($managerPath)) {
    http_response_code(500);
    echo 'Filegator not found';
    return;
}

require $managerPath;
