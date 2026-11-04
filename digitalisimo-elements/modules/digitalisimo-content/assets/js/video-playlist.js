/* Lista de videos (digitalisimo-video-playlist): pestañas, fachadas y reproductor. Sin dependencias. Deriva del
   comportamiento de modules/video-playlist/assets/js/frontend/ de PRO Elements (GPL). */
(() => {
	const W = window;
	const D = document;
	const YT = /^https:\/\/www\.youtube(-nocookie)?\.com$/;
	const VM = /^https:\/\/player\.vimeo\.com$/;
	const players = new Set();
	const data = (e) => { try { return typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch (x) { return null; } };
	const send = (frame, msg) => { try { frame.contentWindow.postMessage(JSON.stringify(msg), '*'); } catch (x) {} };
	const store = (key, value) => { try { if (value === undefined) return JSON.parse(W.localStorage.getItem(key) || '[]'); W.localStorage.setItem(key, JSON.stringify(value)); } catch (x) {} return []; };

	// Fin de un vídeo de un servicio: YouTube y Vimeo lo avisan por postMessage al iframe que lo reproduce.
	W.addEventListener('message', (e) => {
		const d = data(e);
		if (!d || !(YT.test(e.origin) || VM.test(e.origin))) return;
		const ended = d.event === 'ended' || (d.event === 'onStateChange' && d.info === 0) || (d.event === 'infoDelivery' && d.info && d.info.playerState === 0);
		if (!ended) return;
		players.forEach((p) => { if (p.frame && p.frame.contentWindow === e.source) p.ended(); });
	});

	function init(root) {
		if (!root || root.digiVpl) return;
		const id = root.getAttribute('data-digi-vpl') || '';
		const key = 'digi-vpl-watched-' + id;
		const tabs = [...root.querySelectorAll('.digi-vpl__tab')];
		const list = root.querySelector('.digi-vpl__list');
		const fold = root.querySelector('.digi-vpl__fold');
		const reduce = W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches;
		const panel = (tab) => D.getElementById(tab.getAttribute('aria-controls'));
		const inner = (tab) => root.querySelector('.digi-vpl__in[data-tab="' + tab.getAttribute('data-tab') + '"]');
		let current = tabs.find((t) => t.classList.contains('digi-vpl__tab--on')) || tabs[0];
		const emit = (type, tab) => root.dispatchEvent(new CustomEvent('digi-video-playlist', { bubbles: true, detail: { type, index: tabs.indexOf(tab) + 1, item: tab.getAttribute('data-item') } }));

		// Reproductor de una pestaña: el <video> alojado o el iframe del servicio, que sólo se crea al reproducir.
		const player = (tab) => {
			const p = panel(tab);
			if (!p) return null;
			if (p.digiPlayer) return p.digiPlayer;
			const video = p.querySelector('video');
			const o = {
				tab,
				frame: null,
				ended() {
					emit('videoEnded', tab);
					if (root.hasAttribute('data-watched')) {
						tab.classList.add('digi-vpl__tab--seen');
						const seen = store(key);
						if (seen.indexOf(tab.getAttribute('data-item')) < 0) store(key, seen.concat(tab.getAttribute('data-item')));
					}
					const next = tabs[tabs.indexOf(tab) + 1];
					if (root.hasAttribute('data-next') && next && tab === current) select(next, true, true);
				},
				play(muted) {
					const cover = p.querySelector('.digi-vpl__overlay');
					if (cover) cover.remove();
					if (video) { video.muted = !!muted; const r = video.play(); if (r && r.catch) r.catch(() => {}); return; }
					const src = p.getAttribute('data-src');
					if (!src) return;
					if (o.frame) { send(o.frame, YT.test(new URL(src).origin) ? { event: 'command', func: 'playVideo', args: '' } : { method: 'play' }); return; }
					const yt = YT.test(new URL(src).origin);
					const f = D.createElement('iframe');
					f.src = src + '&autoplay=1' + (yt ? '&origin=' + encodeURIComponent(location.origin) : '');
					f.title = p.getAttribute('data-title') || '';
					f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
					f.allowFullscreen = true;
					f.addEventListener('load', () => send(f, yt ? { event: 'listening', id: 1, channel: 'widget' } : { method: 'addEventListener', value: 'ended' }));
					const box = p.querySelector('.digi-vpl__player');
					box.replaceChildren(f);
					o.frame = f;
					f.focus();
				},
				pause() {
					if (video) video.pause();
					else if (o.frame) send(o.frame, YT.test(new URL(o.frame.src).origin) ? { event: 'command', func: 'pauseVideo', args: '' } : { method: 'pause' });
				},
			};
			if (video) video.addEventListener('ended', () => o.ended());
			players.add(o);
			p.digiPlayer = o;
			return o;
		};

		// Elige una pestaña: la marca, muestra su panel y sus pestañas internas, para la anterior y, si se pide, reproduce.
		function select(tab, play, auto) {
			if (!tab) return;
			if (tab !== current) {
				const old = player(current);
				if (old) old.pause();
				for (const t of tabs) {
					const on = t === tab;
					t.classList.toggle('digi-vpl__tab--on', on);
					t.setAttribute('aria-selected', on ? 'true' : 'false');
					t.tabIndex = on ? 0 : -1;
					const p = panel(t);
					if (p) p.hidden = !on;
					const i = inner(t);
					if (i) i.hidden = !on;
				}
				current = tab;
				more(inner(tab));
			}
			if (play) {
				const p = player(tab);
				if (p) { p.play(); emit(auto ? 'videoNext' : 'videoStart', tab); }
				params(tab);
			}
		}

		// ?playlist=<id del widget>&video=<id de la fila>, como PRO.
		function params(tab) {
			try {
				const q = new URLSearchParams(location.search);
				q.set('playlist', id);
				q.set('video', tab.getAttribute('data-item'));
				history.replaceState(history.state, '', location.pathname + '?' + q + location.hash);
			} catch (x) {}
		}

		// «Mostrar más» de las pestañas internas plegables: sólo si el texto no cabe.
		function more(block) {
			if (!block) return;
			block.querySelectorAll('.digi-vpl__in-panel--fold:not([hidden])').forEach((p) => {
				const b = p.querySelector('.digi-vpl__more');
				const t = p.querySelector('.digi-vpl__in-text');
				if (b && t) b.hidden = !p.classList.contains('digi-vpl__in-panel--open') && t.scrollHeight <= t.clientHeight + 1;
			});
		}

		const move = (list, from, key) => {
			const i = list.indexOf(from);
			const rtl = getComputedStyle(root).direction === 'rtl';
			const step = { ArrowDown: 1, ArrowUp: -1, ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[key];
			if (key === 'Home') return list[0];
			if (key === 'End') return list[list.length - 1];
			return step ? list[(i + step + list.length) % list.length] : null;
		};

		tabs.forEach((tab) => {
			tab.addEventListener('click', () => select(tab, true));
			tab.addEventListener('keydown', (e) => {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(tab, true); return; }
				const to = move(tabs, tab, e.key);
				if (to) { e.preventDefault(); to.focus(); }
			});
		});
		root.querySelectorAll('.digi-vpl__play').forEach((b) => b.addEventListener('click', () => { const t = tabs.find((x) => panel(x) && panel(x).contains(b)); if (t) select(t, true); }));

		// Pestañas internas (activación automática) y «Mostrar más».
		root.querySelectorAll('.digi-vpl__in-list').forEach((l) => {
			const own = [...l.querySelectorAll('[role="tab"]')];
			const pick = (t) => {
				own.forEach((x) => {
					const on = x === t;
					x.classList.toggle('digi-vpl__in-tab--on', on);
					x.setAttribute('aria-selected', on ? 'true' : 'false');
					x.tabIndex = on ? 0 : -1;
					const p = D.getElementById(x.getAttribute('aria-controls'));
					if (p) p.hidden = !on;
				});
				more(l.parentElement);
				emit('tabOpened', current);
			};
			own.forEach((t) => {
				t.addEventListener('click', () => pick(t));
				t.addEventListener('keydown', (e) => { const to = move(own, t, e.key); if (to) { e.preventDefault(); to.focus(); pick(to); } });
			});
		});
		root.querySelectorAll('.digi-vpl__more').forEach((b) => b.addEventListener('click', () => {
			const p = b.closest('.digi-vpl__in-panel');
			const open = p.classList.toggle('digi-vpl__in-panel--open');
			b.setAttribute('aria-expanded', open ? 'true' : 'false');
			b.textContent = b.getAttribute(open ? 'data-less' : 'data-more');
			emit(open ? 'tabExpanded' : 'tabCollapsed', current);
		}));

		// Lista plegable en el móvil.
		if (fold && list) fold.addEventListener('click', () => {
			const open = fold.getAttribute('aria-expanded') !== 'true';
			fold.setAttribute('aria-expanded', open ? 'true' : 'false');
			root.classList.toggle('digi-vpl--closed', !open);
		});

		// Vídeos vistos de visitas anteriores.
		if (root.hasAttribute('data-watched')) {
			const seen = store(key);
			tabs.forEach((t) => { if (seen.indexOf(t.getAttribute('data-item')) > -1) t.classList.add('digi-vpl__tab--seen'); });
		}

		// Vídeo de la dirección (?playlist=<id>&video=<fila>): se elige sin reproducir y se desplaza hasta la lista.
		const q = new URLSearchParams(location.search);
		const editing = W.elementorFrontend && W.elementorFrontend.isEditMode && W.elementorFrontend.isEditMode();
		if (!editing && q.get('playlist') === id) {
			select(tabs.find((t) => t.getAttribute('data-item') === q.get('video')) || tabs[0], false);
			root.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' });
		}
		more(current && inner(current));

		// Al cargar: sólo un vídeo alojado, silenciado, cuando la lista se ve (nunca un servicio antes del clic).
		const p = current && panel(current);
		if (!editing && root.hasAttribute('data-onload') && p && p.querySelector('video') && 'IntersectionObserver' in W) {
			const io = new IntersectionObserver((en) => {
				if (!en.some((x) => x.isIntersecting)) return;
				io.disconnect();
				player(current).play(true);
				params(current);
				emit('videoStart', current);
			});
			io.observe(root);
		}
		root.digiVpl = { select: (n, play) => select(tabs[n - 1], !!play) };
	}

	const scan = (scope) => (scope || D).querySelectorAll('[data-digi-vpl]').forEach(init);
	if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', () => scan()); else scan();
	const hook = () => W.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-video-playlist.default', (s) => scan(s && s[0]));
	if (W.elementorFrontend && W.elementorFrontend.hooks) hook(); else W.addEventListener('elementor/frontend/init', hook);
})();
