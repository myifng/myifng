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
          plugins: { legend: { display: cfg.legend !== false, position: 'bottom', labels: { color: ink, usePointStyle: true, boxWidth: 8, font: { family: 'Mukta' } } }, tooltip: { padding: 10 } },
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

/* ==========================================================
   Phase 2: एडिटर, मेनू बिल्डर, होमपेज बिल्डर, बल्क, SEO प्रीव्यू
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';

  function toast(msg, type) {
    var wrap = $('.toast-stack');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-stack'; wrap.setAttribute('aria-live', 'polite'); document.body.appendChild(wrap); }
    var t = document.createElement('div');
    t.className = 'toast-msg ' + (type || 'ok');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  }
  function post(url, data) {
    return fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'सर्वर से सही जवाब नहीं मिला (' + r.status + ')' }; }); })
      .catch(function () { return { ok: false, message: 'नेटवर्क में दिक्कत है। दोबारा कोशिश करें।' }; });
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  /* ---------- रंग: hex दिखाएँ ---------- */
  $$('[data-color-sync]').forEach(function (c) {
    c.addEventListener('input', function () { var t = $(c.dataset.colorSync); if (t) t.value = c.value; });
  });

  /* ---------- अक्षर गिनती ---------- */
  $$('[data-count]').forEach(function (inp) {
    var limit = +inp.dataset.count, n = document.createElement('span');
    n.className = 'char-count';
    inp.insertAdjacentElement('afterend', n);
    var upd = function () { var l = inp.value.length; n.textContent = l + ' / ' + limit; n.classList.toggle('over', l > limit); };
    inp.addEventListener('input', upd); upd();
  });

  /* ---------- Google प्रीव्यू (SEO) ---------- */
  var st = $('[data-serp-title]'), sd = $('[data-serp-desc]'), ss = $('[data-serp-slug]');
  if (st) {
    var title = $('[data-seo-title]'), mt = $('#f_meta_title'), md = $('#f_meta_description'), slug = $('#f_slug');
    var upd = function () {
      st.textContent = (mt && mt.value) || (title && title.value) || 'पेज का शीर्षक';
      if (sd) sd.textContent = (md && md.value) || 'पेज का विवरण यहाँ दिखेगा।';
      if (ss && slug) ss.textContent = slug.value || '…';
    };
    [title, mt, md, slug].forEach(function (i) { if (i) i.addEventListener('input', upd); });
  }

  /* ---------- बिना सेव किए छोड़ने पर चेतावनी ---------- */
  $$('form[data-unsaved]').forEach(function (f) {
    var dirty = false;
    f.addEventListener('input', function () { dirty = true; });
    f.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });

  /* ---------- बल्क चुनाव ---------- */
  $$('[data-bulk-form]').forEach(function (f) {
    var bar = $('.bulk-bar', f), count = $('[data-bulk-count]', f), all = $('[data-check-all]', f), action = $('select[name="action"]', f);
    var boxes = function () { return $$('input[name="ids[]"]', f); };
    var upd = function () {
      var n = boxes().filter(function (b) { return b.checked; }).length;
      bar.hidden = n === 0; count.textContent = n + ' चुने गए';
      if (all) { all.checked = n > 0 && n === boxes().length; all.indeterminate = n > 0 && n < boxes().length; }
    };
    var noun = f.dataset.bulkNoun || 'पेज', merge = $('[data-merge-target]', f);
    var syncExtra = function () {
      $$('[data-show-for]', f).forEach(function (x) { x.hidden = !action || action.value !== x.dataset.showFor; });
      if (!merge) return;
      merge.hidden = !action || action.value !== 'merge';
      var keep = merge.value;
      merge.innerHTML = '<option value="">किस टैग में मिलाएँ?</option>';
      boxes().filter(function (b) { return b.checked; }).forEach(function (b) {
        var o = document.createElement('option'); o.value = b.value; o.textContent = b.dataset.name || b.value; merge.appendChild(o);
      });
      merge.value = keep; merge.required = !merge.hidden;
    };
    f.addEventListener('change', function (e) {
      if (e.target === all) boxes().forEach(function (b) { b.checked = all.checked; });
      if (e.target === action) {
        if (action.value === 'delete' || action.value === 'trash') f.dataset.confirm = action.value === 'delete' ? 'चुने गए ' + noun + ' हमेशा के लिए हट जाएँगे।' : 'चुने गए ' + noun + ' ट्रैश में जाएँगे।';
        else if (action.value === 'merge') f.dataset.confirm = 'चुने गए टैग एक टैग में मिल जाएँगे और बाकी हट जाएँगे।';
        else delete f.dataset.confirm;
      }
      if (e.target !== merge) syncExtra();
      upd();
    });
  });

  /* ==========================================================
     रिच एडिटर: <div data-editor><textarea name="content"></textarea></div>
     सेव करते समय सर्वर HTML को साफ़ (sanitize) करता है।
     ========================================================== */
  var TOOLS = [
    ['block'], '|', ['bold', 'fa-bold', 'बोल्ड (Ctrl+B)'], ['italic', 'fa-italic', 'इटैलिक (Ctrl+I)'], ['underline', 'fa-underline', 'अंडरलाइन'], ['strikeThrough', 'fa-strikethrough', 'काटें'],
    '|', ['insertUnorderedList', 'fa-list-ul', 'बुलेट सूची'], ['insertOrderedList', 'fa-list-ol', 'नंबर सूची'], ['quote', 'fa-quote-left', 'उद्धरण'],
    '|', ['link', 'fa-link', 'लिंक'], ['unlink', 'fa-link-slash', 'लिंक हटाएँ'], ['image', 'fa-image', 'इमेज अपलोड'], ['library', 'fa-photo-film', 'मीडिया लाइब्रेरी से इमेज'], ['youtube', 'fa-youtube', 'YouTube वीडियो', 'fa-brands'],
    ['table', 'fa-table', 'टेबल'], ['button', 'fa-hand-pointer', 'बटन'], ['callout', 'fa-circle-info', 'सूचना बॉक्स'], ['insertHorizontalRule', 'fa-minus', 'लाइन'],
    '|', ['removeFormat', 'fa-eraser', 'फ़ॉर्मैटिंग हटाएँ'], ['undo', 'fa-rotate-left', 'पहले जैसा'], ['redo', 'fa-rotate-right', 'फिर से'], ['source', 'fa-code', 'HTML देखें']
  ];
  function youtubeId(url) { var m = String(url).match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/))([A-Za-z0-9_-]{11})/); return m ? m[1] : (/^[A-Za-z0-9_-]{11}$/.test(url) ? url : ''); }

  $$('[data-editor]').forEach(function (wrap) {
    var ta = $('textarea', wrap), upload = wrap.dataset.upload, source = false;
    var bar = document.createElement('div'); bar.className = 'rte-bar'; bar.setAttribute('role', 'toolbar'); bar.setAttribute('aria-label', 'फ़ॉर्मैटिंग');
    var area = document.createElement('div'); area.className = 'rte-area'; area.contentEditable = 'true'; area.setAttribute('role', 'textbox'); area.setAttribute('aria-multiline', 'true'); area.setAttribute('aria-label', ta.getAttribute('aria-label') || 'सामग्री');
    area.innerHTML = ta.value;
    var file = document.createElement('input'); file.type = 'file'; file.accept = 'image/*'; file.hidden = true;

    TOOLS.forEach(function (t) {
      if (t[0] === 'library' && !document.getElementById('mediaPicker')) return;
      if (t === '|') { bar.insertAdjacentHTML('beforeend', '<span class="rte-sep"></span>'); return; }
      if (t[0] === 'block') {
        var sel = document.createElement('select'); sel.className = 'form-select form-select-sm rte-block'; sel.setAttribute('aria-label', 'पैराग्राफ़ का प्रकार');
        sel.innerHTML = '<option value="p">सामान्य</option><option value="h2">हेडिंग 2</option><option value="h3">हेडिंग 3</option><option value="h4">हेडिंग 4</option>';
        sel.addEventListener('change', function () { area.focus(); document.execCommand('formatBlock', false, sel.value); });
        bar.appendChild(sel); return;
      }
      var b = document.createElement('button'); b.type = 'button'; b.className = 'rte-btn'; b.dataset.cmd = t[0]; b.title = t[2]; b.setAttribute('aria-label', t[2]);
      b.innerHTML = '<i class="' + (t[3] || 'fa-solid') + ' ' + t[1] + '"></i>';
      bar.appendChild(b);
    });
    ta.hidden = true;
    wrap.insertBefore(bar, ta); wrap.insertBefore(area, ta); wrap.appendChild(file);

    function insert(html) { area.focus(); document.execCommand('insertHTML', false, html); }
    function uploadImage(f) {
      if (!f || !upload) return;
      var fd = new FormData(); fd.append('image', f);
      wrap.classList.add('is-busy');
      post(upload, fd).then(function (r) {
        wrap.classList.remove('is-busy');
        if (!r.ok) { toast(r.message || 'इमेज अपलोड नहीं हुई', 'err'); return; }
        var alt = prompt('इमेज का विवरण (alt text, सुलभता और SEO के लिए):', '') || '';
        insert('<figure><img src="' + esc(r.url) + '" alt="' + esc(alt) + '"><figcaption>' + (alt ? esc(alt) : 'कैप्शन यहाँ लिखें') + '</figcaption></figure><p><br></p>');
      });
    }
    file.addEventListener('change', function () { uploadImage(file.files[0]); file.value = ''; });

    bar.addEventListener('click', function (e) {
      var b = e.target.closest('.rte-btn'); if (!b) return;
      var c = b.dataset.cmd;
      if (c === 'source') {
        source = !source;
        if (source) { ta.value = area.innerHTML; ta.hidden = false; area.hidden = true; ta.classList.add('form-control', 'font-monospace', 'rte-src'); }
        else { area.innerHTML = ta.value; ta.hidden = true; area.hidden = false; }
        b.classList.toggle('active', source);
        $$('.rte-btn, .rte-block', bar).forEach(function (x) { if (x !== b) x.disabled = source; });
        return;
      }
      area.focus();
      switch (c) {
        case 'quote': document.execCommand('formatBlock', false, 'blockquote'); break;
        case 'link': var u = prompt('लिंक का पता (https://… या /page/…):', 'https://'); if (u && /^(https?:\/\/|\/|mailto:|tel:)/i.test(u)) document.execCommand('createLink', false, u); else if (u) toast('लिंक https://, / , mailto: या tel: से शुरू हो', 'err'); break;
        case 'image': file.click(); break;
        case 'library':
          var sel = window.getSelection(), range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
          window.MediaPicker.open({ kind: 'image', onChoose: function (it, alt, cap) {
            area.focus();
            if (range) { sel.removeAllRanges(); sel.addRange(range); }
            var credit = it.credit ? ' <small>(' + esc(it.credit) + ')</small>' : '';
            insert('<figure><img src="' + esc(it.large) + '" alt="' + esc(alt) + '"' + (it.width ? ' width="' + it.width + '" height="' + it.height + '"' : '') + '><figcaption>' + esc(cap || alt || 'कैप्शन यहाँ लिखें') + credit + '</figcaption></figure><p><br></p>');
          } });
          break;
        case 'youtube': var y = youtubeId(prompt('YouTube वीडियो का लिंक:', '') || ''); if (y) insert('<figure class="embed" data-youtube="' + y + '">▶ YouTube वीडियो: ' + y + '</figure><p><br></p>'); else toast('YouTube लिंक सही नहीं है', 'err'); break;
        case 'table':
          var rc = (prompt('कितनी पंक्तियाँ × कॉलम? (जैसे 3x3)', '3x3') || '').match(/(\d+)\s*[x×*]\s*(\d+)/i); if (!rc) break;
          var r = Math.min(+rc[1], 30), cl = Math.min(+rc[2], 10), h = '<table class="table"><thead><tr>';
          for (var j = 0; j < cl; j++) h += '<th>हेडिंग</th>';
          h += '</tr></thead><tbody>';
          for (var i = 1; i < r; i++) { h += '<tr>'; for (j = 0; j < cl; j++) h += '<td>&nbsp;</td>'; h += '</tr>'; }
          insert(h + '</tbody></table><p><br></p>'); break;
        case 'button':
          var text = prompt('बटन पर क्या लिखा हो?', 'और जानें'); if (!text) break;
          var href = prompt('बटन का लिंक:', 'https://');
          if (href && /^(https?:\/\/|\/)/i.test(href)) insert('<a class="btn-cta" href="' + esc(href) + '">' + esc(text) + '</a>&nbsp;'); else toast('लिंक सही नहीं है', 'err');
          break;
        case 'callout':
          var kind = (prompt('सूचना बॉक्स का प्रकार: info / warning / success / danger', 'info') || 'info').trim();
          if (['info', 'warning', 'success', 'danger'].indexOf(kind) === -1) kind = 'info';
          insert('<div class="callout callout-' + kind + '"><p>यहाँ अपनी सूचना लिखें।</p></div><p><br></p>'); break;
        default: document.execCommand(c, false, null);
      }
    });

    // चिपकाते समय सिर्फ़ सादा टेक्स्ट (Word/वेबसाइट की गंदी फ़ॉर्मैटिंग नहीं)
    area.addEventListener('paste', function (e) {
      var files = e.clipboardData && e.clipboardData.files;
      if (files && files.length && files[0].type.indexOf('image/') === 0) { e.preventDefault(); uploadImage(files[0]); return; }
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
    area.addEventListener('drop', function (e) {
      var f = e.dataTransfer && e.dataTransfer.files[0];
      if (f && f.type.indexOf('image/') === 0) { e.preventDefault(); uploadImage(f); }
    });
    area.addEventListener('input', function () { area.closest('form') && area.closest('form').dispatchEvent(new Event('input', { bubbles: true })); });
    var form = wrap.closest('form');
    if (form) form.addEventListener('submit', function () { if (!source) ta.value = area.innerHTML; });
  });

  /* ==========================================================
     मेनू बिल्डर: फ़्लैट सूची + depth; ड्रैग, ऊपर/नीचे, अंदर/बाहर
     ========================================================== */
  var mb = $('[data-menu-builder]');
  if (mb) {
    var data = JSON.parse($('#menuData').textContent), items = data.items, typeLabels = data.types;
    var tree = $('[data-tree]', mb), tpl = $('#mbItemTpl'), maxDepth = +mb.dataset.maxDepth, readonly = mb.dataset.readonly === '1';
    var dirty = false, dragFrom = null;

    var blockEnd = function (i) { var d = items[i].depth, j = i + 1; while (j < items.length && items[j].depth > d) j++; return j; };
    var normalize = function () { items.forEach(function (it, i) { var prev = i ? items[i - 1].depth : -1; it.depth = Math.max(0, Math.min(it.depth, prev + 1, maxDepth - 1)); }); };
    var moveBlock = function (from, to) {
      var end = blockEnd(from), block = items.splice(from, end - from);
      if (to > from) to -= block.length;
      Array.prototype.splice.apply(items, [to, 0].concat(block));
      return to;
    };
    var shiftBlock = function (i, delta) { var end = blockEnd(i); for (var k = i; k < end; k++) items[k].depth += delta; };
    var markDirty = function () { dirty = true; };

    function render() {
      normalize();
      tree.innerHTML = '';
      $('[data-tree-empty]', mb).hidden = items.length > 0;
      items.forEach(function (it, i) {
        var li = tpl.content.firstElementChild.cloneNode(true);
        li.dataset.index = i; li.style.setProperty('--depth', it.depth);
        if (readonly) { li.draggable = false; $('.mb-actions', li).remove(); }
        $('.mb-title', li).textContent = it.title;
        $('.mb-type', li).textContent = (typeLabels[it.type] || it.type) + (it.ref_title && it.ref_title !== it.title ? ': ' + it.ref_title : '') + (it.url ? ' · ' + it.url : '');
        $('.mb-off', li).hidden = !!+it.is_active;
        if (it.missing) li.classList.add('is-missing');
        var ed = $('.mb-edit', li);
        $('[data-f="title"]', ed).value = it.title;
        var urlWrap = $('[data-url-wrap]', ed);
        if (it.type === 'custom' || it.type === 'external') $('[data-f="url"]', ed).value = it.url || ''; else urlWrap.hidden = true;
        $('[data-f="target_blank"]', ed).checked = !!+it.target_blank;
        $('[data-f="is_active"]', ed).checked = !!+it.is_active;
        ed.addEventListener('input', function (e) {
          var f = e.target.dataset.f; if (!f) return;
          it[f] = e.target.type === 'checkbox' ? (e.target.checked ? 1 : 0) : e.target.value;
          $('.mb-title', li).textContent = it.title; $('.mb-off', li).hidden = !!+it.is_active; markDirty();
        });
        tree.appendChild(li);
      });
    }

    tree.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]'); if (!b) return;
      var li = b.closest('.mb-item'), i = +li.dataset.index, act = b.dataset.act;
      if (act === 'edit') { var ed = $('.mb-edit', li); ed.hidden = !ed.hidden; if (!ed.hidden) $('input', ed).focus(); return; }
      if (act === 'remove') { var n = blockEnd(i) - i; if (n > 1 && !confirm('इसके ' + (n - 1) + ' उप-लिंक भी हटेंगे। आगे बढ़ें?')) return; items.splice(i, n); }
      if (act === 'up' && i > 0) { var p = i - 1; while (p > 0 && items[p].depth > items[i].depth) p--; moveBlock(i, p); }
      if (act === 'down') { var end = blockEnd(i); if (end < items.length) moveBlock(i, blockEnd(end)); }
      if (act === 'in' && i > 0 && items[i].depth < Math.min(items[i - 1].depth + 1, maxDepth - 1)) shiftBlock(i, 1);
      if (act === 'out' && items[i].depth > 0) shiftBlock(i, -1);
      markDirty(); render();
      var again = tree.children[Math.min(i, tree.children.length - 1)]; if (again) { var sameBtn = $('[data-act="' + act + '"]', again); if (sameBtn) sameBtn.focus(); }
    });

    // ड्रैग-ड्रॉप (उप-लिंक साथ चलते हैं)
    tree.addEventListener('dragstart', function (e) { var li = e.target.closest('.mb-item'); if (!li) return; dragFrom = +li.dataset.index; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', dragFrom); });
    tree.addEventListener('dragend', function () { $$('.dragging, .drop-before, .drop-after', tree).forEach(function (x) { x.classList.remove('dragging', 'drop-before', 'drop-after'); }); });
    tree.addEventListener('dragover', function (e) {
      var li = e.target.closest('.mb-item'); if (!li || dragFrom === null) return;
      e.preventDefault();
      var r = li.getBoundingClientRect(), after = e.clientY > r.top + r.height / 2;
      $$('.drop-before, .drop-after', tree).forEach(function (x) { x.classList.remove('drop-before', 'drop-after'); });
      li.classList.add(after ? 'drop-after' : 'drop-before');
    });
    tree.addEventListener('drop', function (e) {
      var li = e.target.closest('.mb-item'); if (!li || dragFrom === null) return;
      e.preventDefault();
      var t = +li.dataset.index, after = li.classList.contains('drop-after');
      if (t >= dragFrom && t < blockEnd(dragFrom)) { dragFrom = null; render(); return; }
      var to = after ? blockEnd(t) : t, depth = items[t].depth;
      var newIdx = moveBlock(dragFrom, to);
      var delta = depth - items[newIdx].depth; shiftBlock(newIdx, delta);
      dragFrom = null; markDirty(); render();
    });

    // आइटम जोड़ें
    var typeSel = $('#mb_type', mb);
    var syncPanels = function () {
      $$('[data-type-panel]', mb).forEach(function (p) { p.hidden = p.dataset.typePanel.split(' ').indexOf(typeSel.value) === -1; });
    };
    if (typeSel) {
      typeSel.addEventListener('change', syncPanels); syncPanels();
      $('[data-add-item]', mb).addEventListener('click', function () {
        var type = typeSel.value, it = { type: type, title: $('#mb_title').value.trim(), reference_id: null, url: null, target_blank: type === 'external' ? 1 : 0, is_active: 1, depth: 0 };
        var refSel = $('#mb_ref_' + type);
        if (refSel) {
          if (!refSel.value) { toast('पहले चुनें कि किससे जोड़ना है', 'err'); return; }
          it.reference_id = +refSel.value; it.ref_title = refSel.options[refSel.selectedIndex].text; it.title = it.title || it.ref_title;
        }
        if (type === 'custom' || type === 'external') {
          it.url = $('#mb_url').value.trim();
          if (!it.url) { toast('पता (URL) लिखें', 'err'); return; }
        }
        if (!it.title) it.title = typeLabels[type];
        items.push(it); $('#mb_title').value = ''; if ($('#mb_url')) $('#mb_url').value = '';
        markDirty(); render(); toast('“' + it.title + '” जुड़ गया। सेव करना न भूलें।');
      });
    }

    // सेव
    var saveBtn = $('[data-save-menu]', mb), errBox = $('[data-menu-errors]', mb);
    if (saveBtn) saveBtn.addEventListener('click', function () {
      var fd = new FormData();
      fd.append('name', $('#mb_name').value);
      fd.append('items', JSON.stringify(items.map(function (i) { return { title: i.title, type: i.type, reference_id: i.reference_id, url: i.url, target_blank: +i.target_blank, is_active: +i.is_active, depth: i.depth }; })));
      saveBtn.disabled = true;
      post(mb.dataset.save, fd).then(function (r) {
        saveBtn.disabled = false;
        if (r.ok) { dirty = false; errBox.hidden = true; toast(r.message); return; }
        errBox.innerHTML = '<b>' + esc(r.message) + '</b>' + (r.errors ? '<ul class="mb-0">' + r.errors.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' : '');
        errBox.hidden = false; errBox.scrollIntoView({ block: 'center', behavior: 'smooth' });
      });
    });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    render();
  }

  /* ==========================================================
     होमपेज बिल्डर: ड्रैग/बटन से क्रम (AJAX), ढाँचा प्रीव्यू
     ========================================================== */
  var hb = $('[data-home-builder]');
  if (hb) {
    var list = $('[data-sortable="sections"]', hb), wire = $('[data-wire-list]', hb), wireBox = $('[data-wire]', hb), dragEl = null;
    var labels = {};
    $$('.hb-section', list).forEach(function (li) { labels[li.dataset.id] = $('.hb-name b', li).textContent; });

    function drawWire() {
      var dev = wireBox.dataset.device;
      wire.innerHTML = '';
      $$('.hb-section', list).forEach(function (li) {
        var on = !li.classList.contains('is-off') && li.dataset[dev] === '1';
        var d = document.createElement('div');
        d.className = 'wire-block wb-' + li.dataset.type + (on ? '' : ' hidden-block');
        d.textContent = labels[li.dataset.id];
        wire.appendChild(d);
      });
    }
    function saveOrder() {
      var fd = new FormData();
      $$('.hb-section', list).forEach(function (li) { fd.append('ids[]', li.dataset.id); });
      post(hb.dataset.reorder, fd).then(function (r) { toast(r.message, r.ok ? 'ok' : 'err'); });
      drawWire();
    }
    list.addEventListener('dragstart', function (e) {
      if (!e.target.closest('.hb-handle') && e.target.closest('input,select,textarea,button,.collapse')) { e.preventDefault(); return; }
      dragEl = e.target.closest('.hb-section'); if (dragEl) { dragEl.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', dragEl.dataset.id); }
    });
    list.addEventListener('dragover', function (e) {
      var over = e.target.closest('.hb-section'); if (!dragEl || !over || over === dragEl) return;
      e.preventDefault();
      var r = over.getBoundingClientRect();
      list.insertBefore(dragEl, e.clientY > r.top + r.height / 2 ? over.nextSibling : over);
    });
    list.addEventListener('dragend', function () { if (dragEl) { dragEl.classList.remove('dragging'); dragEl = null; saveOrder(); } });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('[data-move]'); if (!b) return;
      var li = b.closest('.hb-section');
      if (b.dataset.move === 'up' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
      else if (b.dataset.move === 'down' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
      else return;
      b.focus(); saveOrder();
    });
    $$('[data-preview-device]', hb).forEach(function (btn) {
      btn.addEventListener('click', function () {
        wireBox.dataset.device = btn.dataset.previewDevice;
        $$('[data-preview-device]', hb).forEach(function (x) { x.classList.toggle('active', x === btn); x.setAttribute('aria-pressed', x === btn); });
        drawWire();
      });
    });
    // "हाथ से चुनें" पर ही ख़बर IDs का खाना
    $$('.hb-form', hb).forEach(function (f) {
      var src = $('select[name="settings[source]"]', f), box = $('[data-show-when-manual]', f);
      if (!src || !box) return;
      var sync = function () { box.hidden = src.value !== 'manual'; };
      src.addEventListener('change', sync); sync();
    });
    // #section-ID से आए हों तो वह सेक्शन खोलें
    if (location.hash.indexOf('#section-') === 0) {
      var target = $(location.hash + ' .collapse');
      if (target && window.bootstrap) { new bootstrap.Collapse(target, { toggle: true }); target.closest('.hb-section').classList.add('flash'); }
    }
    drawWire();
  }
})();

/* ==========================================================
   Phase 3: मीडिया अपलोड, मीडिया पिकर, लोकेशन खोज, छोटे काम
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  /** XHR अपलोड (प्रगति के साथ) */
  function uploadFile(url, file, extra, onProgress) {
    return new Promise(function (resolve) {
      var fd = new FormData(); fd.append('file', file);
      Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
      var x = new XMLHttpRequest();
      x.open('POST', url);
      x.setRequestHeader('X-CSRF-Token', csrf); x.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); x.setRequestHeader('Accept', 'application/json');
      x.upload.onprogress = function (e) { if (e.lengthComputable && onProgress) onProgress(Math.round(e.loaded * 100 / e.total)); };
      x.onload = function () {
        var r; try { r = JSON.parse(x.responseText); } catch (e) { r = { ok: false, message: x.status === 413 ? 'फ़ाइल सर्वर की सीमा से बड़ी है।' : x.status === 429 ? 'बहुत ज़्यादा अपलोड; थोड़ी देर बाद कोशिश करें।' : 'सर्वर से सही जवाब नहीं मिला (' + x.status + ')' }; }
        resolve(r);
      };
      x.onerror = function () { resolve({ ok: false, message: 'नेटवर्क में दिक्कत है।' }); };
      x.send(fd);
    });
  }
  window.AdminUpload = uploadFile; // Phase 7 (गैलरी) भी इसे इस्तेमाल करती है

  /* ---------- मीडिया पेज: ड्रैग-ड्रॉप अपलोड (एक-एक करके) ---------- */
  $$('[data-uploader]').forEach(function (zone) {
    var input = $('[data-uploader-input]', zone), queue = $('[data-uploader-queue]', zone), done = 0, running = false, list = [];
    function add(files) {
      Array.prototype.forEach.call(files, function (f) {
        var li = document.createElement('li');
        li.innerHTML = '<span class="uq-name"></span><span class="uq-bar"><i></i></span><span class="uq-msg">प्रतीक्षा…</span>';
        $('.uq-name', li).textContent = f.name;
        queue.appendChild(li); list.push([f, li]);
      });
      if (!running) next();
    }
    function next() {
      var item = list.shift();
      if (!item) { running = false; if (done) { $('.uq-done', zone) || queue.insertAdjacentHTML('afterend', '<p class="uq-done small mt-2 mb-0">' + done + ' फ़ाइलें अपलोड हुईं। <a href="">सूची ताज़ा करें</a></p>'); } return; }
      running = true;
      var li = item[1], bar = $('.uq-bar i', li), msg = $('.uq-msg', li);
      msg.textContent = 'अपलोड…';
      uploadFile(zone.dataset.url, item[0], { folder_id: zone.dataset.folder || 0 }, function (p) { bar.style.width = p + '%'; }).then(function (r) {
        li.classList.add(r.ok ? 'ok' : 'err');
        if (r.ok) { done++; bar.style.width = '100%'; msg.innerHTML = r.warning ? esc(r.warning) : '<a href="' + esc(r.edit) + '">विवरण भरें</a>'; }
        else msg.textContent = r.message || 'अपलोड नहीं हुआ';
        next();
      });
    }
    input.addEventListener('change', function () { add(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('over'); }); });
    zone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files); });
  });

  /* ---------- मीडिया पिकर (modal) ---------- */
  var mp = $('#mediaPicker');
  if (mp && window.bootstrap) {
    var modal = new bootstrap.Modal(mp), grid = $('[data-mp-grid]', mp), more = $('[data-mp-more]', mp), moreWrap = $('[data-mp-more-wrap]', mp);
    var empty = $('[data-mp-empty]', mp), search = $('[data-mp-search]', mp), side = $('[data-mp-side]', mp), choose = $('[data-mp-choose]', mp), status = $('[data-mp-status]', mp);
    var state = { page: 1, q: '', kind: 'image', items: {}, selected: null, onChoose: null, timer: null };

    function card(it, prepend) {
      state.items[it.id] = it;
      var li = document.createElement('li');
      li.className = 'media-card' + (it.kind !== 'image' ? ' is-file' : '');
      li.dataset.id = it.id; li.tabIndex = 0; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
      li.innerHTML = '<span class="media-thumb">' + (it.thumb ? '<img src="' + esc(it.thumb) + '" alt="" loading="lazy">' : '<i class="fa-solid fa-file"></i>') + '</span><div class="media-meta"><b></b><span></span></div>';
      $('.media-meta b', li).textContent = it.title || it.name;
      $('.media-meta span', li).textContent = (it.width ? it.width + '×' + it.height + ' · ' : '') + it.size;
      if (prepend) grid.insertBefore(li, grid.firstChild); else grid.appendChild(li);
      return li;
    }
    function load(reset) {
      if (reset) { state.page = 1; grid.innerHTML = ''; state.items = {}; select(null); }
      status.textContent = 'लोड हो रहा है…';
      var u = mp.dataset.browse + '?kind=' + encodeURIComponent(state.kind) + '&q=' + encodeURIComponent(state.q) + '&page=' + state.page;
      fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          r.items.forEach(function (it) { card(it); });
          moreWrap.hidden = !r.has_more; empty.hidden = grid.children.length > 0; status.textContent = '';
        })
        .catch(function () { status.textContent = 'लाइब्रेरी लोड नहीं हुई।'; });
    }
    function select(id) {
      state.selected = id ? state.items[id] : null;
      $$('.media-card', grid).forEach(function (c) { var on = +c.dataset.id === +id; c.classList.toggle('selected', on); c.setAttribute('aria-selected', on ? 'true' : 'false'); });
      choose.disabled = !state.selected; side.hidden = !state.selected;
      if (state.selected) {
        var it = state.selected;
        $('[data-mp-prev]', side).src = it.thumb || '';
        $('[data-mp-name]', side).textContent = it.title || it.name;
        $('[data-mp-info]', side).textContent = (it.width ? it.width + '×' + it.height + ' · ' : '') + it.size + (it.credit ? ' · ' + it.credit : '');
        $('[data-mp-alt]', side).value = it.alt || '';
        $('[data-mp-caption]', side).value = it.caption || '';
      }
    }
    grid.addEventListener('click', function (e) { var c = e.target.closest('.media-card'); if (c) select(c.dataset.id); });
    grid.addEventListener('dblclick', function (e) { var c = e.target.closest('.media-card'); if (c) { select(c.dataset.id); choose.click(); } });
    grid.addEventListener('keydown', function (e) {
      var c = e.target.closest('.media-card'); if (!c) return;
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (state.selected && +state.selected.id === +c.dataset.id) choose.click(); else select(c.dataset.id); }
      if (e.key === 'ArrowRight' && c.nextElementSibling) c.nextElementSibling.focus();
      if (e.key === 'ArrowLeft' && c.previousElementSibling) c.previousElementSibling.focus();
    });
    more.addEventListener('click', function () { state.page++; load(false); });
    search.addEventListener('input', function () { clearTimeout(state.timer); state.timer = setTimeout(function () { state.q = search.value.trim(); load(true); }, 300); });
    // चुनाव modal बंद होने के बाद लागू हो (खुले modal में focus बाहर नहीं जा सकता, जैसे एडिटर में)
    var pending = null;
    choose.addEventListener('click', function () {
      if (!state.selected || !state.onChoose) return;
      pending = [state.onChoose, state.selected, $('[data-mp-alt]', side).value.trim(), $('[data-mp-caption]', side).value.trim()];
      modal.hide();
    });
    mp.addEventListener('hidden.bs.modal', function () {
      if (!pending) return;
      var run = pending; pending = null;
      run[0](run[1], run[2], run[3]);
    });
    var up = $('[data-mp-upload]', mp);
    if (up) up.addEventListener('change', function () {
      var files = Array.prototype.slice.call(up.files); up.value = '';
      var run = function () {
        var f = files.shift(); if (!f) return;
        status.textContent = f.name + ' अपलोड हो रही है…';
        uploadFile(mp.dataset.upload, f, state.kind ? { only: state.kind } : {}, function (p) { status.textContent = f.name + ': ' + p + '%'; }).then(function (r) {
          if (r.ok) { card(r.item, true); empty.hidden = true; select(r.item.id); status.textContent = r.warning || 'अपलोड हो गई। Alt टेक्स्ट भरें।'; $('[data-mp-alt]', side).focus(); }
          else status.textContent = r.message || 'अपलोड नहीं हुई';
          run();
        });
      };
      run();
    });

    window.MediaPicker = {
      open: function (opts) {
        state.onChoose = opts.onChoose; state.kind = opts.kind == null ? 'image' : opts.kind;
        if (up) up.accept = { image: 'image/*', video: 'video/*', audio: 'audio/*', document: '.pdf,.docx,.xlsx,.pptx,.txt,.csv' }[state.kind] || '';
        $('#mediaPickerTitle', mp).lastChild.textContent = { image: 'इमेज चुनें', video: 'वीडियो चुनें', audio: 'ऑडियो चुनें', document: 'दस्तावेज़ चुनें' }[state.kind] || 'फ़ाइल चुनें';
        search.value = ''; state.q = '';
        load(true); modal.show();
      }
    };
    mp.addEventListener('shown.bs.modal', function () { search.focus(); });
  }

  /* ---------- इमेज खाना (media_field) ---------- */
  $$('[data-media-field]').forEach(function (box) {
    var input = $('input[type="hidden"]', box), prev = $('[data-mf-preview]', box), clear = $('[data-mf-clear]', box), pick = $('[data-media-pick]', box);
    var changed = function () { box.classList.toggle('has-value', !!input.value); clear.hidden = !input.value; input.dispatchEvent(new Event('input', { bubbles: true })); };
    if (pick && window.MediaPicker) pick.addEventListener('click', function () {
      window.MediaPicker.open({ kind: pick.dataset.kind || 'image', onChoose: function (it) {
        input.value = it.file;
        prev.innerHTML = it.thumb ? '<img src="' + esc(it.thumb) + '" alt="">' : '<span class="mf-file"><i class="fa-regular fa-file"></i><small>' + esc(it.name || it.file) + '</small></span>';
        changed(); pick.focus();
      } });
    });
    clear.addEventListener('click', function () { input.value = ''; prev.innerHTML = '<i class="fa-regular fa-image"></i>'; changed(); });
  });

  /* ---------- लोकेशन खोज-चयन (ऊपर वाली लोकेशन आदि) ---------- */
  $$('[data-location-picker]').forEach(function (box) {
    var hidden = $('input[type="hidden"]', box), text = $('input[type="search"]', box), list = $('.loc-results', box), timer = null, active = -1, rows = [];
    function render() {
      list.innerHTML = '';
      rows.forEach(function (r, i) {
        var li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action' + (i === active ? ' active' : '');
        li.id = 'locopt-' + r.id; li.setAttribute('role', 'option');
        li.innerHTML = '<b></b> <span class="badge text-bg-light"></span><span class="d-block small text-body-secondary font-monospace"></span>';
        $('b', li).textContent = r.label; $('.badge', li).textContent = r.type_label; $('.small', li).textContent = r.path ? '/' + r.path + '/' : '';
        li.addEventListener('mousedown', function (e) { e.preventDefault(); pickRow(r); });
        list.appendChild(li);
      });
      if (!rows.length) list.innerHTML = '<li class="list-group-item small text-body-secondary">कुछ नहीं मिला</li>';
      list.hidden = false; text.setAttribute('aria-expanded', 'true');
    }
    function pickRow(r) { hidden.value = r.id; text.value = r.name + ' (' + r.type_label + ')'; list.hidden = true; text.setAttribute('aria-expanded', 'false'); hidden.dispatchEvent(new Event('input', { bubbles: true })); }
    text.addEventListener('input', function () {
      clearTimeout(timer);
      if (!text.value.trim()) { hidden.value = ''; list.hidden = true; return; }
      timer = setTimeout(function () {
        fetch(box.dataset.search + '?q=' + encodeURIComponent(text.value.trim()), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); }).then(function (r) { rows = r.items; active = 0; render(); });
      }, 250);
    });
    text.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { active = Math.min(active + 1, rows.length - 1); render(); e.preventDefault(); }
      if (e.key === 'ArrowUp') { active = Math.max(active - 1, 0); render(); e.preventDefault(); }
      if (e.key === 'Enter' && rows[active]) { e.preventDefault(); pickRow(rows[active]); }
      if (e.key === 'Escape') { list.hidden = true; }
    });
    text.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; text.setAttribute('aria-expanded', 'false'); }, 150); });
  });

  /* ---------- आइकन/रंग प्रीव्यू ---------- */
  $$('[data-icon-preview]').forEach(function (inp) {
    var box = $(inp.dataset.iconPreview); if (!box) return;
    var color = $('#f_color');
    inp.addEventListener('input', function () {
      var cls = inp.value.trim().replace(/^fa-(solid|regular|brands)\s+/, '');
      if (/^fa-[a-z0-9-]+$/.test(cls)) box.innerHTML = '<i class="fa-solid ' + cls + '"></i>';
    });
    if (color) color.addEventListener('input', function () { box.style.setProperty('--c', color.value); });
  });

  /* ---------- कॉपी बटन ---------- */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = $(b.dataset.copy); if (!i) return;
      i.select();
      (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.resolve(document.execCommand('copy'))).then(function () { var t = b.textContent; b.textContent = 'कॉपी हो गया'; setTimeout(function () { b.textContent = t; }, 1500); });
    });
  });
})();

