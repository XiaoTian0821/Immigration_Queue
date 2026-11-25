<?php
/**
 * PUBLIC API - Issue a queue number for an applicant.
 *
 * POST JSON:
 *   full_name     (required)
 *   passport_number (optional, unique when set)
 *   id_number     (optional, unique when set)
 *   service_id    (required, int)
 *
 * Response: { ok, queue_number, service, waiting_ahead, estimated_wait_min }
 */
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::json(['ok' => false, 'error' => 'POST required'], 405);
}

$b = Security::jsonBody();
$name  = trim((string)($b['full_name'] ?? ''));
$svcId = (int)($b['service_id'] ?? 0);
$pass  = strtoupper(trim((string)($b['passport_number'] ?? '')));
$idNo  = strtoupper(trim((string)($b['id_number'] ?? '')));

if ($name === '' || mb_strlen($name) < 2) {
    Security::json(['ok' => false, 'error' => 'Please enter the applicant full name.'], 422);
}
if ($svcId <= 0) {
    Security::json(['ok' => false, 'error' => 'Please select an immigration service.'], 422);
}

// Rate-limit friendly guard: max 5 new queue numbers per passport/id per day
if ($pass !== '' || $idNo !== '') {
    $day = QueueManager::today();
    $cnt = (int)Database::value(
        'SELECT COUNT(*) FROM queues q JOIN applicants a ON a.id = q.applicant_id
         WHERE q.operating_date = ? AND (a.passport_number = ? OR a.id_number = ?)',
        [$day, $pass ?: null, $idNo ?: null]
    );
    if ($cnt >= 5) {
        Security::json(['ok' => false, 'error' => 'Daily limit reached: 5 queue numbers per identification number.'], 429);
    }
}

// Find or create the applicant record (so officers/admins can view it later)
$applicantId = null;
if ($pass !== '' || $idNo !== '') {
    $applicant = null;
    if ($pass !== '') {
        $applicant = Database::row('SELECT * FROM applicants WHERE passport_number = ?', [$pass]);
    }
    if ($applicant === null && $idNo !== '') {
        $applicant = Database::row('SELECT * FROM applicants WHERE id_number = ?', [$idNo]);
    }
    if ($applicant !== null) {
        $applicantId = (int)$applicant['id'];
    } else {
        $uid = $_SESSION['user_id'] ?? null;
        Database::run(
            'INSERT INTO applicants (full_name, passport_number, id_number, registered_by_user) VALUES (?,?,?,?)',
            [$name, $pass !== '' ? $pass : null, $idNo !== '' ? $idNo : null, $uid]
        );
        $applicantId = (int)Database::lastInsertId();
    }
}

$officerId = $_SESSION['user_id'] ?? null;
$queue = QueueManager::issue($svcId, $applicantId, $officerId);

$svc = Database::row('SELECT * FROM immigration_services WHERE id = ?', [$svcId]);
$ahead = (int)Database::value(
    'SELECT COUNT(*) FROM queues WHERE service_id = ? AND operating_date = ? AND status = "waiting" AND id < ?',
    [$svcId, $queue['operating_date'], $queue['id']]
);

Security::json([
    'ok'               => true,
    'queue_id'         => (int)$queue['id'],
    'queue_number'     => $queue['queue_number'],
    'service'          => ['id' => $svcId, 'name' => $svc['name'] ?? '', 'code' => $svc['code'] ?? ''],
    'operating_date'   => $queue['operating_date'],
    'waiting_ahead'    => $ahead,
    'estimated_wait_min' => QueueManager::estimateWait($queue),
]);
