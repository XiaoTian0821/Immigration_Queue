<?php
/**
 * ADMIN API - Reset today's queue numbering for a new operating day.
 * Closes all open (waiting/called/serving) queues as cancelled.
 * POST (admin)
 */
require_once __DIR__ . '/../config/config.php';

Security::apiAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::json(['ok' => false, 'error' => 'POST required'], 405);
}

$b     = Security::jsonBody();
$me    = Auth::currentUser();
$closed = QueueManager::resetDay((int)$me['id'], (string)($b['reason'] ?? 'New operating day'));

Security::json([
    'ok'     => true,
    'closed' => $closed,
    'message'=> $closed === 0
        ? 'No open queues today. Numbering will start at 001 for tomorrow.'
        : "Closed $closed open queue(s). Tomorrow's numbering starts fresh at 001.",
]);
