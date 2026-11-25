<?php
/**
 * ADMIN API - activity log listing.
 * GET api/activity.php?limit=100&offset=0&action=login_failed
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

$limit  = min(500, max(1, (int)($_GET['limit'] ?? 100)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$action = (string)($_GET['action'] ?? '');

$where = '';
$params = [];
if ($action !== '') {
    $where = 'WHERE a.action = ?';
    $params[] = $action;
}
$total = (int)Database::value(
    'SELECT COUNT(*) FROM activity_logs a ' . ($where ? $where : ''), $params
);
// LIMIT/OFFSET are embedded as validated ints (MySQL native prepares disallow placeholders there)
$rows = Database::select(
    'SELECT a.*, u.full_name user_full FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ' . ($where ? $where : '') . '
     ORDER BY a.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
    $params
);
Security::json(['ok' => true, 'total' => $total, 'rows' => $rows]);
