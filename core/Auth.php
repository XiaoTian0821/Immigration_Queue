<?php
/**
 * Authentication & session management.
 */
class Auth
{
    /**
     * Attempt login. Returns user row or null.
     */
    public static function attempt(string $username, string $password): ?array
    {
        $user = Database::row(
            'SELECT * FROM users WHERE username = ? AND status = "active" LIMIT 1',
            [$username]
        );
        if ($user === null || !password_verify($password, $user['password'])) {
            Security::log('login_failed', 'username=' . $username);
            return null;
        }
        // Regenerate session id to prevent fixation
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        Security::log('login_success', 'user=' . $user['username']);
        return $user;
    }

    public static function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'        => $_SESSION['user_id'],
            'username'  => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role'      => $_SESSION['role'] ?? '',
        ];
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            Security::log('logout', 'user=' . ($_SESSION['username'] ?? ''));
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function is(string ...$roles): bool
    {
        $u = self::currentUser();
        return $u !== null && in_array($u['role'], $roles, true);
    }
}
