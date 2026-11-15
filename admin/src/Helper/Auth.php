<?php
declare(strict_types=1);

class Auth
{
    private const SESSION_KEY = 'admin_id';
    private const CSRF_KEY    = 'csrf_token';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $admin['id'];
        $_SESSION['admin_name']      = $admin['name'];
        $_SESSION['admin_email']     = $admin['email'];
        $_SESSION['admin_role']      = $admin['role'];
        $_SESSION['login_at']        = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: /index.php?page=login');
            exit;
        }
    }

    public static function id(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY])
            ? (int)$_SESSION[self::SESSION_KEY]
            : null;
    }

    public static function name(): string
    {
        return $_SESSION['admin_name'] ?? '';
    }

    public static function role(): string
    {
        return $_SESSION['admin_role'] ?? '';
    }

    // ─── CSRF ───────────────────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public static function verifyCsrf(string $token): bool
    {
        return !empty($_SESSION[self::CSRF_KEY])
            && hash_equals($_SESSION[self::CSRF_KEY], $token);
    }

    public static function csrfInput(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::csrfToken()) . '">';
    }
}
