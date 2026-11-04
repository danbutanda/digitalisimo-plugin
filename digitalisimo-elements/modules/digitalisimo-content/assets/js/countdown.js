/* DIGITALÍSIMO Elements · Cuenta regresiva: pone al día días, horas, minutos y segundos cada segundo y, al terminar, corre
   las acciones del servidor (ocultar, redirigir a http/https, mostrar el mensaje) una sola vez y emite
   `digi-countdown-expire`. El temporizador perpetuo guarda su vencimiento en el navegador con las claves de siempre. */
(() => {
	const W = window, D = document, K = 'digi-countdown', P = ['days', 'hours', 'minutes', 'seconds'];
	const store = (k, v) => { try { return v === undefined ? localStorage.getItem(k) : localStorage.setItem(k, v); } catch (e) { return null; } };
	const evergreen = (id, interval) => {
		const dk = id + '-evergreen_due_date', ik = id + '-evergreen_interval', due = store(dk), saved = store(ik);
		if (due > 0 && parseInt(saved, 10) === interval) return +due;
		const end = Date.now() + interval * 1e3;
		store(dk, end); store(ik, interval);
		return end;
	};
	const init = el => {
		if (el.digiCd) return;
		const host = el.closest('.elementor-element'), id = host ? host.getAttribute('data-id') : '', ev = +el.getAttribute('data-digi-evergreen') || 0;
		const end = ev > 0 ? evergreen(id, ev) : (+el.getAttribute('data-digi-date') || 0) * 1e3;
		const spans = P.map(p => el.querySelector('.' + K + '__' + p));
		let actions = [];
		try { actions = JSON.parse(el.getAttribute('data-digi-actions') || '[]') || []; } catch (e) {}
		const tick = () => {
			if (!el.isConnected) return clearInterval(el.digiCd);
			const t = end - Date.now();
			let v = [Math.floor(t / 864e5), Math.floor(t / 36e5 % 24), Math.floor(t / 6e4 % 60), Math.floor(t / 1e3 % 60)];
			if (v[0] < 0 || v[1] < 0 || v[2] < 0) v = [0, 0, 0, 0];
			spans.forEach((s, i) => { if (s) { const n = String(v[i]); s.textContent = n.length < 2 ? '0' + n : n; } });
			if (t > 0) return;
			clearInterval(el.digiCd);
			el.digiCd = -1;
			(host || el).dispatchEvent(new CustomEvent(K + '-expire', { bubbles: true }));
			actions.forEach(a => {
				if (a.type === 'hide') el.hidden = true;
				else if (a.type === 'message') { const m = el.parentNode && el.parentNode.querySelector('.' + K + '__message'); if (m) m.hidden = false; }
				else if (a.type === 'redirect' && /^https?:\/\//i.test(a.redirect_url || '')) W.location.href = a.redirect_url;
			});
		};
		el.digiCd = setInterval(tick, 1e3);
		tick();
	};
	const scan = s => (s && s.querySelectorAll ? s : D).querySelectorAll('.' + K).forEach(init);
	const hook = () => W.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-countdown.default', s => scan(s && s[0]));
	W.elementorFrontend && W.elementorFrontend.hooks ? hook() : W.addEventListener('elementor/frontend/init', hook);
	D.readyState === 'loading' ? D.addEventListener('DOMContentLoaded', () => scan()) : scan();
})();
