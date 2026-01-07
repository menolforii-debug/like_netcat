<?php
?>
<section class="mb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <h2 class="h5 mb-0"><?= htmlspecialchars($infoblock['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <span class="badge bg-secondary-subtle text-secondary">Новости</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($items)) : ?>
                <div class="p-4">
                    <div class="alert alert-light border mb-0">Нет новостей.</div>
                </div>
            <?php else : ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($items as $item) : ?>
                        <?php $data = $item['data'] ?? []; ?>
                        <div class="list-group-item">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                <div>
                                    <?php if (!empty($data['date'])) : ?>
                                        <div class="text-muted small mb-1"><?= htmlspecialchars($data['date'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <div class="fw-semibold"><?= htmlspecialchars($data['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($data['text'])) : ?>
                                        <div class="text-muted mt-2"><?= htmlspecialchars($data['text'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
