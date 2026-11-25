<?php
/**
 * ADMIN API - Immigration services CRUD.
 * POST JSON: { action: create|update|delete|toggle, service_id?, data? }
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

// GET -> list services (optional ?ids=1,2 filter)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ids = (string)($_GET['ids'] ?? '');
    if ($ids !== '') {
        $list = array_map(fn($x) => (int)$x, explode(',', $ids));
        $in   = implode(',', $list);
        $rows = Database::select("SELECT * FROM immigration_services WHERE id IN ($in) ORDER BY id");
    } else {
        $rows = Database::select(
            'SELECT s.*, (SELECT COUNT(*) FROM queues q WHERE q.service_id = s.id AND q.operating_date = ?) today_count
             FROM immigration_services s ORDER BY s.id',
            [QueueManager::today()]
        );
    }
    Security::json(['ok' => true, 'rows' => $rows]);
}

$b      = Security::jsonBody();
$action = (string)($b['action'] ?? '');

switch ($action) {
    case 'create':
        $d      = $b['data'] ?? [];
        $name   = trim((string)($d['name'] ?? ''));
        $code   = strtoupper(trim((string)($d['code'] ?? '')));
        $est    = max(1, (int)($d['est_minutes'] ?? 15));
        $desc   = trim((string)($d['description'] ?? ''));
        if ($name === '' || !preg_match('/^[A-Z]{2}$/', $code)) {
            Security::json(['ok' => false, 'error' => 'Name and a 2-letter code (A-Z) are required.'], 422);
        }
        $dup = (int)Database::value('SELECT COUNT(*) FROM immigration_services WHERE code = ? OR name = ?', [$code, $name]);
        if ($dup > 0) {
            Security::json(['ok' => false, 'error' => 'That code or name is already in use.'], 409);
        }
        Database::run(
            'INSERT INTO immigration_services (name, code, description, est_minutes, active) VALUES (?,?,?,?,1)',
            [$name, $code, $desc !== '' ? $desc : null, $est]
        );
        Security::log('service_created', "$code - $name");
        Security::json(['ok' => true, 'message' => 'Service created.', 'id' => (int)Database::lastInsertId()]);

    case 'update':
        $d  = $b['data'] ?? [];
        $id = (int)($b['service_id'] ?? 0);
        if ($id <= 0) {
            Security::json(['ok' => false, 'error' => 'service_id required.'], 422);
        }
        $name = trim((string)($d['name'] ?? ''));
        $est  = max(1, (int)($d['est_minutes'] ?? 15));
        $desc = trim((string)($d['description'] ?? ''));
        if ($name === '') {
            Security::json(['ok' => false, 'error' => 'Name required.'], 422);
        }
        Database::run(
            'UPDATE immigration_services SET name = ?, description = ?, est_minutes = ? WHERE id = ?',
            [$name, $desc !== '' ? $desc : null, $est, $id]
        );
        Security::log('service_updated', "id=$id");
        Security::json(['ok' => true, 'message' => 'Service updated.']);

    case 'toggle':
        $id = (int)($b['service_id'] ?? 0);
        Database::run('UPDATE immigration_services SET active = IF(active = 1, 0, 1) WHERE id = ?', [$id]);
        Security::json(['ok' => true, 'message' => 'Status toggled.']);

    case 'delete':
        $id = (int)($b['service_id'] ?? 0);
        $used = (int)Database::value('SELECT COUNT(*) FROM queues WHERE service_id = ?', [$id]);
        if ($used > 0) {
            Security::json(['ok' => false, 'error' => 'Service has ' . $used . ' queue record(s). Deactivate it instead.'], 409);
        }
        Database::run('DELETE FROM immigration_services WHERE id = ?', [$id]);
        Security::log('service_deleted', "id=$id");
        Security::json(['ok' => true, 'message' => 'Service deleted.']);

    default:
        Security::json(['ok' => false, 'error' => 'Unknown action.'], 400);
}
