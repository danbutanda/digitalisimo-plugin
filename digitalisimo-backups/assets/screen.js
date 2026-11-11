/* Pantalla de Backups («interfaz simple», 1.0.74). «Restaurar» abre y cierra su panel bajo la fila sin recargar (sin
   JavaScript, el enlace lleva ?restore=… y PHP pinta el panel abierto) y «Destino» del botón dice el elegido. Todo se
   pinta como texto: nada de HTML. */
(function () {
  'use strict';
  function toggle(link, row, open) {
    row.hidden = !open;
    if (link) link.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      var input = row.querySelector('input[name="confirmation"]');
      if (input && input.focus) input.focus();
    } else if (link && link.focus) link.focus();
  }
  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target.closest('[data-restore-toggle], [data-restore-cancel]') : null;
    if (!target) return;
    var cancel = target.getAttribute('data-restore-cancel');
    var id = cancel || target.getAttribute('data-restore-toggle');
    var row = id ? document.getElementById(id) : null;
    if (!row) return;
    event.preventDefault();
    var link = document.querySelector('[data-restore-toggle="' + id + '"]');
    toggle(link, row, cancel ? false : row.hidden);
  });
  document.addEventListener('change', function (event) {
    var select = event.target;
    if (!select || select.name !== 'digitalisimo_backup_destination' || !select.form) return;
    var label = select.form.querySelector('[data-destination-label]');
    var option = select.options[select.selectedIndex];
    if (label && option) label.textContent = option.text;
  });
}());
