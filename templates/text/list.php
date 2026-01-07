<?php
/** GENERATED FILE. Do not edit manually. */
if (!isset($isSingle)) { $isSingle = false; }
if ($isSingle && isset($object) && is_array($object)) {
?>
<?php if (!empty($object['data']['text'])): ?>
<h1><?= htmlspecialchars($object['data']['text'], ENT_QUOTES, 'UTF-8') ?></h1>
<?php endif; ?>
<?php
} else {
?>
<?php foreach ($objects as $obj): ?>
<div><?= htmlspecialchars($obj['data']['text'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>
<?php
}
