<?php
?>
<section class="mb-4">
    <h2 class="h4 mb-3"><?= htmlspecialchars($infoblock['name'], ENT_QUOTES, 'UTF-8') ?></h2>

    <?php if (empty($items)) : ?>
        <p class="text-muted">Нет новостей.</p>
    <?php else : ?>
        <ul class="list-group">
            <?php foreach ($items as $item) : ?>
                <?php $data = $item['data'] ?? []; ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php if (!empty($data['date'])) : ?>
                                <time class="text-muted small me-2"><?= htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8') ?></time>
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($data['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($data['text'])) : ?>
                                <div class="mt-2"><?= htmlspecialchars($data['text'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($editMode) && ($item['status'] ?? '') === 'draft') : ?>
                            <span class="badge bg-warning text-dark">Draft</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($editMode) && !empty($item['controls']['delete_url'])) : ?>
                        <div class="mt-2">
                            <a class="btn btn-sm btn-outline-danger" href="<?= htmlspecialchars($item['controls']['delete_url'], ENT_QUOTES, 'UTF-8') ?>">Удалить</a>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
