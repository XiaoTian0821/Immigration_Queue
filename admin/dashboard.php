<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['admin']);
$page_title = 'Admin Dashboard';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="row g-3 mb-4" id="statCards"></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-chart-column text-primary me-2"></i>Applicants per Day (last 14 days)</h2>
      <canvas id="chartDaily" height="160"></canvas>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card p-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Completed vs Pending (today)</h2>
      <canvas id="chartMix" height="160"></canvas>
    </div></div>
  </div>
</div>

<div class="row g-3 mt-0">
  <div class="col-lg-7">
    <div class="card p-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-chart-line text-primary me-2"></i>Queue Statistics (last 6 months)</h2>
      <canvas id="chartMonthly" height="160"></canvas>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card p-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-id-card text-primary me-2"></i>Today by Immigration Service</h2>
      <canvas id="chartService" height="160"></canvas>
    </div></div>
  </div>
</div>

<?php
$today = QueueManager::today();
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  const s = document.getElementById("statCards");
  IQMS.get("../api/stats.php?scope=admin").then(res => {
    if (!res.ok) return;
    const st = res.stats;
    const cards = [
      ["Total Applicants Today", st.total, "fa-user-group", "#0d3b66"],
      ["Waiting", st.waiting, "fa-hourglass-half", "#856404"],
      ["Being Served", st.serving + st.called, "fa-person", "#0c5460"],
      ["Completed", st.completed, "fa-circle-check", "#155724"],
      ["Cancelled", st.cancelled + st.skipped, "fa-ban", "#721c24"],
      ["Available Counters", st.available_counters + " / " + st.active_counters, "fa-desktop", "#0d3b66"],
    ];
    s.innerHTML = cards.map(function (c) {
      return "<div class=\"col-6 col-md-4 col-xl-2\"><div class=\"card stat-card p-3 text-center border-start\" style=\"border-left:4px solid " + c[3] + "\">" +
        "<i class=\"fa-solid " + c[2] + " fa-2x\" style=\"color:" + c[3] + "\"></i>" +
        "<div class=\"fs-2 fw-bold mt-1\">" + c[1] + "</div>" +
        "<div class=\"text-muted small\">" + c[0] + "</div></div></div>";
    }).join("");

    new Chart(document.getElementById("chartDaily"), {
      type: "line",
      data: {
        labels: res.daily.map(r => r.d.slice(5)),
        datasets: [{
          label: "Issued", data: res.daily.map(r => r.total), borderColor: "#1d8fb0",
          backgroundColor: "rgba(29,143,176,.15)", tension: .35, fill: true
        },{
          label: "Closed", data: res.daily.map(r => r.closed), borderColor: "#0d3b66",
          backgroundColor: "rgba(13,59,102,.1)", tension: .35
        }]
      },
      options: { plugins: { legend: { position: "bottom" } }, scales: { beginAtZero: true } }
    });

    new Chart(document.getElementById("chartMix"), {
      type: "doughnut",
      data: {
        labels: ["Pending", "Completed"],
        datasets: [{ data: [res.status_mix.pending, res.status_mix.completed],
          backgroundColor: ["#f0ad4e", "#5cb85c"], borderWidth: 2 }]
      },
      options: { plugins: { legend: { position: "bottom" } } }
    });

    new Chart(document.getElementById("chartMonthly"), {
      type: "bar",
      data: {
        labels: res.monthly.map(r => r.m),
        datasets: [
          { label: "Completed", data: res.monthly.map(r => r.completed || 0), backgroundColor: "#5cb85c" },
          { label: "Open",      data: res.monthly.map(r => r.pending || 0), backgroundColor: "#f0ad4e" }
        ]
      },
      options: { plugins: { legend: { position: "bottom" } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
    });

    new Chart(document.getElementById("chartService"), {
      type: "bar",
      data: {
        labels: res.by_service.map(r => r.name),
        datasets: [
          { label: "Completed", data: res.by_service.map(r => r.completed || 0), backgroundColor: "#5cb85c" },
          { label: "Open",      data: res.by_service.map(r => r.open || 0), backgroundColor: "#f0ad4e" }
        ]
      },
      options: { indexAxis: "y", plugins: { legend: { position: "bottom" } } }
    });
  });
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php'; ?>

