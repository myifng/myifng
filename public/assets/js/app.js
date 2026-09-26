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

  /* Google Analytics (सेटिंग में ID हो तो) */
  var ga = document.body.dataset.ga;
  if (ga) {
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', ga);
  }
})();