/* ==========================================================
   Phase 4: ख़बर फ़ॉर्म (टैग, संबंधित ख़बरें, गैलरी, शेड्यूल, शब्द गिनती), असाइनमेंट अटैचमेंट
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function withQ(url, q) { return url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q); }
  function getJSON(url) { return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }); }
  function dirty(el) { el.dispatchEvent(new Event('input', { bubbles: true })); }

  /** खोज-सूची (कीबोर्ड के साथ): input + ul.loc-results */
  function suggest(input, list, fetchRows, render, onPick) {
    var rows = [], active = -1, timer = null;
    function draw() {
      list.innerHTML = '';
      rows.forEach(function (r, i) {
        var li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action' + (i === active ? ' active' : '');
        li.setAttribute('role', 'option'); li.innerHTML = render(r);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); onPick(r); list.hidden = true; });
        list.appendChild(li);
      });
      list.hidden = rows.length === 0;
    }
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (!q) { rows = []; draw(); return; }
      timer = setTimeout(function () { fetchRows(q).then(function (r) { rows = r; active = 0; draw(); }); }, 250);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' && rows.length) { active = Math.min(active + 1, rows.length - 1); draw(); e.preventDefault(); }
      else if (e.key === 'ArrowUp' && rows.length) { active = Math.max(active - 1, 0); draw(); e.preventDefault(); }
      else if (e.key === 'Enter') { if (!list.hidden && rows[active]) { e.preventDefault(); onPick(rows[active]); rows = []; draw(); } }
      else if (e.key === 'Escape') { list.hidden = true; }
    });
    input.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; }, 150); });
    return { clear: function () { rows = []; draw(); } };
  }

  /* ---------- टैग इनपुट: चिप्स + सुझाव; hidden में कॉमा से ---------- */
  $$('[data-tag-input]').forEach(function (box) {
    var hidden = $('input[type="hidden"]', box), input = $('input[type="text"]', box), list = $('.loc-results', box), max = +box.dataset.max || 15;
    var tags = hidden.value.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
    var wrap = document.createElement('div'); wrap.className = 'tag-chips'; box.insertBefore(wrap, input);
    function sync() {
      hidden.value = tags.join(', ');
      wrap.innerHTML = tags.map(function (t, i) { return '<span class="tag-chip">' + esc(t) + '<button type="button" data-i="' + i + '" aria-label="हटाएँ: ' + esc(t) + '">×</button></span>'; }).join('');
      input.disabled = tags.length >= max; input.placeholder = tags.length >= max ? 'सीमा पूरी' : 'टैग लिखें, Enter दबाएँ';
    }
    function add(t) {
      t = t.replace(/[,،]/g, ' ').trim().slice(0, 100);
      if (t && tags.map(function (x) { return x.toLowerCase(); }).indexOf(t.toLowerCase()) === -1 && tags.length < max) { tags.push(t); sync(); dirty(hidden); }
      input.value = '';
    }
    wrap.addEventListener('click', function (e) { var b = e.target.closest('button[data-i]'); if (!b) return; tags.splice(+b.dataset.i, 1); sync(); dirty(hidden); input.focus(); });
    input.addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' && list.hidden) || e.key === ',') { e.preventDefault(); add(input.value); }
      if (e.key === 'Backspace' && !input.value && tags.length) { tags.pop(); sync(); dirty(hidden); }
    });
    input.addEventListener('blur', function () { setTimeout(function () { if (input.value.trim()) add(input.value); }, 200); });
    if (box.dataset.search) suggest(input, list, function (q) { return getJSON(withQ(box.dataset.search, q)).then(function (r) { return r.items; }); },
      function (r) { return esc(r.name) + ' <small class="text-body-secondary">' + (+r.usage_count || 0) + '</small>'; }, function (r) { add(r.name); });
    sync();
  });

  /* ---------- ख़बर चुनने वाला (संबंधित, होमपेज) ---------- */
  $$('[data-news-picker]').forEach(function (box) {
    var ul = $('.np-list', box), input = $('input[type="search"]', box), list = $('.loc-results', box), max = +box.dataset.max || 10;
    function ids() { return $$('li[data-id]', ul).map(function (li) { return +li.dataset.id; }); }
    function refresh() { input.disabled = ids().length >= max; }
    ul.addEventListener('click', function (e) { var b = e.target.closest('.ml-remove'); if (!b) return; b.closest('li').remove(); refresh(); dirty(box); });
    suggest(input, list, function (q) {
      var u = withQ(box.dataset.search, q) + (box.dataset.exclude ? '&exclude=' + box.dataset.exclude : '');
      return getJSON(u).then(function (r) { var have = ids(); return r.items.filter(function (x) { return have.indexOf(x.id) === -1; }); });
    }, function (r) { return '<b>#' + r.id + '</b> ' + esc(r.title) + ' <small class="text-body-secondary">' + esc(r.status) + '</small>'; }, function (r) {
      if (ids().length >= max) return;
      var li = document.createElement('li'); li.dataset.id = r.id;
      li.innerHTML = '<span>#' + r.id + ' ' + esc(r.title) + '</span><input type="hidden" name="' + esc(box.dataset.name) + '" value="' + r.id + '"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button>';
      ul.appendChild(li); input.value = ''; refresh(); dirty(box);
    });
    refresh();
  });

  /* ---------- मीडिया सूची (गैलरी, अटैचमेंट) ---------- */
  $$('[data-media-list]').forEach(function (box) {
    var add = $('[data-ml-add]', box), max = +box.dataset.max || 20;
    box.addEventListener('click', function (e) { var b = e.target.closest('.ml-remove'); if (!b) return; b.closest('.ml-item').remove(); dirty(box); });
    if (add && window.MediaPicker) add.addEventListener('click', function () {
      if ($$('.ml-item', box).length >= max) return;
      window.MediaPicker.open({ kind: box.dataset.kind, onChoose: function (it) {
        if ($('.ml-item[data-id="' + it.id + '"]', box)) return;
        var s = document.createElement('span'); s.className = 'ml-item'; s.dataset.id = it.id;
        s.innerHTML = (it.thumb ? '<img src="' + esc(it.thumb) + '" alt="">' : '<span class="ml-file"><i class="fa-regular fa-file"></i><small>' + esc(it.name) + '</small></span>') +
          '<input type="hidden" name="' + esc(box.dataset.name) + '" value="' + it.id + '"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button>';
        box.insertBefore(s, add); dirty(box); add.focus();
      } });
    });
  });

  /* ---------- फ़ॉर्म के बाहर वाले पिकर बटन (जैसे वीडियो): input में path ---------- */
  $$('[data-media-pick][data-pick-value]').forEach(function (b) {
    if (b.closest('[data-media-field]') || !window.MediaPicker) return;
    b.addEventListener('click', function () {
      window.MediaPicker.open({ kind: b.dataset.kind, onChoose: function (it) { var i = $(b.dataset.mediaPick); if (i) { i.value = it.file; dirty(i); } } });
    });
  });

  /* ---------- ख़बर फ़ॉर्म: शेड्यूल, पुष्टि, शब्द गिनती ---------- */
  var nf = $('.news-form');
  if (nf) {
    var box = $('[data-schedule-box]', nf), sBtn = $('[data-schedule-btn]', nf);
    if (sBtn && box) sBtn.addEventListener('click', function (e) {
      var inp = $('input', box);
      if (box.hidden || !inp.value) { e.preventDefault(); box.hidden = false; inp.focus(); inp.reportValidity && inp.setCustomValidity(''); }
    });
    $$('[data-confirm-then]', nf).forEach(function (b) { b.addEventListener('click', function (e) { if (!confirm(b.dataset.confirmThen)) e.preventDefault(); }); });
    var wc = $('[data-word-count]', nf), area = $('.rte-area', nf);
    if (wc && area) {
      var count = function () { var t = (area.innerText || '').trim(); var n = t ? t.split(/\s+/).length : 0; wc.textContent = n + ' शब्द · पढ़ने में लगभग ' + Math.max(1, Math.ceil(n / 200)) + ' मिनट'; };
      area.addEventListener('input', count); count();
    }
  }
})();

