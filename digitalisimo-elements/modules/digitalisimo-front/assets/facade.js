(function () {
  'use strict';
  // Fachadas: el iframe del vídeo, el mapa o el documento se crea al pulsar su botón, nunca antes, y recibe el foco.
  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-digi-facade-src]') : null;
    var facade = button && button.closest('[data-digi-facade]');
    if (!facade) return;
    var data = button.dataset;
    if (!/^https:\/\//i.test(data.digiFacadeSrc)) return;
    var frame = document.createElement('iframe');
    if (data.digiFacadeClass) frame.className = data.digiFacadeClass;
    frame.title = data.digiFacadeTitle || '';
    if (data.digiFacadeAllow) frame.setAttribute('allow', data.digiFacadeAllow);
    if (data.digiFacadeReferrer) frame.setAttribute('referrerpolicy', data.digiFacadeReferrer);
    if ('digiFacadeFullscreen' in data) frame.setAttribute('allowfullscreen', '');
    frame.src = data.digiFacadeSrc;
    facade.replaceWith(frame);
    frame.focus();
  });
})();
