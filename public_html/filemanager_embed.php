<?php

require __DIR__ . '/../app/admin/bootstrap.php';

// Единственная точка входа админки — AdminRouter
$_GET['action'] = 'filemanager_embed';
AdminRouter::run();
