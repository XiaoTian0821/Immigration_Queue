<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['officer']);
$page_title = 'Queue Operations Console';
include __DIR__ . '/../parts/layout-top.php';

$services = Database::select('SELECT id, name, code FROM immigration_services WHERE active = 1 ORDER BY id');
$counters = Database::select('SELECT * FROM counters WHERE active = 1 ORDER BY id');
?>
<div class="row g-3">
  <div class="col-lg-4">
    <!-- Service + counter selection -->
    <div class="card p-3 mb-3">
      <h2 class="h6 fw-bold mb-2"><i class="fa-solid fa-sliders text-primary me-2"></i>Focus</h2>
      <div class="mb-2"><label class="form-label small">Service</label>
        <select class="form-select form-select-sm" id="o_service">
          <option value="">All services</option>
          <?php foreach ($services as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= Security::e($s['code'] . ' · ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="form-label small">Counter</label>
        <select class="form-select form-select-sm" id="o_counter">
          <option value="">— choose counter —</option>
          <?php foreach ($counters as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= Security::e($c['counter_number']) ?> <?= Security::e($c['location'] ? '· ' . $c['location'] : '') ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-text">Select a counter, then use the buttons below. "Call Next" pulls the oldest waiting applicant.</div>
    </div>

    <!-- Action buttons -->
    <div class="card p-3">
      <h2 class="h6 fw-bold mb-2"><i class="fa-solid fa-hand-point-right text-danger me-2"></i>Actions</h2>
      <div class="d-grid gap-2">
        <button class="btn btn-danger" id="btnCallNext"><i class="fa-solid fa-phone-volume me-1"></i>Call Next Applicant</button>
        <button class="btn btn-warning" id="btnRecall" disabled><i class="fa-solid fa-rotate-left me-1"></i>Recall Current Number</button>
        <button class="btn btn-info" id="btnServing" disabled><i class="fa-solid fa-person me-1"></i>Mark as Serving</button>
        <button class="btn btn-success" id="btnComplete" disabled><i class="fa-solid fa-check me-1"></i>Complete Service</button>
        <button class="btn btn-secondary" id="btnSkip" disabled><i class="fa-solid fa-forward me-1"></i>Skip Applicant</button>
        <button class="btn btn-outline-danger" id="btnCancel" disabled><i class="fa-solid fa-ban me-1"></i>Cancel Queue</button>
      </div>
      <div class="form-text text-center mt-2">Buttons unlock when a queue is being served (Recall / Complete / Skip) or is called / waiting (Cancel).</div>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- Now serving -->
    <div class="card p-3 mb-3 bg-dark text-white">
      <h2 class="h6 fw-bold mb-1 text-uppercase" style="letter-spacing:.2em;color:#8fb3d9">Now Serving</h2>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div id="ns_number" class="queue-number fs-1" style="color:#4dd2ff">—</div>
          <div id="ns_meta" class="text-white-50 small">Select a counter and call the next applicant.</div>
        </div>
        <div class="text-end">
          <div class="text-white-50 small text-uppercase" style="letter-spacing:.15em">Counter</div>
          <div id="ns_counter" class="queue-number fs-2">—</div>
        </div>
      </div>
    </div>

    <!-- Waiting list -->
    <div class="card p-3">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-hourglass text-warning me-2"></i>Waiting Queue
        <button class="btn btn-sm btn-light float-end" data-refresh><i class="fa-solid fa-rotate me-1"></i></button>
      </h2>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Queue</th><th>Applicant</th><th>Issued</th><th>Est. Wait</th><th>Status</th></tr></thead>
          <tbody id="wBody"><tr><td colspan="5" class="text-center py-4 text-muted">No waiting applicants.</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}
  const API = "api/queue-action.php";
  let current = null; // { id, queue_number, status }

  function svc(){ return document.getElementById("o_service").value || null; }
  function counter(){ return document.getElementById("o_counter").value || null; }

  function action(a, extra) {
    const body = Object.assign({ action: a }, extra || {});
    return IQMS.post(API, body).then(res => {
      if (res.ok) { IQMS.toast(res.message || "Done", "success"); refreshAll(); }
      else if (res.empty) { IQMS.toast("No waiting applicant for this service.","warning"); }
      else Swal.fire("Error", res.error || "Action failed.", "error");
      return res;
    });
  }

  document.getElementById("btnCallNext").onclick = () => {
    if (!svc() || !counter()) return Swal.fire("Pick a service and a counter first.","","warning");
    action("call_next", { service_id: +svc(), counter_id: +counter() }).then(res => {
      if (res && res.queue) current = res.queue;
      updateButtons();
    });
  };
  document.getElementById("btnRecall").onclick   = () => current && action("recall",   { queue_id: current.id });
  document.getElementById("btnComplete").onclick = () => current && action("complete", { queue_id: current.id }).then(r => { current = null; updateButtons(); });
  document.getElementById("btnSkip").onclick     = () => current && action("skip",     { queue_id: current.id }).then(r => { current = null; updateButtons(); });
  document.getElementById("btnCancel").onclick   = () => {
    if (!current) return;
    Swal.fire({
      title: "Cancel queue " + current.queue_number + "?", input: "text",
      inputPlaceholder: "Reason (optional)", showCancelButton: true,
    }).then(v => {
      if (v.isConfirmed) action("cancel", { queue_id: current.id, reason: (v.value||"") }).then(r => { current = null; updateButtons(); });
    });
  };

  function updateButtons() {
    const serving = current && ["serving","called"].includes(current.status);
    const open    = current && ["waiting","called","serving"].includes(current.status);
    document.getElementById("btnRecall").disabled   = !(current && current.status === "serving");
    document.getElementById("btnComplete").disabled = !open;
    document.getElementById("btnSkip").disabled     = !(current && ["waiting","called"].includes(current.status));
    document.getElementById("btnCancel").disabled   = !open;
  }

  function refreshAll() {
    // Now serving: the queue currently serving on the chosen counter
    IQMS.get("api/queues.php?status=serving" + (svc() ? "&service_id=" + svc() : "") + "&date=" + today()).then(res => {
      const rows = res.rows || [];
      const c = counter();
      let row = c ? rows.find(r => r.counter_id === +c) : null;
      if (!row && !c) row = rows[0] || null;
      if (row && row.queue_number) {
        current = { id: +row.id, queue_number: row.queue_number, status: row.status };
        document.getElementById("ns_number").textContent = row.queue_number;
        document.getElementById("ns_counter").textContent = row.counter_number || "—";
        document.getElementById("ns_meta").textContent = (row.applicant_name || "Unregistered") + " · " + row.service_name;
      } else {
        document.getElementById("ns_number").textContent = "—";
        document.getElementById("ns_counter").textContent = c ? c : "—";
        document.getElementById("ns_meta").textContent = "Nothing is being served at this counter right now.";
      }
      updateButtons();
    });

    // Waiting list
    IQMS.get("api/queues.php?status=waiting" + (svc() ? "&service_id=" + svc() : "") + "&date=" + today()).then(res => {
      const b = document.getElementById("wBody");
      if (!res.rows.length) { b.innerHTML = "<tr><td colspan=5 class=text-center py-4 text-muted>No waiting applicants.</td></tr>"; return; }
      b.innerHTML = res.rows.map(q => `
        <tr>
          <td class="queue-number fs-5">${esc(q.queue_number)}</td>
          <td>${esc(q.applicant_name) || "<span class=text-muted>unregistered</span>"}</td>
          <td class="small">${esc(q.issued_at)}</td>
          <td class="text-muted small">—</td>
          <td><span class="badge badge-${q.status}">${q.status}</span></td>
        </tr>`).join("");
    });
  }

  function today() { return new Date().toISOString().slice(0,10); }

  ["o_service","o_counter"].forEach(id => document.getElementById(id).onchange = refreshAll);
  document.querySelectorAll("[data-refresh]").forEach(el => el.onclick = refreshAll);
  setInterval(refreshAll, 15000);
  refreshAll();
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
