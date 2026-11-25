<?php
/**
 * Applicant lookup API.
 *
 * GET  api/applicants.php?q=PASS123   (officer/admin; matches passport, id, name)
 *      returns matching applicants + their queues for today
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin', 'officer']);

$q = strtoupper(trim((string)($_GET['q'] ?? '')));
if ($q === '') {
    Security::json(['ok' => true, 'rows' => []]);
}

$like = '%' . $q . '%';
$rows = Database::select(
    'SELECT a.*, u.full_name registered_by FROM applicants a
     LEFT JOIN users u ON u.id = a.registered_by_user
     WHERE a.passport_number LIKE ? OR a.id_number LIKE ? OR a.full_name LIKE ?
     ORDER BY a.created_at DESC LIMIT 50',
    [$like, $like, $like]
);

$today = QueueManager::today();
foreach ($rows as &$r) {
    $r['queues_today'] = Database::select(
        'SELECT q.id, q.queue_number, q.status, q.issued_at, q.called_at, s.name service_name, c.counter_number
         FROM queues q
         JOIN immigration_services s ON s.id = q.service_id
         LEFT JOIN counters c ON c.id = q.counter_id
         WHERE q.applicant_id = ? AND q.operating_date = ?
         ORDER BY q.issued_at DESC LIMIT 10',
        [(int)$r['id'], $today]
    );
}

Security::json(['ok' => true, 'rows' => $rows]);
