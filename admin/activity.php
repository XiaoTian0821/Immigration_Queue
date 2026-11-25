<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'System Activity Logs';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="card p-3">
  <div class="row g-2 align-items-end mb-3">
    <div class="col-sm-4">
      <label class="form-label small mb-1">Filter by action</label>
      <select class="form-select form-select-sm" id="l_action">
        <option value="">All actions</option>
        <?php
        $actions = Database::select('SELECT DISTINCT action FROM activity_logs ORDER BY action');
        foreach ($actions as $a): ?>
        <option value="<?= Security::e($a['action']) ?>"><?= Security::e($a['action']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-3">
      <label class="form-label small mb-1">Per page</label>
      <select class="form-select form-select-sm" id="l_limit">
        <option>50</option><option>100</option><option>200</option>
      </select>
    </div>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm" id="l_load"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
    </div>
    <div class="col-auto"><span class="text-muted small" id="l_total"></span></div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead><tr>
        <th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th>
      </tr></thead>
      <tbody id="lBody"></tbody>
    </table>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}
  function load(offset){
    const a = document.getElementById("l_action").value;
    const limit = document.getElementById("l_limit").value || 100;
    const p = "limit=" + limit + "&offset=" + (offset||0) + (a ? "&action=" + encodeURIComponent(a) : "");
    IQMS.get("api/activity.php?" + p).then(res=>{
      if(!res.ok){document.getElementById("lBody").innerHTML="<tr><td colspan=5 class=text-danger>"+esc(res.error)+"</td></tr>";return;}
      document.getElementById("l_total").textContent = res.total + " log(s)";
      const b = document.getElementById("lBody");
      if(!res.rows.length){b.innerHTML="<tr><td colspan=5 class=text-center py-4 text-muted>No log entries.</td></tr>";return;}
      b.innerHTML = res.rows.map(r=>`
        <tr>
          <td class="small text-muted">${esc(r.created_at)}</td>
          <td>${esc(r.user_full)||esc(r.username)||"—"}</td>
          <td><span class="badge bg-dark">${esc(r.action)}</span></td>
          <td class="small">${esc(r.details)||"—"}</td>
          <td class="small text-muted">${esc(r.ip_address)||"—"}</td>
        </tr>`).join("");
    });
  }
  document.getElementById("l_load").onclick = () => load(0);
  document.getElementById("l_action").onchange = () => load(0);
  load(0);
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
