<?php
namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function valido(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
