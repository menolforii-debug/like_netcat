<?php
?>
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<?php $data = $items[0]['data'] ?? []; ?>
=======
<?php $item = $item ?? ($items[0] ?? null); ?>
<?php $data = $item['data'] ?? []; ?>
>>>>>>> origin/codex/add-netcat-style-control-layer-k9g8k9
=======
<?php $item = $item ?? ($items[0] ?? null); ?>
<?php $data = $item['data'] ?? []; ?>
>>>>>>> origin/codex/add-netcat-style-control-layer-7kxozg
=======
<?php $item = $item ?? ($items[0] ?? null); ?>
<?php $data = $item['data'] ?? []; ?>
>>>>>>> origin/codex/add-netcat-style-control-layer-wgkqxy
<article class="card shadow-sm mb-5">
    <div class="card-body">
        <h1 class="h4 mb-2"><?= htmlspecialchars($data['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></h1>
=======
<?php $item = $item ?? ($items[0] ?? null); ?>
<?php $data = $item['data'] ?? []; ?>
<article class="card shadow-sm mb-5">
    <div class="card-header bg-white">
        <h1 class="h4 mb-0"><?= htmlspecialchars($data['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <div class="card-body">
>>>>>>> origin/codex/add-netcat-style-control-layer-cda74t
        <?php if (!empty($data['date'])) : ?>
            <div class="text-muted small mb-3"><?= htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (!empty($data['text'])) : ?>
            <div class="text-body"><?= nl2br(htmlspecialchars($data['text'], ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>
    </div>
</article>
