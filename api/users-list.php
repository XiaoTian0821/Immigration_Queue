<?php
/**
 * ADMIN API - list user accounts.
 * GET api/users-list.php?ids=1,2  (optional filter by ids)
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

$ids = (string)($_GET['ids'] ?? '');
if ($ids !== '') {
    $list = array_filter(array_map(fn($x) => (int)$x, explode(',', $ids)));
    if (empty($list)) {
        Security::json(['ok' => true, 'rows' => []]);
    }
    $in = implode(',', $list);
    $rows = Database::select("SELECT * FROM users WHERE id IN ($in) ORDER BY id");
} else {
    $rows = Database::select('SELECT * FROM users ORDER BY role DESC, full_name');
}
Security::json(['ok' => true, 'rows' => $rows]);
