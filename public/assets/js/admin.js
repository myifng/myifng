/* ==========================================================
   न्यूज़रूम एडमिन: पूरी JavaScript (एक फ़ाइल, सभी एडमिन पेज)
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };

  /* ---------- मोबाइल साइडबार ---------- */
  var sidebar = $('#sidebar'), backdrop = $('.sidebar-backdrop');
  function toggleSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('show', open);
  }
  $$('[data-sidebar-toggle]').forEach(function (b) { b.addEventListener('click', function () { toggleSidebar(!sidebar.classList.contains('open')); }); });
  $$('[data-sidebar-close]').forEach(function (b) { b.addEventListener('click', function () { toggleSidebar(false); }); });

  /* ---------- डार्क / लाइट मोड (कुकी में याद) ---------- */
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var r = document.documentElement, next = r.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      r.setAttribute('data-bs-theme', next);
      document.cookie = 'admin_theme=' + next + ';path=/;max-age=31536000;samesite=lax';
      document.dispatchEvent(new CustomEvent('themechange'));
    });
  });

  /* ---------- पुष्टि: data-confirm वाले फ़ॉर्म ---------- */
  var modalEl = $('#confirmModal'), pendingForm = null, modal = null;
  if (modalEl && window.bootstrap) {
    modal = new bootstrap.Modal(modalEl);
    $('[data-confirm-yes]', modalEl).addEventListener('click', function () {
      if (pendingForm) { pendingForm.dataset.confirmed = '1'; pendingForm.submit(); }
      modal.hide();
    });
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[data-confirm]') || f.dataset.confirmed === '1') return;
    e.preventDefault();
    if (!modal) { if (window.confirm(f.dataset.confirm)) { f.dataset.confirmed = '1'; f.submit(); } return; }
    pendingForm = f;
    $('[data-confirm-text]', modalEl).textContent = f.dataset.confirm;
    modal.show();
  });

  /* ---------- दोबारा सबमिट रोकें ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || f.method.toLowerCase() !== 'post') return;
    var btn = f.querySelector('button[type="submit"]:not([data-no-lock])');
    if (btn) setTimeout(function () { btn.disabled = true; btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'); }, 0);
  });

  /* ---------- पासवर्ड दिखाएँ ---------- */
  $$('[data-show-password]').forEach(function (c) {
    c.addEventListener('change', function () { var i = $(c.dataset.showPassword); if (i) i.type = c.checked ? 'text' : 'password'; });
  });

  /* ---------- नाम से स्लग (अंग्रेज़ी नाम पर; हिंदी का स्लग सर्वर बनाता है) ---------- */
  $$('[data-slug-source]').forEach(function (src) {
    var target = $(src.dataset.slugSource);
    if (!target || target.readOnly) return;
    var touched = target.value !== '';
    target.addEventListener('input', function () { touched = target.value !== ''; });
    src.addEventListener('input', function () {
      if (touched) return;
      target.value = src.value.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
    });
  });

  /* ---------- मेनू खोज (Ctrl+K) ---------- */
  var qs = $('#quickSearch'), qr = $('#quickResults');
  if (qs && qr) {
    var items = $$('[data-nav-label]').map(function (a) { return { label: a.dataset.navLabel, href: a.href }; });
    $$('.topbar .dropdown-menu a.dropdown-item[href]').forEach(function (a) { items.push({ label: a.textContent.trim(), href: a.href }); });
    var active = -1, results = [];
    function render() {
      var q = qs.value.trim().toLowerCase();
      results = q ? items.filter(function (i) { return i.label.toLowerCase().indexOf(q) !== -1; }).slice(0, 8) : [];
      qr.innerHTML = '';
      results.forEach(function (r, i) {
        var a = document.createElement('a');
        a.className = 'list-group-item list-group-item-action' + (i === active ? ' active' : '');
        a.href = r.href; a.textContent = r.label;
        qr.appendChild(a);
      });
      if (q && !results.length) qr.innerHTML = '<div class="list-group-item text-body-secondary small">कुछ नहीं मिला</div>';
      qr.hidden = !q;
    }
    qs.addEventListener('input', function () { active = 0; render(); });
    qs.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { active = Math.min(active + 1, results.length - 1); render(); e.preventDefault(); }
      if (e.key === 'ArrowUp') { active = Math.max(active - 1, 0); render(); e.preventDefault(); }
      if (e.key === 'Enter' && results[active]) { location.href = results[active].href; e.preventDefault(); }
      if (e.key === 'Escape') { qs.value = ''; render(); qs.blur(); }
    });
    qs.addEventListener('blur', function () { setTimeout(function () { qr.hidden = true; }, 150); });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); qs.focus(); }
    });
  }

  /* ---------- अनुमति मैट्रिक्स ---------- */
  var table = $('[data-matrix-table]');
  if (table) {
    var boxes = function (row) { return $$('input[name="permissions[]"]:not(:disabled)', row); };
    var syncRow = function (tr) {
      var rowBox = $('.mx-row', tr); if (!rowBox) return;
      var b = boxes(tr), on = b.filter(function (x) { return x.checked; }).length;
      rowBox.checked = b.length > 0 && on === b.length;
      rowBox.indeterminate = on > 0 && on < b.length;
    };
    $$('tr', table).forEach(syncRow);
    table.addEventListener('change', function (e) {
      var tr = e.target.closest('tr');
      if (e.target.classList.contains('mx-row')) { boxes(tr).forEach(function (b) { b.checked = e.target.checked; }); }
      syncRow(tr);
    });
    $$('.mx-col', table).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var col = $$('input[data-action="' + btn.dataset.col + '"]:not(:disabled)', table);
        var allOn = col.every(function (b) { return b.checked; });
        col.forEach(function (b) { b.checked = !allOn; });
        $$('tr', table).forEach(syncRow);
      });
    });
    $$('[data-matrix]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var mode = btn.dataset.matrix;
        $$('input[name="permissions[]"]:not(:disabled)', table).forEach(function (b) {
          b.checked = mode === 'all' ? true : mode === 'view' ? b.dataset.action === 'view' : false;
        });
        $$('tr', table).forEach(syncRow);
      });
    });
  }

  /* ---------- यूज़र-स्तर अनुमति: रंग बदलें ---------- */
  $$('select.ov').forEach(function (s) {
    s.addEventListener('change', function () { s.classList.remove('ov-inherit', 'ov-allow', 'ov-deny'); s.classList.add('ov-' + s.value); });
  });

  /* ---------- चार्ट (Chart.js): <canvas data-chart='{...}'> ---------- */
  var charts = [];
  function css(v, fallback) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim() || fallback; }
  function drawCharts() {
    if (!window.Chart) return;
    charts.forEach(function (c) { c.destroy(); });
    charts = [];
    var ink = css('--muted', '#6d6873'), grid = css('--line', '#e7e4e5'), brand = css('--brand', '#d71920');
    var palette = { brand: brand, muted: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#5b5762' : '#c9c5cc' };
    $$('canvas[data-chart]').forEach(function (cv) {
      var cfg; try { cfg = JSON.parse(cv.dataset.chart); } catch (e) { return; }
      charts.push(new Chart(cv, {
        type: cfg.type || 'bar',
        data: {
          labels: cfg.labels,
          datasets: cfg.datasets.map(function (d) {
            var col = palette[d.color] || d.color || brand;
            return { label: d.label, data: d.data, backgroundColor: col, borderColor: col, borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 26, tension: .35, fill: cfg.type === 'line' ? { target: 'origin', above: col + '22' } : false, pointRadius: cfg.type === 'line' ? 2 : 0 };
          })
        },
        options: {
          responsive: true, maintainAspectRatio: false, animation: { duration: 400 },
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { position: 'bottom', labels: { color: ink, usePointStyle: true, boxWidth: 8, font: { family: 'Mukta' } } }, tooltip: { padding: 10 } },
          scales: {
            x: { stacked: !!cfg.stacked, grid: { display: false }, ticks: { color: ink, font: { family: 'Mukta' } } },
            y: { stacked: !!cfg.stacked, beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { color: ink, precision: 0 } }
          }
        }
      }));
    });
  }
  drawCharts();
  document.addEventListener('themechange', drawCharts);
})();
