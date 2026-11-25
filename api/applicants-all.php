<?php
/**
 * ADMIN API - paginated applicant listing with today's queue badges.
 * GET api/applicants-all.php?page=1&per=15&service_id=2
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

$page      = max(1, (int)($_GET['page'] ?? 1));
$per       = min(100, max(1, (int)($_GET['per'] ?? 15)));
$svcId     = (int)($_GET['service_id'] ?? 0);
$today     = QueueManager::today();

$base = 'FROM applicants a';
$sel  = 'SELECT a.*, u.full_name registered_by_name';
$where = [];
$params = [];

if ($svcId > 0) {
    $where[] = 'a.id IN (SELECT q.applicant_id FROM queues q WHERE q.service_id = ? AND q.operating_date = ? AND q.applicant_id IS NOT NULL)';
    $params = [$svcId, $today];
}

$sql = $sel . $base . ' LEFT JOIN users u ON u.id = a.registered_by_user'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY a.id DESC';
$total = (int)Database::value(
    'SELECT COUNT(*) ' . $base . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $params
);
$rows = Database::select(
    $sql . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params
);

foreach ($rows as &$r) {
    $r['queues_today'] = Database::select(
        'SELECT q.id, q.queue_number, q.status, s.name service_name, q.issued_at
         FROM queues q JOIN immigration_services s ON s.id = q.service_id
         WHERE q.applicant_id = ? AND q.operating_date = ? ORDER BY q.issued_at DESC LIMIT 10',
        [(int)$r['id'], $today]
    );
}

Security::json(['ok' => true, 'total' => $total, 'page' => $page, 'per' => $per, 'rows' => $rows]);
