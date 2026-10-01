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
  /* शेयर की गिनती (ट्रेंडिंग / सबसे ज़्यादा शेयर): बीकन, पेज नहीं रुकता; GA हो तो इवेंट भी */
  $$('[data-share-id]').forEach(function (box) {
    var nets = { 's-wa': 'whatsapp', 's-fb': 'facebook', 's-x': 'x', 's-tg': 'telegram', 's-in': 'linkedin', 's-cp': 'copy', 's-native': 'native' };
    box.addEventListener('click', function (ev) {
      var b = ev.target.closest('a, button'), net = null;
      if (!b) return;
      Object.keys(nets).forEach(function (c) { if (b.classList.contains(c)) net = nets[c]; });
      if (!net) return;
      var fd = new FormData();
      fd.append('_csrf', ($('meta[name="csrf-token"]') || {}).content || ''); fd.append('news_id', box.dataset.shareId); fd.append('network', net);
      if (!(navigator.sendBeacon && navigator.sendBeacon(box.dataset.shareUrl, fd))) fetch(box.dataset.shareUrl, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }).catch(function () {});
      if (typeof window.gtag === 'function') window.gtag('event', 'share', { method: net, item_id: box.dataset.shareId, content_type: 'article' });
    });
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

  /* ब्रेकिंग: होम बैनर / मोबाइल अलर्ट; पाठक ने बंद किया तो उस आइटम के लिए याद */
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
  $$('[data-dismiss-key]').forEach(function (box) {
    var key = 'dismiss-' + box.dataset.dismissKey;
    if (store.get(key)) { box.remove(); return; }
    if (box.classList.contains('brk-mobile')) setTimeout(function () { box.hidden = false; }, 1200);
    $('[data-dismiss]', box).addEventListener('click', function () { store.set(key, '1'); box.remove(); });
  });

  /* लाइव ब्लॉग: हर N सेकंड में नए अपडेट; पढ़ते हुए पेज न खिसके, इसलिए "N नए अपडेट" बटन */
  var lb = $('[data-live-poll]');
  if (lb) {
    var listEl = $('[data-live-list]', lb), newBtn = $('[data-live-new]', lb), countEl = $('[data-live-count]', lb);
    var last = +lb.dataset.last || 0, queue = [], every = Math.max(15, +lb.dataset.interval || 30) * 1000, timer = null;
    var flush = function () {
      var empty = $('.lb-empty', listEl); if (empty) empty.remove();
      queue.forEach(function (it) { var t = document.createElement('div'); t.innerHTML = it.html; var n = t.firstChild; n.classList.add('new'); listEl.insertBefore(n, listEl.firstChild); });
      if (countEl) countEl.textContent = (+countEl.textContent.replace(/\D/g, '') || 0) + queue.length;
      queue = []; newBtn.hidden = true;
    };
    newBtn.addEventListener('click', function () { flush(); listEl.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    var poll = function () {
      if (document.hidden) return;
      fetch(lb.dataset.livePoll + '?after=' + last, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (r) {
          if (!r || !r.ok) return;
          r.items.forEach(function (it) { if (it.id > last) { last = it.id; queue.push(it); } });
          if (queue.length) {
            var top = listEl.getBoundingClientRect().top;
            if (top > 0 && top < innerHeight) flush(); // टाइमलाइन स्क्रीन पर ऊपर है: सीधे जोड़ दें
            else { newBtn.textContent = queue.length + ' नए अपडेट · देखें'; newBtn.hidden = false; }
          }
          if (r.status === 'ended') clearInterval(timer);
        }).catch(function () {});
    };
    timer = setInterval(poll, every);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  }

  /* लाइव टीवी / स्ट्रीम: HLS (.m3u8) — Safari/Android में सीधे, बाकी में लोकल hls.js (सिर्फ़ ज़रूरत पर लोड) */
  $$('video[data-hls]').forEach(function (v) {
    var src = v.dataset.hls;
    if (v.canPlayType('application/vnd.apple.mpegurl')) { v.src = src; return; }
    var start = function () { var h = new window.Hls({ lowLatencyMode: true }); h.loadSource(src); h.attachMedia(v); };
    if (window.Hls) { start(); return; }
    var sc = document.createElement('script'); sc.src = v.dataset.hlsLib; sc.onload = function () { if (window.Hls && window.Hls.isSupported()) start(); }; document.head.appendChild(sc);
  });

  /* फ़ोटो गैलरी: फ़ुलस्क्रीन लाइटबॉक्स (कीबोर्ड ←/→/Esc, मोबाइल पर स्वाइप) */
  var lbRoot = $('[data-lb]'), lbItems = $$('[data-lb-item]');
  if (lbRoot && lbItems.length) {
    var lbImg = $('[data-lb-img]', lbRoot), lbIdx = 0, lbOpener = null, sx = null;
    var lbShow = function (i) {
      lbIdx = (i + lbItems.length) % lbItems.length; var a = lbItems[lbIdx];
      lbImg.src = a.href; lbImg.alt = a.dataset.caption || '';
      $('[data-lb-caption]', lbRoot).textContent = a.dataset.caption || '';
      $('[data-lb-credit]', lbRoot).textContent = a.dataset.credit || '';
      $('[data-lb-count]', lbRoot).textContent = (lbIdx + 1) + ' / ' + lbItems.length;
      if (history.replaceState) history.replaceState(null, '', '#photo-' + (lbIdx + 1));
    };
    var lbOpen = function (i) { lbOpener = document.activeElement; lbRoot.hidden = false; document.body.style.overflow = 'hidden'; lbShow(i); $('[data-lb-close]', lbRoot).focus(); };
    var lbClose = function () { lbRoot.hidden = true; document.body.style.overflow = ''; if (lbOpener) lbOpener.focus(); };
    lbItems.forEach(function (a, i) { a.addEventListener('click', function (e) { e.preventDefault(); lbOpen(i); }); });
    $('[data-lb-close]', lbRoot).addEventListener('click', lbClose);
    $('[data-lb-prev]', lbRoot).addEventListener('click', function () { lbShow(lbIdx - 1); });
    $('[data-lb-next]', lbRoot).addEventListener('click', function () { lbShow(lbIdx + 1); });
    lbRoot.addEventListener('click', function (e) { if (e.target === lbRoot) lbClose(); });
    document.addEventListener('keydown', function (e) {
      if (lbRoot.hidden) return;
      if (e.key === 'Escape') lbClose(); else if (e.key === 'ArrowLeft') lbShow(lbIdx - 1); else if (e.key === 'ArrowRight') lbShow(lbIdx + 1);
      else if (e.key === 'Tab') { var f = $$('button', lbRoot), first = f[0], last = f[f.length - 1]; if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } }
    });
    lbRoot.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    lbRoot.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 50) lbShow(lbIdx + (dx < 0 ? 1 : -1)); sx = null; });
    var m = /^#photo-(\d+)$/.exec(location.hash); if (m && lbItems[+m[1] - 1]) lbOpen(+m[1] - 1);
  }

  /* वेब स्टोरी प्लेयर: अपने आप आगे, टैप (बाएँ = पीछे), स्वाइप, कीबोर्ड, दबाकर रखें = रुकें */
  var st = $('[data-story]');
  if (st) {
    st.classList.add('js');
    var slides = $$('.wst-slide', st), bars = $$('.wst-bars span', st), cur = -1, timer = null, startAt = 0, left = 0, paused = false, muted = true;
    var pauseBtn = $('[data-story-pause]', st), muteBtn = $('[data-story-mute]', st);
    var video = function (i) { return slides[i] ? $('video', slides[i]) : null; };
    var tick = function () {
      var bar = bars[cur] && $('i', bars[cur]); if (!bar) return;
      var dur = (+slides[cur].dataset.duration || 7) * 1000, el = dur - left + (Date.now() - startAt);
      bar.style.width = Math.min(100, el / dur * 100) + '%';
      if (el >= dur) go(cur + 1); else timer = requestAnimationFrame(tick);
    };
    var run = function () { cancelAnimationFrame(timer); startAt = Date.now(); if (!paused && cur < bars.length) timer = requestAnimationFrame(tick); };
    var go = function (i) {
      if (i < 0) i = 0;
      if (i >= slides.length) return;
      cancelAnimationFrame(timer);
      var v = video(cur); if (v) v.pause();
      cur = i;
      slides.forEach(function (s, k) { s.classList.toggle('on', k === i); s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
      bars.forEach(function (b, k) { b.classList.toggle('done', k < i); $('i', b).style.width = k < i ? '100%' : '0'; });
      v = video(i); if (muteBtn) muteBtn.hidden = !v;
      if (v) { v.muted = muted; v.currentTime = 0; v.play().catch(function () {}); }
      left = (+slides[i].dataset.duration || 7) * 1000;
      if (i < bars.length) run(); else cancelAnimationFrame(timer); // आख़िरी "और स्टोरी" स्लाइड पर रुकें
    };
    var setPaused = function (p) {
      if (cur >= bars.length) return;
      if (p && !paused) { left -= Date.now() - startAt; cancelAnimationFrame(timer); } else if (!p && paused) { paused = false; run(); }
      paused = p; pauseBtn.innerHTML = '<i class="fa-solid fa-' + (p ? 'play' : 'pause') + '"></i>';
      var v = video(cur); if (v) { if (p) v.pause(); else v.play().catch(function () {}); }
    };
    var suppress = false; // दबाकर छोड़ने पर अगली स्लाइड न खुले
    $('[data-story-next]', st).addEventListener('click', function () { if (suppress) { suppress = false; return; } go(cur + 1); });
    $('[data-story-prev]', st).addEventListener('click', function () { if (suppress) { suppress = false; return; } go(cur - 1); });
    pauseBtn.addEventListener('click', function () { setPaused(!paused); });
    if (muteBtn) muteBtn.addEventListener('click', function () { muted = !muted; var v = video(cur); if (v) v.muted = muted; muteBtn.innerHTML = '<i class="fa-solid fa-volume-' + (muted ? 'xmark' : 'high') + '"></i>'; });
    var rp = $('[data-story-replay]', st); if (rp) rp.addEventListener('click', function () { go(0); });
    var sh = $('[data-story-share]', st);
    if (sh) sh.addEventListener('click', function () {
      if (navigator.share) navigator.share({ title: sh.dataset.title, url: sh.dataset.url }).catch(function () {});
      else if (navigator.clipboard) navigator.clipboard.writeText(sh.dataset.url).then(function () { toast('लिंक कॉपी हो गया'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') go(cur + 1); else if (e.key === 'ArrowLeft') go(cur - 1);
      else if (e.key === ' ') { e.preventDefault(); setPaused(!paused); } else if (e.key === 'Escape') location.href = st.dataset.exit;
    });
    var hold = null, tx = null;
    st.addEventListener('pointerdown', function (e) { if (!e.target.closest('.wst-tap')) return; tx = e.clientX; hold = setTimeout(function () { hold = 'held'; setPaused(true); }, 350); });
    st.addEventListener('pointerup', function (e) {
      if (tx === null) return;
      var dx = e.clientX - tx;
      if (hold === 'held') { setPaused(false); suppress = true; }
      else { clearTimeout(hold); if (Math.abs(dx) > 60) { suppress = true; go(cur + (dx < 0 ? 1 : -1)); } } // स्वाइप
      hold = null; tx = null;
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) setPaused(true); });
    go(0);
  }

  /* ई-पेपर रीडर: एक पेज, थंबनेल, ज़ूम (बटन/डबल-टैप/पिंच/Ctrl+स्क्रॉल), खींचकर देखें, स्वाइप, कीबोर्ड, फ़ुलस्क्रीन */
  var epr = $('[data-epr]');
  if (epr) {
    epr.classList.add('js');
    var stage = $('[data-epr-stage]', epr), pgs = $$('[data-epr-page]', epr), thumbs = $$('[data-epr-thumb]', epr), sel = $('[data-epr-select]', epr), zl = $('[data-epr-zl]', epr);
    var cur = +epr.dataset.page || 1, z = 1, total = pgs.length;
    var pageEl = function (n) { return pgs[n - 1]; };
    var load = function (n) { var p = pageEl(n); if (!p) return; var img = $('img[data-src]', p); if (img) { img.src = img.dataset.src; img.removeAttribute('data-src'); } };
    var ratio = function (p) { var sh = $('.epr-sheet', p); if (!sh) return 0.75; var a = (sh.style.aspectRatio || '3 / 4').split('/'); return (+a[0] || 3) / (+a[1] || 4); };
    var fit = function () {
      var p = pageEl(cur); if (!p) return;
      var mobile = innerWidth <= 760, sw = stage.clientWidth - (mobile ? 12 : 28), sh = stage.clientHeight - 28;
      var base = mobile || !sh ? sw : Math.min(sw, sh * ratio(p));
      p.style.width = Math.max(200, Math.round(base * z)) + 'px';
      epr.classList.toggle('zoomed', z > 1.01); zl.textContent = Math.round(z * 100) + '%';
      stage.style.touchAction = z > 1.01 ? 'pan-x pan-y' : 'pan-y'; // ज़ूम नहीं: आड़ा स्वाइप JS को (पेज बदले)
    };
    var zoomTo = function (nz, cx, cy) {
      nz = Math.max(1, Math.min(4, nz)); if (Math.abs(nz - z) < 0.01) return;
      var r = stage.getBoundingClientRect(), ox = (cx == null ? r.width / 2 : cx - r.left), oy = (cy == null ? r.height / 2 : cy - r.top);
      var fx = (stage.scrollLeft + ox) / stage.scrollWidth, fy = (stage.scrollTop + oy) / stage.scrollHeight;
      z = nz; fit();
      stage.scrollLeft = fx * stage.scrollWidth - ox; stage.scrollTop = fy * stage.scrollHeight - oy;
    };
    var go = function (n, push) {
      n = Math.max(1, Math.min(total, n)); if (!pageEl(n)) return;
      pgs.forEach(function (p) { p.classList.remove('on'); p.style.width = ''; });
      cur = n; pageEl(n).classList.add('on'); [n, n + 1, n - 1].forEach(load);
      z = 1; fit(); stage.scrollTop = 0; stage.scrollLeft = 0;
      thumbs.forEach(function (t) { var on = +t.dataset.eprThumb === n; if (on) { t.setAttribute('aria-current', 'page'); t.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } else t.removeAttribute('aria-current'); });
      if (sel) sel.value = n;
      if (push !== false && history.replaceState) history.replaceState(null, '', epr.dataset.base + (n > 1 ? '?page=' + n : ''));
    };
    thumbs.forEach(function (t) { t.addEventListener('click', function (e) { e.preventDefault(); go(+t.dataset.eprThumb); stage.focus({ preventScroll: true }); }); });
    $$('[data-epr-prev]', epr).forEach(function (b) { b.addEventListener('click', function () { go(cur - 1); }); });
    $$('[data-epr-next]', epr).forEach(function (b) { b.addEventListener('click', function () { go(cur + 1); }); });
    if (sel) sel.addEventListener('change', function () { go(+sel.value); });
    $$('[data-epr-zoom]', epr).forEach(function (b) { b.addEventListener('click', function () { var d = +b.dataset.eprZoom; if (!d) { z = 1; fit(); } else zoomTo(z + d * 0.5); }); });
    var full = $('[data-epr-full]', epr);
    if (full) {
      if (!document.fullscreenEnabled) full.hidden = true;
      full.addEventListener('click', function () { if (document.fullscreenElement) document.exitFullscreen(); else epr.requestFullscreen().catch(function () {}); });
      document.addEventListener('fullscreenchange', function () { full.innerHTML = '<i class="fa-solid fa-' + (document.fullscreenElement ? 'compress' : 'expand') + '"></i>'; setTimeout(fit, 50); });
    }
    var pick = $('[data-epr-pick]', epr);
    if (pick) $$('select, input', pick).forEach(function (i) { i.addEventListener('change', function () { pick.submit(); }); });
    document.addEventListener('keydown', function (e) {
      if (/INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) return;
      if (e.key === 'ArrowRight') go(cur + 1); else if (e.key === 'ArrowLeft') go(cur - 1);
      else if (e.key === '+' || e.key === '=') zoomTo(z + 0.5); else if (e.key === '-') zoomTo(z - 0.5); else if (e.key === '0') { z = 1; fit(); }
      else if (e.key === 'f' && full && !full.hidden) full.click();
    });
    stage.addEventListener('dblclick', function (e) { if (e.target.closest('.epr-hs')) return; if (z > 1) { z = 1; fit(); } else zoomTo(2.5, e.clientX, e.clientY); });
    stage.addEventListener('wheel', function (e) { if (!e.ctrlKey) return; e.preventDefault(); zoomTo(z * (e.deltaY < 0 ? 1.15 : 0.87), e.clientX, e.clientY); }, { passive: false });
    // पॉइंटर: एक उँगली/माउस = स्वाइप (ज़ूम नहीं) या खींचना (ज़ूम में); दो उँगली = पिंच
    var pts = {}, start = null, pinch = null, lastTap = 0;
    stage.addEventListener('pointerdown', function (e) {
      pts[e.pointerId] = { x: e.clientX, y: e.clientY };
      var ids = Object.keys(pts);
      if (ids.length === 2) { var a = pts[ids[0]], b = pts[ids[1]]; pinch = { d: Math.hypot(a.x - b.x, a.y - b.y), z: z }; start = null; return; }
      start = { x: e.clientX, y: e.clientY, sl: stage.scrollLeft, st: stage.scrollTop, t: Date.now(), touch: e.pointerType === 'touch' };
      if (z > 1 && e.pointerType === 'mouse') stage.classList.add('drag');
    });
    stage.addEventListener('pointermove', function (e) {
      if (!pts[e.pointerId]) return; pts[e.pointerId] = { x: e.clientX, y: e.clientY };
      var ids = Object.keys(pts);
      if (pinch && ids.length === 2) { var a = pts[ids[0]], b = pts[ids[1]]; zoomTo(pinch.z * Math.hypot(a.x - b.x, a.y - b.y) / pinch.d, (a.x + b.x) / 2, (a.y + b.y) / 2); return; }
      if (start && z > 1 && !start.touch) { stage.scrollLeft = start.sl - (e.clientX - start.x); stage.scrollTop = start.st - (e.clientY - start.y); }
    });
    var end = function (e) {
      if (start && z <= 1.01 && !pinch) {
        var dx = e.clientX - start.x, dy = e.clientY - start.y;
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5 && Date.now() - start.t < 800) go(cur + (dx < 0 ? 1 : -1));
        else if (start.touch && Math.abs(dx) < 10 && Math.abs(dy) < 10 && !e.target.closest('.epr-hs')) { var now = Date.now(); if (now - lastTap < 300) zoomTo(2.5, e.clientX, e.clientY); lastTap = now; }
      }
      delete pts[e.pointerId]; if (Object.keys(pts).length < 2) pinch = null; start = null; stage.classList.remove('drag');
    };
    stage.addEventListener('pointerup', end); stage.addEventListener('pointercancel', end);
    stage.addEventListener('touchmove', function (e) { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });
    addEventListener('resize', fit);
    go(cur, false);
  }

  /* विज्ञापन: स्क्रीन पर आधा दिखे तब इम्प्रेशन (एक पेज पर एक बार); पॉपअप (तय घंटों में एक बार); मोबाइल स्टिकी बंद करना */
  (function () {
    var ads = $$('[data-ad-id]'); if (!ads.length) return;
    var csrf = ($('meta[name="csrf-token"]') || {}).content || '', impUrl = (document.querySelector('meta[name="ad-imp"]') || {}).content, queue = [], sent = {}, t = null;
    var flushImp = function () {
      if (!queue.length || !impUrl) return;
      var fd = new FormData(); queue.splice(0).forEach(function (id) { fd.append('ids[]', id); }); fd.append('_csrf', csrf);
      fetch(impUrl, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true, headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } }).catch(function () {});
    };
    var seen = function (el) { var id = el.dataset.adId; if (sent[id]) return; sent[id] = 1; queue.push(id); clearTimeout(t); t = setTimeout(flushImp, 800); };
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting && e.target.offsetParent !== null) { seen(e.target); io.unobserve(e.target); } }); }, { threshold: 0.5 });
      ads.forEach(function (a) { io.observe(a); });
    }
    addEventListener('pagehide', flushImp);
    var popup = $('[data-ad-popup]');
    if (popup) {
      var key = 'adpop', last = +(store.get(key) || 0), hours = +popup.dataset.hours || 24;
      if (Date.now() - last > hours * 3600000) setTimeout(function () {
        popup.hidden = false; store.set(key, String(Date.now()));
        var b = $('[data-ad-close]', popup); b.focus();
        var close = function () { popup.remove(); };
        b.addEventListener('click', close); popup.addEventListener('click', function (e) { if (e.target === popup) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && popup.parentNode) close(); });
      }, (+popup.dataset.delay || 0) * 1000);
      else popup.remove();
    }
    var sticky = $('[data-ad-sticky]');
    if (sticky) { if (sessionStorage && (function () { try { return sessionStorage.getItem('adsticky'); } catch (e) { return null; } })()) sticky.classList.add('closed');
      $('[data-ad-close]', sticky).addEventListener('click', function () { sticky.classList.add('closed'); try { sessionStorage.setItem('adsticky', '1'); } catch (e) {} }); }
  })();

  /* Google Analytics (सेटिंग में ID हो तो) */
  var ga = document.body.dataset.ga;
  if (ga) {
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', ga);
  }
})();

