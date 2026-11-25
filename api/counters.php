<?php
/**
 * ADMIN API - Counters CRUD.
 * POST JSON: { action: create|update|delete|toggle-status|toggle, counter_id?, data? }
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

// GET -> list counters (optional ?ids=1,2 filter)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ids = (string)($_GET['ids'] ?? '');
    if ($ids !== '') {
        $list = array_map(fn($x) => (int)$x, explode(',', $ids));
        $in   = implode(',', $list);
        $rows = Database::select("SELECT * FROM counters WHERE id IN ($in) ORDER BY id");
    } else {
        $rows = Database::select(
            'SELECT c.*, (SELECT COUNT(*) FROM queues q WHERE q.counter_id = c.id AND q.operating_date = ? AND q.status IN ("called","serving")) active_now
             FROM counters c ORDER BY c.id',
            [QueueManager::today()]
        );
    }
    Security::json(['ok' => true, 'rows' => $rows]);
}

$b      = Security::jsonBody();
$action = (string)($b['action'] ?? '');

switch ($action) {
    case 'create':
        $d  = $b['data'] ?? [];
        $no = strtoupper(trim((string)($d['counter_number'] ?? '')));
        $loc = trim((string)($d['location'] ?? ''));
        if ($no === '') {
            Security::json(['ok' => false, 'error' => 'Counter number is required.'], 422);
        }
        $dup = (int)Database::value('SELECT COUNT(*) FROM counters WHERE counter_number = ?', [$no]);
        if ($dup > 0) {
            Security::json(['ok' => false, 'error' => 'Counter number already exists.'], 409);
        }
        Database::run(
            'INSERT INTO counters (counter_number, location, status, active) VALUES (?,?, "available", 1)',
            [$no, $loc !== '' ? $loc : null]
        );
        Security::log('counter_created', "counter=$no");
        Security::json(['ok' => true, 'message' => 'Counter created.', 'id' => (int)Database::lastInsertId()]);

    case 'update':
        $d  = $b['data'] ?? [];
        $id = (int)($b['counter_id'] ?? 0);
        $no = strtoupper(trim((string)($d['counter_number'] ?? '')));
        $loc = trim((string)($d['location'] ?? ''));
        if ($id <= 0 || $no === '') {
            Security::json(['ok' => false, 'error' => 'Invalid input.'], 422);
        }
        Database::run('UPDATE counters SET counter_number = ?, location = ? WHERE id = ?', [$no, $loc !== '' ? $loc : null, $id]);
        Security::json(['ok' => true, 'message' => 'Counter updated.']);

    case 'toggle-status':
        $id = (int)($b['counter_id'] ?? 0);
        $newStatus = (string)($b['status'] ?? 'available');
        if (!in_array($newStatus, ['available', 'busy', 'closed'], true)) {
            $newStatus = 'available';
        }
        Database::run('UPDATE counters SET status = ? WHERE id = ?', [$newStatus, $id]);
        Security::json(['ok' => true, 'message' => 'Counter status set to ' . $newStatus . '.']);

    case 'toggle':
        $id = (int)($b['counter_id'] ?? 0);
        Database::run('UPDATE counters SET active = IF(active = 1, 0, 1) WHERE id = ?', [$id]);
        Security::json(['ok' => true, 'message' => 'Status toggled.']);

    case 'delete':
        $id = (int)($b['counter_id'] ?? 0);
        $used = (int)Database::value('SELECT COUNT(*) FROM queues WHERE counter_id = ? AND operating_date = ?', [$id, QueueManager::today()]);
        if ($used > 0) {
            Security::json(['ok' => false, 'error' => 'Counter is in use today. Deactivate it instead.'], 409);
        }
        Database::run('DELETE FROM counters WHERE id = ?', [$id]);
        Security::log('counter_deleted', "counter_id=$id");
        Security::json(['ok' => true, 'message' => 'Counter deleted.']);

    default:
        Security::json(['ok' => false, 'error' => 'Unknown action.'], 400);
}