/* ==========================================================
   Phase 7: ब्रेकिंग फ़ॉर्म, लाइव ब्लॉग कंट्रोल, गैलरी फ़ोटो, वेब स्टोरी बिल्डर, लाइव टीवी
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function toast(msg, type) {
    var wrap = $('.toast-stack');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-stack'; wrap.setAttribute('aria-live', 'polite'); document.body.appendChild(wrap); }
    var t = document.createElement('div'); t.className = 'toast-msg ' + (type || 'ok'); t.textContent = msg; wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  }
  function send(url, fd) {
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'सर्वर से सही जवाब नहीं मिला (' + r.status + ')' }; }); })
      .catch(function () { return { ok: false, message: 'नेटवर्क में दिक्कत है। दोबारा कोशिश करें।' }; });
  }
  function firstError(r) { if (r.errors) { var k = Object.keys(r.errors)[0]; if (k) return r.errors[k]; } return r.message || 'कुछ गड़बड़ हुई।'; }

  /* ---------- ब्रेकिंग: "तय समय" चुनने पर ही ख़त्म होने का खाना ---------- */
  $$('[data-ends-in]').forEach(function (sel) {
    var box = $('[data-ends-at]', sel.form);
    var sync = function () { if (box) box.hidden = sel.value !== 'custom'; };
    sel.addEventListener('change', sync); sync();
  });

  /* ---------- data-when="source:youtube,embed": किसी चुनाव पर ही खाना दिखे ---------- */
  $$('[data-when]').forEach(function (el) {
    var parts = el.dataset.when.split(':'), form = el.closest('form'); if (!form) return;
    var ctl = form.elements[parts[0]], vals = parts[1].split(','); if (!ctl) return;
    var sync = function () { var v = ctl.value; if (ctl.length && !ctl.tagName) { v = (Array.prototype.find.call(ctl, function (r) { return r.checked; }) || {}).value; } el.hidden = vals.indexOf(v) === -1; };
    (ctl.tagName ? [ctl] : Array.prototype.slice.call(ctl)).forEach(function (c) { c.addEventListener('change', sync); });
    sync();
  });

  /* ---------- गैलरी: कई फ़ोटो अपलोड, लाइब्रेरी से, क्रम, हटाना ---------- */
  var gp = $('[data-gp]');
  if (gp) {
    var gpList = $('[data-gp-list]', gp), gpTpl = $('[data-gp-template]', gp), gpCount = $('[data-gp-count]', gp), gpEmpty = $('[data-gp-empty]', gp);
    var gpStatus = $('[data-gp-status]', gp), gpMax = +gp.dataset.max || 200, gpN = 0;
    var gpRefresh = function () { var c = gpList.children.length; gpCount.textContent = c; gpEmpty.hidden = c > 0; gpList.dispatchEvent(new Event('input', { bubbles: true })); };
    var gpAdd = function (it) {
      if (gpList.children.length >= gpMax) { toast('ज़्यादा से ज़्यादा ' + gpMax + ' फ़ोटो।', 'err'); return; }
      var t = document.createElement('div'); t.innerHTML = gpTpl.innerHTML.replace(/__i__/g, 'n' + (++gpN) + '_' + Date.now());
      var li = t.firstElementChild;
      $('[data-gp-image]', li).value = it.file; $('[data-gp-thumb]', li).src = it.thumb || '';
      var cap = $('input[name$="[caption]"]', li), cr = $('input[name$="[credit]"]', li);
      if (cap && it.caption) cap.value = it.caption; if (cr && it.credit) cr.value = it.credit;
      gpList.appendChild(li); gpRefresh();
    };
    gpList.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return; var li = b.closest('[data-gp-item]');
      if (b.hasAttribute('data-gp-remove')) { li.remove(); gpRefresh(); }
      else if (b.hasAttribute('data-gp-up') && li.previousElementSibling) { gpList.insertBefore(li, li.previousElementSibling); b.focus(); gpRefresh(); }
      else if (b.hasAttribute('data-gp-down') && li.nextElementSibling) { gpList.insertBefore(li.nextElementSibling, li); b.focus(); gpRefresh(); }
    });
    var gpPick = $('[data-gp-pick]', gp);
    if (gpPick && window.MediaPicker) gpPick.addEventListener('click', function () {
      window.MediaPicker.open({ kind: 'image', onChoose: function (it, alt, cap) { gpAdd({ file: it.file, thumb: it.thumb, caption: cap || it.caption, credit: it.credit }); gpPick.focus(); } });
    });
    var gpUpload = function (files) {
      if (!gp.dataset.upload || !window.AdminUpload) return;
      files = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type); });
      var total = files.length, done = 0;
      var next = function () {
        var f = files.shift();
        if (!f) { gpStatus.textContent = done + ' / ' + total + ' फ़ोटो जुड़ीं। सेव करना न भूलें।'; return; }
        gpStatus.textContent = (done + 1) + ' / ' + total + ': ' + f.name + ' अपलोड हो रही है…';
        window.AdminUpload(gp.dataset.upload, f, { only: 'image' }, function (pc) { gpStatus.textContent = (done + 1) + ' / ' + total + ': ' + f.name + ' ' + pc + '%'; }).then(function (r) {
          if (r.ok) { done++; gpAdd(r.item); } else toast(f.name + ': ' + (r.message || 'अपलोड नहीं हुई'), 'err');
          next();
        });
      };
      next();
    };
    var gpFiles = $('[data-gp-files]', gp);
    if (gpFiles) gpFiles.addEventListener('change', function () { var f = Array.prototype.slice.call(gpFiles.files); gpFiles.value = ''; gpUpload(f); });
    ['dragenter', 'dragover'].forEach(function (ev) { gp.addEventListener(ev, function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') > -1) { e.preventDefault(); gp.classList.add('drop-over'); } }); });
    ['dragleave', 'drop'].forEach(function (ev) { gp.addEventListener(ev, function () { gp.classList.remove('drop-over'); }); });
    gp.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); gpUpload(e.dataTransfer.files); } });
  }

  /* ---------- वेब स्टोरी बिल्डर: स्लाइड जोड़ें/क्रम/कॉपी/हटाएँ, मीडिया, 9:16 प्रीव्यू ---------- */
  var ws = $('[data-ws]');
  if (ws) {
    var slList = $('[data-sl-list]', ws), slTpl = $('[data-sl-template]', ws), slCount = $('[data-sl-count]', ws), slMax = +slList.dataset.max || 30, slN = 0, current = null;
    var pv = $('[data-ws-preview]', ws), pvMedia = $('[data-wsp-media]', pv), pvText = $('[data-wsp-text]', pv), pvBars = $('[data-wsp-bars]', pv);
    var val = function (li, k) { var el = $('[data-sl="' + k + '"]', li); return el ? el.value : ''; };
    var isVideo = function (p) { return /\.(mp4|webm|mov)$/i.test(p || ''); };
    var render = function () {
      var items = $$('[data-sl-item]', slList);
      items.forEach(function (li, i) { $('[data-sl-no]', li).textContent = 'स्लाइड ' + (i + 1); });
      slCount.textContent = items.length;
      if (!current || !current.parentNode) current = items[0] || null;
      pvBars.innerHTML = items.map(function (li) { return '<i class="' + (li === current ? 'on' : '') + '"></i>'; }).join('');
      if (!current) { pvMedia.innerHTML = ''; pvText.hidden = true; return; }
      var m = val(current, 'media'), thumb = $('[data-sl-thumb] img, [data-sl-thumb] video', current);
      pvMedia.innerHTML = m ? (isVideo(m) ? '<video src="' + esc(thumb ? thumb.src : '') + '" muted autoplay loop playsinline></video>' : '<img src="' + esc(thumb ? thumb.src : '') + '" alt="">') : '';
      pv.className = 'ws-preview pos-' + val(current, 'text_position') + ' theme-' + val(current, 'theme') + (m ? '' : ' no-media');
      $('[data-wsp-h]', pv).textContent = val(current, 'heading'); $('[data-wsp-p]', pv).textContent = val(current, 'body');
      var cta = $('[data-wsp-cta]', pv); cta.textContent = val(current, 'cta_label') || 'और पढ़ें'; cta.hidden = !val(current, 'cta_url');
      pvText.hidden = !val(current, 'heading') && !val(current, 'body');
      items.forEach(function (li) { li.classList.toggle('active', li === current); });
    };
    var fresh = function (html) { var t = document.createElement('div'); t.innerHTML = html.replace(/__i__/g, 's' + (++slN) + '_' + Date.now()); return t.firstElementChild; };
    var changed = function () { slList.dispatchEvent(new Event('input', { bubbles: true })); render(); };
    $('[data-sl-add]', ws).addEventListener('click', function () {
      if ($$('[data-sl-item]', slList).length >= slMax) { toast('ज़्यादा से ज़्यादा ' + slMax + ' स्लाइड।', 'err'); return; }
      var li = fresh(slTpl.innerHTML); slList.appendChild(li); current = li; changed(); $('[data-sl="heading"]', li).focus();
    });
    slList.addEventListener('focusin', function (e) { var li = e.target.closest('[data-sl-item]'); if (li && li !== current) { current = li; render(); } });
    slList.addEventListener('input', function (e) { if (e.target.closest('[data-sl-item]')) render(); });
    slList.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return; var li = b.closest('[data-sl-item]'); current = li;
      if (b.hasAttribute('data-sl-remove')) { if ($$('[data-sl-item]', slList).length > 1 || confirm('आख़िरी स्लाइड हटाएँ?')) { li.remove(); current = null; changed(); } }
      else if (b.hasAttribute('data-sl-up') && li.previousElementSibling) { slList.insertBefore(li, li.previousElementSibling); b.focus(); changed(); }
      else if (b.hasAttribute('data-sl-down') && li.nextElementSibling) { slList.insertBefore(li.nextElementSibling, li); b.focus(); changed(); }
      else if (b.hasAttribute('data-sl-copy')) {
        if ($$('[data-sl-item]', slList).length >= slMax) return;
        var c = fresh(slTpl.innerHTML);
        $$('[data-sl]', li).forEach(function (el) { var t = $('[data-sl="' + el.dataset.sl + '"]', c); if (t) t.value = el.value; });
        $('[data-sl-thumb]', c).innerHTML = $('[data-sl-thumb]', li).innerHTML;
        li.parentNode.insertBefore(c, li.nextSibling); current = c; changed();
      }
      else if (b.hasAttribute('data-sl-clear')) { $('[data-sl="media"]', li).value = ''; $('[data-sl-thumb]', li).innerHTML = '<i class="fa-regular fa-image"></i>'; changed(); }
      else if (b.dataset.slPick && window.MediaPicker) {
        window.MediaPicker.open({ kind: b.dataset.slPick, onChoose: function (it) {
          $('[data-sl="media"]', li).value = it.file;
          $('[data-sl-thumb]', li).innerHTML = it.kind === 'video' || isVideo(it.file) ? '<video src="' + esc(it.url) + '" muted preload="metadata"></video>' : '<img src="' + esc(it.large || it.thumb) + '" alt="">';
          current = li; changed(); b.focus();
        } });
      }
    });
    render();
  }

  /* ---------- लाइव ब्लॉग कंट्रोल: अपडेट डालना/बदलना/पिन/हटाना बिना रीलोड ---------- */
  var lf = $('[data-lu-form]'), list = $('[data-lu-list]');
  if (lf && list) {
    var status = $('[data-lu-status]', lf), submit = $('[data-lu-submit]', lf), cancel = $('[data-lu-cancel]', lf), mode = $('[data-lu-mode]');
    var empty = $('[data-lu-empty]'), count = $('[data-lu-count]'), editing = null;
    var field = function (n) { return lf.elements[n]; };
    var setCount = function () { if (count) count.textContent = list.children.length; if (empty) empty.hidden = list.children.length > 0; };
    var setImage = function (path, thumb) {
      var box = $('[data-media-field]', lf); if (!box) return;
      $('input[type="hidden"]', box).value = path || '';
      $('[data-mf-preview]', box).innerHTML = thumb ? '<img src="' + esc(thumb) + '" alt="">' : '<i class="fa-regular fa-image"></i>';
      box.classList.toggle('has-value', !!path); $('[data-mf-clear]', box).hidden = !path;
    };
    var reset = function () {
      editing = null; lf.reset(); setImage('', '');
      $$('input[type="checkbox"]', lf).forEach(function (c) { c.checked = false; });
      $('span', submit).textContent = 'अपडेट डालें'; mode.textContent = 'नया अपडेट'; cancel.hidden = true;
      var m = $('input[name="_method"]', lf); if (m) m.remove();
    };
    var bindItem = function (li) {
      var edit = $('[data-lu-edit]', li);
      if (edit) edit.addEventListener('click', function () {
        var d = JSON.parse(li.dataset.json); editing = d.id;
        field('title').value = d.title; field('body').value = d.body; field('embed_url').value = d.embed_url; field('posted_at').value = d.posted_at;
        field('is_key').checked = !!d.is_key; field('is_pinned').checked = !!d.is_pinned; setImage(d.image, d.image_thumb);
        if (!$('input[name="_method"]', lf)) lf.insertAdjacentHTML('afterbegin', '<input type="hidden" name="_method" value="PUT">');
        $('span', submit).textContent = 'बदलाव सेव करें'; mode.textContent = 'अपडेट बदलें'; cancel.hidden = false;
        field('body').focus(); lf.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      $$('form[data-lu-ajax]', li).forEach(function (f) {
        f.addEventListener('submit', function (e) {
          e.preventDefault();
          if (f.dataset.luConfirm && !confirm(f.dataset.luConfirm)) return;
          send(f.action, new FormData(f)).then(function (r) {
            if (!r.ok) { toast(firstError(r), 'err'); return; }
            if (r.html) { var n = place(r.html); n.classList.add('flash'); } else { li.remove(); setCount(); if (editing === r.id) reset(); }
            toast(r.message);
          });
        });
      });
    };
    var place = function (html) {
      var tmp = document.createElement('div'); tmp.innerHTML = html.trim(); var li = tmp.firstChild;
      var old = $('[data-update-id="' + li.dataset.updateId + '"]', list);
      if (old) list.replaceChild(li, old); else list.insertBefore(li, list.firstChild);
      bindItem(li); setCount(); return li;
    };
    $$('li[data-update-id]', list).forEach(bindItem);
    cancel.addEventListener('click', reset);
    lf.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); lf.requestSubmit ? lf.requestSubmit() : submit.click(); } });
    lf.addEventListener('submit', function (e) {
      e.preventDefault();
      var url = editing ? lf.dataset.updateBase + editing : lf.dataset.store;
      submit.disabled = true; status.textContent = 'भेज रहे हैं…';
      send(url, new FormData(lf)).then(function (r) {
        submit.disabled = false;
        if (!r.ok) { status.textContent = ''; toast(firstError(r), 'err'); return; }
        var li = place(r.html); li.classList.add('flash');
        status.textContent = r.message; setTimeout(function () { status.textContent = ''; }, 3000);
        reset(); field('body').focus();
      });
    });
  }
})();


