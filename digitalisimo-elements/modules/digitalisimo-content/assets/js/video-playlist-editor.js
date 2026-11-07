/* DIGITALÍSIMO Elements · Lista de videos en el editor (C2). Sólo se encola en el editor (Module::editor_scripts, con
   `wp-api-fetch`). «Obtener datos del vídeo» (`vimeo_fetch_data`), como «Get Video Data» de PRO: rellena el título, la
   miniatura y la duración de la fila con los datos del servicio, pedidos sólo cuando el administrador pulsa el botón.
   Vimeo, por el proxy oEmbed de WordPress (título, miniatura y duración); YouTube, por el mismo proxy (título), con la
   miniatura que guardaba PRO y la duración de su reproductor sin cookies, que oEmbed no da. Deriva de PRO Elements (GPL):
   `modules/video-playlist/assets/js/frontend/player-*.js` y `editor/module.js`. */
((w) => {
	// Duración como PRO (`formatDuration`): «m:ss» o «h:mm:ss».
	const fmt = (sec) => {
		const s = Math.round(+sec || 0), h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = String(s % 60).padStart(2, '0');
		return h ? h + ':' + String(m).padStart(2, '0') + ':' + x : m + ':' + x;
	};
	const ytId = (url) => (String(url).match(/(?:youtube(?:-nocookie)?\.com\/(?:[^/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([\w-]{11})/i) || [])[1] || '';
	// Duración de YouTube: la que anuncia su reproductor (sin cookies) en un iframe oculto, que se quita al responder.
	const ytTime = (id) => new Promise((done) => {
		const f = document.createElement('iframe');
		let timer = 0;
		const end = (v) => { w.removeEventListener('message', on); clearTimeout(timer); f.remove(); done(v); };
		const on = (e) => {
			if (e.source !== f.contentWindow) return;
			let d = e.data;
			try { d = 'string' === typeof d ? JSON.parse(d) : d; } catch (x) { return; }
			const v = d && d.info && d.info.duration;
			if (v) end(v);
		};
		timer = setTimeout(() => end(0), 8000);
		f.src = 'https://www.youtube-nocookie.com/embed/' + id + '?enablejsapi=1&origin=' + encodeURIComponent(location.origin);
		f.title = 'YouTube';
		f.tabIndex = -1;
		f.setAttribute('aria-hidden', 'true');
		f.style.cssText = 'position:fixed;left:-9999px;top:0;width:320px;height:180px;border:0';
		f.onload = () => f.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1, channel: 'widget' }), '*');
		w.addEventListener('message', on);
		document.body.appendChild(f);
	});
	// El evento del botón trae la vista del control: su contenedor es la fila del repetidor.
	const fetchData = async (view) => {
		const row = view && view.container;
		if (!row || !row.settings || !w.wp || !w.wp.apiFetch || !w.$e) return;
		const type = row.settings.get('type');
		const url = String(row.settings.get(type + '_url') || '').trim();
		if (!url || ('youtube' !== type && 'vimeo' !== type)) return;
		let o = {};
		try { o = (await w.wp.apiFetch({ path: '/oembed/1.0/proxy?url=' + encodeURIComponent(url) })) || {}; } catch (e) { o = {}; }
		const out = {};
		if (o.title) out.title = String(o.title);
		if ('youtube' === type) {
			const id = ytId(url);
			if (id) {
				out.thumbnail = { url: 'https://img.youtube.com/vi/' + id + '/maxresdefault.jpg' };
				const d = await ytTime(id);
				if (d) out.duration = fmt(d);
			}
		} else {
			if (o.thumbnail_url) out.thumbnail = { url: String(o.thumbnail_url) };
			if (o.duration) out.duration = fmt(o.duration);
		}
		if (Object.keys(out).length) w.$e.run('document/elements/settings', { container: row, settings: out, options: { external: true } });
	};
	const boot = () => {
		const e = w.elementor;
		if (e && e.channels && e.channels.editor) e.channels.editor.on('digitalisimoPlaylist:fetchVideoData', fetchData);
	};
	if (w.elementor && w.elementor.channels) boot(); else w.addEventListener('elementor/init', boot, { once: true });
})(window);
