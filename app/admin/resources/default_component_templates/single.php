<?php
/** @var array|null $object */
if ($object === null) {
    return;
}
$title = (string) ($f_title ?? '');
$text = (string) ($f_text ?? '');
?>
<article class="component-single">
    <h1><?php echo $title; ?></h1>
    <?php if ($text !== ''): ?>
        <div class="content"><?php echo $text; ?></div>
    <?php endif; ?>
</article>