/* ==========================================================
   Phase 8: ई-पेपर — PDF → पेज (pdf.js, ब्राउज़र में), पेज अपलोड/क्रम/नाम/हटाना, हॉटस्पॉट
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
  function toast(msg, type) {
    var wrap = $('.toast-stack');
    if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-stack'; wrap.setAttribute('aria-live', 'polite'); document.body.appendChild(wrap); }
    var t = document.createElement('div'); t.className = 'toast-msg ' + (type || 'ok'); t.textContent = msg; wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  }
  function send(url, fd) {
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'सर्वर से सही जवाब नहीं मिला (' + r.status + ')' }; }); })
      .catch(function () { return { ok: false, message: 'नेटवर्क में दिक्कत है।' }; });
  }

  /* ---------- पेज प्रबंधन ---------- */
  var ep = $('[data-ep]'), list = $('[data-ep-pages]');
  if (list) {
    var empty = $('[data-ep-empty]'), count = $('[data-ep-count]'), tpl = $('[data-ep-template]');
    var renum = function () {
      $$('.ep-page', list).forEach(function (li, i) { var n = $('.ep-no', li); if (n) n.textContent = i + 1; });
      var c = list.children.length; if (count) count.textContent = c; if (empty) empty.hidden = c > 0;
    };
    var saveOrder = function () {
      if (!ep) return; var fd = new FormData();
      $$('.ep-page', list).forEach(function (li) { fd.append('ids[]', li.dataset.id); });
      send(ep.dataset.orderUrl, fd).then(function (r) { toast(r.message || (r.ok ? 'क्रम सेव हुआ' : 'क्रम सेव नहीं हुआ'), r.ok ? 'ok' : 'err'); });
    };
    list.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return; var li = b.closest('.ep-page');
      if (b.dataset.epMove) {
        var d = +b.dataset.epMove;
        if (d < 0 && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
        else if (d > 0 && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
        else return;
        b.focus(); renum(); saveOrder();
      } else if (b.dataset.epDelete) {
        if (!confirm('यह पेज हट जाएगा।')) return;
        var fd = new FormData(); fd.append('_method', 'DELETE');
        send(b.dataset.epDelete, fd).then(function (r) { if (r.ok) { li.remove(); renum(); } toast(r.message, r.ok ? 'ok' : 'err'); });
      }
    });
    list.addEventListener('change', function (e) {
      var inp = e.target.closest('[data-ep-label]'); if (!inp || !inp.dataset.epLabel) return;
      var fd = new FormData(); fd.append('_method', 'PUT'); fd.append('label', inp.value);
      send(inp.dataset.epLabel, fd).then(function (r) { toast(r.message || 'सेव हुआ', r.ok ? 'ok' : 'err'); });
    });
    var addPage = function (p) {
      var t = document.createElement('div'); t.innerHTML = tpl.innerHTML.trim(); var li = t.firstElementChild;
      li.dataset.id = p.id; $('.ep-thumb', li).href = p.thumb; $('img', li).src = p.thumb; $('img', li).alt = 'पेज ' + p.page_no;
      $('[data-ep-label]', li).dataset.epLabel = p.update_url; $('[data-ep-label]', li).value = p.label || '';
      $('[data-ep-hs]', li).href = p.hotspots_url; $('[data-ep-delete]', li).dataset.epDelete = p.delete_url;
      list.appendChild(li); renum();
    };

    if (ep) {
      var prog = $('[data-ep-progress]', ep), bar = $('[data-ep-bar]', ep), status = $('[data-ep-status]', ep), busy = false;
      var setProg = function (done, total, msg) { prog.hidden = false; bar.style.width = (total ? Math.round(done * 100 / total) : 0) + '%'; status.textContent = msg; };
      var leaving = function (e) { if (busy) { e.preventDefault(); e.returnValue = ''; } };
      window.addEventListener('beforeunload', leaving);
      var upload = function (file, label) {
        return window.AdminUpload(ep.dataset.pageUrl, file, label ? { label: label } : {}, null).then(function (r) { if (r.ok) addPage(r.page); return r; });
      };
      // सीधे इमेज
      var imgs = $('[data-ep-images]', ep);
      imgs.addEventListener('change', function () {
        var files = Array.prototype.slice.call(imgs.files).sort(function (a, b) { return a.name.localeCompare(b.name, undefined, { numeric: true }); }); imgs.value = '';
        if (!files.length || busy) return; busy = true;
        var i = 0, fail = 0;
        var next = function () {
          if (i >= files.length) { busy = false; setProg(1, 1, (files.length - fail) + ' पेज जुड़े' + (fail ? ', ' + fail + ' नहीं' : '') + '। अब दाईं ओर से प्रकाशित करें।'); return; }
          setProg(i, files.length, 'पेज ' + (i + 1) + ' / ' + files.length + ' अपलोड हो रहा है…');
          upload(files[i]).then(function (r) { if (!r.ok) { fail++; toast(files[i].name + ': ' + r.message, 'err'); } i++; next(); });
        };
        next();
      });
      // PDF → पेज
      var pdfIn = $('[data-ep-pdf]', ep), zone = $('[data-ep-pdf-zone]', ep);
      var loadPdfjs = function () {
        return new Promise(function (res, rej) {
          if (window.pdfjsLib) return res(window.pdfjsLib);
          var s = document.createElement('script'); s.src = ep.dataset.pdfjs;
          s.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = ep.dataset.pdfjsWorker; res(window.pdfjsLib); };
          s.onerror = function () { rej(new Error('pdf.js लोड नहीं हुआ')); };
          document.head.appendChild(s);
        });
      };
      var convert = function (file) {
        if (busy) return;
        if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') { toast('यह PDF फ़ाइल नहीं है।', 'err'); return; }
        if (file.size > (+ep.dataset.maxPdf || 50) * 1048576) { toast('PDF ' + ep.dataset.maxPdf + ' MB से छोटी हो।', 'err'); return; }
        busy = true; setProg(0, 1, 'PDF खुल रही है…');
        loadPdfjs().then(function (pdfjsLib) {
          return file.arrayBuffer().then(function (buf) { return pdfjsLib.getDocument({ data: buf }).promise; });
        }).then(function (pdf) {
          var total = pdf.numPages, n = 0, fail = 0;
          var step = function () {
            if (n >= total) return Promise.resolve();
            n++;
            setProg(n - 1, total, 'पेज ' + n + ' / ' + total + ': इमेज बन रही है…');
            return pdf.getPage(n).then(function (page) {
              var vp1 = page.getViewport({ scale: 1 }), scale = Math.min(3, Math.max(1, 1800 / vp1.width)), vp = page.getViewport({ scale: scale });
              var c = document.createElement('canvas'); c.width = Math.floor(vp.width); c.height = Math.floor(vp.height);
              var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
              return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
                return new Promise(function (res) { c.toBlob(res, 'image/jpeg', 0.85); });
              }).then(function (blob) {
                page.cleanup(); c.width = c.height = 0;
                setProg(n - 1, total, 'पेज ' + n + ' / ' + total + ': अपलोड हो रहा है…');
                return upload(new File([blob], 'page-' + n + '.jpg', { type: 'image/jpeg' }));
              }).then(function (r) { if (!r.ok) { fail++; toast('पेज ' + n + ': ' + r.message, 'err'); } });
            }).then(step);
          };
          return step().then(function () {
            if (!$('[data-ep-keep]', ep).checked) return { total: total, fail: fail };
            setProg(total, total, 'PDF सेव हो रही है…');
            return window.AdminUpload(ep.dataset.pdfUrl, file, {}, function (p) { status.textContent = 'PDF सेव हो रही है… ' + p + '%'; })
              .then(function (r) { if (!r.ok) toast(r.message, 'err'); return { total: total, fail: fail }; });
          });
        }).then(function (res) {
          busy = false; setProg(1, 1, (res.total - res.fail) + ' / ' + res.total + ' पेज बन गए। अब दाईं ओर से प्रकाशित करें।');
        }).catch(function (err) { busy = false; setProg(0, 1, 'PDF नहीं खुल सकी: ' + (err && err.message ? err.message : err) + '। पासवर्ड वाली या ख़राब PDF हो सकती है; पेज की इमेज अपलोड करें।'); });
      };
      pdfIn.addEventListener('change', function () { var f = pdfIn.files[0]; pdfIn.value = ''; if (f) convert(f); });
      ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('over'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('over'); }); });
      zone.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files[0]) convert(e.dataTransfer.files[0]); });
    }
  }

  /* ---------- हॉटस्पॉट: पेज पर खींचकर आयत, हर एक को ख़बर/लिंक से जोड़ें ---------- */
  var hs = $('[data-hs]');
  if (hs) {
    var stage = $('[data-hs-stage]', hs), hlist = $('[data-hs-list]', hs), hcount = $('[data-hs-count]', hs), hempty = $('[data-hs-empty]', hs), hstatus = $('[data-hs-status]', hs);
    var spots = []; try { spots = JSON.parse(hs.dataset.spots || '[]'); } catch (e) {}
    var active = -1, dirtyFlag = false;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var draw = function () {
      $$('.hs-box', stage).forEach(function (b) { b.remove(); });
      spots.forEach(function (s, i) {
        var b = document.createElement('div'); b.className = 'hs-box' + (i === active ? ' on' : ''); b.dataset.i = i;
        b.style.left = s.x + '%'; b.style.top = s.y + '%'; b.style.width = s.w + '%'; b.style.height = s.h + '%';
        b.innerHTML = '<span>' + (i + 1) + '</span>'; stage.appendChild(b);
      });
      hlist.innerHTML = spots.map(function (s, i) {
        return '<li class="' + (i === active ? 'on' : '') + '" data-i="' + i + '"><div class="d-flex justify-content-between align-items-center mb-1"><b>#' + (i + 1) + '</b><button type="button" class="btn btn-sm btn-link text-danger p-0" data-hs-del>हटाएँ</button></div>'
          + '<input class="form-control form-control-sm mb-1" data-f="label" maxlength="190" placeholder="नाम (जैसे: मुख्य ख़बर)" value="' + esc(s.label) + '">'
          + '<div class="hs-news"><input class="form-control form-control-sm" data-f="q" placeholder="ख़बर खोजें (शीर्षक/ID)" value="' + esc(s.news_id ? '#' + s.news_id + ' ' + (s.news_title || '') : '') + '"><ul class="list-group hs-results" hidden></ul></div>'
          + '<input class="form-control form-control-sm mt-1" data-f="url" maxlength="500" placeholder="या लिंक: https://… या /…" value="' + esc(s.url) + '"' + (s.news_id ? ' disabled' : '') + '></li>';
      }).join('');
      hcount.textContent = spots.length; hempty.hidden = spots.length > 0;
    };
    var pct = function (e) { var r = stage.getBoundingClientRect(); return { x: Math.max(0, Math.min(100, (e.clientX - r.left) / r.width * 100)), y: Math.max(0, Math.min(100, (e.clientY - r.top) / r.height * 100)) }; };
    var start = null, ghost = null;
    stage.addEventListener('pointerdown', function (e) {
      var box = e.target.closest('.hs-box'); if (box) { active = +box.dataset.i; draw(); return; }
      e.preventDefault(); stage.setPointerCapture(e.pointerId); start = pct(e);
      ghost = document.createElement('div'); ghost.className = 'hs-box on'; stage.appendChild(ghost);
    });
    stage.addEventListener('pointermove', function (e) {
      if (!start) return; var p = pct(e);
      ghost.style.left = Math.min(start.x, p.x) + '%'; ghost.style.top = Math.min(start.y, p.y) + '%';
      ghost.style.width = Math.abs(p.x - start.x) + '%'; ghost.style.height = Math.abs(p.y - start.y) + '%';
    });
    stage.addEventListener('pointerup', function (e) {
      if (!start) return; var p = pct(e), s = { x: Math.min(start.x, p.x), y: Math.min(start.y, p.y), w: Math.abs(p.x - start.x), h: Math.abs(p.y - start.y), news_id: null, news_title: '', url: '', label: '' };
      start = null; ghost.remove();
      if (s.w < 1.5 || s.h < 1.5) return;
      spots.push(s); active = spots.length - 1; dirtyFlag = true; draw();
      var q = $('li[data-i="' + active + '"] [data-f="q"]', hlist); if (q) q.focus();
    });
    var timer = null;
    hlist.addEventListener('input', function (e) {
      var li = e.target.closest('li'); if (!li) return; var i = +li.dataset.i, f = e.target.dataset.f; dirtyFlag = true;
      if (f === 'label' || f === 'url') { spots[i][f] = e.target.value; return; }
      if (f === 'q') {
        spots[i].news_id = null; spots[i].news_title = ''; $('[data-f="url"]', li).disabled = false;
        var res = $('.hs-results', li), q = e.target.value.trim(); clearTimeout(timer);
        if (q.length < 2) { res.hidden = true; return; }
        timer = setTimeout(function () {
          fetch(hs.dataset.search + (hs.dataset.search.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); }).then(function (r) {
              res.innerHTML = (r.items || []).slice(0, 8).map(function (n) { return '<li class="list-group-item list-group-item-action small" data-id="' + n.id + '" data-title="' + esc(n.title) + '">#' + n.id + ' ' + esc(n.title) + '</li>'; }).join('') || '<li class="list-group-item small">कुछ नहीं मिला</li>';
              res.hidden = false;
            });
        }, 250);
      }
    });
    hlist.addEventListener('mousedown', function (e) {
      var opt = e.target.closest('.hs-results li[data-id]'); if (!opt) return; e.preventDefault();
      var li = opt.closest('li[data-i]'), i = +li.dataset.i;
      spots[i].news_id = +opt.dataset.id; spots[i].news_title = opt.dataset.title; spots[i].url = ''; dirtyFlag = true; draw();
    });
    hlist.addEventListener('click', function (e) {
      var li = e.target.closest('li[data-i]'); if (!li) return;
      if (e.target.closest('[data-hs-del]')) { spots.splice(+li.dataset.i, 1); active = -1; dirtyFlag = true; draw(); return; }
      if (active !== +li.dataset.i) { active = +li.dataset.i; $$('.hs-box', stage).forEach(function (b) { b.classList.toggle('on', +b.dataset.i === active); }); $$('li[data-i]', hlist).forEach(function (x) { x.classList.toggle('on', x === li); }); }
    });
    $('[data-hs-save]', hs).addEventListener('click', function () {
      var fd = new FormData(); fd.append('spots', JSON.stringify(spots.map(function (s) { return { x: s.x, y: s.y, w: s.w, h: s.h, news_id: s.news_id, url: s.url, label: s.label }; })));
      hstatus.textContent = 'सेव हो रहा है…';
      send(hs.dataset.save, fd).then(function (r) { hstatus.textContent = r.message || ''; if (r.ok) dirtyFlag = false; toast(r.message, r.ok ? 'ok' : 'err'); });
    });
    window.addEventListener('beforeunload', function (e) { if (dirtyFlag) { e.preventDefault(); e.returnValue = ''; } });
    draw();
  }
})();

