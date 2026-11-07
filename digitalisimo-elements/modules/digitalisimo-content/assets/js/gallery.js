/* Galería avanzada (digitalisimo-gallery): filtros por galería y disposiciones justificada y mampostería, sin jQuery.
 * La cuadrícula es sólo CSS. Mismo cálculo que la librería de galería del núcleo (GPL), con las medidas del adjunto
 * (data-w, data-h) y las variables del dispositivo (--digi-gallery-row, --columns, --hgap, --vgap). */
(() => {
	'use strict';
	const num = (el, name, fallback) => {
		const v = parseFloat(getComputedStyle(el).getPropertyValue(name));
		return isNaN(v) ? fallback : v;
	};
	const ratio = (el) => (+el.dataset.w || 1) / (+el.dataset.h || 1);
	const place = (el, left, top, width, height) => {
		const s = el.style;
		s.insetInlineStart = left + 'px';
		s.top = top + 'px';
		s.width = width + 'px';
		s.height = null === height ? '' : height + 'px';
	};

	// Justificada: filas con la altura ideal; una fila se cierra donde queda más cerca del ancho; la última sólo se estira
	// si llena el 70 %.
	function justified(box, list, W, gap, vgap) {
		const ideal = num(box, '--digi-gallery-row', 200);
		const rows = [];
		let start = 0;
		let used = 0;
		const width = [];
		const fit = (from, to, rowWidth) => {
			const gaps = to - from - 1;
			const room = W - gaps * gap;
			let acc = 0;
			const row = { items: [], h: (width[from] / rowWidth) * room / ratio(list[from]) };
			for (let i = from; i < to; i++) {
				const p = width[i] / rowWidth;
				row.items.push([list[i], acc * room + gap * (i - from), p * room]);
				acc += p;
			}
			rows.push(row);
		};
		for (let i = 0; i < list.length; i++) {
			const w = Math.min(W, Math.round(ideal * ratio(list[i])));
			const next = used + w;
			if (next > W && W - used < next - W) {
				fit(start, i, used);
				start = i;
				used = 0;
				i--;
				continue;
			}
			width[i] = w;
			if (i === list.length - 1) {
				fit(start, i + 1, next / W >= 0.7 ? next : W);
			}
			used = next;
		}
		let top = 0;
		rows.forEach((row) => {
			row.items.forEach(([el, left, w]) => place(el, left, top, w, row.h));
			top += row.h + vgap;
		});
		return top - vgap;
	}

	// Mampostería: cada imagen va a la columna de su posición salvo que otra sea más de 5 px más baja.
	function masonry(box, list, W, gap, vgap) {
		const cols = Math.max(1, Math.round(num(box, '--columns', 4)));
		const w = (W - gap * (cols - 1)) / cols;
		const heights = new Array(cols).fill(0);
		const count = new Array(cols).fill(0);
		list.forEach((el, i) => {
			let col = i % cols;
			let natural = heights[col];
			heights.forEach((h, c) => {
				if (h && natural > h + 5) {
					natural = h;
					col = c;
				}
			});
			place(el, col * (w + gap), heights[col] + count[col] * vgap, w, null);
			el.style.setProperty('--digi-gallery-ratio', (+el.dataset.w || 1) + '/' + (+el.dataset.h || 1));
			heights[col] += w / ratio(el);
			count[col]++;
		});
		const highest = Math.max(...heights);
		return highest + (count[heights.indexOf(highest)] - 1) * vgap;
	}

	function layout(box) {
		const type = box.dataset.digiLayout;
		const list = [...box.children].filter((el) => !el.hidden);
		box.classList.remove('digi-gallery__container--abs');
		const W = box.clientWidth;
		if (!W || !list.length || !(type === 'justified' || type === 'masonry')) return;
		const gap = num(box, '--hgap', 10);
		const vgap = num(box, '--vgap', 10);
		const height = (type === 'justified' ? justified : masonry)(box, list, W, gap, vgap);
		box.classList.add('digi-gallery__container--abs');
		box.style.height = Math.max(0, height) + 'px';
	}

	function filter(root, box, button) {
		const value = button.dataset.digiFilter;
		const id = box.id.replace(/^digi-gallery-/, '');
		root.querySelectorAll('.digi-gallery__filter').forEach((b) => {
			const on = b === button;
			b.classList.toggle('digi-gallery__filter--active', on);
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
		[...box.children].forEach((el) => {
			const show = 'all' === value || (el.dataset.digiTags || '').split(',').includes(value);
			el.hidden = !show;
			// El lightbox del núcleo agrupa por este atributo: sólo las imágenes visibles del filtro.
			if (show && el.hasAttribute('data-elementor-lightbox-slideshow')) el.setAttribute('data-elementor-lightbox-slideshow', value + '_' + id);
		});
		layout(box);
	}

	function init(root) {
		const box = root.querySelector('.digi-gallery__container');
		if (!box || root.digiGallery) return;
		root.digiGallery = true;
		root.querySelectorAll('.digi-gallery__filter').forEach((b) => b.addEventListener('click', () => filter(root, box, b)));
		// «Carga diferida», como la librería de PRO: cada imagen entra al cargarse, desde la mitad de su tamaño y sin
		// opacidad (la hoja); la que ya está cargada, al instante. Lo pone el script: sin él, las imágenes se ven.
		if (box.hasAttribute('data-digi-lazy')) {
			box.classList.add('digi-gallery__container--lazy');
			box.querySelectorAll('.digi-gallery__image').forEach((img) => {
				const done = () => img.classList.add('is-loaded');
				if (img.complete && img.naturalWidth) done();
				else img.addEventListener('load', done, { once: true });
			});
		}
		if (box.dataset.digiLayout) {
			let last = -1;
			const run = () => {
				if (box.clientWidth !== last) {
					last = box.clientWidth;
					layout(box);
				}
			};
			run();
			if ('ResizeObserver' in window) new ResizeObserver(run).observe(box);
			else addEventListener('resize', run);
		}
		root.classList.add('digi-gallery--ready');
	}

	const boot = (scope) => {
		if (scope && scope.querySelectorAll) scope.querySelectorAll('.digi-gallery').forEach(init);
	};
	boot(document);
	if ('loading' === document.readyState) document.addEventListener('DOMContentLoaded', () => boot(document));
	addEventListener('elementor/frontend/init', () => {
		const front = window.elementorFrontend;
		if (front && front.hooks) front.hooks.addAction('frontend/element_ready/digitalisimo-gallery.default', ($scope) => boot($scope && $scope[0] ? $scope[0] : $scope));
	});
})();
