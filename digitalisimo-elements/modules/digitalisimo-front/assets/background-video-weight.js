/**
 * Aviso de peso de los vídeos de fondo en el editor (Background_Video, modules/digitalisimo-front/class-background-video.php).
 * Al abrir el fondo de un contenedor o sección con un vídeo alojado en el sitio, pide su tamaño (HEAD, mismo origen) y,
 * si pasa del umbral del sitio, lo escribe en el control informativo `digitalisimo_background_video_weight`, con si se
 * descarga también en el celular y si falta la imagen de respaldo. Sólo avisa: no cambia ni guarda nada.
 */
(function (window, document) {
	'use strict';
	var config = window.digitalisimoBackgroundVideo || {};
	var max = Number(config.maxBytes) > 0 ? Number(config.maxBytes) : 2097152;
	var sizes = {};
	var watched = null;
	var timer = 0;

	function mb(bytes) {
		return (Math.round(bytes / 104857.6) / 10).toString().replace('.', ',') + ' MB';
	}

	/** La dirección absoluta del vídeo si es del mismo origen que el editor; si no, ''. */
	function local(link) {
		if (!link || typeof link !== 'string') {
			return '';
		}
		try {
			var url = new window.URL(link, window.location.href);
			return url.origin === window.location.origin ? url.href : '';
		} catch (e) {
			return '';
		}
	}

	function size(url) {
		if (!sizes[url]) {
			sizes[url] = window.fetch(url, { method: 'HEAD', credentials: 'same-origin' }).then(function (response) {
				var bytes = Number(response.headers.get('content-length'));
				return response.ok && bytes > 0 ? bytes : 0;
			}).catch(function () {
				return 0;
			});
		}
		return sizes[url];
	}

	/**
	 * Lo que dice el aviso, o '' si no hay nada que decir. El peso, sólo si pasa del umbral; la falta de imagen de
	 * respaldo, siempre (sin ella el celular no muestra nada y «Nunca en el celular» no quita el vídeo). Exportado para
	 * las pruebas.
	 */
	function message(settings, bytes, mode) {
		if (!settings || settings.background_background !== 'video' || !settings.background_video_link) {
			return '';
		}
		var fallback = !!(settings.background_video_fallback && settings.background_video_fallback.url);
		var mobile = mode === 'always' || (settings.background_play_on_mobile === 'yes' && !(mode === 'never' && fallback));
		var parts = [];
		if (bytes > max) {
			parts.push('Este vídeo de fondo pesa ' + mb(bytes) + ', más que el límite del sitio (' + mb(max) + '): hace lenta la página. Comprímelo (720p, 8 a 10 segundos, sin audio).');
			parts.push(mobile ? 'También se descarga en el celular: apaga «Reproducir en móvil» para que el celular muestre la imagen de respaldo.' : 'En el celular no se descarga.');
		}
		if (!fallback) {
			parts.push('Falta la «Imagen de respaldo»: es lo que se ve mientras carga el vídeo y en el celular cuando no se reproduce.');
		}
		return parts.join(' ');
	}

	function paint() {
		timer = 0;
		var boxes = document.querySelectorAll('.digitalisimo-bg-video-weight');
		if (!boxes.length || !watched) {
			return;
		}
		var settings = watched.toJSON ? watched.toJSON() : watched;
		var url = settings.background_background === 'video' ? local(settings.background_video_link) : '';
		var write = function (text) {
			Array.prototype.forEach.call(boxes, function (box) {
				box.textContent = text;
				box.className = 'digitalisimo-bg-video-weight' + (text ? ' elementor-panel-alert elementor-panel-alert-warning' : '');
			});
		};
		if (!url) {
			// Sin medir (otro origen o sin vídeo): sólo lo que no depende del peso.
			write(message(settings, 0, config.mobile));
			return;
		}
		size(url).then(function (bytes) {
			write(message(settings, bytes, config.mobile));
		});
	}

	function schedule() {
		if (!timer) {
			timer = window.setTimeout(paint, 0);
		}
	}

	function watch(panel, model) {
		var settings = model && model.get ? model.get('settings') : null;
		if (watched && watched.off) {
			watched.off(null, schedule);
		}
		watched = settings;
		if (settings && settings.on) {
			settings.on('change:background_background change:background_video_link change:background_play_on_mobile change:background_video_fallback', schedule);
		}
		schedule();
	}

	function boot() {
		var elementor = window.elementor;
		if (!elementor || !elementor.hooks || boot.done) {
			return;
		}
		boot.done = true;
		elementor.hooks.addAction('panel/open_editor/container', watch);
		elementor.hooks.addAction('panel/open_editor/section', watch);
		if (elementor.channels && elementor.channels.editor) {
			elementor.channels.editor.on('section:activated', schedule);
		}
	}

	window.digitalisimoBackgroundVideoMessage = message;
	if (window.elementor && window.elementor.hooks) {
		boot();
	} else if (window.jQuery) {
		window.jQuery(window).on('elementor:init', boot);
	}
}(window, document));
