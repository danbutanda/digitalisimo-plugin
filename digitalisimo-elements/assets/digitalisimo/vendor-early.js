/**
 * DIGITALÍSIMO Elements: guarda temprana contra las peticiones del navegador al proveedor del núcleo incluido.
 * La carga digitalisimo-vendor-free.php en la cabecera (en la App, antes que sus scripts), sólo con el núcleo incluido
 * y nunca en el frontal. Algunos bundles del núcleo pintan imágenes, vídeos o marcos con una dirección del proveedor
 * escrita en el código (promociones del editor atómico, ventanas de activación): el navegador las pide en cuanto
 * reciben su dirección, antes de que vendor-free.js las desactive. Aquí esa dirección nunca se asigna: el elemento
 * queda sin ella y marcado `data-digitalisimo-vendor="off"`, que identity.css oculta. Las del propio sitio pasan
 * siempre, aunque esté alojado en un subdominio del proveedor.
 */
( function () {
	'use strict';
	var hosts = ( window.digitalisimoVendorFree && window.digitalisimoVendorFree.hosts ) || [];
	var media = /^(IMG|SOURCE|IFRAME|VIDEO|AUDIO|EMBED|TRACK)$/;
	var attributes = /^(src|srcset|poster)$/i;
	if ( ! hosts.length || ! window.Element || ! window.URL ) {
		return;
	}

	function vendor( url ) {
		url = String( url );
		if ( url.indexOf( '//' ) === -1 ) {
			return false;
		}
		var host = '';
		try {
			host = new URL( url.trim().split( /\s+/ )[ 0 ], window.location.href ).hostname.toLowerCase();
		} catch ( e ) {
			return false;
		}
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

	var setAttribute = Element.prototype.setAttribute;
	function off( node ) {
		setAttribute.call( node, 'data-digitalisimo-vendor', 'off' );
	}

	// Lo que pintan las interfaces en React y en plantillas pasa por setAttribute().
	Element.prototype.setAttribute = function ( name, value ) {
		if ( media.test( this.tagName ) && attributes.test( String( name ) ) && vendor( value ) ) {
			return off( this );
		}
		return setAttribute.apply( this, arguments );
	};

	// Y lo que se asigna como propiedad (`img.src = …`). El `src` de vídeo y audio es de HTMLMediaElement; el `poster`, de HTMLVideoElement.
	[ 'HTMLImageElement', 'HTMLSourceElement', 'HTMLIFrameElement', 'HTMLMediaElement', 'HTMLVideoElement', 'HTMLEmbedElement', 'HTMLTrackElement' ].forEach( function ( name ) {
		var proto = window[ name ] && window[ name ].prototype;
		[ 'src', 'srcset', 'poster' ].forEach( function ( property ) {
			var descriptor = proto && Object.getOwnPropertyDescriptor( proto, property );
			if ( ! descriptor || ! descriptor.set || ! descriptor.configurable ) {
				return;
			}
			Object.defineProperty( proto, property, {
				configurable: true,
				enumerable: descriptor.enumerable,
				get: descriptor.get,
				set: function ( value ) {
					if ( vendor( value ) ) {
						off( this );
						return;
					}
					descriptor.set.call( this, value );
				},
			} );
		} );
	} );
}() );
