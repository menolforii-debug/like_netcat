<?php
/** @var array $ctx */
/** @var callable $body */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php Layout::renderCss(); ?>
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (!empty($meta['description'])): ?>
        <meta name="description" content="<?= htmlspecialchars((string) $meta['description'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!empty($meta['keywords'])): ?>
        <meta name="keywords" content="<?= htmlspecialchars((string) $meta['keywords'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body class="bg-light">
<div class="page-wrapper d-flex flex-column min-vh-100">
    <div class="content-wrapper flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="/"><?= htmlspecialchars((string) ($site['title'] ?? 'CMS'), ENT_QUOTES, 'UTF-8') ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/admin.php">Админ</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="container py-4">
            <?php $body(); ?>
        </main>
    </div>
</div>
<?php Layout::renderJs(); ?>
</body>
</html>
