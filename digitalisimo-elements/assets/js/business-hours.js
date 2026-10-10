(function () {
  'use strict';
  // Resalta el día de hoy del visitante en las filas que nombran un solo día.
  function init(root) {
    var today = String(new Date().getDay());
    (root || document).querySelectorAll('[data-digi-hours-today] [data-weekday]').forEach(function (row) {
      row.classList.toggle('is-today', row.getAttribute('data-weekday') === today);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
})();
