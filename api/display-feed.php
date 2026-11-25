<?php
/**
 * PUBLIC API - Live display feed for one or all services.
 *
 * GET: display.php?service_id=2   -> single service feed
 * GET: display.php               -> all active services (for rotating screens)
 *
 * Response: { ok, services: [ { service, current, nexts, completed, waiting, updated_at } ] }
 * No personal data is exposed (security requirement).
 */
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Security::json(['ok' => false, 'error' => 'GET required'], 405);
}

$serviceId = (int)($_GET['service_id'] ?? 0);

if ($serviceId > 0) {
    Security::json([
        'ok'       => true,
        'services' => [QueueManager::displayData($serviceId)],
    ]);
}

$active = Database::select('SELECT * FROM immigration_services WHERE active = 1 ORDER BY id');
$out = array_map(fn($s) => QueueManager::displayData((int)$s['id']), $active);
Security::json(['ok' => true, 'services' => $out]);
