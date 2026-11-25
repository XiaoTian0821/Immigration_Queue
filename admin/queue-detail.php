<?php
/**
 * Queue detail & action history (admin/officer).
 * GET queue-detail.php?id=123
 */
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin', 'officer']);

$id = (int)($_GET['id'] ?? 0);
$queue = Database::row(
    'SELECT q.*, s.name service_name, s.code service_code, a.full_name applicant_name,
            c.counter_number, u.full_name officer_name
     FROM queues q
     JOIN immigration_services s ON s.id = q.service_id
     LEFT JOIN applicants a ON a.id = q.applicant_id
     LEFT JOIN counters c ON c.id = q.counter_id
     LEFT JOIN users u ON u.id = q.assigned_officer_id
     WHERE q.id = ?', [$id]
);
if ($queue === null) {
    http_response_code(404);
    exit('Queue record not found.');
}
$logs = Database::select(
    'SELECT l.*, u.full_name user_full FROM queue_logs l
     LEFT JOIN users u ON u.id = l.user_id
     WHERE l.queue_id = ? ORDER BY l.id DESC', [$id]
);

$page_title = 'Queue ' . $queue['queue_number'] . ' — History';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-3">
      <h2 class="h6 fw-bold mb-3">Queue Details</h2>
      <dl class="row mb-0 small">
        <dt class="col-5">Queue No.</dt><dd class="col-7 queue-number fs-5"><?= Security::e($queue['queue_number']) ?></dd>
        <dt class="col-5">Service</dt><dd class="col-7"><?= Security::e($queue['service_name']) ?> (<?= Security::e($queue['service_code']) ?>)</dd>
        <dt class="col-5">Applicant</dt><dd class="col-7"><?= Security::e($queue['applicant_name'] ?? 'unregistered') ?></dd>
        <dt class="col-5">Counter</dt><dd class="col-7"><?= Security::e($queue['counter_number'] ?? '—') ?></dd>
        <dt class="col-5">Officer</dt><dd class="col-7"><?= Security::e($queue['officer_name'] ?? '—') ?></dd>
        <dt class="col-5">Status</dt><dd class="col-7"><span class="badge badge-<?= $queue['status'] ?>"><?= Security::e($queue['status']) ?></span></dd>
        <dt class="col-5">Issued</dt><dd class="col-7"><?= Security::e($queue['issued_at']) ?></dd>
        <?php if ($queue['cancellation_reason']): ?>
        <dt class="col-5">Cancel Reason</dt><dd class="col-7"><?= Security::e($queue['cancellation_reason']) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card p-3">
      <h2 class="h6 fw-bold mb-3">Action History</h2>
      <?php if (empty($logs)): ?>
        <p class="text-muted mb-0">No logged actions for this queue yet.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($logs as $l): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <div>
              <span class="badge bg-dark"><?= Security::e($l['action']) ?></span>
              <?php if ($l['details']): ?><span class="text-muted ms-2"><?= Security::e($l['details']) ?></span><?php endif; ?>
              <?php if ($l['user_full']): ?><small class="text-muted">by <?= Security::e($l['user_full']) ?></small><?php endif; ?>
            </div>
            <small class="text-muted"><?= Security::e($l['created_at']) ?></small>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../parts/layout-bottom.php'; ?>
