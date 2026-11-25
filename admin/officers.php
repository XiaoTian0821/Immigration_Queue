<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'Manage Officers & Admins';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 fw-bold m-0"><i class="fa-solid fa-user-shield text-primary me-2"></i>User Accounts</h2>
    <button class="btn btn-primary btn-sm" id="btnCreate"><i class="fa-solid fa-plus me-1"></i>Add User</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>
        <th>ID</th><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="userBody"></tbody>
    </table>
  </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="userForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="userModalTitle">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="f_id" name="id">
        <div class="mb-2"><label class="form-label">Full Name</label>
          <input class="form-control" id="f_full_name" name="full_name" required></div>
        <div class="mb-2"><label class="form-label">Username</label>
          <input class="form-control" id="f_username" name="username" required></div>
        <div class="mb-2"><label class="form-label">Email</label>
          <input type="email" class="form-control" id="f_email" name="email"></div>
        <div class="mb-2"><label class="form-label">Role</label>
          <select class="form-select" id="f_role" name="role">
            <option value="officer">Officer</option><option value="admin">Admin</option>
          </select></div>
        <div class="mb-2"><label class="form-label" id="f_pw_label">Password</label>
          <input type="password" class="form-control" id="f_password" name="password" minlength="8"></div>
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
  const modal = new bootstrap.Modal(document.getElementById("userModal"));
  const form  = document.getElementById("userForm");
  const API   = "api/officers.php";
  let isEdit  = false;

  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}

  function load() {
    IQMS.get("api/users-list.php").then(res => {
      if (!res.ok) { document.getElementById("userBody").innerHTML = "<tr><td colspan=7 class=text-danger>" + res.error + "</td></tr>"; return; }
      const b = document.getElementById("userBody");
      if (!res.rows.length) { b.innerHTML = "<tr><td colspan=7 class=text-center py-4 text-muted>No users.</td></tr>"; return; }
      b.innerHTML = res.rows.map(u => `
        <tr>
          <td>${u.id}</td>
          <td class="fw-semibold">${esc(u.full_name)}</td>
          <td>${esc(u.username)}</td>
          <td class="text-muted">${esc(u.email) || "—"}</td>
          <td><span class="badge ${u.role==="admin"?"bg-primary":"bg-secondary"}">${u.role}</span></td>
          <td><span class="badge ${u.status==="active"?"bg-success":"bg-danger"}">${u.status}</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" data-edit="${u.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>
            <button class="btn btn-sm btn-outline-secondary" data-toggle="${u.id}" title="Toggle status"><i class="fa-solid fa-power-off"></i></button>
            <button class="btn btn-sm btn-outline-danger" data-del="${u.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </td>
        </tr>`).join("");

      document.querySelectorAll("[data-edit]").forEach(el => el.onclick = () => openEdit(el.dataset.edit));
      document.querySelectorAll("[data-toggle]").forEach(el => el.onclick = async () => {
        if (!await IQMS.confirm("Toggle status of this account?")) return;
        const r = await IQMS.post(API, { action: "toggle", user_id: +el.dataset.toggle });
        IQMS.toast(r.message || "Done", r.ok ? "success" : "error");
        load();
      });
      document.querySelectorAll("[data-del]").forEach(el => el.onclick = async () => {
        if (!await IQMS.confirm("Delete this account?", "Queue history assigned to them is kept.", "warning")) return;
        const r = await IQMS.post(API, { action: "delete", user_id: +el.dataset.del });
        IQMS.toast(r.message || "Done", r.ok ? "success" : "error");
        load();
      });
    });
  }

  async function openEdit(id) {
    const r = await IQMS.get("api/users-list.php?ids=" + id);
    const u = r.rows && r.rows[0];
    if (!u) return;
    isEdit = true;
    document.getElementById("userModalTitle").textContent = "Edit User #" + id;
    document.getElementById("f_id").value        = u.id;
    document.getElementById("f_full_name").value = u.full_name;
    document.getElementById("f_username").value  = u.username;
    document.getElementById("f_email").value     = u.email || "";
    document.getElementById("f_role").value      = u.role;
    document.getElementById("f_password").value  = "";
    document.getElementById("f_pw_label").textContent = "New Password (leave blank to keep)";
    document.getElementById("f_username").disabled = true;
    document.getElementById("f_password").required = false;
    modal.show();
  }

  form.onsubmit = async e => {
    e.preventDefault();
    const d = Object.fromEntries(new FormData(form).entries());

    if (isEdit) {
      const payload = {
        action: "update",
        user_id: parseInt(d.id, 10),
        data: {
          full_name: d.full_name,
          email: d.email,
          role: d.role
        }
      };
      if (d.password !== "") payload.data.new_password = d.password;
      const r = await IQMS.post(API, payload);
      IQMS.toast(r.message || "Saved", r.ok ? "success" : "error");
      if (!r.ok && r.error) Swal.fire("Error", r.error, "error");
    } else {
      const r = await IQMS.post(API, {
        action: "create",
        data: {
          username: d.username,
          full_name: d.full_name,
          email: d.email,
          role: d.role,
          password: d.password
        }
      });
      IQMS.toast(r.message || "Saved", r.ok ? "success" : "error");
      if (!r.ok && r.error) Swal.fire("Error", r.error, "error");
    }
    modal.hide();
    form.reset();
    isEdit = false;
    document.getElementById("f_username").disabled = false;
    load();
  };

  document.getElementById("btnCreate").onclick = () => {
    isEdit = false;
    document.getElementById("userModalTitle").textContent = "Add User";
    document.getElementById("f_id").value = "";
    form.reset();
    document.getElementById("f_username").disabled = false;
    document.getElementById("f_pw_label").textContent = "Password";
    document.getElementById("f_password").required = true;
    modal.show();
  };

  load();
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