/* ==========================================================
   Phase 11: सेव, फ़ॉलो, टिप्पणी का जवाब, पोल, न्यूज़लेटर, वेब पुश
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
  var post = function (url, body) {
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; }); });
  };
  var toLogin = function (j) { if (j && j._status === 401) { location.href = j.login || '/account/login'; return true; } return false; };
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

  // पुष्टि वाले फ़ॉर्म
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.matches('form[data-confirm]') && !confirm(f.dataset.confirm)) e.preventDefault();
  }, true);

  // सेव (बुकमार्क)
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-bookmark]'); if (!b) return;
    e.preventDefault(); b.disabled = true;
    post(b.dataset.bookmark, new FormData()).then(function (j) {
      b.disabled = false; if (toLogin(j) || !j.ok) return;
      b.classList.toggle('on', j.on); b.setAttribute('aria-pressed', j.on ? 'true' : 'false');
      b.setAttribute('aria-label', j.on ? 'सेव है' : 'बाद में पढ़ने के लिए सेव करें');
      var i = $('i', b); if (i) i.className = (j.on ? 'fa-solid' : 'fa-regular') + ' fa-bookmark';
    }).catch(function () { b.disabled = false; });
  });

  // फ़ॉलो
  var setFollow = function (key, on) {
    $$('[data-follow="' + key + '"]').forEach(function (btn) {
      btn.classList.toggle('on', on); btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      var i = $('i', btn); if (i) i.className = 'fa-solid ' + (on ? 'fa-check' : 'fa-plus');
    });
  };
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.matches('.follow-form')) return;
    e.preventDefault();
    var btn = $('button', f); btn.disabled = true;
    post(f.action, new FormData(f)).then(function (j) { btn.disabled = false; if (toLogin(j)) return; if (j.ok) setFollow(btn.dataset.follow, j.on); })
      .catch(function () { btn.disabled = false; });
  });

  // लोकेशन खोज: फ़ॉलो वाले चिप या "मेरा शहर" चुनना
  $$('[data-loc-follow], [data-loc-pick]').forEach(function (box) {
    var inp = $('input[type="search"]', box), out = $('[data-loc-results]', box), t, pick = box.hasAttribute('data-loc-pick');
    var followUrl = ($('.follow-form') || {}).action || (location.origin + '/account/follow');
    inp.addEventListener('input', function () {
      clearTimeout(t); var q = inp.value.trim();
      if (pick) { $('input[type="hidden"]', box).value = ''; }
      if (q.length < 2) { out.innerHTML = ''; return; }
      t = setTimeout(function () {
        fetch(box.dataset.search + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
          out.innerHTML = (j.items || []).slice(0, 8).map(function (l) {
            return pick ? '<button type="button" class="follow-chip" data-pick="' + l.id + '" data-name="' + esc(l.name) + '">' + esc(l.label || l.name) + '</button>'
              : '<form method="post" action="' + esc(followUrl) + '" class="follow-form"><input type="hidden" name="_csrf" value="' + esc(csrf) + '"><input type="hidden" name="type" value="location"><input type="hidden" name="id" value="' + l.id + '">'
                + '<button type="submit" class="follow-chip" data-follow="location:' + l.id + '" aria-pressed="false"><i class="fa-solid fa-plus"></i> ' + esc(l.label || l.name) + '</button></form>';
          }).join('') || '<small class="muted">कोई लोकेशन नहीं मिली</small>';
        });
      }, 250);
    });
    if (pick) out.addEventListener('click', function (e) {
      var b = e.target.closest('[data-pick]'); if (!b) return;
      $('input[type="hidden"]', box).value = b.dataset.pick; inp.value = b.dataset.name; out.innerHTML = '';
    });
  });

  // टिप्पणी का जवाब
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-reply]'); if (!b) return;
    var f = b.parentNode.querySelector('[data-reply-form]'); if (!f) return;
    f.hidden = !f.hidden; if (!f.hidden) { var ta = $('textarea', f); if (ta) ta.focus(); }
  });

  // पोल
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.matches('[data-poll-form]')) return;
    e.preventDefault();
    var box = f.closest('[data-poll]'), msg = $('[data-poll-msg]', f), btn = $('button[type="submit"]', f);
    if (!$$('input[name="options[]"]:checked', f).length) { msg.textContent = 'कोई विकल्प चुनें।'; return; }
    if (btn) btn.disabled = true;
    post(f.action, new FormData(f)).then(function (j) {
      if (j.ok && j.html) { var d = document.createElement('div'); d.innerHTML = j.html; box.replaceWith(d.firstElementChild); }
      else { msg.textContent = j.message || 'वोट नहीं हो सका।'; if (btn) btn.disabled = false; }
    }).catch(function () { f.submit(); });
  });

  // न्यूज़लेटर
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.matches('[data-nl-form]')) return;
    e.preventDefault();
    var msg = $('[data-nl-msg]', f), btn = $('button', f); btn.disabled = true;
    post(f.action, new FormData(f)).then(function (j) {
      btn.disabled = false; msg.textContent = j.message || 'कुछ ग़लत हुआ।'; msg.className = 'nl-msg ' + (j.ok ? 'ok' : 'err');
      if (j.ok) f.reset();
    }).catch(function () { btn.disabled = false; f.submit(); });
  });

  // वेब पुश
  var key = ($('meta[name="push-key"]') || {}).content;
  var btns = $$('[data-push-toggle]');
  if (key && btns.length && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window) {
    var swUrl = $('meta[name="push-sw"]').content, subUrl = $('meta[name="push-sub"]').content, unsubUrl = $('meta[name="push-unsub"]').content;
    var appKey = function () { var s = key.replace(/-/g, '+').replace(/_/g, '/'); s += '='.repeat((4 - s.length % 4) % 4); var r = atob(s), a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a; };
    var scope = new URL('./', swUrl).pathname;
    var reg = navigator.serviceWorker.register(swUrl, { scope: scope });
    var paint = function (on) {
      btns.forEach(function (b) {
        b.hidden = false; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false');
        var i = $('i', b); if (i) i.className = (on ? 'fa-solid' : 'fa-regular') + ' fa-bell';
        if (!b.classList.contains('nav-tool')) b.textContent = on ? 'पुश बंद करें' : 'पुश चालू करें';
        else b.title = on ? 'ब्रेकिंग न्यूज़ की सूचना चालू है (बंद करने के लिए दबाएँ)' : (b.dataset.title || (b.dataset.title = b.title));
      });
      $$('[data-push-box]').forEach(function (x) { x.hidden = false; });
    };
    reg.then(function (r) { return r.pushManager.getSubscription(); }).then(function (s) { paint(!!s && Notification.permission === 'granted'); }).catch(function () {});
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        reg.then(function (r) {
          return r.pushManager.getSubscription().then(function (s) {
            if (s) {
              var fd = new FormData(); fd.append('endpoint', s.endpoint);
              return s.unsubscribe().then(function () { return post(unsubUrl, fd); }).then(function () { paint(false); });
            }
            return Notification.requestPermission().then(function (p) {
              if (p !== 'granted') { alert('ब्राउज़र ने सूचना की अनुमति नहीं दी। ब्राउज़र की साइट सेटिंग से बदलें।'); return; }
              return r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: appKey() }).then(function (sub) {
                var fd = new FormData(); fd.append('subscription', JSON.stringify(sub)); fd.append('topics[]', 'breaking'); fd.append('topics[]', 'epaper');
                return post(subUrl, fd).then(function (j) { paint(!!j.ok); });
              });
            });
          });
        }).catch(function () { alert('इस ब्राउज़र में पुश सूचना नहीं चल सकी।'); });
      });
    });
  }
})();

/* Phase 14: चुनाव: लाइव टैली (30 सेकंड), सीट खोज/ज़िला फ़िल्टर, सीट पेज रीलोड */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var fmt = function (n) { return Number(n).toLocaleString('hi-IN'); };
  $$('[data-el-live]').forEach(function (box) {
    var known = $$('[data-el-party]', box).map(function (r) { return r.dataset.elParty; }).join(',');
    var tick = function () {
      if (document.hidden) return;
      fetch(box.dataset.elLive, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
        if (!j) return;
        if (j.election.status !== 'counting') { location.reload(); return; }
        var t = $('[data-el="trends"]', box), d = $('[data-el="declared"]', box);
        if (t) t.textContent = fmt(j.progress.trends); if (d) d.textContent = fmt(j.progress.declared);
        var seats = Math.max(1, j.progress.seats);
        j.parties.forEach(function (p) {
          var row = $('[data-el-party="' + p.party + '"]', box);
          if (row) ['won', 'leading', 'total'].forEach(function (k) { var c = $('[data-k="' + k + '"]', row); if (c) c.textContent = fmt(p[k]); });
          if (row) { var sh = $('[data-k="share"]', row); if (sh) sh.textContent = p.share + '%'; }
          var seg = $('[data-el-seg="' + p.party + '"]', box); if (seg) seg.style.width = (p.total * 100 / seats) + '%';
        });
        // नई पार्टी को सीट मिली तो पूरा पेज
        var now = j.parties.filter(function (p) { return p.total > 0; }).map(function (p) { return p.party; });
        if (now.some(function (p) { return known.indexOf(p) === -1; })) location.reload();
      }).catch(function () {});
    };
    setInterval(tick, 30000);
  });
  var search = $('[data-el-search]'), dist = $('[data-el-district]');
  if (search || dist) {
    var apply = function () {
      var q = search ? search.value.trim().toLowerCase() : '', dv = dist ? dist.value : '';
      $$('[data-el-row]').forEach(function (r) { r.hidden = (q && r.dataset.text.indexOf(q) === -1) || (dv && r.dataset.district !== dv); });
    };
    if (search) search.addEventListener('input', apply);
    if (dist) dist.addEventListener('change', apply);
  }
  var rl = $('[data-el-reload]');
  if (rl) setTimeout(function () { if (!document.hidden) location.reload(); }, (+rl.dataset.elReload || 60) * 1000);
})();

