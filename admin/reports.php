<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'Reports & Exports';
include __DIR__ . '/../parts/layout-top.php';

$services = Database::select('SELECT id, name FROM immigration_services WHERE active = 1 ORDER BY id');
$officers = Database::select("SELECT id, full_name FROM users WHERE role = 'officer' AND status = 'active' ORDER BY full_name");
?>
<div class="card p-3 mb-3">
  <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-chart-line text-primary me-2"></i>Report Filters</h2>
  <form id="repForm" class="row g-2">
    <div class="col-sm-3"><label class="form-label small">Report Type</label>
      <select class="form-select form-select-sm" id="r_type" required>
        <option value="daily">Daily Queue Records</option>
        <option value="monthly">Monthly Queue Records</option>
        <option value="service">Service Category Performance</option>
        <option value="officer">Officer Performance</option>
        <option value="counter">Counter Performance</option>
        <option value="avg">Average Waiting &amp; Service Time</option>
      </select></div>
    <div class="col-sm-3"><label class="form-label small">Date (daily)</label>
      <input type="date" class="form-control form-control-sm" id="r_date" value="<?= Security::e(QueueManager::today()) ?>"></div>
    <div class="col-sm-3"><label class="form-label small">Month (monthly)</label>
      <input type="month" class="form-control form-control-sm" id="r_month" value="<?= Security::e(date('Y-m')) ?>"></div>
    <div class="col-sm-3"><label class="form-label small">Service (optional filter)</label>
      <select class="form-select form-select-sm" id="r_service"><option value="">All</option>
        <?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= Security::e($s['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-sm-6 d-flex align-items-end gap-2">
      <button type="button" class="btn btn-primary btn-sm" id="r_show"><i class="fa-solid fa-eye me-1"></i>Show</button>
      <button type="button" class="btn btn-outline-success btn-sm" id="r_csv"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</button>
    </div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h6 fw-bold mb-3" id="repTitle">Report</h2>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead id="repHead"></thead>
      <tbody id="repBody"><tr><td class="text-center py-4 text-muted">Configure filters and press Show.</td></tr></tbody>
    </table>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}
  function qs(){
    const p = new URLSearchParams();
    p.set("type", document.getElementById("r_type").value);
    p.set("date", document.getElementById("r_date").value);
    p.set("month", document.getElementById("r_month").value);
    const s = document.getElementById("r_service").value; if (s) p.set("service_id", s);
    return p.toString();
  }
  function show(){
    IQMS.get("api/reports.php?" + qs()).then(res=>{
      if(!res.ok){document.getElementById("repBody").innerHTML="<tr><td class=text-danger>"+esc(res.error)+"</td></tr>";return;}
      const rows = res.rows;
      if(!rows.length){
        document.getElementById("repHead").innerHTML="";
        document.getElementById("repBody").innerHTML="<tr><td class=text-center py-4 text-muted>No data for this filter.</td></tr>";
        return;
      }
      const cols = Object.keys(rows[0]);
      document.getElementById("repTitle").textContent =
        "Report: " + res.meta.type + (res.meta.date ? " (" + res.meta.date + ")" : "");
      document.getElementById("repHead").innerHTML =
        "<tr>" + cols.map(c=>"<th>"+esc(c)+"</th>").join("") + "</tr>";
      document.getElementById("repBody").innerHTML =
        rows.map(r=>"<tr>"+cols.map(c=>"<td>"+esc(r[c] ?? "")+"</td>").join("")+"</tr>").join("");
    });
  }
  document.getElementById("r_show").onclick = show;
  document.getElementById("r_csv").onclick = () => {
    window.location.href = "api/reports.php?" + qs() + "&export=csv";
  };
  show();
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
