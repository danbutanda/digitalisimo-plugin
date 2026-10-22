/**
 * DIGITALÍSIMO Elements · muestras de variación (F4-Woo, V.6.4). Sin dependencias.
 *
 * Los radios manejan el <select> de WooCommerce sin cambiarlo: elegir uno fija `select.value` y lanza un `change`
 * nativo con `bubbles`, que llega al manejador delegado de WooCommerce (jQuery escucha con addEventListener). El estado
 * de los radios se relee del <select> tras cada mutación (WooCommerce quita y deshabilita opciones), tras «Limpiar» (su
 * `.val('').trigger('change')` no produce ni mutación ni evento nativo) y, por el puente jQuery que exige el modo AJAX
 * (más de 30 variaciones: WooCommerce no toca las opciones), con `woocommerce_variation_has_changed`, `reset_data` y
 * `show_variation`. Lo que WooCommerce dispara con `.trigger()` nunca llega a un addEventListener propio.
 * En los bucles, «Cambiar la imagen al pasar el cursor» cambia la imagen de la ficha del producto.
 */
( function () {
	'use strict';
	var count = 0;

	function option( select, value ) {
		for ( var i = 0; i < select.options.length; i++ ) {
			if ( select.options[ i ].value === value ) {
				return select.options[ i ];
			}
		}
		return null;
	}

	/** Habilitado sólo si existe un <option> con ese valor y no está deshabilitado; elegido si es el valor del <select>. */
	function sync( field ) {
		var select = field.querySelector( 'select' );
		if ( ! select ) {
			return;
		}
		field.querySelectorAll( 'input[type="radio"]' ).forEach( function ( radio ) {
			var found = option( select, radio.value );
			var off = ! found || found.disabled;
			var label = radio.closest( '.digi-swatch' ) || radio.parentNode;
			var na = label.querySelector( '.digi-swatch__na' );
			radio.disabled = off;
			radio.checked = ! off && '' !== select.value && select.value === radio.value;
			label.classList.toggle( 'is-disabled', off );
			label.classList.toggle( 'is-selected', radio.checked );
			if ( na ) {
				na.textContent = off ? ' (no disponible)' : '';
			}
		} );
	}

	function init( field ) {
		var select = field.querySelector( 'select' );
		var radios = field.querySelectorAll( 'input[type="radio"]' );
		if ( field.hasAttribute( 'data-digi-ready' ) || ! select || ! radios.length ) {
			return;
		}
		field.setAttribute( 'data-digi-ready', '' );
		// Formulario vacío propio, fuera del de compra: los radios siguen agrupados y no viajan con el pedido.
		var id = 'digi-sw-form-' + ( ++count ) + '-' + Date.now().toString( 36 );
		var holder = document.createElement( 'form' );
		holder.id = id;
		holder.hidden = true;
		document.body.appendChild( holder );
		radios.forEach( function ( radio ) {
			radio.setAttribute( 'form', id );
			radio.name = id;
		} );
		// El <select> oculto no puede mostrar su aviso de obligatorio: el primer radio lo lleva mientras esté oculto.
		if ( select.required ) {
			select.required = false;
			radios[ 0 ].required = true;
		}
		field.addEventListener( 'change', function ( event ) {
			var radio = event.target;
			if ( ! radio || 'radio' !== radio.type ) {
				return;
			}
			if ( option( select, radio.value ) ) {
				select.value = radio.value;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			sync( field );
		} );
		new MutationObserver( function () {
			sync( field );
		} ).observe( select, { childList: true, subtree: true, attributes: true } );
		var cart = select.form;
		if ( cart ) {
			cart.addEventListener( 'submit', function ( event ) {
				if ( radios[ 0 ].required && '' === select.value ) {
					event.preventDefault();
					radios[ 0 ].reportValidity();
				}
			}, true );
			if ( window.jQuery ) {
				window.jQuery( cart ).on( 'woocommerce_variation_has_changed reset_data show_variation', function () {
					sync( field );
				} );
			}
		}
		field.classList.add( 'digi-swatch-field--ready' );
		sync( field );
	}

	function scan( root ) {
		root = root && root.querySelectorAll ? root : document;
		if ( root.matches && root.matches( '.digi-swatch-field' ) ) {
			init( root );
		}
		root.querySelectorAll( '.digi-swatch-field' ).forEach( init );
	}

	// «Limpiar»: WooCommerce ya cambió el valor cuando el clic llega a document; se relee tras una microtarea.
	document.addEventListener( 'click', function ( event ) {
		var link = event.target && event.target.closest ? event.target.closest( '.reset_variations' ) : null;
		if ( ! link ) {
			return;
		}
		var form = link.closest( 'form' ) || document;
		Promise.resolve().then( function () {
			form.querySelectorAll( '.digi-swatch-field[data-digi-ready]' ).forEach( sync );
		} );
	} );

	// Bucle: la imagen de la variación en la ficha del producto, y la original al salir.
	function swap( link, on ) {
		var list = link.closest( '[data-digi-sw-hover]' );
		var card = list ? list.closest( '.e-loop-item, [data-elementor-type="loop-item"], li.product, .product' ) : null;
		var img = card ? card.querySelector( 'img:not(.digi-swatch__img)' ) : null;
		if ( ! img ) {
			return;
		}
		var keys = [ 'src', 'srcset', 'sizes' ];
		if ( on ) {
			var data;
			try {
				data = JSON.parse( link.getAttribute( 'data-digi-sw-img' ) );
			} catch ( e ) {
				return;
			}
			if ( ! img.hasAttribute( 'data-digi-sw-src' ) ) {
				keys.forEach( function ( key ) {
					img.setAttribute( 'data-digi-sw-' + key, img.getAttribute( key ) || '' );
				} );
			}
			keys.forEach( function ( key ) {
				if ( data[ key ] ) {
					img.setAttribute( key, data[ key ] );
				} else if ( 'src' !== key ) {
					img.removeAttribute( key );
				}
			} );
		} else if ( img.hasAttribute( 'data-digi-sw-src' ) ) {
			keys.forEach( function ( key ) {
				var value = img.getAttribute( 'data-digi-sw-' + key );
				if ( value ) {
					img.setAttribute( key, value );
				} else {
					img.removeAttribute( key );
				}
				img.removeAttribute( 'data-digi-sw-' + key );
			} );
		}
	}

	function hover( on ) {
		return function ( event ) {
			var link = event.target && event.target.closest ? event.target.closest( 'a[data-digi-sw-img]' ) : null;
			if ( link && ! ( event.relatedTarget && link.contains( event.relatedTarget ) ) ) {
				swap( link, on );
			}
		};
	}
	document.addEventListener( 'mouseover', hover( true ) );
	document.addEventListener( 'mouseout', hover( false ) );
	document.addEventListener( 'focusin', hover( true ) );
	document.addEventListener( 'focusout', hover( false ) );

	// Contenido que llega después: vista rápida (`digitalisimo:content`) y refresco de bucles del constructor.
	document.addEventListener( 'digitalisimo:content', function ( event ) {
		scan( event.detail && event.detail.root );
	} );
	function hooks() {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/widget', function ( $scope ) {
				scan( $scope && $scope[ 0 ] );
			} );
		}
	}
	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		hooks();
	} else {
		window.addEventListener( 'elementor/frontend/init', hooks );
	}

	window.digitalisimoSwatches = { init: scan, sync: sync, version: 1 };
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			scan( document );
		} );
	} else {
		scan( document );
	}
} )();
