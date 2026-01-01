<?php
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h1>

    <?php if (!empty($items)) : ?>
        <ul>
            <?php foreach ($items as $child) : ?>
                <li>
                    <a href="<?= htmlspecialchars($child['path'] ?? '/', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($child['title'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?= $core['infoblocks_html'] ?? '' ?>
</body>
</html>
