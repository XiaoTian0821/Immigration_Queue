<?php
/**
 * ADMIN API - Officers CRUD.
 * POST JSON: { action: create|update|delete|toggle, user_id?, data? }
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

$b      = Security::jsonBody();
$action = (string)($b['action'] ?? '');

switch ($action) {
    case 'create':
        $d      = $b['data'] ?? [];
        $u      = trim((string)($d['username'] ?? ''));
        $fn     = trim((string)($d['full_name'] ?? ''));
        $pw     = (string)($d['password'] ?? '');
        $role   = ($d['role'] ?? 'officer') === 'admin' ? 'admin' : 'officer';
        $email  = trim((string)($d['email'] ?? ''));
        if ($u === '' || $fn === '' || strlen($pw) < 8) {
            Security::json(['ok' => false, 'error' => 'Username, full name and a password of at least 8 characters are required.'], 422);
        }
        $dup = (int)Database::value('SELECT COUNT(*) FROM users WHERE username = ?', [$u]);
        if ($dup > 0) {
            Security::json(['ok' => false, 'error' => 'Username already exists.'], 409);
        }
        Database::run(
            'INSERT INTO users (full_name, username, email, password, role, status) VALUES (?,?,?,?,?,"active")',
            [$fn, $u, $email !== '' ? $email : null, password_hash($pw, PASSWORD_BCRYPT), $role]
        );
        Security::log('officer_created', 'user=' . $u);
        Security::json(['ok' => true, 'message' => 'Officer account created.', 'id' => (int)Database::lastInsertId()]);

    case 'update':
        $d = $b['data'] ?? [];
        $id   = (int)($b['user_id'] ?? 0);
        $fn   = trim((string)($d['full_name'] ?? ''));
        $email= trim((string)($d['email'] ?? ''));
        $role = ($d['role'] ?? 'officer') === 'admin' ? 'admin' : 'officer';
        if ($fn === '' || $id <= 0) {
            Security::json(['ok' => false, 'error' => 'Invalid input.'], 422);
        }
        $sets = ['full_name = ?', 'email = ?', 'role = ?'];
        $params = [$fn, $email !== '' ? $email : null, $role];
        if (!empty($d['new_password'])) {
            $sets[] = 'password = ?';
            $params[] = password_hash((string)$d['new_password'], PASSWORD_BCRYPT);
        }
        $params[] = $id;
        $params[] = $id;
        Database::run('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ? AND id <> ?', $params);
        Security::log('officer_updated', "user_id=$id");
        Security::json(['ok' => true, 'message' => 'Officer updated.']);

    case 'toggle':
        $id = (int)($b['user_id'] ?? 0);
        if ($id === (int)Auth::currentUser()['id']) {
            Security::json(['ok' => false, 'error' => 'You cannot deactivate your own account.'], 422);
        }
        Database::run(
            'UPDATE users SET status = IF(status = "active", "inactive", "active") WHERE id = ?',
            [$id]
        );
        Security::json(['ok' => true, 'message' => 'Status toggled.']);

    case 'delete':
        $id = (int)($b['user_id'] ?? 0);
        if ($id === (int)Auth::currentUser()['id']) {
            Security::json(['ok' => false, 'error' => 'You cannot delete your own account.'], 422);
        }
        Database::run('DELETE FROM users WHERE id = ?', [$id]);
        Security::log('officer_deleted', "user_id=$id");
        Security::json(['ok' => true, 'message' => 'Officer account deleted.']);

    default:
        Security::json(['ok' => false, 'error' => 'Unknown action.'], 400);
}
