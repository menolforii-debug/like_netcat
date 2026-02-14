<?php

declare(strict_types=1);

$root = dirname(__DIR__, 6);
require_once $root . '/app/admin/bootstrap.php';

/**
 * Check if user is authorized.
 */
function auth(): int
{
    if (!Auth::canEdit()) {
        echo 'Not enough rights or Hack attempt!';
        exit;
    }

    $user = Auth::user();
    return (int) ($user['id'] ?? 0);
}

function nc_ckeditor_check_file_name($file_name): bool
{
    $file_extension = strtolower(pathinfo(trim((string) $file_name), PATHINFO_EXTENSION));
    $allowed_extensions = [
        'jpg', 'jpeg', 'gif', 'png', 'svg', 'webp',
        'txt', 'pdf', 'odp', 'ods', 'odt', 'rtf', 'doc', 'docx',
        'xls', 'xlsx', 'ppt', 'pptx',
        'ogv', 'mp4', 'webm', 'ogg', 'mp3', 'wav',
    ];

    return in_array($file_extension, $allowed_extensions, true);
}

$config['culture'] = 'ru';
$config['date'] = 'd M Y H:i';

$config['icons']['path'] = 'images/fileicons/';
$config['icons']['directory'] = '_Open.png';
$config['icons']['default'] = 'default.png';

$config['upload']['overwrite'] = false;
$config['upload']['size'] = false;
$config['upload']['imagesonly'] = false;

$config['images'] = ['jpg', 'jpeg', 'gif', 'png', 'svg', 'webp'];

$config['unallowed_files'] = ['.htaccess'];
$config['unallowed_dirs'] = ['_thumbs', '.CDN_ACCESS_LOGS', 'cloudservers'];

$config['plugin'] = null;

$userId = auth();
$isAdmin = Auth::isAdmin();

$basePublicPath = '/files/userfiles';
$baseDiskPath = $root . '/public_html' . $basePublicPath;
if (!is_dir($baseDiskPath)) {
    mkdir($baseDiskPath, 0777, true);
}

if ($isAdmin || $userId <= 0) {
    $config['rel_path'] = $basePublicPath;
} else {
    $config['rel_path'] = $basePublicPath . '/' . $userId;
    if (!is_dir($root . '/public_html' . $config['rel_path'])) {
        mkdir($root . '/public_html' . $config['rel_path'], 0777, true);
    }
}

$config['doc_root'] = $root . '/public_html' . $config['rel_path'];
