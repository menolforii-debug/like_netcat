<?php

final class Seo
{
    public static function resolve(array $section, ?array $object = null, string $fallbackTitle = ''): array
    {
        $objectData = [];
        if ($object !== null) {
            if (isset($object['data']) && is_array($object['data'])) {
                $objectData = $object['data'];
            } elseif (isset($object['data_json'])) {
                $decoded = json_decode((string) $object['data_json'], true);
                if (is_array($decoded)) {
                    $objectData = $decoded;
                }
            }
        }

        $sectionExtra = [];
        if (isset($section['extra']) && is_array($section['extra'])) {
            $sectionExtra = $section['extra'];
        } elseif (isset($section['extra_json'])) {
            $decoded = json_decode((string) $section['extra_json'], true);
            if (is_array($decoded)) {
                $sectionExtra = $decoded;
            }
        }

        $title = self::pick($objectData, $sectionExtra, 'seo_title');
        $description = self::pick($objectData, $sectionExtra, 'seo_description');
        $keywords = self::pick($objectData, $sectionExtra, 'seo_keywords');

        if ($title === '' && $fallbackTitle !== '') {
            $title = $fallbackTitle;
        }

        if ($title === '') {
            $title = (string) ($section['title'] ?? '');
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
        ];
    }

    private static function pick(array $objectData, array $sectionExtra, $key): string
    {
        if (isset($objectData[$key]) && $objectData[$key] !== '') {
            return (string) $objectData[$key];
        }

        if (isset($sectionExtra[$key]) && $sectionExtra[$key] !== '') {
            return (string) $sectionExtra[$key];
        }

        return '';
    }
}
