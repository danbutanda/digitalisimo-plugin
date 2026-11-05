/**
 * DIGITALÍSIMO Elements sin rastro del proveedor del núcleo incluido (administración, editor y App).
 * Lo carga digitalisimo-vendor-free.php sólo con el núcleo incluido. Sin enlaces, iframes, vídeos ni ventanas
 * hacia los dominios del proveedor: se desactivan sin quitar nodos (las interfaces en React los esperan), los
 * accesos de cuenta, ayuda, novedades y Angie del editor se ocultan con la API de sus menús, la biblioteca deja
 * sólo «Mis plantillas», la ruta de kits del proveedor en la App lleva a importar kits en ZIP y la de su asistente de
 * configuración, al Inicio rápido propio. Las filas del plugin Elementor original instalado no se tocan (regla del
 * proyecto).
 */
( function () {
	'use strict';
	var config = window.digitalisimoVendorFree || {};
	var hosts = config.hosts || [];
	var videos = /XHOmBV4js_E|235215203|x6tqhqb|ZCAOzTNk/;

	function vendor( url ) {
		if ( ! url || typeof url !== 'string' ) {
			return false;
		}
		// Marca con la que los bundles derivados de PRO sustituyen las direcciones del proveedor.
		if ( url.indexOf( '#digitalisimo-vendor-off' ) !== -1 ) {
			return true;
		}
		var host = '';
		try {
			host = new URL( url, window.location.href ).hostname.toLowerCase();
		} catch ( e ) {
			return false;
		}
		// El propio sitio nunca es del proveedor, aunque esté alojado en uno de sus subdominios.
		if ( host === window.location.hostname.toLowerCase() ) {
			return false;
		}
		for ( var i = 0; i < hosts.length; i++ ) {
			if ( host === hosts[ i ] || host.slice( -hosts[ i ].length - 1 ) === '.' + hosts[ i ] ) {
				return true;
			}
		}
		return false;
	}

	/** Filas y ventanas del plugin original instalado: se muestran tal cual. */
	function original( node ) {
		return !! ( node.closest && node.closest( 'tr[data-plugin^="elementor/"], tr[data-plugin^="elementor-pro/"], #TB_window, .plugin-card-elementor' ) );
	}

	function disarm( node ) {
		if ( ! node || node.nodeType !== 1 || original( node ) ) {
			return;
		}
		var attrs = [ 'href', 'src', 'action', 'poster', 'data-src', 'formaction' ];
		var touched = false;
		for ( var i = 0; i < attrs.length; i++ ) {
			var value = node.getAttribute( attrs[ i ] );
			if ( value && vendor( value ) ) {
				node.removeAttribute( attrs[ i ] );
				touched = true;
			}
		}
		if ( touched ) {
			node.setAttribute( 'hidden', '' );
			node.setAttribute( 'aria-hidden', 'true' );
			node.setAttribute( 'data-digitalisimo-vendor', 'off' );
			if ( node.style ) {
				node.style.setProperty( 'display', 'none', 'important' );
			}
		}
	}

	function sweep( root ) {
		if ( ! root || ! root.querySelectorAll ) {
			return;
		}
		disarm( root );
		var nodes = root.querySelectorAll( 'a[href], iframe[src], video[src], source[src], img[src], embed[src], form[action], link[href], [data-src]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			disarm( nodes[ i ] );
		}
	}

	// Ninguna ventana nueva hacia el proveedor (algunos accesos abren la URL desde un clic en JavaScript).
	var open = window.open;
	window.open = function ( url ) {
		if ( vendor( url ) ) {
			return null;
		}
		return open.apply( window, arguments );
	};

	function observe() {
		sweep( document.documentElement );
		new MutationObserver( function ( list ) {
			for ( var i = 0; i < list.length; i++ ) {
				var m = list[ i ];
				if ( m.type === 'attributes' ) {
					disarm( m.target );
				} else {
					for ( var j = 0; j < m.addedNodes.length; j++ ) {
						sweep( m.addedNodes[ j ] );
					}
				}
			}
		} ).observe( document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'href', 'src', 'action', 'poster', 'data-src' ] } );
	}

	/** Editor: accesos de cuenta, ayuda, novedades, comentarios y Angie fuera de los menús de la barra superior. */
	function appBar() {
		var bar = window.elementorV2 && window.elementorV2.editorAppBar;
		if ( ! bar ) {
			return false;
		}
		var hidden = function () {
			return { visible: false, title: '', icon: function () {
				return null;
			} };
		};
		var items = [
			[ 'mainMenu', 'registerLink', { id: 'app-bar-connect', group: 'exits', priority: 10 } ],
			[ 'mainMenu', 'registerLink', { id: 'open-help-center', group: 'help', priority: 10 } ],
			[ 'mainMenu', 'registerAction', { id: 'open-send-feedback', group: 'help', priority: 20 } ],
			[ 'toolsMenu', 'registerToggleAction', { id: 'toggle-angie', priority: 2 } ],
			[ 'utilitiesMenu', 'registerLink', { id: 'app-bar-menu-item-whats-new', priority: 10 } ],
		];
		for ( var i = 0; i < items.length; i++ ) {
			var menu = bar[ items[ i ][ 0 ] ];
			if ( menu && typeof menu[ items[ i ][ 1 ] ] === 'function' ) {
				var args = items[ i ][ 2 ];
				args.overwrite = true;
				args.useProps = hidden;
				try {
					menu[ items[ i ][ 1 ] ]( args );
				} catch ( e ) {}
			}
		}
		return true;
	}

	/** Biblioteca: sólo «Mis plantillas» (las pestañas de bloques y páginas son del catálogo remoto del proveedor). */
	function library() {
		var component = window.$e && window.$e.components && window.$e.components.get( 'library' );
		if ( ! component ) {
			return false;
		}
		var remote = [ 'templates/blocks', 'templates/pages' ];
		if ( ! component.digitalisimoVendorFree ) {
			component.digitalisimoVendorFree = true;
			var addTab = component.addTab;
			component.addTab = function ( tab ) {
				if ( remote.indexOf( tab ) !== -1 ) {
					return;
				}
				return addTab.apply( this, arguments );
			};
		}
		for ( var i = 0; i < remote.length; i++ ) {
			if ( component.tabs && component.tabs[ remote[ i ] ] ) {
				component.removeTab( remote[ i ] );
			}
		}
		return true;
	}

	/** Controles con vídeos o URL del proveedor como valor por defecto o ejemplo: vacíos. */
	function controls() {
		var caches = [];
		if ( window.elementor && window.elementor.widgetsCache ) {
			caches.push( window.elementor.widgetsCache );
		}
		if ( window.elementor && window.elementor.config && window.elementor.config.elements ) {
			caches.push( window.elementor.config.elements );
		}
		if ( ! caches.length ) {
			return false;
		}
		for ( var c = 0; c < caches.length; c++ ) {
			for ( var name in caches[ c ] ) {
				var widget = caches[ c ][ name ];
				if ( ! widget || typeof widget !== 'object' ) {
					continue;
				}
				if ( widget.help_url ) {
					widget.help_url = '';
				}
				var list = widget.controls || {};
				for ( var key in list ) {
					var control = list[ key ];
					if ( ! control || typeof control !== 'object' ) {
						continue;
					}
					[ 'default', 'placeholder' ].forEach( function ( prop ) {
						if ( typeof control[ prop ] === 'string' && ( videos.test( control[ prop ] ) || vendor( control[ prop ] ) ) ) {
							control[ prop ] = '';
						}
					} );
				}
			}
		}
		return true;
	}

	function retry( task, tries ) {
		if ( task() || tries <= 0 ) {
			return;
		}
		setTimeout( function () {
			retry( task, tries - 1 );
		}, 250 );
	}

	/**
	 * Kits en la App: un solo importador, el 3.0 («#/import-customization»). La biblioteca de kits del proveedor y el
	 * importador y el exportador heredados (2.0, que instalan plugins y cambian el tema) llevan a él, sin su consulta.
	 * Las expresiones no capturan «#/import-customization/…» ni «#/export-customization/…». Devuelve '' si se queda.
	 */
	function kitRoute( hash ) {
		if ( /^#\/kit-library/.test( hash ) || /^#\/import(?:[\/?]|$)/.test( hash ) ) {
			return '#/import-customization';
		}
		return /^#\/export(?:[\/?]|$)/.test( hash ) ? '#/export-customization' : '';
	}

	/**
	 * App: el asistente de configuración del núcleo (cuenta, planes y tema del proveedor; el núcleo redirige a
	 * «#onboarding» tras activarse) lleva al Inicio rápido propio, y los kits, al importador 3.0 (kitRoute).
	 */
	function app() {
		var guard = function () {
			var hash = window.location.hash;
			var target = kitRoute( hash );
			if ( /^#\/?onboarding\b/.test( hash ) && config.home ) {
				window.location.replace( config.home );
			} else if ( target ) {
				window.location.replace( target );
			}
		};
		guard();
		window.addEventListener( 'hashchange', guard );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', observe );
	} else {
		observe();
	}
	if ( config.context === 'editor' ) {
		retry( appBar, 40 );
		retry( controls, 80 );
		retry( library, 80 );
		if ( window.jQuery ) {
			window.jQuery( window ).on( 'elementor:init', function () {
				retry( controls, 40 );
				retry( library, 40 );
			} );
		}
	}
	if ( config.context === 'app' ) {
		app();
	}
}() );
