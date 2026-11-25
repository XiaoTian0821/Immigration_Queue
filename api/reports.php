<?php
/**
 * ADMIN API - Reports & CSV export.
 *
 * GET  api/reports.php?type=daily|monthly|service|officer|counter|avg
 * GET  api/reports.php?export=csv&type=daily&date=2026-11-24   -> streams a CSV download
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

$type   = (string)($_GET['type'] ?? 'daily');
$export = (bool)($_GET['export'] ?? false);
$date   = (string)($_GET['date'] ?? QueueManager::today());
$month  = (string)($_GET['month'] ?? date('Y-m'));
$svcId  = (int)($_GET['service_id'] ?? 0);
$offId  = (int)($_GET['officer_id'] ?? 0);
$cId    = (int)($_GET['counter_id'] ?? 0);

$rows = [];
$meta = ['type' => $type, 'date' => $date, 'month' => $month];

switch ($type) {
    case 'daily':
        $rows = Database::select(
            'SELECT q.id, q.queue_number, s.name service_name, a.full_name applicant_name,
                    a.passport_number, a.id_number, c.counter_number, u.full_name officer_name,
                    q.status, q.issued_at, q.called_at, q.serving_at, q.completed_at, q.cancellation_reason
             FROM queues q
             JOIN immigration_services s ON s.id = q.service_id
             LEFT JOIN applicants a ON a.id = q.applicant_id
             LEFT JOIN counters c ON c.id = q.counter_id
             LEFT JOIN users u ON u.id = q.assigned_officer_id
             WHERE q.operating_date = ? AND (? = 0 OR q.service_id = ?)
             ORDER BY q.issued_at',
            [$date, $svcId, $svcId]
        );
        break;

    case 'monthly':
        $rows = Database::select(
            'SELECT q.queue_number, s.name service_name, q.status, q.issued_at, q.operating_date
             FROM queues q
             JOIN immigration_services s ON s.id = q.service_id
             WHERE DATE_FORMAT(q.operating_date, "%Y-%m") = ?
                   AND (? = 0 OR q.service_id = ?)
             ORDER BY q.operating_date, q.issued_at
             LIMIT 5000',
            [$month, $svcId, $svcId]
        );
        break;

    case 'service':
        $rows = Database::select(
            'SELECT s.id service_id, s.name, s.code,
                    COUNT(q.id) total,
                    SUM(q.status = "completed") completed,
                    SUM(q.status IN ("waiting","called","serving")) open,
                    SUM(q.status = "skipped") skipped,
                    SUM(q.status = "cancelled") cancelled,
                    COALESCE(AVG(CASE WHEN q.status="completed" AND q.serving_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,q.issued_at,q.serving_at) END),0) avg_wait_min
             FROM immigration_services s
             LEFT JOIN queues q ON q.service_id = s.id AND q.operating_date = ?
             WHERE (? = 0 OR s.id = ?)
             GROUP BY s.id, s.name, s.code
             ORDER BY s.id',
            [$date, $svcId, $svcId]
        );
        break;

    case 'officer':
        $rows = Database::select(
            'SELECT u.id officer_id, u.full_name, u.username,
                    COUNT(q.id) assigned,
                    SUM(q.status = "completed") completed,
                    SUM(q.status IN ("called","serving")) active,
                    COALESCE(AVG(CASE WHEN q.status="completed" AND q.serving_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,q.serving_at,q.completed_at) END),0) avg_service_min
             FROM users u
             LEFT JOIN queues q ON q.assigned_officer_id = u.id AND q.operating_date = ?
             WHERE u.role = "officer" AND (? = 0 OR u.id = ?)
             GROUP BY u.id
             ORDER BY assigned DESC
             LIMIT 200',
            [$date, $offId, $offId]
        );
        break;

    case 'counter':
        $rows = Database::select(
            'SELECT c.id counter_id, c.counter_number, c.location, c.status,
                    COUNT(q.id) handled,
                    SUM(q.status = "completed") completed,
                    COALESCE(AVG(CASE WHEN q.status="completed" AND q.serving_at IS NOT NULL AND q.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,q.serving_at,q.completed_at) END),0) avg_service_min
             FROM counters c
             LEFT JOIN queues q ON q.counter_id = c.id AND q.operating_date = ?
             WHERE (? = 0 OR c.id = ?)
             GROUP BY c.id, c.counter_number, c.location, c.status
             ORDER BY c.id
             LIMIT 200',
            [$date, $cId, $cId]
        );
        break;

    case 'avg':
        $rows = Database::select(
            'SELECT s.name service_name,
                    COUNT(q.id) total,
                    COALESCE(AVG(CASE WHEN q.status="completed" AND q.serving_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,q.issued_at,q.serving_at) END),0) avg_wait_min,
                    COALESCE(AVG(CASE WHEN q.status="completed" AND q.serving_at IS NOT NULL AND q.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,q.serving_at,q.completed_at) END),0) avg_service_min,
                    COALESCE(AVG(CASE WHEN q.status="completed" AND q.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,q.issued_at,q.completed_at) END),0) avg_total_min
             FROM immigration_services s
             LEFT JOIN queues q ON q.service_id = s.id
             WHERE q.operating_date IS NOT NULL
                   AND q.operating_date >= DATE_SUB(?, INTERVAL 30 DAY)
             GROUP BY s.id, s.name
             ORDER BY s.id',
            [$date]
        );
        break;

    default:
        Security::json(['ok' => false, 'error' => 'Unknown report type.'], 400);
}

Security::log('report_generated', 'type=' . $type . ($export ? ' (csv)' : ''));

if ($export) {
    $cols = $rows ? array_keys($rows[0]) : [];
    $fname = 'iqms-report-' . $type . '-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $cols);
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($v) => $v === null ? '' : (string)$v, $r));
    }
    fclose($out);
    exit;
}

Security::json(['ok' => true, 'meta' => $meta + ['count' => count($rows)], 'rows' => $rows]);
