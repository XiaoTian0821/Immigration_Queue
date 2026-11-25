<?php
require_once __DIR__ . '/../config/config.php';
Security::requireAuth(['officer']);
$page_title = 'Officer Dashboard';
include __DIR__ . '/../parts/layout-top.php';
?>
<div class="row g-3 mb-4" id="statCards"></div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-chart-column text-primary me-2"></i>My Queue Volume (last 14 days)</h2>
      <canvas id="chartDaily" height="150"></canvas>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card p-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i>My Today: Completed vs Open</h2>
      <canvas id="chartMix" height="150"></canvas>
    </div></div>
  </div>
</div>

<div class="row g-3 mt-0">
  <div class="col-lg-12">
    <div class="card p-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-bolt text-danger me-2"></i>Quick Actions</h2>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="new-applicant.php"><i class="fa-solid fa-user-plus me-1"></i>Register Applicant</a>
        <a class="btn btn-success" href="queue-ops.php"><i class="fa-solid fa-phone-volume me-1"></i>Open Queue Console</a>
        <a class="btn btn-outline-primary" href="my-queues.php"><i class="fa-solid fa-list-check me-1"></i>My Queue History</a>
        <a class="btn btn-outline-secondary" href="../../public/display.php" target="_blank"><i class="fa-solid fa-tv me-1"></i>Display Screen</a>
      </div>
    </div></div>
  </div>
</div>

<?php
$page_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
  IQMS.get("../api/stats.php?scope=officer").then(res => {
    if (!res.ok) return;
    const st = res.stats;
    const cards = [
      ["Total Queues Today (mine)", st.today_mine_total, "fa-list-check", "#0d3b66"],
      ["Currently Open (mine)", st.today_mine_serving, "fa-person", "#0c5460"],
      ["Completed Today (mine)", st.today_mine_completed, "fa-circle-check", "#155724"],
      ["Office Waiting", st.waiting, "fa-hourglass-half", "#856404"],
      ["Avg Wait Time (today)", res.avg_wait_min + " min", "fa-stopwatch", "#721c24"],
      ["Avg Service Time (today)", res.avg_service_min + " min", "fa-timer", "#556c8b"],
    ];
    document.getElementById("statCards").innerHTML = cards.map(function (c) {
      return "<div class=\"col-6 col-md-4 col-xl-2\"><div class=\"card stat-card p-3 text-center border-start\" style=\"border-left:4px solid " + c[3] + "\">" +
        "<i class=\"fa-solid " + c[2] + " fa-2x\" style=\"color:" + c[3] + "\"></i>" +
        "<div class=\"fs-2 fw-bold mt-1\">" + c[1] + "</div>" +
        "<div class=\"text-muted small\">" + c[0] + "</div></div></div>";
    }).join("");

    new Chart(document.getElementById("chartDaily"), {
      type: "line",
      data: {
        labels: res.daily.map(r => r.d.slice(5)),
        datasets: [{ label: "Issued", data: res.daily.map(r => r.total),
          borderColor: "#1d8fb0", backgroundColor: "rgba(29,143,176,.15)", tension: .35, fill: true }]
      },
      options: { scales: { beginAtZero: true }, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById("chartMix"), {
      type: "doughnut",
      data: {
        labels: ["Open (mine)", "Completed (mine)"],
        datasets: [{ data: [st.today_mine_serving, st.today_mine_completed],
          backgroundColor: ["#f0ad4e", "#5cb85c"], borderWidth: 2 }]
      },
      options: { plugins: { legend: { position: "bottom" } } }
    });
  });
});
</script>';
include __DIR__ . '/../parts/layout-bottom.php';
?>
