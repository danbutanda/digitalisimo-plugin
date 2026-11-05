/* Botones para compartir: el botón «Imprimir» abre el diálogo de impresión del navegador. Sin dependencias ni peticiones. */
document.addEventListener('click', function (e) {
  var b = e.target && e.target.closest && e.target.closest('[data-digi-share-print]');
  if (b) { e.preventDefault(); window.print(); }
});