/* Phase 14: खेल: लाइव मैच (15 सेकंड), नई कमेंट्री ऊपर */
(function () {
  'use strict';
  var box = document.querySelector('[data-sc-match]');
  if (!box) return;
  var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
  var set = function (k, v) { var el = box.querySelector('[data-sc="' + k + '"]'); if (el && v != null && el.textContent !== String(v)) el.textContent = v; };
  var labels = { four: 'चौका', six: 'छक्का', wicket: 'विकेट', goal: 'गोल', yellow: 'पीला कार्ड', red: 'लाल कार्ड', sub: 'बदलाव', highlight: 'अहम' };
  var cls = { four: 'c-four', six: 'c-six', wicket: 'c-wicket', goal: 'c-goal', yellow: 'c-yellow', red: 'c-red', highlight: 'c-hl' };
  var tick = function () {
    if (document.hidden) return;
    fetch(box.dataset.scMatch + '?after=' + (box.dataset.after || 0), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
      if (!j) return;
      set('score1', j.score1 || ''); set('score2', j.score2 || ''); set('line', j.result || j.status_text || '');
      var feed = box.querySelector('[data-sc="comm"]');
      if (feed && j.commentary.length) {
        var empty = feed.querySelector('[data-empty]'); if (empty) empty.remove();
        j.commentary.forEach(function (c) {
          var li = document.createElement('li');
          li.className = (cls[c.type] || '') + ' is-new';
          li.innerHTML = '<span class="cm-mk">' + esc(c.marker || '') + '</span><div>' + (labels[c.type] ? '<b class="cm-tag">' + labels[c.type] + '</b> ' : '') + esc(c.text) + '</div>';
          feed.insertBefore(li, feed.firstChild);
          box.dataset.after = c.id;
        });
      }
      if (j.status !== 'live' && j.status !== 'break') { setTimeout(function () { location.reload(); }, 1500); }
    }).catch(function () {});
  };
  setInterval(tick, 15000);
})();

