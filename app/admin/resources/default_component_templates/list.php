<?php
/** @var array $items */
/** @var callable $setFields */
?>
<div class="component-list">
    <?php foreach ($items as $item): ?>
        <?php $setFields($item); ?>
        <article class="component-item">
            <h3>
                <?php if (!empty($fullLink)): ?>
                    <a href="<?php echo (string) $fullLink; ?>">
                        <?php echo (string) ($f_title ?? ''); ?>
                    </a>
                <?php else: ?>
                    <?php echo (string) ($f_title ?? ''); ?>
                <?php endif; ?>
            </h3>
            <?php if (!empty($f_text)): ?>
                <p><?php echo (string) $f_text; ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
