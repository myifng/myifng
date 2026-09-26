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
          if (!r.ok) { hint.textContent = r.message || 'सेव नहीं हुआ'; return r; }
          nameEl.textContent = r.name || 'चुनें'; clearBtn.hidden = !r.name; open(false);
          document.dispatchEvent(new CustomEvent('mycity', { detail: r }));
          return r;
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
    // कहीं और के "शहर चुनें" बटन
    $$('[data-open-mycity]').forEach(function (b) { b.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); open(true); }); });
    // लोकेशन पेज: "इसे मेरा शहर बनाएँ"
    $$('[data-set-city]').forEach(function (b) {
      b.addEventListener('click', function () {
        save(b.dataset.setCity).then(function (r) { if (!r || !r.ok) { toast((r && r.message) || 'सेव नहीं हुआ'); return; } b.disabled = true; b.innerHTML = '<i class="fa-solid fa-location-dot"></i> यह आपका शहर है'; toast(b.dataset.cityName + ' आपका शहर चुना गया।'); });
      });
    });
  }

  function toast(msg) {
    var t = document.createElement('div'); t.className = 'toast-site'; t.setAttribute('role', 'status'); t.textContent = msg;
    document.body.appendChild(t); setTimeout(function () { t.remove(); }, 2600);
  }

  /* खोज पट्टी */
  var sb = $('#siteSearch');
  $$('[data-open-search]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      if (!sb) return;
      e.preventDefault();
      sb.hidden = !sb.hidden;
      $$('[data-open-search]').forEach(function (x) { if (x.hasAttribute('aria-expanded')) x.setAttribute('aria-expanded', String(!sb.hidden)); });
      if (!sb.hidden) { window.scrollTo({ top: 0, behavior: 'smooth' }); $('input', sb).focus(); }
    });
  });

  /* टैब (लोकेशन सेक्शन), कीबोर्ड के साथ */
  $$('[data-tabs]').forEach(function (list) {
    var tabs = $$('[role="tab"]', list);
    function sel(t) {
      tabs.forEach(function (x) { var on = x === t; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; var p = document.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = !on; });
    }
    list.addEventListener('click', function (e) { var t = e.target.closest('[role="tab"]'); if (t) sel(t); });
    list.addEventListener('keydown', function (e) {
      var i = tabs.indexOf(document.activeElement); if (i < 0) return;
      var j = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : -1;
      if (j < 0 || j >= tabs.length) return;
      e.preventDefault(); tabs[j].focus(); sel(tabs[j]);
    });
  });

  /* स्लाइडर (हीरो) */
  $$('[data-slider]').forEach(function (sl) {
    var track = $('.slides', sl), n = track.children.length, i = 0, timer = null;
    var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    function go(k) { i = (k + n) % n; track.style.transform = 'translateX(' + (-100 * i) + '%)'; $$('.slide', sl).forEach(function (s, x) { s.setAttribute('aria-hidden', x !== i); $$('a', s).forEach(function (a) { a.tabIndex = x === i ? 0 : -1; }); }); }
    function play() { if (sl.dataset.autoplay === '1' && !reduce && n > 1) { stop(); timer = setInterval(function () { go(i + 1); }, 6000); } }
    function stop() { clearInterval(timer); }
    $('.prev', sl).addEventListener('click', function () { go(i - 1); play(); });
    $('.next', sl).addEventListener('click', function () { go(i + 1); play(); });
    sl.addEventListener('mouseenter', stop); sl.addEventListener('mouseleave', play); sl.addEventListener('focusin', stop);
    var x0 = null;
    sl.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
    sl.addEventListener('touchend', function (e) { if (x0 === null) return; var d = e.changedTouches[0].clientX - x0; if (Math.abs(d) > 40) go(i + (d < 0 ? 1 : -1)); x0 = null; play(); });
    go(0); play();
  });

  /* आड़ी पट्टी (स्लाइडर ब्लॉक) */
  $$('[data-hscroll]').forEach(function (h) {
    var t = $('.hs-track', h);
    $('.prev', h).addEventListener('click', function () { t.scrollBy({ left: -t.clientWidth * 0.8, behavior: 'smooth' }); });
    $('.next', h).addEventListener('click', function () { t.scrollBy({ left: t.clientWidth * 0.8, behavior: 'smooth' }); });
  });

  /* शेयर: लिंक कॉपी, मोबाइल शेयर */
  $$('[data-copy-link]').forEach(function (b) {
    b.addEventListener('click', function () {
      var u = b.dataset.copyLink;
      (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { toast('लिंक कॉपी हो गया'); }, function () { prompt('लिंक कॉपी करें:', u); });
    });
  });
  $$('[data-native-share]').forEach(function (b) {
    if (!navigator.share) return;
    b.hidden = false;
    b.addEventListener('click', function () { navigator.share({ title: b.dataset.title, url: b.dataset.url }).catch(function () {}); });
  });

  /* रिपोर्टर फ़ॉर्म: चरण (बिना JS पूरा फ़ॉर्म एक साथ), राज्य → ज़िले */
  var sf = $('[data-steps]');
  if (sf) {
    var steps = $$('[data-step]', sf), nav = $('[data-steps-nav]'), prev = $('[data-step-prev]', sf), next = $('[data-step-next]', sf), submit = $('[data-step-submit]', sf), cur = 0;
    // सर्वर से त्रुटि आई हो तो पहली त्रुटि वाले चरण पर
    var errStep = steps.findIndex(function (s) { return $('.has-err, .notice', s); });
    if (errStep > -1) cur = errStep;
    var show = function (i) {
      cur = i;
      steps.forEach(function (s, k) { s.hidden = k !== i; });
      if (nav) $$('li', nav).forEach(function (li, k) { li.classList.toggle('on', k === i); li.classList.toggle('done', k < i); });
      prev.hidden = i === 0; next.hidden = i === steps.length - 1; submit.hidden = i !== steps.length - 1;
    };
    var valid = function (i) {
      var bad = $$('input, select, textarea', steps[i]).filter(function (el) { return !el.checkValidity(); });
      if (bad.length) { bad[0].reportValidity(); bad[0].focus(); return false; }
      return true;
    };
    if (nav) nav.hidden = false;
    next.addEventListener('click', function () { if (valid(cur)) { show(cur + 1); sf.scrollIntoView({ behavior: 'smooth' }); } });
    prev.addEventListener('click', function () { show(cur - 1); sf.scrollIntoView({ behavior: 'smooth' }); });
    sf.addEventListener('submit', function (e) { for (var k = 0; k < steps.length; k++) { if (!valid(k)) { e.preventDefault(); show(k); valid(k); return; } } });
    show(cur);
    var st = $('[data-state-select]', sf), ds = $('[data-district-select]', sf);
    if (st && ds) {
      var all = $$('option', ds).filter(function (o) { return o.value; });
      var filter = function () {
        all.forEach(function (o) { o.hidden = o.disabled = !!st.value && o.dataset.parent !== st.value; });
        if (ds.selectedOptions[0] && ds.selectedOptions[0].disabled) ds.value = '';
      };
      st.addEventListener('change', filter); filter();
    }
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
