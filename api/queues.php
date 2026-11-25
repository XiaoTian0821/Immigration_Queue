<?php
/**
 * ADMIN API - Queue records lookup / admin issue & reset actions.
 *
 * GET  api/queues.php?status=waiting&service_id=1&date=2026-11-24
 * POST JSON: { action: issue_admin, service_id, applicant_id?, counter_id?, officer_id? }
 */
require_once __DIR__ . '/../config/config.php';

// Both roles may read the queue list; only admins may issue on behalf of others
Security::apiAuth(['admin', 'officer']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = (string)($_GET['status'] ?? '');
    $svc    = (int)($_GET['service_id'] ?? 0);
    $date   = (string)($_GET['date'] ?? QueueManager::today());
    $search = trim((string)($_GET['q'] ?? ''));

    $where = [];
    $params = [];
    if (in_array($status, ['waiting','called','serving','completed','skipped','cancelled'], true)) {
        $where[] = 'q.status = ?'; $params[] = $status;
    }
    if ($svc > 0) { $where[] = 'q.service_id = ?'; $params[] = $svc; }
    if ($date !== '') { $where[] = 'q.operating_date = ?'; $params[] = $date; }
    if ($search !== '') {
        $where[] = '(q.queue_number LIKE ? OR a.passport_number LIKE ? OR a.id_number LIKE ? OR a.full_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $sql = 'SELECT q.*, s.name service_name, s.code service_code,
                   a.full_name applicant_name, a.passport_number, a.id_number,
                   c.counter_number, u.full_name officer_name
            FROM queues q
            JOIN immigration_services s ON s.id = q.service_id
            LEFT JOIN applicants a ON a.id = q.applicant_id
            LEFT JOIN counters c ON c.id = q.counter_id
            LEFT JOIN users u ON u.id = q.assigned_officer_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY q.issued_at DESC LIMIT 500';
    $rows = Database::select($sql, $params);
    Security::json(['ok' => true, 'rows' => $rows, 'count' => count($rows)]);
}

// POST: admin can issue a queue on behalf of an applicant
if (!Auth::is('admin')) {
    Security::json(['ok' => false, 'error' => 'Forbidden: admin only.'], 403);
}
$b = Security::jsonBody();
$action = (string)($b['action'] ?? '');
if ($action !== 'issue_admin') {
    Security::json(['ok' => false, 'error' => 'Only issue_admin is supported here.'], 400);
}
$svc = (int)($b['service_id'] ?? 0);
if ($svc <= 0) {
    Security::json(['ok' => false, 'error' => 'service_id required.'], 422);
}
try {
    $q = QueueManager::issue($svc, (int)($b['applicant_id'] ?? 0) ?: null, (int)($b['officer_id'] ?? 0) ?: null);
    Security::json(['ok' => true, 'queue' => $q, 'message' => 'Queue ' . $q['queue_number'] . ' issued.']);
} catch (Throwable $e) {
    Security::json(['ok' => false, 'error' => $e->getMessage()], 400);
}
