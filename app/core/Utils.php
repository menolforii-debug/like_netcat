<?php

final class Utils
{
    public static function isUrlSafe(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $value);
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return $host;
    }

    public static function decodeExtra(array $row): array
    {
        if (isset($row['extra']) && is_array($row['extra'])) {
            return $row['extra'];
        }

        $decoded = json_decode((string) ($row['extra_json'] ?? '{}'), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
