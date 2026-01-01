<?php
declare(strict_types=1);

final class Auth
{
    public static function check(string $role): bool
    {
        return isset($_GET['edit']) && $_GET['edit'] === '1';
    }
}
