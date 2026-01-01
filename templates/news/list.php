<?php
?>
<section>
    <h2><?= htmlspecialchars($infoblock['name'], ENT_QUOTES, 'UTF-8') ?></h2>

    <?php if (empty($items)) : ?>
        <p>Нет новостей.</p>
    <?php else : ?>
        <ul>
            <?php foreach ($items as $item) : ?>
                <?php $data = $item['data'] ?? []; ?>
                <li>
                    <?php if (!empty($data['date'])) : ?>
                        <time><?= htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8') ?></time>
                    <?php endif; ?>
                    <strong><?= htmlspecialchars($data['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($data['text'])) : ?>
                        <div><?= htmlspecialchars($data['text'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
