<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'Manage Counters';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 fw-bold m-0"><i class="fa-solid fa-desktop text-primary me-2"></i>Service Counters</h2>
    <button class="btn btn-primary btn-sm" id="btnCreate"><i class="fa-solid fa-plus me-1"></i>Add Counter</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>
        <th>Counter</th><th>Location</th><th>Status</th><th>Active</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="cntBody"></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="cntModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="cntForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="cntModalTitle">Add Counter</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="c_id" name="id">
        <div class="mb-2"><label class="form-label">Counter Number</label>
          <input class="form-control" id="c_number" name="counter_number" required placeholder="07"></div>
        <div class="mb-1"><label class="form-label">Location</label>
          <input class="form-control" id="c_location" name="location" placeholder="Main Hall - Window 4"></div>
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
  const modal = new bootstrap.Modal(document.getElementById("cntModal"));
  const form  = document.getElementById("cntForm");
  const API   = "api/counters.php";
  let isEdit  = false;

  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}

  const statusBadge = s => s==="available"?"bg-success":(s==="busy"?"bg-warning text-dark":"bg-danger");

  function load(){
    IQMS.get("api/counters.php").then(res=>{
      if(!res.ok){document.getElementById("cntBody").innerHTML="<tr><td colspan=5 class=text-danger>"+esc(res.error)+"</td></tr>";return;}
      const b=document.getElementById("cntBody");
      if(!res.rows.length){b.innerHTML="<tr><td colspan=5 class=text-center py-4 text-muted>No counters.</td></tr>";return;}
      b.innerHTML=res.rows.map(c=>`
        <tr>
          <td><span class="badge bg-dark fs-6 queue-number">${esc(c.counter_number)}</span></td>
          <td class="text-muted">${esc(c.location)||"—"}</td>
          <td><span class="badge ${statusBadge(c.status)}">${c.status}</span></td>
          <td><span class="badge ${c.active?"bg-success":"bg-secondary"}">${c.active?"Active":"Inactive"}</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" data-edit="${c.id}"><i class="fa-solid fa-pen"></i></button>
            <button class="btn btn-sm btn-outline-secondary" data-toggle="${c.id}"><i class="fa-solid fa-power-off"></i></button>
            <button class="btn btn-sm btn-outline-danger" data-del="${c.id}"><i class="fa-solid fa-trash"></i></button>
          </td>
        </tr>`).join("");
      document.querySelectorAll("[data-edit]").forEach(el=>el.onclick=()=>openEdit(el.dataset.edit));
      document.querySelectorAll("[data-toggle]").forEach(el=>el.onclick=async()=>{
        const r=await IQMS.post(API,{action:"toggle",counter_id:+el.dataset.toggle});
        IQMS.toast(r.message||"Done", r.ok?"success":"error"); load();
      });
      document.querySelectorAll("[data-del]").forEach(el=>el.onclick=async()=>{
        if(!await IQMS.confirm("Delete this counter?","Counters in use today cannot be deleted.","warning"))return;
        const r=await IQMS.post(API,{action:"delete",counter_id:+el.dataset.del});
        IQMS.toast(r.message||r.error||"Done", r.ok?"success":"error"); load();
      });
    });
  }

  async function openEdit(id){
    const r=await IQMS.get("api/counters.php?ids="+id);
    const c=r.rows&&r.rows[0]; if(!c)return;
    isEdit=true;
    document.getElementById("cntModalTitle").textContent="Edit Counter";
    document.getElementById("c_id").value=c.id;
    document.getElementById("c_number").value=c.counter_number;
    document.getElementById("c_location").value=c.location||"";
    modal.show();
  }

  form.onsubmit=async e=>{
    e.preventDefault();
    const d=Object.fromEntries(new FormData(form).entries());
    if(isEdit){
      const r=await IQMS.post(API,{action:"update",counter_id:parseInt(d.id,10),
        data:{counter_number:d.counter_number,location:d.location}});
      IQMS.toast(r.message||r.error||"Saved", r.ok?"success":"error");
    } else {
      const r=await IQMS.post(API,{action:"create",data:{counter_number:d.counter_number,location:d.location}});
      IQMS.toast(r.message||r.error||"Saved", r.ok?"success":"error");
    }
    modal.hide(); form.reset(); isEdit=false; load();
  };

  document.getElementById("btnCreate").onclick=()=>{
    isEdit=false;
    document.getElementById("cntModalTitle").textContent="Add Counter";
    form.reset(); document.getElementById("c_id").value="";
    modal.show();
  };

  load();
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
