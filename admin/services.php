<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'Immigration Services';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 fw-bold m-0"><i class="fa-solid fa-id-card text-primary me-2"></i>Service Catalog &amp; Queue Categories</h2>
    <button class="btn btn-primary btn-sm" id="btnCreate"><i class="fa-solid fa-plus me-1"></i>Add Service</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>
        <th>Code</th><th>Name</th><th>Description</th><th>Est. Min</th><th>Active</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="svcBody"></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="svcModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="svcForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="svcModalTitle">Add Service</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="s_id" name="id">
        <div class="row g-2">
          <div class="col-4"><label class="form-label">2-Letter Code</label>
            <input class="form-control text-uppercase" id="s_code" name="code" maxlength="2" required
                   pattern="[A-Z]{2}" placeholder="P">
            <div class="form-text">Queue prefix, e.g. P, V, W</div></div>
          <div class="col"><label class="form-label">Service Name</label>
            <input class="form-control" id="s_name" name="name" required placeholder="Visa Application"></div>
        </div>
        <div class="mt-2 mb-2"><label class="form-label">Description</label>
          <input class="form-control" id="s_description" name="description"></div>
        <div class="mb-1"><label class="form-label">Estimated Minutes per Applicant</label>
          <input type="number" class="form-control" id="s_est" name="est_minutes" min="1" value="15"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  const modal = new bootstrap.Modal(document.getElementById("svcModal"));
  const form  = document.getElementById("svcForm");
  const API   = "api/services.php";
  let isEdit  = false;

  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}

  function load(){
    IQMS.get("api/services.php").then(res=>{
      if(!res.ok){document.getElementById("svcBody").innerHTML="<tr><td colspan=6 class=text-danger>"+esc(res.error)+"</td></tr>";return;}
      const b=document.getElementById("svcBody");
      if(!res.rows.length){b.innerHTML="<tr><td colspan=6 class=text-center py-4 text-muted>No services.</td></tr>";return;}
      b.innerHTML=res.rows.map(s=>`
        <tr>
          <td><span class="badge bg-dark fs-5 queue-number">${esc(s.code)}</span></td>
          <td class="fw-semibold">${esc(s.name)}</td>
          <td class="text-muted">${esc(s.description)||"—"}</td>
          <td>${s.est_minutes}</td>
          <td><span class="badge ${s.active?"bg-success":"bg-danger"}">${s.active?"Active":"Inactive"}</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" data-edit="${s.id}"><i class="fa-solid fa-pen"></i></button>
            <button class="btn btn-sm btn-outline-secondary" data-toggle="${s.id}"><i class="fa-solid fa-power-off"></i></button>
            <button class="btn btn-sm btn-outline-danger" data-del="${s.id}"><i class="fa-solid fa-trash"></i></button>
          </td>
        </tr>`).join("");
      document.querySelectorAll("[data-edit]").forEach(el=>el.onclick=()=>openEdit(el.dataset.edit));
      document.querySelectorAll("[data-toggle]").forEach(el=>el.onclick=async()=>{
        const r=await IQMS.post(API,{action:"toggle",service_id:+el.dataset.toggle});
        IQMS.toast(r.message||"Done", r.ok?"success":"error"); load();
      });
      document.querySelectorAll("[data-del]").forEach(el=>el.onclick=async()=>{
        if(!await IQMS.confirm("Delete this service?","Services in use cannot be deleted; deactivate instead.","warning"))return;
        const r=await IQMS.post(API,{action:"delete",service_id:+el.dataset.del});
        IQMS.toast(r.message||r.error||"Done", r.ok?"success":"error"); load();
      });
    });
  }

  async function openEdit(id){
    const r=await IQMS.get("api/services.php?ids="+id);
    const s=r.rows&&r.rows[0]; if(!s)return;
    isEdit=true;
    document.getElementById("svcModalTitle").textContent="Edit Service";
    document.getElementById("s_id").value=s.id;
    document.getElementById("s_code").value=s.code;
    document.getElementById("s_name").value=s.name;
    document.getElementById("s_description").value=s.description||"";
    document.getElementById("s_est").value=s.est_minutes;
    modal.show();
  }

  form.onsubmit=async e=>{
    e.preventDefault();
    const d=Object.fromEntries(new FormData(form).entries());
    if(isEdit){
      const r=await IQMS.post(API,{action:"update",service_id:parseInt(d.id,10),
        data:{name:d.name,description:d.description,est_minutes:+d.est_minutes}});
      IQMS.toast(r.message||r.error||"Saved", r.ok?"success":"error");
    } else {
      const r=await IQMS.post(API,{action:"create",data:{
        code:d.code.toUpperCase(),name:d.name,description:d.description,est_minutes:+d.est_minutes}});
      IQMS.toast(r.message||r.error||"Saved", r.ok?"success":"error");
    }
    modal.hide(); form.reset(); isEdit=false;
    document.getElementById("s_code").disabled=false;
    load();
  };

  document.getElementById("btnCreate").onclick=()=>{
    isEdit=false;
    document.getElementById("svcModalTitle").textContent="Add Service";
    form.reset();
    document.getElementById("s_id").value="";
    document.getElementById("s_code").disabled=false;
    modal.show();
  };

  load();
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
