/* IQMS shared front-end helpers */
(function () {
  const CSRF = document.currentScript ? document.currentScript.dataset.csrf || '' : '';
  window.IQMS = {
    csrf: CSRF,

    /** fetch JSON POST with CSRF; returns parsed body. */
    post(url, body = {}) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF,
        },
        body: JSON.stringify(body),
      }).then(r => r.json());
    },

    get(url) {
      return fetch(url, { headers: { 'X-CSRF-Token': CSRF } }).then(r => r.json());
    },

    /** SweetAlert2 confirm wrapper: resolves true/false. */
    confirm(title, text = '', icon = 'question') {
      return Swal.fire({
        title, text, icon,
        showCancelButton: true,
        confirmButtonColor: '#0d3b66',
        cancelButtonColor: '#d13',
      }).then(r => r.isConfirmed);
    },

    toast(title, type = 'success') {
      Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
        title,
        icon: type === 'success' ? 'success' : 'error',
      });
    },

    err(t) { console.error(t); },
  };

  // Mobile sidebar toggle
  const btn = document.getElementById('sidebarToggle');
  if (btn) btn.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));

  // Day reset button (admin)
  document.querySelectorAll('[data-reset-day]').forEach(el => el.addEventListener('click', async (e) => {
    e.preventDefault();
    const ok = await IQMS.confirm('Reset today\'s queue numbers?', 'All open (waiting/called/serving) queues for today will be cancelled so a new day can start fresh.', 'warning');
    if (!ok) return;
    const res = await IQMS.post(el.getAttribute('href'));
    if (res.ok) IQMS.toast(`Reset complete: ${res.closed} open queue(s) closed.`);
    else Swal.fire('Error', res.error || 'Reset failed.', 'error');
  }));

  // Auto-dismiss sweetalerts triggered by page load (data-iqms-alert)
  const el = document.querySelector('[data-iqms-alert]');
  if (el) IQMS.toast(el.dataset.iqmsAlert, el.dataset.iqmsAlertType || 'success');
})();
