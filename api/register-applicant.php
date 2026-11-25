<?php
/**
 * OFFICER API - register a new applicant (with optional queue issue).
 * POST JSON: {
 *   full_name (required), passport_number?, id_number?, phone?, email?,
 *   nationality?, date_of_birth?, service_id? (optional: issue queue now)
 * }
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['officer']);

$b    = Security::jsonBody();
$name = trim((string)($b['full_name'] ?? ''));
if ($name === '' || mb_strlen($name) < 2) {
    Security::json(['ok' => false, 'error' => 'Applicant full name is required.'], 422);
}
$pass = strtoupper(trim((string)($b['passport_number'] ?? '')));
$idNo = strtoupper(trim((string)($b['id_number'] ?? '')));

if ($pass !== '') {
    $dup = (int)Database::value('SELECT COUNT(*) FROM applicants WHERE passport_number = ?', [$pass]);
    if ($dup > 0) {
        Security::json(['ok' => false, 'error' => 'Passport number already registered to another applicant.'], 409);
    }
}
if ($idNo !== '') {
    $dup = (int)Database::value('SELECT COUNT(*) FROM applicants WHERE id_number = ?', [$idNo]);
    if ($dup > 0) {
        Security::json(['ok' => false, 'error' => 'ID number already registered to another applicant.'], 409);
    }
}

$me = Auth::currentUser();
Database::run(
    'INSERT INTO applicants
       (full_name, passport_number, id_number, phone, email, nationality, date_of_birth, registered_by_user)
     VALUES (?,?,?,?,?,?,?,?)',
    [
        $name,
        $pass !== '' ? $pass : null,
        $idNo !== '' ? $idNo : null,
        trim((string)($b['phone'] ?? '')) ?: null,
        trim((string)($b['email'] ?? '')) ?: null,
        trim((string)($b['nationality'] ?? '')) ?: null,
        (string)($b['date_of_birth'] ?? '') !== '' ? $b['date_of_birth'] : null,
        (int)$me['id'],
    ]
);
$applicantId = (int)Database::lastInsertId();

$payload = ['ok' => true, 'applicant_id' => $applicantId, 'message' => 'Applicant registered.'];

// Optional immediate queue issue
$svc = (int)($b['service_id'] ?? 0);
if ($svc > 0) {
    try {
        $q = QueueManager::issue($svc, $applicantId, (int)$me['id']);
        $payload['queue'] = $q;
        $payload['message'] = 'Applicant registered and queue ' . $q['queue_number'] . ' issued.';
    } catch (Throwable $e) {
        $payload['message'] = 'Applicant registered, but queue issue failed: ' . $e->getMessage();
    }
}

Security::json($payload);
