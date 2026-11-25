<?php
/**
 * Security helpers: CSRF, output escaping, auth guards, audit logging.
 */
class Security
{
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Hidden input for forms. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(): bool
    {
        $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return is_string($token) && $token !== '' && hash_equals(self::csrfToken(), $token);
    }

    /** Enforce CSRF on every POST. */
    public static function enforceCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !self::verifyCsrf()) {
            http_response_code(419);
            die('CSRF token invalid. Go back, refresh the page and try again.');
        }
    }

    /** XSS-safe echo helper. */
    public static function e(?string $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /** Write to activity_logs. */
    public static function log(string $action, string $details = ''): void
    {
        try {
            $uid = $_SESSION['user_id'] ?? null;
            $un  = $_SESSION['username'] ?? null;
            Database::run(
                'INSERT INTO activity_logs (user_id, username, action, details, ip_address) VALUES (?,?,?,?,?)',
                [$uid, $un, $action, mb_substr($details, 0, 250), self::ip()]
            );
        } catch (Throwable $e) {
            error_log('activity log failed: ' . $e->getMessage());
        }
    }

    /** Flash message support. */
    public static function flash(string $type, string $msg): void
    {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }

    public static function getFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    /** JSON API response helper. */
    public static function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    /** Parse the JSON request body (php://input). */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $d   = json_decode($raw ?: '[]', true);
        return is_array($d) ? $d : [];
    }

    /**
     * Require auth (and optional roles) for JSON APIs.
     * Verifies CSRF from the X-CSRF-Token header. Sends 401/403 on failure.
     */
    public static function apiAuth(array $roles = []): void
    {
        // CSRF check first (session always exists via config)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !self::verifyCsrf()) {
            self::json(['ok' => false, 'error' => 'CSRF token invalid or missing'], 419);
        }
        if (empty($_SESSION['user_id'])) {
            self::json(['ok' => false, 'error' => 'Unauthorized: please log in.'], 401);
        }
        if ($roles && !in_array($_SESSION['role'] ?? '', $roles, true)) {
            self::json(['ok' => false, 'error' => 'Forbidden: insufficient permissions.'], 403);
        }
    }

    /** Require login; optionally restrict to given roles. */
    public static function requireAuth(array $roles = []): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . 'public/login.php');
            exit;
        }
        if ($roles && !in_array($_SESSION['role'] ?? '', $roles, true)) {
            http_response_code(403);
            exit('Forbidden: you do not have permission to access this page.');
        }
    }

}