/* ==========================================================
   Phase 9: विज्ञापन (कई लोकेशन चुनना) और इनवॉइस की पंक्तियाँ
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  /* ---------- कई लोकेशन (विज्ञापन टार्गेटिंग) ---------- */
  $$('[data-loc-multi]').forEach(function (box) {
    var ul = $('.np-list', box), input = $('input[type="search"]', box), res = $('.loc-results', box), timer = null;
    var ids = function () { return $$('li[data-id]', ul).map(function (li) { return +li.dataset.id; }); };
    var dirty = function () { box.dispatchEvent(new Event('input', { bubbles: true })); };
    ul.addEventListener('click', function (e) { var b = e.target.closest('.ml-remove'); if (b) { b.closest('li').remove(); dirty(); } });
    input.addEventListener('input', function () {
      clearTimeout(timer); var q = input.value.trim(); if (q.length < 2) { res.hidden = true; return; }
      timer = setTimeout(function () {
        fetch(box.dataset.search + (box.dataset.search.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); }).then(function (r) {
            var have = ids();
            res.innerHTML = (r.items || []).filter(function (x) { return have.indexOf(+x.id) === -1; }).slice(0, 10).map(function (x) {
              return '<li class="list-group-item list-group-item-action small" data-id="' + x.id + '" data-name="' + esc(x.name) + '">' + esc(x.label || x.name) + ' <span class="text-body-secondary">' + esc(x.type_label || '') + '</span></li>';
            }).join('') || '<li class="list-group-item small">कुछ नहीं मिला</li>';
            res.hidden = false;
          });
      }, 250);
    });
    res.addEventListener('mousedown', function (e) {
      var li = e.target.closest('li[data-id]'); if (!li) return; e.preventDefault();
      var n = document.createElement('li'); n.dataset.id = li.dataset.id;
      n.innerHTML = '<span>' + esc(li.dataset.name) + '</span><input type="hidden" name="' + esc(box.dataset.name) + '" value="' + (+li.dataset.id) + '"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button>';
      ul.appendChild(n); input.value = ''; res.hidden = true; dirty();
    });
    input.addEventListener('blur', function () { setTimeout(function () { res.hidden = true; }, 200); });
  });

  /* ---------- इनवॉइस: पंक्तियाँ, जोड़ (अंतिम जोड़ सर्वर पर) ---------- */
  var inv = $('[data-invoice]');
  if (inv) {
    var body = $('[data-lines]', inv), tpl = $('[data-line-template]', inv), rateIn = $('[data-tax-rate]', inv), n = 0;
    var money = function (x) { return '₹' + (Math.round(x * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var calc = function () {
      var sub = 0;
      $$('[data-line]', body).forEach(function (tr) { var a = (parseFloat($('[data-qty]', tr).value) || 0) * (parseFloat($('[data-rate]', tr).value) || 0); a = Math.round(a * 100) / 100; sub += a; $('[data-amount]', tr).textContent = money(a); });
      var r = parseFloat(rateIn.value) || 0, tax = Math.round(sub * r) / 100;
      $('[data-sub]', inv).textContent = money(sub); $('[data-rate-show]', inv).textContent = r; $('[data-tax]', inv).textContent = money(tax); $('[data-total]', inv).textContent = money(sub + tax);
    };
    inv.addEventListener('input', function (e) { if (e.target.matches('[data-qty], [data-rate], [data-tax-rate]')) calc(); });
    $('[data-line-add]', inv).addEventListener('click', function () {
      var t = document.createElement('tbody'); t.innerHTML = tpl.innerHTML.replace(/__i__/g, 'n' + (++n) + '_' + Date.now());
      var tr = t.firstElementChild; body.appendChild(tr); $('input', tr).focus(); calc();
    });
    body.addEventListener('click', function (e) { var b = e.target.closest('[data-line-remove]'); if (!b) return; if ($$('[data-line]', body).length > 1) b.closest('tr').remove(); else $$('input', b.closest('tr')).forEach(function (i) { i.value = i.matches('[data-qty]') ? 1 : ''; }); calc(); });
    var adv = $('[data-inv-adv]', inv), camp = $('[data-inv-camp]', inv);
    var filter = function () { $$('option[data-adv]', camp).forEach(function (o) { o.hidden = adv.value && o.dataset.adv !== adv.value; if (o.hidden && o.selected) camp.value = ''; }); };
    if (adv && camp) { adv.addEventListener('change', filter); filter(); }
    calc();
  }
})();
