/*
 * Digitalísimo · auditoría de calidad frontend.
 *
 * Sólo se carga en una captura iniciada por un administrador (token de un solo
 * uso). Recoge hechos de la página tal como la pinta el navegador: nombre
 * accesible de los enlaces, encabezados visibles, imágenes, controles
 * interactivos, colores computados y posición de los widgets. No modifica la
 * página ni decide nada: el servidor evalúa los datos con sus reglas.
 */
(function () {
	'use strict';

	var script = document.currentScript;
	var config;
	try { config = JSON.parse(atob(script.getAttribute('data-config'))); } catch (e) { return; }

	var OWN = '#digitalisimo-quality-banner, #digitalisimo-quality-frame, #wpadminbar';
	var TARGETS = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [role="button"], [role="link"], [role="tab"], [role="checkbox"], [role="switch"], [role="menuitem"], [tabindex]:not([tabindex="-1"]), .swiper-pagination-bullet';
	var NOISE = /^(elementor-(invisible|animated|motion-effects-element)|animated|lazyload(ed)?|e-lazyloaded|swiper-slide-(active|next|prev|visible|duplicate[\w-]*)|is-(active|visible|open))$/;

	function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
	function clean(value, max) { return String(value == null ? '' : value).replace(/\s+/g, ' ').trim().slice(0, max || 160); }
	function round(n) { return Math.round(n * 10) / 10; }
	function ours(el) { return !!(el.closest && el.closest(OWN)); }
	function classes(el) { return clean(el.getAttribute('class') || '', 300); }

	function banner(text) {
		var box = document.getElementById('digitalisimo-quality-banner');
		if (!box) {
			box = document.createElement('div');
			box.id = 'digitalisimo-quality-banner';
			box.setAttribute('role', 'status');
			box.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:2147483647;padding:10px 16px;background:#1d2327;color:#fff;font:14px/1.4 system-ui,sans-serif;text-align:center';
			document.body.appendChild(box);
		}
		box.textContent = text;
	}

	/* ---------------------------------------------------------------- *
	 * Visibilidad, selector y origen
	 * ---------------------------------------------------------------- */

	function visible(el, win) {
		if (!el.getClientRects().length) return false;
		var style = win.getComputedStyle(el);
		if (style.visibility === 'hidden' || style.visibility === 'collapse' || parseFloat(style.opacity) === 0) return false;
		var rect = el.getBoundingClientRect();
		return rect.width > 0 && rect.height > 0;
	}

	/** Recortado por un ancestro con overflow (menús cerrados, slides fuera del carrusel). */
	function clipped(el, win) {
		var rect = el.getBoundingClientRect();
		var node = el.parentElement;
		for (var depth = 0; node && node !== el.ownerDocument.body && depth < 20; depth++, node = node.parentElement) {
			var style = win.getComputedStyle(node);
			if (style.overflowX === 'visible' && style.overflowY === 'visible') continue;
			var box = node.getBoundingClientRect();
			if (rect.right <= box.left || rect.left >= box.right || rect.bottom <= box.top || rect.top >= box.bottom) return true;
		}
		return false;
	}

	function selector(el) {
		var own = el.tagName.toLowerCase();
		if (el.id && /^[A-Za-z][\w-]*$/.test(el.id)) return own + '#' + el.id;
		var list = Array.prototype.filter.call(el.classList || [], function (c) { return /^[A-Za-z_-][\w-]*$/.test(c) && !NOISE.test(c); }).slice(0, 3);
		if (list.length) own += '.' + list.join('.');
		var holder = el.parentElement && el.parentElement.closest('.elementor-element[data-id]');
		if (holder) return ('.elementor-element-' + holder.getAttribute('data-id') + ' ' + own).slice(0, 200);
		var landmark = el.parentElement && el.parentElement.closest('header, footer, nav, main, aside');
		return ((landmark ? landmark.tagName.toLowerCase() + ' ' : '') + own).slice(0, 200);
	}

	function widget(el) {
		var holder = el.closest('[data-widget_type]');
		if (holder) return clean(holder.getAttribute('data-widget_type').split('.')[0], 60);
		var landmark = el.closest('header, footer, nav');
		return landmark ? landmark.tagName.toLowerCase() : '';
	}

	/** Nombre accesible simplificado: aria-labelledby, aria-label, contenido (alt, title de SVG) y title. */
	function nameOf(el, win, depth) {
		depth = depth || 0;
		if (!depth) {
			var labelled = el.getAttribute('aria-labelledby');
			if (labelled) {
				var joined = labelled.split(/\s+/).map(function (id) { var n = el.ownerDocument.getElementById(id); return n ? n.textContent : ''; }).join(' ');
				if (clean(joined)) return clean(joined, 200);
			}
			var label = el.getAttribute('aria-label');
			if (label && clean(label)) return clean(label, 200);
		}
		var out = '';
		Array.prototype.forEach.call(el.childNodes, function (child) {
			if (child.nodeType === 3) { out += ' ' + child.nodeValue; return; }
			if (child.nodeType !== 1 || child.getAttribute('aria-hidden') === 'true') return;
			var style = win.getComputedStyle(child);
			if (style.display === 'none' || style.visibility === 'hidden') return;
			var tag = child.tagName.toLowerCase();
			if (tag === 'img') out += ' ' + (child.getAttribute('alt') || '');
			else if (tag === 'svg') { var title = child.querySelector('title'); out += ' ' + (child.getAttribute('aria-label') || (title ? title.textContent : '')); }
			else if (tag !== 'script' && tag !== 'style') out += ' ' + (child.getAttribute('aria-label') || nameOf(child, win, depth + 1));
		});
		out = clean(out, 200);
		if (!out && !depth) out = clean(el.getAttribute('title') || '', 200);
		return out;
	}

	/* ---------------------------------------------------------------- *
	 * Hechos por categoría
	 * ---------------------------------------------------------------- */

	function links(doc, win) {
		var out = [];
		Array.prototype.forEach.call(doc.querySelectorAll('a'), function (a) {
			if (out.length >= 600 || ours(a)) return;
			var href = a.getAttribute('href');
			var exists = null;
			if (href && href.charAt(0) === '#' && href.length > 1 && href.indexOf('#elementor-action') !== 0) {
				try { var id = decodeURIComponent(href.slice(1)); exists = !!(doc.getElementById(id) || doc.getElementsByName(id).length); } catch (e) { exists = false; }
			}
			var item = a.closest('li');
			var cls = classes(a);
			out.push({
				href: href, resolved: href === null ? '' : clean(a.href, 500), name: nameOf(a, win),
				icon: !!a.querySelector('i, svg, [class*="icon"]'), selector: selector(a), widget: widget(a), visible: visible(a, win) && !clipped(a, win),
				submenu: !!(item && item.querySelector('ul, .sub-menu')) || a.getAttribute('aria-haspopup') === 'true' || /\bhas-submenu\b/.test(cls),
				social: /\belementor-social-icon\b|\bsocial\b/.test(cls), button: /\belementor-button\b/.test(cls) || a.getAttribute('role') === 'button',
				target_exists: exists
			});
		});
		return out;
	}

	function headings(doc, win) {
		var out = [];
		Array.prototype.forEach.call(doc.querySelectorAll('h1, h2, h3, h4, h5, h6, [role="heading"]'), function (h) {
			if (out.length >= 300 || ours(h)) return;
			var level = /^H[1-6]$/.test(h.tagName) ? +h.tagName.charAt(1) : parseInt(h.getAttribute('aria-level') || '2', 10);
			out.push({ level: level, text: nameOf(h, win), selector: selector(h), widget: widget(h), visible: visible(h, win), image: !!h.querySelector('img, svg') });
		});
		return out;
	}

	function widths(srcset) {
		return String(srcset || '').split(',').map(function (part) { var m = /\s(\d+)w\s*$/.exec(part.trim()); return m ? m[1] + 'w' : ''; }).filter(Boolean).join(', ');
	}

	/**
	 * Ancho real del archivo descargado. Con un srcset de anchos, naturalWidth
	 * viene dividido por la densidad elegida; el descriptor del candidato que
	 * coincide con currentSrc es el ancho del archivo.
	 */
	function file(img, doc) {
		var natural = [img.naturalWidth || 0, img.naturalHeight || 0];
		var current = img.currentSrc;
		if (!current || !natural[0]) return natural;
		var found = 0;
		String(img.getAttribute('srcset') || '').split(/,\s+/).forEach(function (part) {
			var m = /^(\S+)\s+(\d+)w$/.exec(part.trim());
			if (!m || found) return;
			try { if (new URL(m[1], doc.baseURI).href === current) found = +m[2]; } catch (e) {}
		});
		return found ? [found, Math.round(found * natural[1] / natural[0])] : natural;
	}

	function images(doc, win, full) {
		var out = [];
		Array.prototype.forEach.call(doc.querySelectorAll('img'), function (img) {
			if (out.length >= 300 || ours(img)) return;
			var rect = img.getBoundingClientRect();
			var style = win.getComputedStyle(img);
			var row = {
				src: clean(img.currentSrc || img.getAttribute('src') || img.getAttribute('data-src') || '', 500), cls: classes(img),
				natural: file(img, doc), rect: [round(rect.width), round(rect.height)], fit: style.objectFit,
				srcset: widths(img.getAttribute('srcset') || img.getAttribute('data-srcset')), sizes: clean(img.getAttribute('sizes') || img.getAttribute('data-sizes') || '', 300),
				width: clean(img.getAttribute('width') || '', 10), height: clean(img.getAttribute('height') || '', 10),
				loading: clean(img.getAttribute('loading') || '', 10), fetchpriority: clean(img.getAttribute('fetchpriority') || '', 10), decoding: clean(img.getAttribute('decoding') || '', 10),
				visible: visible(img, win) && !clipped(img, win), selector: selector(img), widget: widget(img)
			};
			if (full) {
				row.alt = img.hasAttribute('alt') ? clean(img.getAttribute('alt'), 300) : null;
				row.role = clean(img.getAttribute('role') || '', 20);
				row.aria_hidden = !!img.closest('[aria-hidden="true"]');
				var figure = img.closest('figure');
				var caption = figure && figure.querySelector('figcaption');
				row.caption = caption ? clean(caption.textContent, 300) : '';
				var link = img.closest('a');
				row.link_text = link ? clean(link.textContent, 300) : '';
			}
			out.push(row);
		});
		return out;
	}

	/** Un enlace dentro de una frase está exento del tamaño mínimo (WCAG 2.5.8): tiene texto a su lado. */
	function inline(el, win) {
		if (el.tagName !== 'A' || win.getComputedStyle(el).display !== 'inline' || !el.parentElement) return false;
		return Array.prototype.some.call(el.parentElement.childNodes, function (node) { return node.nodeType === 3 && node.nodeValue.trim().length >= 2; });
	}

	function targets(doc, win) {
		var out = [];
		Array.prototype.forEach.call(doc.querySelectorAll(TARGETS), function (el) {
			if (out.length >= 500 || ours(el) || !visible(el, win) || clipped(el, win)) return;
			var rect = el.getBoundingClientRect();
			out.push({ x: round(rect.left + win.scrollX), y: round(rect.top + win.scrollY), w: round(rect.width), h: round(rect.height), inline: inline(el, win), name: clean(nameOf(el, win), 80), selector: selector(el), widget: widget(el) });
		});
		return out;
	}

	function parse(color) {
		var m = /rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[,\/]\s*([\d.]+)(%?))?/i.exec(color || '');
		if (!m) return null;
		var alpha = m[4] === undefined ? 1 : (m[5] ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
		return [+m[1], +m[2], +m[3], alpha];
	}

	/**
	 * Fondo efectivo: capas de background-color de los ancestros sobre blanco.
	 * Una imagen, degradado, vídeo o slideshow detrás hace el fondo desconocido.
	 */
	function background(el, win) {
		var layers = [];
		for (var node = el; node && node.nodeType === 1; node = node.parentElement) {
			var style = win.getComputedStyle(node);
			if (style.backgroundImage && style.backgroundImage !== 'none') return null;
			var before = win.getComputedStyle(node, '::before');
			if (before && before.backgroundImage && before.backgroundImage !== 'none' && before.content !== 'none') return null;
			if (node.querySelector(':scope > .elementor-background-video-container, :scope > .elementor-background-slideshow')) return null;
			var color = parse(style.backgroundColor);
			if (color && color[3] > 0) { layers.push(color); if (color[3] >= 1) break; }
		}
		var base = [255, 255, 255];
		for (var i = layers.length - 1; i >= 0; i--) {
			var layer = layers[i];
			base = [0, 1, 2].map(function (c) { return layer[c] * layer[3] + base[c] * (1 - layer[3]); });
		}
		return 'rgb(' + base.map(Math.round).join(', ') + ')';
	}

	function opacity(el, win) {
		var value = 1;
		for (var node = el; node && node.nodeType === 1; node = node.parentElement) value *= parseFloat(win.getComputedStyle(node).opacity) || 0;
		return Math.round(value * 100) / 100;
	}

	function texts(doc, win) {
		var walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, null);
		var seen = new Set();
		var combos = {};
		var out = [];
		var node;
		while ((node = walker.nextNode()) && out.length < 600) {
			if (!/\S/.test(node.nodeValue)) continue;
			var el = node.parentElement;
			if (!el || seen.has(el)) continue;
			seen.add(el);
			if (ours(el) || /^(SCRIPT|STYLE|NOSCRIPT|TEMPLATE|OPTION)$/.test(el.tagName) || el.closest('svg, [aria-hidden="true"]')) continue;
			if (!visible(el, win) || clipped(el, win)) continue;
			var style = win.getComputedStyle(el);
			var size = parseFloat(style.fontSize);
			if (!size || size < 6) continue;
			var bg = background(el, win);
			var alpha = opacity(el, win);
			var key = style.color + '|' + bg + '|' + size + '|' + style.fontWeight + '|' + alpha;
			combos[key] = (combos[key] || 0) + 1;
			if (combos[key] > 3) continue;
			out.push({ text: clean(node.nodeValue, 80), fg: style.color, bg: bg, opacity: alpha, size: size, weight: parseInt(style.fontWeight, 10) || 400, selector: selector(el), widget: widget(el) });
		}
		return out;
	}

	/** Widgets con hoja CSS propia: ¿están en la página y en el primer viewport? */
	function fold(doc, win) {
		var out = {};
		(config.widgets || []).forEach(function (name) {
			var present = false;
			var above = false;
			var nodes = doc.querySelectorAll('.elementor-widget-' + (win.CSS && win.CSS.escape ? win.CSS.escape(name) : name));
			Array.prototype.forEach.call(nodes, function (n) {
				if (!n.getClientRects().length) return;
				present = true;
				if (n.getBoundingClientRect().top + win.scrollY < win.innerHeight) above = true;
			});
			out[name] = { present: present, above: above, count: nodes.length };
		});
		return out;
	}

	function collect(win, full) {
		var doc = win.document;
		var view = {
			viewport: { w: win.innerWidth, h: win.innerHeight, dpr: win.devicePixelRatio || 1 },
			headings: headings(doc, win), images: images(doc, win, full), targets: targets(doc, win), css: fold(doc, win)
		};
		if (full) { view.links = links(doc, win); view.texts = texts(doc, win); }
		return view;
	}

	/** Recorre la página para que carguen las imágenes lazy y terminen las animaciones de entrada. */
	function sweep(win) {
		return new Promise(function (resolve) {
			var doc = win.document;
			var step = Math.max(200, win.innerHeight * 0.8);
			var y = 0;
			var count = 0;
			function go(top) { try { win.scrollTo({ top: top, behavior: 'instant' }); } catch (e) { win.scrollTo(0, top); } }
			(function next() {
				var max = Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0) - win.innerHeight;
				if (y >= max || count++ > 40) { go(0); setTimeout(resolve, 800); return; }
				y += step;
				go(y);
				setTimeout(next, 160);
			})();
		});
	}

	/** Vista móvil: la misma URL en un iframe de 390 px, del mismo origen. */
	function mobile() {
		return new Promise(function (resolve) {
			var frame = document.createElement('iframe');
			var done = false;
			function finish(result) { if (done) return; done = true; clearTimeout(timer); resolve(result); }
			var timer = setTimeout(function () { finish({ error: 'La vista móvil no terminó de cargar a tiempo.' }); }, 30000);
			frame.id = 'digitalisimo-quality-frame';
			frame.title = 'Vista móvil de la auditoría';
			frame.setAttribute('aria-hidden', 'true');
			frame.style.cssText = 'position:fixed;top:44px;right:16px;width:390px;height:844px;border:2px solid #2271b1;background:#fff;z-index:2147483646;box-shadow:0 8px 32px rgba(0,0,0,.35)';
			frame.addEventListener('load', function () {
				var win;
				try {
					win = frame.contentWindow;
					if (!win.document || !win.document.body || win.location.href === 'about:blank') throw new Error('blank');
				} catch (e) { finish({ error: 'El sitio impide mostrarse dentro de un iframe (X-Frame-Options o CSP): la vista móvil no se pudo medir.' }); return; }
				wait(1200).then(function () { return sweep(win); }).then(function () { finish(collect(win, false)); }).catch(function (e) { finish({ error: 'Error en la vista móvil: ' + clean(e && e.message, 120) }); });
			});
			frame.src = config.frame;
			document.body.appendChild(frame);
		});
	}

	function send(payload) {
		var body = new FormData();
		body.append('action', config.action);
		body.append('token', config.token);
		body.append('payload', JSON.stringify(payload));
		return fetch(config.endpoint, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () { return null; });
	}

	function run() {
		var payload = { version: 1 };
		var finished = false;
		function finish() {
			if (finished) return;
			finished = true;
			banner('Guardando resultados…');
			send(payload).then(function () { location.replace(config.complete); });
		}
		setTimeout(function () { payload.error = 'La auditoría superó el tiempo máximo: se guarda lo medido.'; finish(); }, 90000);
		banner('Auditando esta página en escritorio… no cierres la pestaña.');
		wait(1000).then(function () { return sweep(window); }).then(function () {
			payload.desktop = collect(window, true);
			banner('Auditando la vista móvil (390 px)…');
			return mobile();
		}).then(function (result) { payload.mobile = result; }).catch(function (e) { payload.error = clean(e && e.message, 200); }).then(finish);
	}

	if (document.readyState === 'complete') run(); else window.addEventListener('load', run);
})();
