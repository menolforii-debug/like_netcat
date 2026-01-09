<?php
?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h1 class="h4 mb-0"><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <div class="card-body">
        <?php if (!empty($items)) : ?>
            <div class="list-group list-group-flush">
                <?php foreach ($items as $child) : ?>
                    <a class="list-group-item list-group-item-action" href="<?= htmlspecialchars($child['path'] ?? '/', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($child['title'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="text-muted">Разделов нет.</div>
        <?php endif; ?>
    </div>
</div>

<?= $core['infoblocks_html'] ?? '' ?>
