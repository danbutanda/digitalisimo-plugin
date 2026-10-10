(function () {
  'use strict';
  // Botón de pausa de cada marquesina (WCAG 2.2.2).
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-digi-marquee-toggle]');
    if (!button) return;
    var marquee = button.closest('[data-digi-marquee]');
    var paused = marquee.classList.toggle('is-paused');
    button.setAttribute('aria-pressed', paused ? 'true' : 'false');
    var text = button.querySelector('.digi-marquee__toggle-text');
    if (text) text.textContent = paused ? 'Reanudar' : 'Pausar';
  });
})();
