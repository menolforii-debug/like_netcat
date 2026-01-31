<?php

/**
 * Уменьшает изображение до заданных габаритов и возвращает путь сохранённого файла.
 * Если изображение меньше лимитов, возвращает исходный путь.
 */
function resize_image(string $sourcePath, int $maxWidth, int $maxHeight, ?string $destPath = null, int $quality = 85): string
{
    if (!is_file($sourcePath)) {
        return $sourcePath;
    }

    if ($maxWidth <= 0 || $maxHeight <= 0) {
        return $sourcePath;
    }

    $info = getimagesize($sourcePath);
    if ($info === false) {
        return $sourcePath;
    }

    [$width, $height] = $info;
    $mime = $info['mime'] ?? '';

    if ($width <= 0 || $height <= 0) {
        return $sourcePath;
    }

    if ($width <= $maxWidth && $height <= $maxHeight) {
        return $sourcePath;
    }

    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));

    switch ($mime) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            return $sourcePath;
    }

    if ($source === false) {
        return $sourcePath;
    }

    $destPath = $destPath ?: $sourcePath;
    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    if ($canvas === false) {
        imagedestroy($source);
        return $sourcePath;
    }

    if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($canvas, $destPath, max(0, min(100, $quality)));
            break;
        case 'image/png':
            $pngQuality = (int) round((100 - max(0, min(100, $quality))) / 10);
            imagepng($canvas, $destPath, max(0, min(9, $pngQuality)));
            break;
        case 'image/gif':
            imagegif($canvas, $destPath);
            break;
        case 'image/webp':
            imagewebp($canvas, $destPath, max(0, min(100, $quality)));
            break;
    }

    imagedestroy($canvas);
    imagedestroy($source);

    return $destPath;
}

function browse_messages(array $cc_env, int $range, $user_template = false): string
{
    $currentPage = isset($cc_env['current_page']) ? (int) $cc_env['current_page'] : 1;
    $totalPages = isset($cc_env['total_pages']) ? (int) $cc_env['total_pages'] : 0;
    $baseUrl = isset($cc_env['base_url']) ? (string) $cc_env['base_url'] : '';
    $queryParams = isset($cc_env['query_params']) && is_array($cc_env['query_params']) ? $cc_env['query_params'] : [];
    $perPage = isset($cc_env['per_page']) ? (int) $cc_env['per_page'] : 0;
    $totalItems = isset($cc_env['total_items']) ? (int) $cc_env['total_items'] : 0;

    if ($totalPages <= 1 || $range <= 0) {
        return '';
    }

    $templates = is_array($user_template) ? $user_template : [];
    $browseMsg = [
        'prefix' => $templates['prefix'] ?? '',
        'suffix' => $templates['suffix'] ?? '',
        'active' => $templates['active'] ?? '%PAGE',
        'unactive' => $templates['unactive'] ?? '<a href="%URL">%PAGE</a>',
        'divider' => $templates['divider'] ?? ' ',
        'first' => $templates['first'] ?? '<a href="%URL">«</a>',
        'last' => $templates['last'] ?? '<a href="%URL">»</a>',
        'prev' => $templates['prev'] ?? '<a href="%URL">‹</a>',
        'next' => $templates['next'] ?? '<a href="%URL">›</a>',
        'ellipsis' => $templates['ellipsis'] ?? '...',
    ];

    $currentPage = max(1, $currentPage);
    $totalPages = max(1, $totalPages);
    $range = max(1, $range);

    $start = 1;
    $end = $totalPages;
    if ($totalPages > $range) {
        $half = (int) floor($range / 2);
        $start = $currentPage - $half;
        $end = $start + $range - 1;
        if ($start < 1) {
            $start = 1;
            $end = $range;
        }
        if ($end > $totalPages) {
            $end = $totalPages;
            $start = max(1, $end - $range + 1);
        }
    }

    $buildUrl = static function (string $baseUrl, array $params): string {
        $query = http_build_query($params);
        if ($query === '') {
            return $baseUrl;
        }
        $delimiter = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $delimiter . $query;
    };

    $renderTemplate = static function (string $template, int $page, string $url, int $perPage, int $totalItems): string {
        $from = $perPage > 0 ? (($page - 1) * $perPage + 1) : 0;
        $to = $perPage > 0 ? min($page * $perPage, $totalItems) : 0;
        return strtr($template, [
            '%PAGE' => (string) $page,
            '%URL' => $url,
            '%FROM' => (string) $from,
            '%TO' => (string) $to,
        ]);
    };

    $parts = [];
    if ($currentPage > 1) {
        $parts[] = $renderTemplate(
            $browseMsg['first'],
            1,
            $buildUrl($baseUrl, array_merge($queryParams, ['page' => 1])),
            $perPage,
            $totalItems
        );
        $parts[] = $renderTemplate(
            $browseMsg['prev'],
            $currentPage - 1,
            $buildUrl($baseUrl, array_merge($queryParams, ['page' => $currentPage - 1])),
            $perPage,
            $totalItems
        );
    }

    if ($start > 1) {
        $parts[] = $browseMsg['ellipsis'];
    }

    for ($page = $start; $page <= $end; $page++) {
        $url = $buildUrl($baseUrl, array_merge($queryParams, ['page' => $page]));

        $template = $page === $currentPage ? $browseMsg['active'] : $browseMsg['unactive'];
        $parts[] = $renderTemplate($template, $page, $url, $perPage, $totalItems);
    }

    if ($end < $totalPages) {
        $parts[] = $browseMsg['ellipsis'];
    }

    if ($currentPage < $totalPages) {
        $parts[] = $renderTemplate(
            $browseMsg['next'],
            $currentPage + 1,
            $buildUrl($baseUrl, array_merge($queryParams, ['page' => $currentPage + 1])),
            $perPage,
            $totalItems
        );
        $parts[] = $renderTemplate(
            $browseMsg['last'],
            $totalPages,
            $buildUrl($baseUrl, array_merge($queryParams, ['page' => $totalPages])),
            $perPage,
            $totalItems
        );
    }

    return $browseMsg['prefix'] . implode($browseMsg['divider'], $parts) . $browseMsg['suffix'];
}
