/* Botones para compartir, como PRO (su manejador y `share-link.js` del núcleo): al pulsar un botón, la página actual que
   se comparte es la dirección del navegador, sin un parámetro `u` de otro dominio (cambia la del servidor,
   `data-digi-share-url`, sólo entera: no el comienzo de la imagen en la portada), y un enlace http(s) se abre en una
   ventana emergente centrada de 640 × 480, sin acceso a esta página; el correo, en la misma ventana. «Imprimir» abre el
   diálogo de impresión. Sin JavaScript queda el enlace del servidor. Sin dependencias ni peticiones. */
(() => {
	const W = window, D = document;
	const here = () => {
		const u = new URL(W.location.href), p = new URLSearchParams(u.search), v = p.get('u');
		if (v) { try { if (new URL(v).hostname !== u.hostname) p.delete('u'); } catch (e) { p.delete('u'); } }
		u.search = p.toString();
		return u.toString();
	};
	const go = (e, type) => {
		const a = e.target && e.target.closest && e.target.closest('.digi-share__btn');
		if (!a || e.defaultPrevented) return;
		if (a.hasAttribute('data-digi-share-print')) { if (type === 'click') { e.preventDefault(); W.print(); } return; }
		if (!a.hasAttribute('href')) return;
		const box = a.closest('[data-digi-share-url]'), from = box && box.getAttribute('data-digi-share-url');
		if (a.digiHref === undefined) a.digiHref = a.getAttribute('href');
		const href = from ? a.digiHref.replace(new RegExp(from.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=&|$)', 'g'), () => encodeURIComponent(here())) : a.digiHref;
		a.setAttribute('href', href);
		if (type !== 'click' || e.button || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || !/^https?:\/\//i.test(href)) return;
		e.preventDefault();
		const w = W.open(href, '', 'toolbar=0,status=0,width=640,height=480,top=' + (screen.height / 2 - 240) + ',left=' + (screen.width / 2 - 320));
		if (w) w.opener = null;
	};
	['click', 'auxclick', 'contextmenu'].forEach(t => D.addEventListener(t, e => go(e, t)));
})();
