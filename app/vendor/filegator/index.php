<?php

if (!class_exists('Auth') || !method_exists('Auth', 'isAdmin') || !Auth::isAdmin()) {
    http_response_code(403);
    echo 'Недостаточно прав';
    exit;
}

$root = realpath(__DIR__ . '/../../../');
if ($root === false) {
    http_response_code(500);
    echo 'Корневая директория файлового менеджера не найдена.';
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['filemanager_csrf'])) {
    $_SESSION['filemanager_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = (string) $_SESSION['filemanager_csrf'];

function normalizePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    $path = trim($path, '/');
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

function resolvePath(string $root, string $relative): string
{
    $relative = normalizePath($relative);
    $candidate = $root . ($relative !== '' ? DIRECTORY_SEPARATOR . $relative : '');
    $real = realpath($candidate);
    if ($real === false) {
        return $candidate;
    }

    $rootReal = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($real . DIRECTORY_SEPARATOR, $rootReal)) {
        throw new RuntimeException('Недопустимый путь.');
    }

    return $real;
}

function deleteRecursive(string $path, string $root): void
{
    $rootReal = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $real = realpath($path);
    if ($real === false || !str_starts_with($real . DIRECTORY_SEPARATOR, $rootReal)) {
        throw new RuntimeException('Недопустимый путь удаления.');
    }

    if (is_file($real) || is_link($real)) {
        if (!unlink($real)) {
            throw new RuntimeException('Не удалось удалить файл.');
        }
        return;
    }

    $items = scandir($real);
    if ($items === false) {
        throw new RuntimeException('Не удалось прочитать директорию.');
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        deleteRecursive($real . DIRECTORY_SEPARATOR . $item, $root);
    }
    if (!rmdir($real)) {
        throw new RuntimeException('Не удалось удалить директорию.');
    }
}

function ensureCsrf(string $token): void
{
    $sessionToken = $_SESSION['filemanager_csrf'] ?? '';
    if ($token === '' || !hash_equals((string) $sessionToken, (string) $token)) {
        throw new RuntimeException('Неверный CSRF-токен.');
    }
}

$current = isset($_GET['path']) ? (string) $_GET['path'] : '';
$current = normalizePath($current);
$currentPath = resolvePath($root, $current);

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensureCsrf((string) ($_POST['csrf_token'] ?? ''));
        $action = (string) ($_POST['action'] ?? '');
        $target = normalizePath((string) ($_POST['target'] ?? $current));
        $targetPath = resolvePath($root, $target);

        if ($action === 'mkdir') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Имя папки не задано.');
            }
            $dest = resolvePath($root, $target . '/' . $name);
            if (!is_dir($dest) && !mkdir($dest, 0770, true)) {
                throw new RuntimeException('Не удалось создать папку.');
            }
            $messages[] = 'Папка создана.';
        } elseif ($action === 'upload') {
            if (!isset($_FILES['file'])) {
                throw new RuntimeException('Файл не передан.');
            }
            $upload = $_FILES['file'];
            if (!is_uploaded_file($upload['tmp_name'] ?? '')) {
                throw new RuntimeException('Невозможно прочитать загруженный файл.');
            }
            $name = basename((string) ($upload['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Некорректное имя файла.');
            }
            $dest = resolvePath($root, $target . '/' . $name);
            if (!move_uploaded_file($upload['tmp_name'], $dest)) {
                throw new RuntimeException('Не удалось сохранить файл.');
            }
            @chmod($dest, 0660);
            $messages[] = 'Файл загружен.';
        } elseif ($action === 'delete') {
            $item = normalizePath((string) ($_POST['item'] ?? ''));
            if ($item === '') {
                throw new RuntimeException('Путь не задан.');
            }
            $path = resolvePath($root, $item);
            deleteRecursive($path, $root);
            $messages[] = 'Удалено.';
        } elseif ($action === 'rename') {
            $item = normalizePath((string) ($_POST['item'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($item === '' || $name === '') {
                throw new RuntimeException('Недостаточно данных для переименования.');
            }
            $source = resolvePath($root, $item);
            $dest = resolvePath($root, dirname($item) . '/' . $name);
            if (!rename($source, $dest)) {
                throw new RuntimeException('Не удалось переименовать.');
            }
            $messages[] = 'Переименовано.';
        } elseif ($action === 'save') {
            $item = normalizePath((string) ($_POST['item'] ?? ''));
            $content = (string) ($_POST['content'] ?? '');
            if ($item === '') {
                throw new RuntimeException('Файл не задан.');
            }
            $path = resolvePath($root, $item);
            if (!is_file($path)) {
                throw new RuntimeException('Файл не найден.');
            }
            if (file_put_contents($path, $content) === false) {
                throw new RuntimeException('Не удалось сохранить файл.');
            }
            $messages[] = 'Файл сохранён.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$items = [];
if (is_dir($currentPath)) {
    $dirItems = scandir($currentPath);
    if ($dirItems !== false) {
        foreach ($dirItems as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($itemPath);
            $items[] = [
                'name' => $item,
                'path' => trim($current . '/' . $item, '/'),
                'type' => $isDir ? 'dir' : 'file',
                'size' => $isDir ? null : filesize($itemPath),
                'modified' => filemtime($itemPath) ?: null,
            ];
        }
    }
}

$parent = '';
if ($current !== '') {
    $parent = dirname($current);
    if ($parent === '.') {
        $parent = '';
    }
}

$editableExtensions = [
    'php',
    'phtml',
    'html',
    'htm',
    'css',
    'scss',
    'sass',
    'less',
    'js',
    'json',
    'yml',
    'yaml',
    'md',
    'txt',
    'xml',
    'svg',
    'env',
    'ini',
    'htaccess',
];
$editTarget = isset($_GET['edit']) ? normalizePath((string) $_GET['edit']) : '';
$editContent = '';
if ($editTarget !== '') {
    $editPath = resolvePath($root, $editTarget);
    $ext = strtolower(pathinfo($editPath, PATHINFO_EXTENSION));
    if (is_file($editPath) && ($ext !== '' && in_array($ext, $editableExtensions, true))) {
        $editContent = (string) file_get_contents($editPath);
    } else {
        $editTarget = '';
    }
}
if ($editTarget === '' && isset($_GET['edit'])) {
    http_response_code(400);
    $errors[] = 'Редактирование разрешено только для текстовых файлов проекта.';
}

$segments = $current === '' ? [] : explode('/', $current);
$breadcrumbs = [];
$crumbPath = '';
foreach ($segments as $segment) {
    $crumbPath = $crumbPath === '' ? $segment : $crumbPath . '/' . $segment;
    $breadcrumbs[] = [
        'name' => $segment,
        'path' => $crumbPath,
    ];
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Файловый менеджер</title>
    <link href="/assets/sow/css/vendor_bundle.min.css" rel="stylesheet">
    <link href="/assets/sow/css/core.min.css" rel="stylesheet">
    <style>
        .file-table td, .file-table th { vertical-align: middle; }
        .file-actions form { display: inline; }
        .file-editor textarea { min-height: 240px; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <strong>Путь:</strong>
            <nav aria-label="breadcrumb" class="d-inline-block">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?path=">/</a></li>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <li class="breadcrumb-item">
                            <a href="?path=<?= urlencode($crumb['path']) ?>">
                                <?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
        <?php if ($parent !== '' || $current !== ''): ?>
            <a class="btn btn-sm btn-outline-secondary" href="?path=<?= urlencode($parent) ?>">Назад</a>
        <?php endif; ?>
    </div>

    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Файлы и папки</div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 file-table">
                        <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Тип</th>
                            <th>Размер</th>
                            <th>Изменён</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($items === []): ?>
                            <tr><td colspan="5" class="text-muted">Пусто</td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item['type'] === 'dir'): ?>
                                        <a href="?path=<?= urlencode($item['path']) ?>">
                                            <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $item['type'] === 'dir' ? 'Папка' : 'Файл' ?></td>
                                <td><?= $item['size'] !== null ? number_format((int) $item['size']) . ' B' : '—' ?></td>
                                <td><?= $item['modified'] ? date('Y-m-d H:i', (int) $item['modified']) : '—' ?></td>
                                <td class="file-actions">
                                    <?php if ($item['type'] === 'file'): ?>
                                        <?php $ext = strtolower(pathinfo((string) $item['name'], PATHINFO_EXTENSION)); ?>
                                        <?php if ($ext !== '' && in_array($ext, $editableExtensions, true)): ?>
                                        <button class="btn btn-sm btn-outline-primary js-edit-file"
                                                type="button"
                                                data-file-path="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-file-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                            Редактировать
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-secondary js-rename-file"
                                            type="button"
                                            data-file-path="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-file-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        Переименовать
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger js-delete-file"
                                            type="button"
                                            data-file-path="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-file-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        Удалить
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Загрузить файл</div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="target" value="<?= htmlspecialchars($current, ENT_QUOTES, 'UTF-8') ?>">
                        <input class="form-control mb-2" type="file" name="file" required>
                        <button class="btn btn-primary w-100" type="submit">Загрузить</button>
                    </form>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Создать папку</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="mkdir">
                        <input type="hidden" name="target" value="<?= htmlspecialchars($current, ENT_QUOTES, 'UTF-8') ?>">
                        <input class="form-control mb-2" type="text" name="name" placeholder="Имя папки" required>
                        <button class="btn btn-outline-primary w-100" type="submit">Создать</button>
                    </form>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Переименовать</div>
                <div class="card-body">
                    <div class="text-muted small">Переименование доступно из таблицы файлов.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fileEditModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактирование файла</h5>
                    <button type="button" class="btn-close js-cancel-edit" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="fileEditForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="item" id="fileEditPath" value="">
                        <textarea class="form-control mb-2" name="content" id="fileEditContent"><?= htmlspecialchars($editContent, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary js-cancel-edit" type="button">Отмена</button>
                            <button class="btn btn-success" type="submit">Сохранить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="fileDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Удалить файл</h5>
                    <button type="button" class="btn-close js-cancel-delete" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">Удалить: <strong id="fileDeleteName"></strong>?</div>
                    <form method="post" id="fileDeleteForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item" id="fileDeletePath" value="">
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary js-cancel-delete" type="button">Отмена</button>
                            <button class="btn btn-danger" type="submit">Удалить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="fileRenameModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Переименовать</h5>
                    <button type="button" class="btn-close js-cancel-rename" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="fileRenameForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="item" id="fileRenamePath" value="">
                        <div class="mb-2">
                            <label class="form-label">Новое имя</label>
                            <input class="form-control" type="text" name="name" id="fileRenameName" required>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary js-cancel-rename" type="button">Отмена</button>
                            <button class="btn btn-primary" type="submit">Переименовать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        var modalEl = document.getElementById('fileEditModal');
        if (!modalEl) {
            return;
        }
        var modal = new bootstrap.Modal(modalEl, {backdrop: 'static', keyboard: false});
        var editButtons = document.querySelectorAll('.js-edit-file');
        var pathInput = document.getElementById('fileEditPath');
        var contentArea = document.getElementById('fileEditContent');
        var titleEl = modalEl.querySelector('.modal-title');
        var cancelButtons = modalEl.querySelectorAll('.js-cancel-edit');
        var deleteModalEl = document.getElementById('fileDeleteModal');
        var deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl, {backdrop: 'static', keyboard: false}) : null;
        var deleteButtons = document.querySelectorAll('.js-delete-file');
        var deletePathInput = document.getElementById('fileDeletePath');
        var deleteNameEl = document.getElementById('fileDeleteName');
        var deleteCancelButtons = document.querySelectorAll('.js-cancel-delete');

        var renameModalEl = document.getElementById('fileRenameModal');
        var renameModal = renameModalEl ? new bootstrap.Modal(renameModalEl, {backdrop: 'static', keyboard: false}) : null;
        var renameButtons = document.querySelectorAll('.js-rename-file');
        var renamePathInput = document.getElementById('fileRenamePath');
        var renameNameInput = document.getElementById('fileRenameName');
        var renameCancelButtons = document.querySelectorAll('.js-cancel-rename');

        editButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var filePath = button.getAttribute('data-file-path') || '';
                var fileName = button.getAttribute('data-file-name') || 'Редактирование файла';
                if (pathInput) {
                    pathInput.value = filePath;
                }
                if (titleEl) {
                    titleEl.textContent = 'Редактирование: ' + fileName;
                }
                modal.show();
                if (window.CodeMirror && contentArea) {
                    if (!contentArea._cm) {
                        contentArea._cm = CodeMirror.fromTextArea(contentArea, {
                            lineNumbers: true,
                            mode: 'application/x-httpd-php',
                            theme: 'default'
                        });
                    }
                    contentArea._cm.refresh();
                }
            });
        });

        cancelButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                modal.hide();
            });
        });

        deleteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (!deleteModal) {
                    return;
                }
                var filePath = button.getAttribute('data-file-path') || '';
                var fileName = button.getAttribute('data-file-name') || '';
                if (deletePathInput) {
                    deletePathInput.value = filePath;
                }
                if (deleteNameEl) {
                    deleteNameEl.textContent = fileName;
                }
                deleteModal.show();
            });
        });

        deleteCancelButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (deleteModal) {
                    deleteModal.hide();
                }
            });
        });

        renameButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (!renameModal) {
                    return;
                }
                var filePath = button.getAttribute('data-file-path') || '';
                var fileName = button.getAttribute('data-file-name') || '';
                if (renamePathInput) {
                    renamePathInput.value = filePath;
                }
                if (renameNameInput) {
                    renameNameInput.value = fileName;
                    renameNameInput.focus();
                    renameNameInput.select();
                }
                renameModal.show();
            });
        });

        renameCancelButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (renameModal) {
                    renameModal.hide();
                }
            });
        });

        var form = document.getElementById('fileEditForm');
        if (form) {
            form.addEventListener('submit', function () {
                if (contentArea && contentArea._cm) {
                    contentArea._cm.save();
                }
            });
        }
    })();
</script>
</body>
</html>
