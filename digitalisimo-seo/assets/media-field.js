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

	function boot() {
		document.querySelectorAll( '.digitalisimo-media' ).forEach( setup );
		inheritGroups();
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )();
