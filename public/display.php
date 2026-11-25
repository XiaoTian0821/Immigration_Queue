<?php
/**
 * PUBLIC - Live Queue Display Screen (for office TV / monitor).
 * Shows current + next + recently completed numbers per service, rotating.
 * Refreshes automatically via AJAX (no manual reload). No personal data shown.
 */
require_once __DIR__ . '/../config/config.php';

$services = Database::select('SELECT id, name, code, active FROM immigration_services WHERE active = 1 ORDER BY id');
$officeName = (string)Database::value("SELECT setting_value FROM system_settings WHERE setting_key = 'office_name'") ?: 'Immigration Office';
$poll = (int)Database::value("SELECT COALESCE(setting_value,5) FROM system_settings WHERE setting_key = 'next_queue_poll'") ?: 5;
$poll = max(2, min(60, $poll));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Queue Display — <?= Security::e($officeName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  html,body{height:100%}
  .display-screen{background:radial-gradient(circle at 50% 0%, #0a2540 0%, #04121f 70%);animation:fadeIn .5s}
  @keyframes fadeIn{from{opacity:0}to{opacity:1}}
  .dsp-header{display:flex;justify-content:space-between;align-items:center;padding:2.5vh 4vw}
  .dsp-title{font-size:clamp(1.2rem,3vw,2.4rem);letter-spacing:.1em;color:#8fb3d9;text-transform:uppercase}
  .dsp-clock{font-size:clamp(1.5rem,4vw,3.2rem);font-weight:700;color:#fff;font-variant-numeric:tabular-nums}
  .dsp-date{font-size:clamp(.8rem,1.8vw,1.3rem);color:#5b7fa6;text-align:right}
  .dsp-main{flex:1;display:flex;align-items:center;justify-content:center;gap:4vw;padding:2vh 4vw}
  .dsp-col{flex:1;max-width:34vw;text-align:center}
  .dsp-col .svc-name{font-size:clamp(1rem,2.6vw,2rem);letter-spacing:.3em;color:#5b7fa6;text-transform:uppercase;margin-bottom:1vh}
  .now-label{font-size:clamp(1rem,2.5vw,2rem);letter-spacing:.4em;color:#4dd2ff;text-transform:uppercase;margin-bottom:1vh}
  .now-num{font-size:clamp(4rem,12vw,10rem);font-weight:800;line-height:.95;color:#4dd2ff;
           text-shadow:0 0 30px rgba(77,210,255,.4)}
  .now-counter{font-size:clamp(1.2rem,3vw,2.4rem);color:#fff;margin-top:1vh}
  .next-label{font-size:clamp(1rem,2.5vw,2rem);letter-spacing:.4em;color:#8fb3d9;margin-top:5vh}
  .next-list{display:flex;gap:2vw;justify-content:center;margin-top:2vh;flex-wrap:wrap}
  .next-num{font-size:clamp(2rem,6vw,4.5rem);font-weight:700;color:#7fe3ff}
  .completed-strip{display:flex;gap:2.5vw;justify-content:center;padding:2.5vh 4vw;flex-wrap:wrap}
  .completed-strip .c-num{font-size:clamp(1rem,2.4vw,1.8rem);color:#4a6b8c;font-weight:600;
    border:1px solid #24455f;border-radius:.5rem;padding:.4em 1em;background:rgba(255,255,255,.02)}
  .empty{color:#2f4d68}
</style>
</head>
<body>
<div class="display-screen">
  <div class="dsp-header">
    <div>
      <div class="dsp-title"><i class="fa-solid fa-landmark me-2"></i><?= Security::e($officeName) ?></div>
      <div class="dsp-title" style="font-size:clamp(.8rem,2vw,1.6rem);letter-spacing:.5em">NOW SERVING</div>
    </div>
    <div>
      <div class="dsp-clock" id="clock">--:--:--</div>
      <div class="dsp-date" id="date"></div>
    </div>
  </div>

  <div class="dsp-main" id="main"><!-- services injected here --></div>

  <div class="completed-strip">
    <span style="font-size:clamp(1rem,2.4vw,1.8rem);color:#4dd2ff;letter-spacing:.3em">RECENTLY COMPLETED</span>
    <div id="completed" style="display:flex;gap:2.5vw;flex-wrap:wrap;align-items:center">
      <span class="c-num empty">—</span>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const services = <?= json_encode($services) ?>;
  const POLL = <?= max(2, $poll) ?> * 1000;
  let feed = [], idx = 0;

  // Clock
  function tick(){
    const d = new Date();
    document.getElementById("clock").textContent = d.toLocaleTimeString([], {hour12:false});
    document.getElementById("date").textContent = d.toLocaleDateString([], {weekday:"long", year:"numeric", month:"long", day:"numeric"});
  }
  tick(); setInterval(tick, 1000);

  const main = document.getElementById("main");

  function render() {
    // Show up to 3 service columns; rotate which services are visible each poll
    const visible = feed.slice(idx, idx + 3);
    const blocks = visible.length ? visible : [null];
    main.innerHTML = blocks.map(block => block ? blockHtml(block) :
      `<div class="dsp-col"><div class="svc-name">Service</div><div class="now-num empty">--</div></div>`
    ).join("");
  }

  function esc(s){return String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}

  function blockHtml(f){
    const cur = f.current;
    const nexts = (f.nexts||[]).map(n => `<div class="next-num">${esc(n.queue_number)}</div>`).join("") || `<div class="next-num empty">--</div>`;
    return `<div class="dsp-col">
      <div class="svc-name">${esc(f.service.name)} ${f.service.code ? "· <span style='color:#4dd2ff'>"+esc(f.service.code)+"</span>" : ""}</div>
      <div class="now-label">Now Serving</div>
      <div class="now-num ${cur ? "" : "empty"}">${cur ? esc(cur.queue_number) : "--"}</div>
      <div class="now-counter">${cur && cur.counter_number ? "COUNTER <b>"+esc(cur.counter_number)+"</b>" : (cur ? "AT COUNTER" : "WAITING")}</div>
      <div class="next-label">Next</div>
      <div class="next-list">${nexts}</div>
      <div style="color:#4a6b8c;font-size:clamp(.8rem,1.8vw,1.4rem);margin-top:2vh">Waiting: <b style="color:#fff">${f.waiting||0}</b></div>
    </div>`;
  }

  function refreshCompleted(){
    const all = feed.flatMap(f => f.completed || []);
    const box = document.getElementById("completed");
    if (!all.length) { box.innerHTML = '<span class="c-num empty">—</span>'; return; }
    box.innerHTML = all.slice(0,8).map(c => `<span class="c-num">${esc(c.queue_number)}</span>`).join("");
  }

  function loadFeed() {
    fetch("<?= APP_URL ?>api/display-feed.php").then(r => r.json()).then(res => {
      if (!res.ok) return;
      feed = res.services || [];
      idx = (idx + 1) % Math.max(1, services.length);
      render();
      refreshCompleted();
    }).catch(() => {});
  }

  loadFeed();
  setInterval(loadFeed, POLL);
});
</script>
</body>
</html>
