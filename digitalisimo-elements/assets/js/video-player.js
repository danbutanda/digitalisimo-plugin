(function () {
  'use strict';
  // Lista de vídeos: cada título cambia el vídeo del escenario al pulsarlo o, con data-digi-playlist="hover",
  // al pasar el cursor o enfocarlo; en ese caso se reproduce silenciado, como una vista previa. El escenario
  // alterna entre <video> (archivo) e <iframe> (YouTube, Vimeo, Dailymotion o Wistia) según el vídeo elegido.
  function withParams(url, params) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + params;
  }

  function select(player, button, play, muted) {
    var stage = player.querySelector('.digi-video-player__stage');
    if (!stage) return;
    var current = stage.firstElementChild;
    if (button.getAttribute('aria-pressed') === 'true') {
      if (play && current && current.tagName === 'VIDEO') { current.muted = !!muted || current.muted; current.play().catch(function () {}); }
      return;
    }
    player.querySelectorAll('.digi-video-player__item').forEach(function (item) { item.setAttribute('aria-pressed', item === button ? 'true' : 'false'); });
    var kind = button.getAttribute('data-kind') === 'embed' ? 'embed' : 'video';
    var src = button.getAttribute('data-src');
    var title = button.getAttribute('data-title') || button.textContent.trim();
    var next;
    if (kind === 'embed') {
      next = document.createElement('iframe');
      next.className = 'digi-video-player__embed';
      next.setAttribute('title', title);
      next.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
      next.setAttribute('allowfullscreen', '');
      // Cada servicio entiende uno de estos nombres; los que no conoce los ignora.
      next.src = play ? withParams(src, muted ? 'autoplay=1&mute=1&muted=1' : 'autoplay=1') : src;
    } else {
      next = document.createElement('video');
      next.controls = true;
      next.playsInline = true;
      next.preload = 'none';
      next.setAttribute('playsinline', '');
      next.setAttribute('aria-label', title);
      var poster = button.getAttribute('data-poster');
      if (poster) next.setAttribute('poster', poster);
      next.muted = !!muted;
      next.src = src;
    }
    if (current) stage.replaceChild(next, current); else stage.appendChild(next);
    var now = player.querySelector('.digi-video-player__now');
    if (now) {
      var description = button.querySelector('.digi-video-player__item-description');
      var heading = now.querySelector('.digi-video-player__now-title');
      var text = now.querySelector('.digi-video-player__now-description');
      if (heading) heading.textContent = title;
      if (text) { text.textContent = description ? description.textContent : ''; text.hidden = !description; }
    }
    if (kind === 'video' && play) next.play().catch(function () {});
  }

  function setup(player) {
    if (player.digiPlaylist) return;
    player.digiPlaylist = true;
    var hover = player.getAttribute('data-digi-playlist') === 'hover';
    player.querySelectorAll('.digi-video-player__item').forEach(function (button) {
      button.addEventListener('click', function () { select(player, button, true, false); });
      if (hover) {
        var preview = function () { select(player, button, true, true); };
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
