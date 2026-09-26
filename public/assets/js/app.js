/* ==========================================================
   वेबसाइट की JavaScript (एक फ़ाइल, सभी पेज)
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };

  /* डार्क / लाइट मोड: कुकी में याद (सर्वर अगली बार वही थीम भेजे) */
  $$('.js-theme').forEach(function (b) {
    b.addEventListener('click', function () {
      var r = document.documentElement;
      var dark = r.dataset.theme ? r.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
      r.dataset.theme = dark ? 'light' : 'dark';
      document.cookie = 'theme=' + r.dataset.theme + ';path=/;max-age=31536000;samesite=lax';
    });
  });

  /* मोबाइल मेनू (ड्रॉअर) */
  var drawer = $('#drawer'), opener = $('.js-drawer');
  function setDrawer(open) {
    if (!drawer) return;
    drawer.hidden = !open;
    if (opener) opener.setAttribute('aria-expanded', open);
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) { var f = $('a, button', drawer); if (f) f.focus(); } else if (opener) opener.focus();
  }
  if (opener) opener.addEventListener('click', function () { setDrawer(true); });
  $$('.js-drawer-close').forEach(function (b) { b.addEventListener('click', function () { setDrawer(false); }); });
  if (drawer) drawer.addEventListener('click', function (e) { if (e.target === drawer) setDrawer(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer && !drawer.hidden) setDrawer(false); });
  $$('.drawer .sub-toggle').forEach(function (b) {
    b.addEventListener('click', function () { var li = b.parentNode, open = li.classList.toggle('open'); b.setAttribute('aria-expanded', open); });
  });

  /* मेरा शहर: खोजें, चुनें (कुकी सर्वर लगाता है) */
  var mc = $('[data-mycity]');
  if (mc) {
    var btn = $('[data-mycity-toggle]', mc), panel = $('.mycity-panel', mc), input = $('[data-mycity-input]', mc), list = $('[data-mycity-list]', mc);
    var hint = $('[data-mycity-hint]', mc), clearBtn = $('[data-mycity-clear]', mc), nameEl = $('[data-mycity-name]', mc), timer = null;
    var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
    var open = function (on) {
      panel.hidden = !on; btn.setAttribute('aria-expanded', on);
      if (on) { input.value = ''; search(''); input.focus(); }
    };
    var search = function (q) {
      hint.textContent = q ? 'खोज रहे हैं…' : 'लोकप्रिय शहर';
      fetch(mc.dataset.search + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          list.innerHTML = '';
          r.items.forEach(function (it) {
            var li = document.createElement('li'), b = document.createElement('button');
            b.type = 'button'; b.dataset.id = it.id; b.setAttribute('role', 'option');
            b.innerHTML = '<span></span><small></small>';
            b.firstChild.textContent = it.label; b.lastChild.textContent = it.type;
            li.appendChild(b); list.appendChild(li);
          });
          hint.textContent = r.items.length ? (q ? 'नतीजे' : 'लोकप्रिय शहर') : 'कुछ नहीं मिला। दूसरे नाम से खोजें।';
        })
        .catch(function () { hint.textContent = 'अभी खोज नहीं हो पा रही।'; });
    };
    var save = function (id) {
      var fd = new FormData(); fd.append('location_id', id);
      return fetch(mc.dataset.save, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r.ok) { hint.textContent = r.message || 'सेव नहीं हुआ'; return; }
          nameEl.textContent = r.name || 'चुनें'; clearBtn.hidden = !r.name; open(false);
          document.dispatchEvent(new CustomEvent('mycity', { detail: r }));
        })
        .catch(function () { hint.textContent = 'सेव नहीं हुआ। दोबारा कोशिश करें।'; });
    };
    btn.addEventListener('click', function () { open(panel.hidden); });
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { search(input.value.trim()); }, 250); });
    input.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown') { var f = $('button', list); if (f) { f.focus(); e.preventDefault(); } } });
    list.addEventListener('click', function (e) { var b = e.target.closest('button[data-id]'); if (b) save(b.dataset.id); });
    list.addEventListener('keydown', function (e) {
      var li = e.target.closest('li'); if (!li) return;
      if (e.key === 'ArrowDown' && li.nextElementSibling) { $('button', li.nextElementSibling).focus(); e.preventDefault(); }
      if (e.key === 'ArrowUp') { (li.previousElementSibling ? $('button', li.previousElementSibling) : input).focus(); e.preventDefault(); }
    });
    clearBtn.addEventListener('click', function () { save(0); });
    document.addEventListener('click', function (e) { if (!panel.hidden && !mc.contains(e.target)) open(false); });
    mc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { open(false); btn.focus(); } });
  }

  /* Google Analytics (सेटिंग में ID हो तो) */
  var ga = document.body.dataset.ga;
  if (ga) {
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', ga);
  }
})();
