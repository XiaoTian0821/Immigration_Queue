<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'System Settings';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-gear text-primary me-2"></i>General</h2>
      <form id="setForm" class="row g-2">
        <div class="col"><label class="form-label small">Office Name</label>
          <input class="form-control form-control-sm" id="set_office_name" required></div>
        <div class="col"><label class="form-label small">Default Avg Service (min)</label>
          <input type="number" class="form-control form-control-sm" id="set_avg_service_min" min="1"></div>
        <div class="col"><label class="form-label small">Display Auto-Refresh (sec)</label>
          <input type="number" class="form-control form-control-sm" id="set_next_queue_poll" min="2" max="60"></div>
        <div class="col-12 mt-2">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i>Save Settings</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-shield-halved text-danger me-2"></i>Queue Day Management</h2>
      <p class="text-muted small">When a new operating day begins, numbering restarts at 001 for every service.
         Still-open queues (waiting / called / serving) from the previous day are closed as cancelled so they do not block the new day.</p>
      <button class="btn btn-danger" data-reset-day href="api/reset-day.php">
        <i class="fa-solid fa-rotate-left me-1"></i>Reset Today's Queue Numbers
      </button>
    </div>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  IQMS.get("api/settings.php").then(res=>{
    if(!res.ok)return;
    const s = res.settings || {};
    document.getElementById("set_office_name").value = s.office_name || "Immigration Office";
    document.getElementById("set_avg_service_min").value = s.avg_service_min || 15;
    document.getElementById("set_next_queue_poll").value = s.next_queue_poll || 3;
  });

  document.getElementById("setForm").onsubmit = async e => {
    e.preventDefault();
    const keys = ["office_name","avg_service_min","next_queue_poll"];
    for (const k of keys) {
      const r = await IQMS.post("api/settings.php", { key: k, value: document.getElementById("set_" + k).value });
      if (!r.ok) Swal.fire("Error", r.error || "Save failed for " + k, "error");
    }
    IQMS.toast("Settings saved.", "success");
  };
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
