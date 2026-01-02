<?php
$item = $item ?? ($items[0] ?? null);
$data = is_array($item) ? ($item['data'] ?? []) : [];
?>

<article class="card shadow-sm mb-5">
    <div class="card-header bg-white">
        <h1 class="h4 mb-0"><?= htmlspecialchars($data['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="card-body">
        <?php if (!empty($data['date'])) : ?>
            <div class="text-muted small mb-3"><?= htmlspecialchars((string)$data['date'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!empty($data['text'])) : ?>
            <div class="text-body"><?= nl2br(htmlspecialchars((string)$data['text'], ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>
    </div>
</article>
