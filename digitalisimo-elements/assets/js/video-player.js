(function () {
  'use strict';
  // Lista de vídeos: cada título cambia el vídeo del reproductor al pulsarlo o, con data-digi-playlist="hover",
  // al pasar el cursor o enfocarlo; en ese caso se reproduce silenciado, como una vista previa.
  function select(player, button, play) {
    var video = player.querySelector('video');
    if (!video || button.getAttribute('aria-pressed') === 'true') { if (play && video) video.play().catch(function () {}); return; }
    player.querySelectorAll('.digi-video-player__item').forEach(function (item) { item.setAttribute('aria-pressed', item === button ? 'true' : 'false'); });
    var source = video.querySelector('source');
    if (source) source.remove();
    video.src = button.getAttribute('data-src');
    var poster = button.getAttribute('data-poster');
    if (poster) video.setAttribute('poster', poster); else video.removeAttribute('poster');
    video.setAttribute('aria-label', button.textContent.trim());
    video.load();
    if (play) video.play().catch(function () {});
  }
  function setup(player) {
    if (player.digiPlaylist) return;
    player.digiPlaylist = true;
    var hover = player.getAttribute('data-digi-playlist') === 'hover';
    player.querySelectorAll('.digi-video-player__item').forEach(function (button) {
      button.addEventListener('click', function () { select(player, button, true); });
      if (hover) {
        var preview = function () { player.querySelector('video').muted = true; select(player, button, true); };
        button.addEventListener('mouseenter', preview);
        button.addEventListener('focus', preview);
      }
    });
  }
  function init(scope) { (scope || document).querySelectorAll('[data-digi-playlist]').forEach(setup); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-video-player.default', function ($scope) { init($scope[0] || document); });
  }
})();
