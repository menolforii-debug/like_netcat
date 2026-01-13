<?php
// Здесь можно описать функции построения меню или другие helper-функции для макета.
/** @var array $ctx */
/** @var callable $body */

$title = (string) ($ctx['title'] ?? '');
$meta = $ctx['meta'] ?? [];
$site = $ctx['site'] ?? [];


function extractDataJsonWithId(array $items): array
{
    $result = [];

    foreach ($items as $item) {
        if (!empty($item['data_json']) && isset($item['id'])) {
            $decoded = json_decode($item['data_json'], true);

            if (is_array($decoded)) {
                $result[] = array_merge(
                    ['id' => $item['id']],
                    $decoded
                );
            }
        }
    }

    return $result;
}