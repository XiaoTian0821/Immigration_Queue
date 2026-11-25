<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['officer']);
$page_title = 'Register Applicant';
include __DIR__ . '/../parts/layout-top.php';

$services = Database::select('SELECT id, name, code FROM immigration_services WHERE active = 1 ORDER BY id');
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3 h-100">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-user-plus text-primary me-2"></i>New Applicant Details</h2>
      <form id="appForm">
        <div class="mb-2"><label class="form-label small">Full Name <span class="text-danger">*</span></label>
          <input class="form-control" id="a_name" required maxlength="150"></div>
        <div class="row g-2">
          <div class="col"><label class="form-label small">Passport Number</label>
            <input class="form-control text-uppercase" id="a_passport" maxlength="30" placeholder="A1234567"></div>
          <div class="col"><label class="form-label small">National ID Number</label>
            <input class="form-control text-uppercase" id="a_id" maxlength="30"></div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col"><label class="form-label small">Phone</label>
            <input class="form-control" id="a_phone" maxlength="30"></div>
          <div class="col"><label class="form-label small">Nationality</label>
            <input class="form-control" id="a_nationality" maxlength="80"></div>
        </div>
        <div class="mt-2"><label class="form-label small">Date of Birth</label>
          <input type="date" class="form-control" id="a_dob"></div>
        <div class="mt-3 d-grid gap-2">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Register Applicant</button>
          <button type="button" class="btn btn-success" id="btnRegAndIssue">
            <i class="fa-solid fa-ticket me-1"></i>Register &amp; Issue Queue
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3 mb-3">
      <h2 class="h6 fw-bold mb-2"><i class="fa-solid fa-magnifying-glass text-primary me-2"></i>Quick Search</h2>
      <p class="text-muted small mb-2">Find an applicant by passport or ID number, then issue a queue for them.</p>
      <div class="input-group input-group-sm">
        <input class="form-control" id="s_q" placeholder="Passport number or ID number">
        <button class="btn btn-outline-primary" id="s_btn"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
      </div>
      <div id="s_result" class="mt-2"></div>
    </div>

    <div class="card p-3">
      <h2 class="h6 fw-bold mb-2"><i class="fa-solid fa-ticket text-danger me-2"></i>Issue Queue Number</h2>
      <div class="mb-2"><label class="form-label small">Applicant</label>
        <select class="form-select form-select-sm" id="i_applicant">
          <option value="0">— unregistered applicant (type name below) —</option>
        </select></div>
      <div class="mb-2" id="i_name_wrap" style="display:none">
        <label class="form-label small">Full Name (for unregistered)</label>
        <input class="form-control form-control-sm" id="i_name"></div>
      <div class="mb-2"><label class="form-label small">Immigration Service</label>
        <select class="form-select form-select-sm" id="i_service" required>
          <option value="">— select service —</option>
          <?php foreach ($services as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= Security::e($s['code'] . ' · ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <button class="btn btn-danger w-100" id="i_issue"><i class="fa-solid fa-bolt me-1"></i>Generate Queue Number</button>
      <div id="issued" class="text-center py-3 d-none">
        <div class="text-muted small text-uppercase">Your Queue Number</div>
        <div class="queue-number fs-1 text-primary" id="issued_num">—</div>
        <div class="text-muted" id="issued_meta"></div>
      </div>
    </div>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}

  const baseData = {
    name:    () => document.getElementById("a_name").value.trim(),
    pass:    () => document.getElementById("a_passport").value.trim().toUpperCase(),
    id:      () => document.getElementById("a_id").value.trim().toUpperCase(),
    phone:   () => document.getElementById("a_phone").value.trim(),
    nat:     () => document.getElementById("a_nationality").value.trim(),
    dob:     () => document.getElementById("a_dob").value || null
  };

  document.getElementById("appForm").onsubmit = async e => {
    e.preventDefault();
    const r = await IQMS.post("api/register-applicant.php", {
      full_name: baseData.name(), passport_number: baseData.pass(), id_number: baseData.id(),
      phone: baseData.phone(), nationality: baseData.nat(), date_of_birth: baseData.dob()
    });
    if (r.ok) { IQMS.toast(r.message, "success"); document.getElementById("appForm").reset(); }
    else Swal.fire("Error", r.error || "Failed to register.", "error");
  };

  document.getElementById("btnRegAndIssue").onclick = async () => {
    const svc = +document.getElementById("i_service").value;
    if (!svc) return Swal.fire("Select a service first","","warning");
    const r = await IQMS.post("api/register-applicant.php", {
      full_name: baseData.name(), passport_number: baseData.pass(), id_number: baseData.id(),
      phone: baseData.phone(), nationality: baseData.nat(), date_of_birth: baseData.dob(),
      service_id: svc
    });
    if (r.ok) {
      IQMS.toast(r.message, "success");
      if (r.queue) showIssued(r.queue, r.waiting_ahead ?? 0, r.estimated_wait_min ?? 0);
      document.getElementById("appForm").reset();
    } else Swal.fire("Error", r.error || "Failed.", "error");
  };

  // Quick search
  document.getElementById("s_btn").onclick = search;
  document.getElementById("s_q").addEventListener("keydown", e => { if (e.key === "Enter") search(); });
  async function search() {
    const q = document.getElementById("s_q").value.trim();
    if (q === "") return;
    const r = await IQMS.get("api/applicants.php?q=" + encodeURIComponent(q));
    const box = document.getElementById("s_result");
    if (!r.ok) { box.innerHTML = "<div class=alert alert-danger py-2>" + esc(r.error) + "</div>"; return; }
    if (!r.rows.length) { box.innerHTML = "<div class=alert alert-light py-2>No applicant found for “" + esc(q) + "”.</div>"; return; }
    box.innerHTML = r.rows.map(a => `
      <div class="card mb-2"><div class="card-body py-2">
        <div class="d-flex justify-content-between">
          <div><b>${esc(a.full_name)}</b>
            <div class="small text-muted">${esc(a.passport_number)||""} ${esc(a.id_number)?"· "+esc(a.id_number):""}</div>
          </div>
          <div class="text-end small">${(a.queues_today||[]).map(q=>"<span class=badge badge-"+q.status+" me-1>"+esc(q.queue_number)+"</span>").join("")||"<span class=text-muted>no queues today</span>"}</div>
        </div>
      </div></div>`).join("");
  }

  // Applicant picker (from search results)
  function pickApplicant(id) {
    const sel = document.getElementById("i_applicant");
    sel.value = String(id);
    document.getElementById("i_name_wrap").style.display = id ? "none" : "block";
  }
  document.getElementById("i_applicant").onchange = function () {
    document.getElementById("i_name_wrap").style.display = this.value ? "none" : "block";
  };
  pickApplicant(0);

  document.getElementById("i_issue").onclick = async () => {
    const svc = +document.getElementById("i_service").value;
    if (!svc) return Swal.fire("Select a service.","","warning");
    const r = await IQMS.post("api/queue-issue.php", {
      service_id: svc,
      full_name: document.getElementById("i_name").value.trim(),
      passport_number: baseData.pass(), id_number: baseData.id()
    });
    if (r.ok) showIssued(r, r.waiting_ahead, r.estimated_wait_min);
    else Swal.fire("Error", r.error || "Failed to issue queue.", "error");
  };

  function showIssued(q, ahead, waitMin) {
    const box = document.getElementById("issued");
    box.classList.remove("d-none");
    document.getElementById("issued_num").textContent = q.queue_number || q;
    document.getElementById("issued_meta").textContent =
      (q.service ? q.service.name : "") + " · " + (ahead||0) + " ahead · est. wait ~" + (waitMin||0) + " min";
  }

});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
