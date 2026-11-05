(function () {
  'use strict';
  // Fachada de los widgets de Facebook: nada se pide a Facebook hasta que la persona pulsa. Al pulsar, el plugin que
  // viaja en <template> entra en la página, el SDK se carga una sola vez para todos y se analiza sólo ese widget.
  var state = 0; // 0: sin cargar · 1: cargando · 2: listo
  var queue = [];
  function parse(el) {
    try { window.FB.XFBML.parse(el); } catch (e) { /* el SDK no respondió como se espera: el marcado queda puesto */ }
  }
  function sdk(lang, app, el) {
    if (state === 0 && window.FB && window.FB.XFBML) state = 2;
    if (state === 2) { parse(el); return; }
    queue.push(el);
    if (state === 1) return;
    state = 1;
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://connect.facebook.net/' + lang + '/sdk.js';
    s.onload = function () {
      if (!window.FB || !window.FB.init) { state = 0; queue.length = 0; return; }
      var conf = { version: 'v2.10', xfbml: false };
      if (app) conf.appId = app;
      window.FB.init(conf);
      state = 2;
      queue.splice(0).forEach(parse);
    };
    s.onerror = function () { state = 0; queue.length = 0; };
    document.head.appendChild(s);
  }
  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('.digi-fb__load') : null;
    var box = button && button.closest('[data-digi-fb]');
    if (!box || box.classList.contains('digi-fb--loaded')) return;
    var tpl = box.querySelector('template.digi-fb__embed');
    if (!tpl || !tpl.content) return;
    var lang = /^[a-z]{2,3}_[A-Z]{2}$/.test(box.dataset.digiFbLang || '') ? box.dataset.digiFbLang : 'es_ES';
    var app = /^[0-9]{1,32}$/.test(box.dataset.digiFbApp || '') ? box.dataset.digiFbApp : '';
    box.replaceChildren(tpl.content.cloneNode(true));
    box.classList.add('digi-fb--loaded');
    box.setAttribute('tabindex', '-1');
    box.focus();
    sdk(lang, app, box);
  });
})();
