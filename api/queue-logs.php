<?php
/**
 * ADMIN API - queue log (action history) for a single queue record.
 * GET api/queue-logs.php?queue_id=12
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin', 'officer']);

$id = (int)($_GET['queue_id'] ?? 0);
if ($id <= 0) {
    Security::json(['ok' => false, 'error' => 'queue_id required'], 422);
}
$rows = Database::select(
    'SELECT l.*, u.full_name user_full
     FROM queue_logs l LEFT JOIN users u ON u.id = l.user_id
     WHERE l.queue_id = ? ORDER BY l.id DESC',
    [$id]
);
Security::json(['ok' => true, 'rows' => $rows]);
