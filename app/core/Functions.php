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
