<?php

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo 'Недостаточно прав';
    exit;
}

$managerPath = dirname(__DIR__, 4) . '/app/vendor/filegator/index.php';
if (!is_file($managerPath)) {
    http_response_code(500);
    echo 'Filegator not found';
    exit;
}

require $managerPath;
