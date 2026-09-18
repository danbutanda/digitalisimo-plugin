/**
 * Campo de archivo de Digitalisimo.
 *
 * Selector de archivo: biblioteca de medios y subida por arrastre. El valor
 * vive en un input oculto que sigue siendo la única fuente de verdad, de modo
 * que el formulario se guarda igual que antes y un campo heredado de la red
 * (input deshabilitado) queda inerte.
 */
( function () {
	'use strict';

	var cfg = window.digitalisimoMediaField || {};

	function text( key, fallback ) {
		return ( cfg.i18n && cfg.i18n[ key ] ) || fallback;
	}

	function isImage( url ) {
		return /\.(jpe?g|png|gif|webp|avif|svg|ico|bmp)(\?|#|$)/i.test( url || '' );
	}

	function setup( wrap ) {
		var input = wrap.querySelector( '.digitalisimo-media__input' );
		if ( ! input || wrap.dataset.ready === '1' ) return;
		wrap.dataset.ready = '1';

		var drop    = wrap.querySelector( '.digitalisimo-media__drop' );
		var preview = wrap.querySelector( '.digitalisimo-media__preview' );
		var status  = wrap.querySelector( '.digitalisimo-media__status' );
		var pick    = wrap.querySelector( '.digitalisimo-media__pick' );
		var clear   = wrap.querySelector( '.digitalisimo-media__clear' );
		var frame;

		function locked() {
			return input.disabled;
		}

		function say( message, isError ) {
			if ( ! status ) return;
			status.textContent = message || '';
			status.classList.toggle( 'is-error', !! isError );
		}

		function render() {
			var url = input.value.trim();
			wrap.classList.toggle( 'is-locked', input.disabled );
			if ( preview ) {
				// Sólo se previsualiza lo que el navegador sabe dibujar; el resto
				// se anuncia por nombre para no mostrar un icono roto.
				if ( url && isImage( url ) ) {
					preview.src = url;
					preview.hidden = false;
				} else {
					preview.removeAttribute( 'src' );
					preview.hidden = true;
				}
			}
			if ( clear ) clear.hidden = ! url;
			wrap.classList.toggle( 'has-file', !! url );
			// El input está oculto: sin el nombre no habría forma de saber qué hay elegido.
			say( url ? decodeURIComponent( url.split( '/' ).pop().split( '?' )[ 0 ] ) : '' );
		}

		function apply( url ) {
			input.value = url;
			render();
			// Otros scripts (y la validación del navegador) esperan el evento.
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		if ( pick ) {
			pick.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				if ( locked() || ! window.wp || ! window.wp.media ) return;
				if ( ! frame ) {
					frame = window.wp.media( {
						title: text( 'choose', 'Elegir archivo' ),
						button: { text: text( 'use', 'Usar este archivo' ) },
						multiple: false
					} );
					frame.on( 'select', function () {
						var item = frame.state().get( 'selection' ).first();
						if ( item ) apply( item.toJSON().url );
					} );
				}
				frame.open();
			} );
		}

		if ( clear ) {
			clear.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				if ( locked() ) return;
				apply( '' );
			} );
		}

		input.addEventListener( 'change', render );
		input.addEventListener( 'input', render );

		if ( drop ) {
			[ 'dragenter', 'dragover' ].forEach( function ( name ) {
				drop.addEventListener( name, function ( event ) {
					event.preventDefault();
					if ( locked() ) return;
					wrap.classList.add( 'is-dragging' );
				} );
			} );
			[ 'dragleave', 'dragend', 'drop' ].forEach( function ( name ) {
				drop.addEventListener( name, function () {
					wrap.classList.remove( 'is-dragging' );
				} );
			} );
			drop.addEventListener( 'drop', function ( event ) {
				event.preventDefault();
				if ( locked() ) return;
				var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[ 0 ];
				if ( file ) upload( file );
			} );
		}

		function upload( file ) {
			if ( ! cfg.uploadUrl || ! cfg.uploadNonce ) {
				say( text( 'noUpload', 'Este sitio no permite subir archivos desde aquí.' ), true );
				return;
			}
			wrap.classList.add( 'is-busy' );
			say( text( 'uploading', 'Subiendo…' ) );

			var data = new FormData();
			data.append( 'action', 'upload-attachment' );
			data.append( 'async-upload', file );
			data.append( 'name', file.name );
			data.append( '_wpnonce', cfg.uploadNonce );

			window.fetch( cfg.uploadUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					wrap.classList.remove( 'is-busy' );
					if ( result && result.success && result.data && result.data.url ) {
						apply( result.data.url );
						return;
					}
					// WordPress devuelve el motivo real (tipo no permitido, tamaño, permisos).
					var reason = result && result.data && ( result.data.message || result.data.error );
					say( reason || text( 'failed', 'No se pudo subir el archivo.' ), true );
				} )
				.catch( function () {
					wrap.classList.remove( 'is-busy' );
					say( text( 'failed', 'No se pudo subir el archivo.' ), true );
				} );
		}

		// «Heredar de la red» deshabilita el input desde otro script: hay que
		// reflejarlo sin acoplarse a ese checkbox.
		if ( window.MutationObserver ) {
			new window.MutationObserver( render ).observe( input, { attributes: true, attributeFilter: [ 'disabled' ] } );
		}

		render();
	}

	/**
	 * «Heredar de la red» para un grupo de campos. El script de los ajustes sólo
	 * sabe deshabilitar un id; los perfiles sociales son diez inputs.
	 */
	function inheritGroups() {
		document.querySelectorAll( '.digitalisimo-network-inherit[data-group]' ).forEach( function ( toggle ) {
			var group = document.querySelector( toggle.dataset.group );
			if ( ! group ) return;
			function sync() {
				group.querySelectorAll( 'input, select, textarea' ).forEach( function ( field ) {
					field.disabled = toggle.checked;
				} );
			}
			toggle.addEventListener( 'change', sync );
			sync();
		} );
	}

	/** Horario semanal: estado por día, horario partido y copia entre días. */
	function schedule() {
		document.querySelectorAll( '.digitalisimo-hours' ).forEach( function ( box ) {
			if ( box.dataset.ready === '1' ) return;
			box.dataset.ready = '1';

			function row( day ) {
				return box.querySelector( '.digitalisimo-hours__day[data-day="' + day + '"]' );
			}

			// Un día cerrado o de 24 horas no necesita mostrar franjas.
			function sync( day ) {
				var el = row( day );
				if ( ! el ) return;
				var state = el.querySelector( '.digitalisimo-hours__state' );
				var open = state && state.value === 'open';
				el.classList.toggle( 'is-open', open );
				el.querySelectorAll( '.digitalisimo-hours__range' ).forEach( function ( range ) {
					if ( ! range.classList.contains( 'digitalisimo-hours__range--second' ) ) range.hidden = ! open;
				} );
				var split = el.querySelector( '.digitalisimo-hours__split' );
				var second = el.querySelector( '.digitalisimo-hours__range--second' );
				if ( split ) split.hidden = ! open;
				if ( second && ! open ) second.hidden = true;
			}

			box.querySelectorAll( '.digitalisimo-hours__day' ).forEach( function ( el ) {
				var day = el.dataset.day;
				var state = el.querySelector( '.digitalisimo-hours__state' );
				if ( state ) state.addEventListener( 'change', function () { sync( day ); } );

				var split = el.querySelector( '.digitalisimo-hours__split' );
				var second = el.querySelector( '.digitalisimo-hours__range--second' );
				if ( split && second ) {
					split.addEventListener( 'click', function ( event ) {
						event.preventDefault();
						second.hidden = ! second.hidden;
						split.setAttribute( 'aria-pressed', second.hidden ? 'false' : 'true' );
						// Un tramo oculto no debe viajar: se vacía al plegarlo.
						if ( second.hidden ) second.querySelectorAll( 'select' ).forEach( function ( sel ) { sel.selectedIndex = 0; } );
					} );
				}
				sync( day );
			} );

			box.querySelectorAll( '.digitalisimo-hours__copy' ).forEach( function ( button ) {
				button.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					var from = row( button.dataset.source );
					if ( ! from ) return;
					var fields = [ 'state', 'from', 'to', 'from2', 'to2' ];
					button.dataset.targets.split( ',' ).forEach( function ( day ) {
						var to = row( day );
						if ( ! to ) return;
						fields.forEach( function ( field ) {
							var a = from.querySelector( '.digitalisimo-hours__' + field );
							var b = to.querySelector( '.digitalisimo-hours__' + field );
							if ( a && b ) b.value = a.value;
						} );
						var source2 = from.querySelector( '.digitalisimo-hours__range--second' );
						var target2 = to.querySelector( '.digitalisimo-hours__range--second' );
						if ( source2 && target2 ) target2.hidden = source2.hidden;
						sync( day );
					} );
				} );
			} );
		} );
	}

	/**
	 * Pestañas en cliente. Todos los paneles están en el mismo formulario, así que
	 * cambiar de pestaña no recarga y lo escrito no se pierde. Además deja la
	 * pestaña abierta en la URL y en el referer, para volver a ella al guardar.
	 */
	function tabs() {
		var nav = document.querySelector( '.digitalisimo-tabs' );
		var panels = document.querySelectorAll( '.digitalisimo-panel' );
		if ( ! nav || ! panels.length ) return;
		var links = nav.querySelectorAll( '[data-tab]' );

		function open( slug ) {
			var found = false;
			panels.forEach( function ( panel ) {
				var match = panel.dataset.tab === slug;
				panel.hidden = ! match;
				if ( match ) found = true;
			} );
			if ( ! found ) return false;
			links.forEach( function ( link ) { link.classList.toggle( 'nav-tab-active', link.dataset.tab === slug ); } );

			var active = document.querySelector( '.digitalisimo-active-tab' );
			if ( active ) active.value = slug;

			try {
				var url = new URL( window.location.href );
				url.searchParams.set( 'tab', slug );
				url.searchParams.delete( 'settings-updated' );
				url.searchParams.delete( 'updated' );
				window.history.replaceState( {}, '', url );
				// options.php devuelve al referer enviado: sin esto se vuelve a la primera pestaña.
				var referer = document.querySelector( 'input[name="_wp_http_referer"]' );
				if ( referer ) referer.value = url.pathname + url.search;
			} catch ( error ) {
				// Una URL que el navegador no sabe parsear no debe romper la navegación.
			}
			return true;
		}

		links.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				// Sin JS el enlace sigue funcionando como recarga normal.
				if ( open( link.dataset.tab ) ) event.preventDefault();
			} );
		} );
	}

	function boot() {
		document.querySelectorAll( '.digitalisimo-media' ).forEach( setup );
		inheritGroups();
		schedule();
		tabs();
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )();