/* Phase 14: /my-city पर शहर बदलते ही पेज ताज़ा */
(function () {
  if (!document.querySelector('[data-mycity-page]')) return;
  document.addEventListener('mycity', function () { setTimeout(function () { location.reload(); }, 400); });
  Array.prototype.forEach.call(document.querySelectorAll('.mc-pick [data-set-city]'), function (b) {
    b.addEventListener('click', function () { setTimeout(function () { location.reload(); }, 900); });
  });
})();

/* ==========================================================
   Phase 16: PWA, मोबाइल, सुलभता
   ========================================================== */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var live = document.createElement('div');
  live.className = 'sr-only'; live.setAttribute('aria-live', 'polite'); live.setAttribute('role', 'status');
  document.body.appendChild(live);
  var say = function (t) { live.textContent = ''; setTimeout(function () { live.textContent = t; }, 50); };

  /* सर्विस वर्कर: पेज लोड होने के बाद; सेटिंग से बंद हो तो पुराना हटाएँ */
  var swMeta = $('meta[name="sw"]');
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      if (swMeta) {
        navigator.serviceWorker.register(swMeta.content, { scope: new URL('./', swMeta.content).pathname }).catch(function () {});
      } else if (navigator.serviceWorker.getRegistrations && !$('.offline-page')) {
        navigator.serviceWorker.getRegistrations().then(function (rs) {
          rs.forEach(function (r) { var w = r.active || r.waiting || r.installing; if (w && /\/sw\.js(\?|$)/.test(w.scriptURL)) r.unregister(); });
        }).catch(function () {});
      }
    });
  }

  /* "ऐप इंस्टॉल करें": दूसरी बार आने पर, मोबाइल पर; "अभी नहीं" = 14 दिन */
  var bar = $('[data-pwa-install]'), deferred = null;
  var standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !/crios|fxios|edgios/i.test(navigator.userAgent);
  try {
    if (!sessionStorage.getItem('np_seen')) { sessionStorage.setItem('np_seen', '1'); store.set('np_visits', String((+store.get('np_visits') || 0) + 1)); }
  } catch (e) {}
  var canShow = function () {
    return bar && !standalone && matchMedia('(max-width: 760px)').matches && (+store.get('np_visits') || 0) >= 2 && (+store.get('np_pi_until') || 0) < Date.now();
  };
  var showBar = function (iosHelp) {
    if (!bar) return;
    if (iosHelp) { $('[data-pi-text]', bar).textContent = 'Safari में नीचे शेयर बटन दबाएँ, फिर "Add to Home Screen" चुनें।'; $('[data-pi-yes]', bar).hidden = true; }
    bar.hidden = false; document.body.classList.add('has-pi');
  };
  var hideBar = function () { if (bar) { bar.hidden = true; document.body.classList.remove('has-pi'); } };
  var installLinks = $$('[data-pwa-link]');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferred = e;
    installLinks.forEach(function (a) { a.hidden = false; });
    if (canShow()) setTimeout(function () { showBar(false); }, 3000);
  });
  if (ios && !standalone) {
    installLinks.forEach(function (a) { a.hidden = false; });
    if (canShow()) setTimeout(function () { showBar(true); }, 3000);
  }
  var install = function () {
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.then(function () { deferred = null; hideBar(); }).catch(hideBar);
    } else if (ios) { showBar(true); }
  };
  if (bar) {
    $('[data-pi-yes]', bar).addEventListener('click', install);
    $('[data-pi-no]', bar).addEventListener('click', function () { store.set('np_pi_until', String(Date.now() + 14 * 864e5)); hideBar(); });
  }
  installLinks.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); install(); }); });
  window.addEventListener('appinstalled', function () { hideBar(); installLinks.forEach(function (a) { a.hidden = true; }); });

  /* ऑफ़लाइन पेज: हाल में पढ़ी (कैश की) ख़बरें */
  var offList = $('[data-offline-list]');
  if (offList && 'caches' in window) {
    caches.open('np-pages').then(function (c) {
      return c.keys().then(function (keys) {
        keys = keys.reverse().slice(0, 40);
        return Promise.all(keys.map(function (k) {
          return c.match(k).then(function (r) { return r ? r.text() : ''; }).then(function (html) {
            var m = /<title>([^<]*)<\/title>/i.exec(html);
            var d = document.createElement('textarea'); d.innerHTML = m ? m[1] : '';
            return { url: k.url, title: d.value };
          });
        }));
      });
    }).then(function (items) {
      var ul = $('[data-offline-items]', offList), seen = {};
      items.forEach(function (it) {
        var path = new URL(it.url).pathname;
        if (!it.title || seen[path] || /\/offline$/.test(path)) return;
        seen[path] = 1;
        var li = document.createElement('li'), a = document.createElement('a');
        var home = $('.off-head .logo');
        a.href = it.url; a.textContent = home && new URL(home.href).pathname === path ? 'होम पेज' : (it.title.replace(/\s*[|·-]\s*[^|·-]+$/, '') || it.title);
        li.appendChild(a); ul.appendChild(li);
      });
      if (ul.children.length) offList.hidden = false;
    }).catch(function () {});
  }
  $$('[data-retry]').forEach(function (b) { b.addEventListener('click', function () { location.reload(); }); });
  if ($('.offline-page')) window.addEventListener('online', function () { location.reload(); });

  /* नेट जाने/आने की सूचना */
  var toast = function (t, cls) {
    var el = $('.net-toast') || document.body.appendChild(Object.assign(document.createElement('div'), { className: 'net-toast' }));
    el.textContent = t; el.className = 'net-toast show ' + (cls || '');
    say(t);
    clearTimeout(el._t); el._t = setTimeout(function () { el.classList.remove('show'); }, 4000);
  };
  window.addEventListener('offline', function () { toast('आप ऑफ़लाइन हैं। हाल में पढ़ी ख़बरें फिर भी खुलेंगी।', 'off'); });
  window.addEventListener('online', function () { toast('फिर से ऑनलाइन।', 'on'); });

  /* "और ख़बरें": पेज बदले बिना अगली ख़बरें (बिना JS पुराना पेजिनेशन) */
  $$('[data-more-pager]').forEach(function (pager) {
    var list = pager.parentNode.querySelector('[data-more-list]');
    if (!list || !$('a[rel="next"]', pager) || !window.fetch || !window.DOMParser) return;
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'btn more-btn'; btn.innerHTML = '<i class="fa-solid fa-angles-down" aria-hidden="true"></i> और ख़बरें';
    pager.parentNode.insertBefore(btn, pager);
    pager.hidden = true;
    var skel = function (n) {
      var f = document.createDocumentFragment();
      for (var i = 0; i < n; i++) {
        var d = document.createElement('div'); d.className = 'skel-item'; d.setAttribute('aria-hidden', 'true');
        d.innerHTML = '<span class="sk sk-img"></span><span class="sk-body"><span class="sk sk-line"></span><span class="sk sk-line w70"></span><span class="sk sk-line w40"></span></span>';
        f.appendChild(d);
      }
      return f;
    };
    btn.addEventListener('click', function () {
      var next = $('a[rel="next"]', pager);
      if (!next || btn.disabled) return;
      btn.disabled = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = 'लोड हो रहा है…';
      list.appendChild(skel(3));
      fetch(next.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'more' } }).then(function (r) {
        if (!r.ok) throw new Error(r.status); return r.text();
      }).then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var nl = doc.querySelector('[data-more-list]'), np = doc.querySelector('[data-more-pager]');
        $$('.skel-item', list).forEach(function (s) { s.remove(); });
        var added = 0, first = null;
        if (nl) Array.prototype.slice.call(nl.children).forEach(function (c) { list.appendChild(c); added++; first = first || c; });
        if (np) { np.hidden = true; pager.replaceWith(np); pager = np; }
        if (history.replaceState) history.replaceState(null, '', next.href);
        say(added + ' और ख़बरें जुड़ गईं');
        var fl = first && (first.matches('a') ? first : $('a', first));
        if (fl) fl.focus({ preventScroll: true });
        if (np && $('a[rel="next"]', np)) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerHTML = '<i class="fa-solid fa-angles-down" aria-hidden="true"></i> और ख़बरें'; }
        else { btn.replaceWith(Object.assign(document.createElement('p'), { className: 'more-end', textContent: 'सभी ख़बरें दिख गईं' })); }
      }).catch(function () {
        $$('.skel-item', list).forEach(function (s) { s.remove(); });
        btn.disabled = false; btn.removeAttribute('aria-busy'); btn.textContent = 'लोड नहीं हुआ, दोबारा कोशिश करें';
      });
    });
  });

  /* ऊपर पेज बदलने की पतली पट्टी (ऐप मोड में भी पता चले कि पेज खुल रहा है) */
  var prog = document.createElement('div'); prog.className = 'nav-progress'; prog.setAttribute('aria-hidden', 'true');
  document.body.appendChild(prog);
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-pwa-link')) return;
    var u = new URL(a.href, location.href);
    if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search)) return;
    prog.classList.add('go');
  });
  window.addEventListener('pageshow', function () { prog.classList.remove('go'); });

  /* ख़बर: पढ़ने की प्रगति + अक्षर का आकार (याद रहता है) */
  var art = $('.news-article .prose');
  if (art) {
    var rp = document.createElement('div'); rp.className = 'read-progress'; rp.setAttribute('aria-hidden', 'true');
    document.body.appendChild(rp);
    var tick = false;
    var upd = function () {
      tick = false;
      var r = art.getBoundingClientRect(), total = r.height - innerHeight * 0.6;
      rp.style.transform = 'scaleX(' + Math.max(0, Math.min(1, total > 0 ? -r.top / total : 1)) + ')';
    };
    addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(upd); } }, { passive: true });
    var fsCtl = $('[data-fs-ctl]');
    var steps = [0.88, 1, 1.12, 1.25, 1.4];
    var cur = Math.max(0, Math.min(steps.length - 1, +(store.get('np_fs') || 1)));
    var apply = function () {
      document.documentElement.style.setProperty('--fs-k', steps[cur]);
      if (fsCtl) { $('[data-fs="-1"]', fsCtl).disabled = cur === 0; $('[data-fs="1"]', fsCtl).disabled = cur === steps.length - 1; }
    };
    if (fsCtl) {
      fsCtl.hidden = false;
      $$('[data-fs]', fsCtl).forEach(function (b) {
        b.addEventListener('click', function () {
          cur = Math.max(0, Math.min(steps.length - 1, cur + (+b.dataset.fs)));
          store.set('np_fs', String(cur)); apply(); say('अक्षर का आकार ' + Math.round(steps[cur] * 100) + '%');
        });
      });
    }
    apply();
  }

  /* "ऊपर जाएँ" बटन */
  var top = document.createElement('button');
  top.type = 'button'; top.className = 'to-top'; top.setAttribute('aria-label', 'पेज के ऊपर जाएँ'); top.hidden = true;
  top.innerHTML = '<i class="fa-solid fa-arrow-up" aria-hidden="true"></i>';
  document.body.appendChild(top);
  var topTick = false;
  addEventListener('scroll', function () {
    if (topTick) return; topTick = true;
    requestAnimationFrame(function () { topTick = false; top.hidden = scrollY < innerHeight * 2; });
  }, { passive: true });
  top.addEventListener('click', function () {
    scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    var f = $('.logo') || $('#main'); if (f) f.focus({ preventScroll: true });
  });

  /* इमेज आ जाए तो स्केलेटन की चमक बंद */
  var loaded = function (img) { img.setAttribute('data-loaded', ''); };
  $$('.th img').forEach(function (img) { if (img.complete) loaded(img); });
  document.addEventListener('load', function (e) { if (e.target.tagName === 'IMG') loaded(e.target); }, true);
  document.addEventListener('error', function (e) { if (e.target.tagName === 'IMG') loaded(e.target); }, true);

  /* मेनू ड्रॉअर: Tab ड्रॉअर के अंदर ही घूमे */
  var drawer = $('#drawer');
  if (drawer) {
    drawer.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab' || drawer.hidden) return;
      var f = $$('a[href], button:not([disabled]), input, select, textarea', drawer).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }
})();
