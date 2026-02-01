<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$_GET['partial'] = 'block';
require __DIR__ . '/components.php';
