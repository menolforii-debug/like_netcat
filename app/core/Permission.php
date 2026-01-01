<?php

final class Permission
{
    public static function canView(?array $user, array $infoblock): bool
    {
        return self::canAction($user, $infoblock, 'view');
    }

    public static function canEdit(?array $user, array $infoblock): bool
    {
        return self::canAction($user, $infoblock, 'edit');
    }

    public static function canAction(?array $user, array $infoblock, string $action): bool
    {
        $role = self::resolveRole($user);
        if ($role === 'admin') {
            return true;
        }

        $permissions = self::resolvePermissions($infoblock);
        if (!isset($permissions[$role]) || !is_array($permissions[$role])) {
            return false;
        }

        $allowed = $permissions[$role];
        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($action, $allowed, true);
    }

    private static function resolveRole(?array $user): string
    {
        if (!$user || !isset($user['role'])) {
            return 'guest';
        }

        return (string) $user['role'];
    }

    private static function resolvePermissions(array $infoblock): array
    {
        $extra = [];
        if (isset($infoblock['extra']) && is_array($infoblock['extra'])) {
            $extra = $infoblock['extra'];
        } elseif (isset($infoblock['extra_json'])) {
            $decoded = json_decode((string) $infoblock['extra_json'], true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $permissions = $extra['permissions'] ?? null;
        if (is_array($permissions)) {
            return $permissions;
        }

        return [
            'guest' => ['view'],
            'editor' => ['view', 'create', 'edit', 'publish', 'unpublish', 'archive', 'delete', 'restore', 'purge'],
        ];
    }
}
