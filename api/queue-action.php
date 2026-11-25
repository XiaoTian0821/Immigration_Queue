<?php
/**
 * OFFICER API - Queue status transitions.
 *
 * POST JSON: { action, service_id, counter_id, queue_id, reason? }
 *
 * actions:
 *   call_next  - call the next waiting applicant for a service to a counter
 *   recall     - recall current serving number (put back to called)  [queue_id]
 *   serving    - mark called queue as serving                        [queue_id]
 *   complete   - complete service                                     [queue_id]
 *   skip       - skip an applicant                                     [queue_id]
 *   cancel     - cancel a queue with reason                           [queue_id, reason]
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['officer', 'admin']);

$b = Security::jsonBody();
$action = (string)($b['action'] ?? '');
$me     = Auth::currentUser();
$uid    = (int)$me['id'];

try {
    switch ($action) {
        case 'call_next':
            $svc = (int)($b['service_id'] ?? 0);
            $cnt = (int)($b['counter_id'] ?? 0);
            if ($svc <= 0 || $cnt <= 0) {
                Security::json(['ok' => false, 'error' => 'service_id and counter_id are required.'], 422);
            }
            $q = QueueManager::callNext($svc, $cnt, $uid);
            if ($q === null) {
                Security::json(['ok' => true, 'empty' => true, 'message' => 'No waiting applicant for this service.']);
            }
            Security::json([
                'ok'   => true,
                'queue'=> $q,
                'message' => 'Called ' . $q['queue_number'],
            ]);

        case 'serving':
            $q = QueueManager::markServing((int)($b['queue_id'] ?? 0), $uid);
            Security::json(['ok' => true, 'queue' => $q, 'message' => $q['queue_number'] . ' now serving.']);

        case 'complete':
            $q = QueueManager::complete((int)($b['queue_id'] ?? 0), $uid);
            Security::json(['ok' => true, 'queue' => $q, 'message' => $q['queue_number'] . ' completed.']);

        case 'skip':
            $q = QueueManager::skip((int)($b['queue_id'] ?? 0), $uid);
            Security::json(['ok' => true, 'queue' => $q, 'message' => $q['queue_number'] . ' skipped.']);

        case 'recall':
            $q = QueueManager::recall((int)($b['queue_id'] ?? 0), $uid);
            Security::json(['ok' => true, 'queue' => $q, 'message' => $q['queue_number'] . ' recalled.']);

        case 'cancel':
            $q = QueueManager::cancel((int)($b['queue_id'] ?? 0), $uid, (string)($b['reason'] ?? ''));
            Security::json(['ok' => true, 'queue' => $q, 'message' => $q['queue_number'] . ' cancelled.']);

        default:
            Security::json(['ok' => false, 'error' => 'Unknown action: ' . Security::e($action)], 400);
    }
} catch (Throwable $e) {
    Security::json(['ok' => false, 'error' => $e->getMessage()], 400);
}
