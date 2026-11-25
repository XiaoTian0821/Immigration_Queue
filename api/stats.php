<?php
/**
 * Dashboard statistics API (admin + officer).
 * GET ?scope=officer|admin
 *
 * Returns card counts + chart datasets:
 *  - daily (last 14 days applicants by service, for admin)
 *  - monthly (last 6 months completed vs pending)
 *  - by_service (today)
 *  - status_mix (today, completed vs pending)
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin', 'officer']);

$scope = ($_GET['scope'] ?? 'admin') === 'officer' ? 'officer' : 'admin';
$me    = Auth::currentUser();
$today = QueueManager::today();
$stats = QueueManager::statusCounts($today);

if ($scope === 'officer') {
    $stats['today_mine_total'] = (int)Database::value(
        'SELECT COUNT(*) FROM queues WHERE assigned_officer_id = ? AND operating_date = ?',
        [(int)$me['id'], $today]
    );
    $stats['today_mine_completed'] = (int)Database::value(
        'SELECT COUNT(*) FROM queues WHERE assigned_officer_id = ? AND operating_date = ? AND status = "completed"',
        [(int)$me['id'], $today]
    );
    $stats['today_mine_serving'] = (int)Database::value(
        'SELECT COUNT(*) FROM queues WHERE assigned_officer_id = ? AND operating_date = ? AND status IN ("called","serving")',
        [(int)$me['id'], $today]
    );
}

// Last 14 days daily totals
$daily = Database::select(
    "SELECT DATE_FORMAT(operating_date, '%Y-%m-%d') d,
            SUM(status IN ('completed','skipped','cancelled')) closed,
            COUNT(*) total
     FROM queues
     WHERE operating_date > DATE_SUB(?, INTERVAL 14 DAY)
     GROUP BY operating_date
     ORDER BY operating_date",
    [$today]
);
// Last 6 months
$monthly = Database::select(
    "SELECT DATE_FORMAT(operating_date, '%Y-%m') m,
            SUM(status = 'completed') completed,
            SUM(status IN ('waiting','called','serving')) pending,
            COUNT(*) total
     FROM queues
     WHERE operating_date >= DATE_SUB(?, INTERVAL 6 MONTH)
     GROUP BY m
     ORDER BY m",
    [$today]
);
// Applicants by service today
$byService = Database::select(
    'SELECT s.name, SUM(q.status = "completed") completed, SUM(q.status IN ("waiting","called","serving")) open
     FROM immigration_services s
     LEFT JOIN queues q ON q.service_id = s.id AND q.operating_date = ?
     WHERE s.active = 1
     GROUP BY s.id
     ORDER BY s.id',
    [$today]
);
// Status mix today
$statusMix = Database::select(
    "SELECT CASE WHEN status IN ('waiting','called','serving') THEN 'pending' ELSE 'completed' END bucket, COUNT(*) c
     FROM queues WHERE operating_date = ? GROUP BY bucket",
    [$today]
);
$mix = ['pending' => 0, 'completed' => 0];
foreach ($statusMix as $r) { $mix[$r['bucket']] = (int)$r['c']; }

// Average waiting time (from issue to called/serving) & service time (from serving to completed)
$avgWait = (float)Database::value(
    "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, issued_at, serving_at)),0)
     FROM queues WHERE operating_date = ? AND serving_at IS NOT NULL AND status IN ('serving','completed','skipped','cancelled')",
    [$today]
);
$avgService = (float)Database::value(
    "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, serving_at, completed_at)),0)
     FROM queues WHERE operating_date = ? AND status = 'completed' AND serving_at IS NOT NULL AND completed_at IS NOT NULL",
    [$today]
);

Security::json([
    'ok'            => true,
    'today'         => $today,
    'stats'         => $stats,
    'daily'         => $daily,
    'monthly'       => $monthly,
    'by_service'    => $byService,
    'status_mix'    => $mix,
    'avg_wait_min'  => round($avgWait, 1),
    'avg_service_min' => round($avgService, 1),
]);
