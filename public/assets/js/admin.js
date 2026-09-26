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
    f.addEventListener('change', function (e) {
      if (e.target === all) boxes().forEach(function (b) { b.checked = all.checked; });
      if (e.target === action) {
        if (action.value === 'delete' || action.value === 'trash') f.dataset.confirm = action.value === 'delete' ? 'चुने गए पेज हमेशा के लिए हट जाएँगे।' : 'चुने गए पेज ट्रैश में जाएँगे।';
        else delete f.dataset.confirm;
      }
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
    '|', ['link', 'fa-link', 'लिंक'], ['unlink', 'fa-link-slash', 'लिंक हटाएँ'], ['image', 'fa-image', 'इमेज अपलोड'], ['youtube', 'fa-youtube', 'YouTube वीडियो', 'fa-brands'],
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
