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
<<<<<<< HEAD
=======
                    <?php if (!empty($editMode) && !empty($item['controls']['delete_url'])) : ?>
                        <div>
                            <a href="<?= htmlspecialchars($item['controls']['delete_url'], ENT_QUOTES, 'UTF-8') ?>">Удалить</a>
                        </div>
                    <?php endif; ?>
>>>>>>> origin/codex/-codex.yaml-7vmunj
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
