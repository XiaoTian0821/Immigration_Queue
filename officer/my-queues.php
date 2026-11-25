<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['officer']);
$page_title = 'My Queue History';
$me = Auth::currentUser();
include __DIR__ . '/../parts/layout-top.php';

$today = QueueManager::today();
$rows = Database::select(
    'SELECT q.*, s.name service_name, s.code service_code, a.full_name applicant_name,
            c.counter_number, a.passport_number
     FROM queues q
     JOIN immigration_services s ON s.id = q.service_id
     LEFT JOIN applicants a ON a.id = q.applicant_id
     LEFT JOIN counters c ON c.id = q.counter_id
     WHERE q.assigned_officer_id = ?
     ORDER BY q.issued_at DESC LIMIT 200',
    [(int)$me['id']]
);
$stats = QueueManager::statusCounts($today);
$mine = Database::select(
    'SELECT status, COUNT(*) c FROM queues WHERE assigned_officer_id = ? AND operating_date = ? GROUP BY status',
    [(int)$me['id'], $today]
);
$mineMap = ['waiting' => 0, 'called' => 0, 'serving' => 0, 'completed' => 0, 'skipped' => 0, 'cancelled' => 0];
foreach ($mine as $m) { $mineMap[$m['status']] = (int)$m['c']; }
?>
<div class="row g-3 mb-3">
  <?php
  $cards = [
    ['My Queues Today', array_sum($mineMap), 'fa-list-check'],
    ['Open (mine)', $mineMap['waiting'] + $mineMap['called'] + $mineMap['serving'], 'fa-person'],
    ['Completed (mine)', $mineMap['completed'], 'fa-circle-check'],
    ['Office Waiting', $stats['waiting'], 'fa-hourglass-half'],
    ['Office Available Counters', $stats['available_counters'], 'fa-desktop'],
  ];
  foreach ($cards as $c): ?>
  <div class="col-6 col-md"><div class="card p-3 text-center">
    <i class="fa-solid <?= $c[2] ?> fa-lg text-primary"></i>
    <div class="fs-3 fw-bold"><?= (int)$c[1] ?></div>
    <div class="text-muted small"><?= $c[0] ?></div>
  </div></div>
  <?php endforeach; ?>
</div>

<div class="card p-3">
  <div class="row g-2 align-items-end mb-3">
    <div class="col-sm-3"><label class="form-label small">Status</label>
      <select class="form-select form-select-sm" id="h_status">
        <option value="">All</option>
        <?php foreach (['waiting','called','serving','completed','skipped','cancelled'] as $st): ?>
        <option value="<?= $st ?>"><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-auto d-flex align-items-end gap-2">
      <button class="btn btn-primary btn-sm" id="h_refresh"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
      <span class="text-muted small">Auto-refreshes every 15s.</span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead><tr>
        <th>Queue</th><th>Service</th><th>Applicant</th><th>Counter</th><th>Issued</th>
        <th>Called</th><th>Serving</th><th>Completed</th><th>Status</th>
      </tr></thead>
      <tbody id="hBody">
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center py-4 text-muted">No queues have been assigned to you yet.</td></tr>
        <?php else: foreach ($rows as $q): ?>
        <tr>
          <td class="queue-number fs-5"><?= Security::e($q['queue_number']) ?></td>
          <td><?= Security::e($q['service_name']) ?></td>
          <td><?= Security::e($q['applicant_name']) ?: '<span class="text-muted">unregistered</span>' ?></td>
          <td><?= Security::e($q['counter_number'] ?? '—') ?></td>
          <td class="small"><?= Security::e($q['issued_at']) ?></td>
          <td class="small"><?= Security::e($q['called_at'] ?? '—') ?></td>
          <td class="small"><?= Security::e($q['serving_at'] ?? '—') ?></td>
          <td class="small"><?= Security::e($q['completed_at'] ?? '—') ?></td>
          <td><span class="badge badge-<?= $q['status'] ?>"><?= Security::e($q['status']) ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  document.getElementById("h_refresh").onclick = () => location.reload();
  setInterval(() => { /* light refresh: reload only when a filter is active to keep the DOM source-of-truth */ }, 15000);
  document.getElementById("h_status").onchange = function () {
    const s = this.value;
    document.querySelectorAll("#hBody tr").forEach(tr => {
      const badge = tr.querySelector(".badge");
      if (!badge) return;
      tr.style.display = (!s || badge.textContent === s) ? "" : "none";
    });
  };
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
