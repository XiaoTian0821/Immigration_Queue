<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'Queue Records & Live Monitor';
include __DIR__ . '/../parts/layout-top.php';

$services = Database::select('SELECT id, name, code FROM immigration_services WHERE active = 1 ORDER BY id');
$counters = Database::select('SELECT id, counter_number FROM counters WHERE active = 1 ORDER BY id');
?>
<div class="row g-3">
  <div class="col-lg-3">
    <div class="card p-3">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-bolt text-danger me-2"></i>Issue Queue (Admin)</h2>
      <div class="mb-2"><label class="form-label small">Service</label>
        <select class="form-select form-select-sm" id="a_service">
          <option value="">— select —</option>
          <?php foreach ($services as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= Security::e($s['code'] . ' · ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="mb-2"><label class="form-label small">Applicant (optional)</label>
        <input class="form-control form-control-sm" id="a_applicant" placeholder="Passport / ID number or name">
        <div class="form-text">Leave blank for unregistered applicant</div></div>
      <button class="btn btn-danger btn-sm w-100" id="btnIssue"><i class="fa-solid fa-ticket me-1"></i>Issue Number</button>
    </div>
    <div class="card p-3 mt-3">
      <h2 class="h6 fw-bold mb-2"><i class="fa-solid fa-satellite-dish text-success me-2"></i>Live Status</h2>
      <div id="liveCounts" class="small"></div>
    </div>
  </div>

  <div class="col-lg-9">
    <div class="card p-3">
      <div class="row g-2 mb-3">
        <div class="col-sm-4 col-md-3"><label class="form-label small">Status</label>
          <select class="form-select form-select-sm" id="f_status">
            <option value="">All</option>
            <?php foreach (['waiting','called','serving','completed','skipped','cancelled'] as $st): ?>
            <option value="<?= $st ?>"><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-sm-4 col-md-3"><label class="form-label small">Service</label>
          <select class="form-select form-select-sm" id="f_service"><option value="">All</option>
            <?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= Security::e($s['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-sm-4 col-md-3"><label class="form-label small">Date</label>
          <input type="date" class="form-control form-control-sm" id="f_date" value="<?= Security::e(QueueManager::today()) ?>"></div>
        <div class="col-sm-4 col-md-3 d-flex align-items-end"><label class="form-label small">Search</label>
          <input class="form-control form-control-sm" id="f_q" placeholder="P001, name, passport…"></div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr>
            <th>Queue</th><th>Service</th><th>Applicant</th><th>Counter</th><th>Officer</th>
            <th>Issued</th><th>Status</th><th class="text-end">Actions</th>
          </tr></thead>
          <tbody id="qBody"></tbody>
        </table>
      </div>
      <div class="form-text">Auto-refreshes every 10s. Use the officer console to run full call/serve/complete cycles.</div>
    </div>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}
  const b = document.getElementById("qBody");

  function params(){
    const p = new URLSearchParams();
    if (document.getElementById("f_status").value) p.set("status", document.getElementById("f_status").value);
    if (document.getElementById("f_service").value) p.set("service_id", document.getElementById("f_service").value);
    if (document.getElementById("f_date").value) p.set("date", document.getElementById("f_date").value);
    const q = document.getElementById("f_q").value.trim();
    if (q) p.set("q", q);
    return p.toString();
  }

  function load(){
    IQMS.get("api/queues.php?" + params()).then(res=>{
      if(!res.ok){b.innerHTML="<tr><td colspan=8 class=text-danger>"+esc(res.error)+"</td></tr>";return;}
      if(!res.rows.length){b.innerHTML="<tr><td colspan=8 class=text-center py-4 text-muted>No queue records match.</td></tr>";return;}
      b.innerHTML=res.rows.map(q=>`
        <tr>
          <td class="queue-number fs-5">${esc(q.queue_number)}</td>
          <td>${esc(q.service_name)}</td>
          <td>${esc(q.applicant_name)||"<span class=text-muted>unregistered</span>"}<br>
              <small class="text-muted">${esc(q.passport_number)||esc(q.id_number)||""}</small></td>
          <td>${esc(q.counter_number)||"—"}</td>
          <td>${esc(q.officer_name)||"—"}</td>
          <td class="small">${esc(q.issued_at)}</td>
          <td><span class="badge badge-${q.status}">${q.status}</span></td>
          <td class="text-end">
            ${(["waiting","called"].includes(q.status)) ? `<button class="btn btn-sm btn-outline-danger" data-cancel="${q.id}"><i class="fa-solid fa-ban"></i></button>` : ""}
            <a class="btn btn-sm btn-outline-secondary" href="queue-detail.php?id=${q.id}" title="History"><i class="fa-solid fa-clock-rotate-left"></i></a>
          </td>
        </tr>`).join("");
      document.querySelectorAll("[data-cancel]").forEach(el=>el.onclick=async()=>{
        if(!await IQMS.confirm("Cancel this queue?"))return;
        const r=await IQMS.post("api/queue-action.php",
          {action:"cancel",queue_id:+el.dataset.cancel,reason:"Cancelled by admin"});
        IQMS.toast(r.message||r.error||"Done", r.ok?"success":"error"); load();
      });
    });
  }

  function loadLive(){
    IQMS.get("api/stats.php?scope=admin").then(res=>{
      if(!res.ok)return;
      const s=res.stats;
      document.getElementById("liveCounts").innerHTML =
        `<div class="d-flex justify-content-between"><span>Waiting</span><b>${s.waiting}</b></div>
         <div class="d-flex justify-content-between"><span>Called</span><b>${s.called}</b></div>
         <div class="d-flex justify-content-between"><span>Serving</span><b>${s.serving}</b></div>
         <div class="d-flex justify-content-between"><span>Completed</span><b>${s.completed}</b></div>
         <div class="d-flex justify-content-between"><span>Cancelled</span><b>${s.cancelled}</b></div>
         <hr><div class="d-flex justify-content-between"><span>Counters free</span><b>${s.available_counters}/${s.active_counters}</b></div>`;
    });
  }

  document.getElementById("btnIssue").onclick = async () => {
    const sid = +document.getElementById("a_service").value;
    if (!sid) return Swal.fire("Select a service","","warning");
    const r = await IQMS.post("api/queues.php", { action: "issue_admin", service_id: sid });
    if (r.ok) { Swal.fire({title:"Queue issued",html:"<div class=queue-number style=font-size:2rem>"+esc(r.queue.queue_number)+"</div>",icon:"success"}); load(); }
    else Swal.fire("Error", r.error||"Failed","error");
  };

  ["f_status","f_service","f_date"].forEach(id=>document.getElementById(id).addEventListener("change",load));
  document.getElementById("f_q").addEventListener("input",()=>{clearTimeout(window.__q);window.__q=setTimeout(load,400);});
  load(); loadLive();
  setInterval(()=>{load();loadLive();},10000);
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
