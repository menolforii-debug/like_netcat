<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$_GET['partial'] = 'block';
require __DIR__ . '/components.php';
