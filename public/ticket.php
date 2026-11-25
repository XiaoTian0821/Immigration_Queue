<?php
/**
 * PUBLIC - Applicant queue ticket.
 * Anyone (no account) can enter name + ID + service and get a queue number.
 */
require_once __DIR__ . '/../config/config.php';

$services = Database::select('SELECT id, name, code FROM immigration_services WHERE active = 1 ORDER BY id');
$officeName = (string)Database::value("SELECT setting_value FROM system_settings WHERE setting_key = 'office_name'") ?: 'Immigration Office';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Queue Ticket — <?= Security::e($officeName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-bg">
<div class="container py-4" style="max-width:520px">
  <div class="text-center mb-4">
    <i class="fa-solid fa-landmark fa-3x" style="color:#0d3b66"></i>
    <h1 class="h4 fw-bold mt-2"><?= Security::e($officeName) ?></h1>
    <p class="text-muted small mb-0">Get your queue number — no account needed.</p>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <form id="ticketForm">
        <div class="mb-3">
          <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="t_name" required maxlength="150" placeholder="As shown on your ID">
        </div>
        <div class="row g-2 mb-3">
          <div class="col"><label class="form-label small">Passport Number</label>
            <input type="text" class="form-control text-uppercase" id="t_passport" maxlength="30" placeholder="A1234567"></div>
          <div class="col"><label class="form-label small">National ID Number</label>
            <input type="text" class="form-control text-uppercase" id="t_id" maxlength="30" placeholder="123456789"></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Immigration Service <span class="text-danger">*</span></label>
          <select class="form-select" id="t_service" required>
            <option value="">— Select a service —</option>
            <?php foreach ($services as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= Security::e($s['code']) ?> · <?= Security::e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2" id="t_submit">
          <i class="fa-solid fa-ticket me-1"></i>Get My Queue Number
        </button>
      </form>

      <!-- Result ticket -->
      <div id="ticketResult" class="text-center py-4 d-none">
        <div class="ticket-card p-4 my-3 bg-white">
          <div class="text-muted small text-uppercase mb-1" style="letter-spacing:.15em">Your Queue Number</div>
          <div class="queue-number" style="font-size:4rem;line-height:1;color:#0d3b66" id="ticketNum">—</div>
          <div class="text-muted mt-1" id="ticketMeta"></div>
        </div>
        <div class="text-start small">
          <div id="ticketStatus" class="mb-2"></div>
          <ul class="list-unstyled text-muted mb-0">
            <li><i class="fa-solid fa-location-dot me-1"></i>Listen for your number to be called.</li>
            <li><i class="fa-solid fa-clock me-1"></i>Check this page to monitor your position.</li>
            <li><i class="fa-solid fa-id-card me-1"></i>Keep your ID document ready at the counter.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="text-center mt-3">
    <a href="display.php" target="_blank" class="small text-decoration-none"><i class="fa-solid fa-tv me-1"></i>Watch the office display screen</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("ticketForm");
  let myQueueId = null, myNumber = null;

  const csrf = () => {
    // Public pages have no session-bound token in app.js; fetch from a tiny endpoint is overkill.
    // queue-issue.php does not require CSRF (public), so no token needed here.
    return null;
  };

  form.onsubmit = async e => {
    e.preventDefault();
    const btn = document.getElementById("t_submit");
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';
    const body = {
      full_name: document.getElementById("t_name").value.trim(),
      passport_number: document.getElementById("t_passport").value.trim().toUpperCase(),
      id_number: document.getElementById("t_id").value.trim().toUpperCase(),
      service_id: +document.getElementById("t_service").value
    };
    try {
      const res = await fetch("<?= APP_URL ?>api/queue-issue.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body)
      }).then(r => r.json());
      if (res.ok) {
        myQueueId = res.queue_id; myNumber = res.queue_number;
        document.getElementById("ticketNum").textContent = myNumber;
        document.getElementById("ticketMeta").textContent =
          res.service.name + " · " + new Date().toLocaleDateString();
        document.getElementById("ticketResult").classList.remove("d-none");
        document.getElementById("ticketStatus").innerHTML =
          '<span class="badge badge-waiting">Waiting</span> <span class="text-muted">' +
          res.waiting_ahead + ' applicant(s) ahead · estimated wait ~' + res.estimated_wait_min + ' min</span>';
        Swal.fire({ icon: "success", title: "Queue number issued",
          html: "Your number is <b>" + res.queue_number + "</b>. Listen for it to be called.", showConfirmButton: false, timer: 3000 });
      } else {
        Swal.fire("Could not issue queue", res.error || "Please try again.", "error");
      }
    } catch (err) {
      Swal.fire("Network error", "Please check your connection and retry.", "error");
    }
    btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-ticket me-1"></i>Get My Queue Number';
  };

  // Poll status of my queue while visible
  setInterval(() => {
    if (!myQueueId) return;
    fetch("<?= APP_URL ?>api/queue-status.php?id=" + myQueueId).then(r => r.json()).then(res => {
      if (!res.ok) return;
      const map = { waiting: "Waiting", called: "Called — please proceed to counter", serving: "Now being served",
                   completed: "Service completed", skipped: "Marked skipped", cancelled: "Cancelled" };
      document.getElementById("ticketStatus").innerHTML =
        '<span class="badge badge-' + res.status + '">' + (map[res.status] || res.status) + "</span>";
      if (res.counter) document.getElementById("ticketStatus").innerHTML +=
        ' <span class="text-muted">Counter ' + res.counter + "</span>";
    });
  }, 8000);
});
</script>
</body>
</html>
