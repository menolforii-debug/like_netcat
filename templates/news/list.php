<?php
/** GENERATED FILE. Do not edit manually. */
if (!isset($isSingle)) { $isSingle = false; }
if ($isSingle && isset($object) && is_array($object)) {
?>
<?php if (!empty($object['data']['titl'])): ?>
<h1><?= htmlspecialchars($object['data']['titl'], ENT_QUOTES, 'UTF-8') ?></h1>
<?php endif; ?>
<?php
} else {
?>
<?php foreach ($objects as $obj): ?>
<div><?= htmlspecialchars($obj['data']['titl'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>
<?php
}
