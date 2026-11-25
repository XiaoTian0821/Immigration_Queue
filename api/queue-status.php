<?php
/**
 * PUBLIC API - status of a specific queue record (ticket polling).
 * GET api/queue-status.php?id=123
 * Returns only non-personal fields (security requirement).
 */
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Security::json(['ok' => false, 'error' => 'GET required'], 405);
}

$id = (int)($_GET['id'] ?? 0);
$row = Database::row(
    'SELECT q.id, q.queue_number, q.status, q.operating_date, c.counter_number, s.name service_name,
            (SELECT COUNT(*) FROM queues q2
             WHERE q2.service_id = q.service_id AND q2.operating_date = q.operating_date
               AND q2.status = "waiting" AND q2.id < q.id) waiting_ahead
     FROM queues q
     JOIN immigration_services s ON s.id = q.service_id
     LEFT JOIN counters c ON c.id = q.counter_id
     WHERE q.id = ?',
    [$id]
);
if ($row === null) {
    Security::json(['ok' => false, 'error' => 'Queue not found.'], 404);
}
// Personal data (applicant name / passport) is deliberately not exposed.
Security::json(['ok' => true, 'queue_number' => $row['queue_number'], 'status' => $row['status'],
                'service' => $row['service_name'], 'counter' => $row['counter_number'],
                'waiting_ahead' => (int)$row['waiting_ahead']]);
