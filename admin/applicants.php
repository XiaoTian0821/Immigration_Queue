<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'All Applicants';
include __DIR__ . '/../parts/layout-top.php';

$services = Database::select('SELECT id, name, code FROM immigration_services ORDER BY id');
?>
<div class="card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-sm-4">
      <label class="form-label small mb-1">Search (name / passport / ID number)</label>
      <input class="form-control form-control-sm" id="a_q" placeholder="Enter search term…">
    </div>
    <div class="col-sm-3">
      <label class="form-label small mb-1">Service of interest (filters today's queues shown)</label>
      <select class="form-select form-select-sm" id="a_service"><option value="">All services</option>
        <?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= Security::e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100" id="a_search"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button></div>
  </div>
</div>

<div class="card p-3">
  <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-users text-primary me-2"></i>Registered Applicants</h2>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead><tr>
        <th>ID</th><th>Full Name</th><th>Passport No.</th><th>ID Number</th><th>Phone</th>
        <th>Nationality</th><th>Queues Today</th><th>Registered</th>
      </tr></thead>
      <tbody id="aBody"></tbody>
    </table>
  </div>
  <nav class="d-flex justify-content-between align-items-center mt-3">
    <div class="text-muted small" id="a_count"></div>
    <div class="btn-group">
      <button class="btn btn-sm btn-light" id="a_prev" disabled><i class="fa-solid fa-chevron-left"></i></button>
      <span class="btn btn-sm btn-light" id="a_page">1 / 1</span>
      <button class="btn btn-sm btn-light" id="a_next"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
  </nav>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}
  const PER = 15;
  let page = 1; let total = 0; let rows = [];

  async function loadAll(){
    const q = document.getElementById("a_q").value.trim();
    const sid = document.getElementById("a_service").value;
    let r;
    if (q !== "") {
      r = await IQMS.get("api/applicants.php?q=" + encodeURIComponent(q));
      rows = r.ok ? r.rows : [];
      if (sid) rows = rows.filter(x => (x.queues_today||[]).some(qu => qu.service_name !== undefined ? true : true) && true);
      total = rows.length;
      page = 1;
    } else {
      r = await IQMS.get("api/applicants-all.php?page=" + page + "&per=" + PER + (sid ? "&service_id=" + sid : ""));
      rows = r.ok ? r.rows : [];
      total = r.total || 0;
    }
    render();
  }

  function render(){
    const b = document.getElementById("aBody");
    let pageRows;
    if (document.getElementById("a_q").value.trim() !== "") {
      const start = (page-1)*PER;
      pageRows = rows.slice(start, start+PER);
    } else {
      pageRows = rows;
    }
    if (!pageRows.length) {
      b.innerHTML = "<tr><td colspan=8 class=text-center py-4 text-muted>No applicants found.</td></tr>";
    } else {
      b.innerHTML = pageRows.map(a => `
        <tr>
          <td>${a.id}</td>
          <td class="fw-semibold">${esc(a.full_name)}</td>
          <td class="queue-number">${esc(a.passport_number)||"—"}</td>
          <td class="queue-number">${esc(a.id_number)||"—"}</td>
          <td>${esc(a.phone)||"—"}</td>
          <td>${esc(a.nationality)||"—"}</td>
          <td>${(a.queues_today||[]).map(q=>`<span class="badge badge-${q.status} me-1">${esc(q.queue_number)}</span>`).join("")||"—"}</td>
          <td class="text-muted small">${esc(a.created_at)}</td>
        </tr>`).join("");
    }
    const pages = Math.max(1, Math.ceil(total/PER));
    document.getElementById("a_page").textContent = page + " / " + pages;
    document.getElementById("a_prev").disabled = page <= 1;
    document.getElementById("a_next").disabled = page >= pages;
    document.getElementById("a_count").textContent = total + " applicant(s) total";
  }

  document.getElementById("a_search").onclick = loadAll;
  document.getElementById("a_q").addEventListener("keydown", e => { if (e.key==="Enter") loadAll(); });
  document.getElementById("a_prev").onclick = () => { if (document.getElementById("a_q").value.trim()===""){page--;loadAll();} };
  document.getElementById("a_next").onclick = () => { if (document.getElementById("a_q").value.trim()===""){page++;loadAll();} };
  loadAll();
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
