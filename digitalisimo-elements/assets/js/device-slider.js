/*
 * Slider de dispositivos: color de la barra de estado y del fondo de cada captura. Lee el primer renglón de la imagen
 * (barra de estado del celular o la tableta) y el último (fondo bajo una captura más baja que la pantalla). Si la
 * imagen no se puede leer (otro dominio sin CORS), se queda el color neutro de la hoja.
 */
(function () {
  'use strict';
  function row(img, y) {
    var canvas = document.createElement('canvas');
    canvas.width = 24;
    canvas.height = 1;
    var g = canvas.getContext('2d', { willReadFrequently: true });
    g.drawImage(img, 0, y, img.naturalWidth, 1, 0, 0, 24, 1);
    var d = g.getImageData(0, 0, 24, 1).data, sum = [0, 0, 0];
    for (var i = 0; i < d.length; i += 4) { sum[0] += d[i]; sum[1] += d[i + 1]; sum[2] += d[i + 2]; }
    return sum.map(function (v) { return Math.round(v / 24); });
  }
  function tint(img) {
    var slide = img.closest('.digi-device-slider__slide');
    if (!slide || !img.naturalWidth) return;
    try {
      var top = row(img, 0), bottom = row(img, img.naturalHeight - 1);
      slide.style.setProperty('--ds-top', 'rgb(' + top.join(',') + ')');
      slide.style.setProperty('--ds-bottom', 'rgb(' + bottom.join(',') + ')');
      var light = (0.2126 * top[0] + 0.7152 * top[1] + 0.0722 * top[2]) / 255;
      slide.classList.toggle('digi-device-slider__slide--dark', light < 0.55);
    } catch (error) { /* imagen de otro dominio: color neutro */ }
  }
  function scan(scope) {
    (scope || document).querySelectorAll('.digi-device-slider img.digi-device-slider__image').forEach(function (img) {
      if (img.complete && img.naturalWidth) tint(img);
      else img.addEventListener('load', function () { tint(img); }, { once: true });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(document); });
  else scan(document);
  function editor() {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-device-slider.default', function (element) { scan(element[0]); });
    }
  }
  if (window.elementorFrontend && elementorFrontend.hooks) editor();
  else if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', editor);
}());
