<?php
declare(strict_types=1);
?>
<article>
    <?php $data = $items[0]['data'] ?? []; ?>
    <h2><?= htmlspecialchars($data['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (!empty($data['date'])) : ?>
        <time><?= htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8') ?></time>
    <?php endif; ?>
    <?php if (!empty($data['text'])) : ?>
        <p><?= htmlspecialchars($data['text'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</article>
