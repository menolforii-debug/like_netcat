<?php
/** GENERATED FILE. Do not edit manually. */
if (!isset($isSingle)) { $isSingle = false; }
if ($isSingle && isset($object) && is_array($object)) {
?>
<?php if (!empty($object['data']['title'])): ?>
<h1><?= htmlspecialchars($object['data']['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<?php endif; ?>
<?php
} else {
?>
<?php foreach ($objects as $obj): ?>
<div><?= htmlspecialchars($obj['data']['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>
<?php
}
// Системные настройки шаблона компонента.
// Пример: игнорировать выборку по разделу (закомментировано).
// $objects = DB::fetchAll(
//     'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
//     FROM objects
//     WHERE component_id = :component_id AND is_deleted = 0 AND status = :status
//     ORDER BY id ASC',
//     ['component_id' => (int) ($component['id'] ?? 0), 'status' => 'published']
// );
// $objects = array_map(static function ($object) {
//     $data = json_decode((string) $object['data_json'], true);
//     if (!is_array($data)) {
//         $data = [];
//     }
//     $object['data'] = $data;
//     return $object;
// }, $objects);
?>
