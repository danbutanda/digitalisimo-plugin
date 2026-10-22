/**
 * DIGITALÍSIMO Elements · campos de muestra en los términos de un atributo (F4-Woo, V.6.2).
 *
 * Color: la paleta nativa y el campo hexadecimal se sincronizan; el campo de texto es el que se guarda (vacío = sin
 * color). Imagen: el modal de Medios del núcleo (`wp.media`), con vista previa y «Quitar»; se guarda el ID del adjunto
 * y la dirección nunca se escribe. Al añadir un término por AJAX, los campos se vacían como los del núcleo.
 */
( function () {
	'use strict';

	function setup( root ) {
		var picker = root.querySelector( '[data-digi-swatch-picker]' );
		var text = root.querySelector( 'input[name="digitalisimo_swatch_color"]' );
		if ( picker && text ) {
			picker.addEventListener( 'input', function () {
				text.value = picker.value;
			} );
			text.addEventListener( 'input', function () {
				var value = text.value.trim();
				if ( /^#[0-9a-f]{6}$/i.test( value ) ) {
					picker.value = value.toLowerCase();
				} else if ( /^#[0-9a-f]{3}$/i.test( value ) ) {
					picker.value = ( '#' + value[ 1 ] + value[ 1 ] + value[ 2 ] + value[ 2 ] + value[ 3 ] + value[ 3 ] ).toLowerCase();
				}
			} );
		}
		var media = root.querySelector( '[data-digi-swatch-media]' );
		if ( ! media ) {
			return;
		}
		var input = media.querySelector( 'input[name="digitalisimo_swatch_image"]' );
		var preview = media.querySelector( '[data-digi-swatch-preview]' );
		var pick = media.querySelector( '[data-digi-swatch-pick]' );
		var remove = media.querySelector( '[data-digi-swatch-remove]' );
		var frame = null;

		function set( id, url, alt ) {
			input.value = id ? String( id ) : '';
			preview.textContent = '';
			if ( id && url ) {
				var img = document.createElement( 'img' );
				img.src = url;
				img.alt = alt || '';
				img.width = 48;
				img.height = 48;
				preview.appendChild( img );
			}
			remove.hidden = ! id;
		}

		pick.addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: 'Imagen de la muestra',
					library: { type: 'image' },
					button: { text: 'Usar esta imagen' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var item = frame.state().get( 'selection' ).first();
					var data = item ? item.toJSON() : null;
					if ( data && 'image' === data.type ) {
						var size = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail : data;
						set( data.id, size.url, data.alt );
					}
					pick.focus();
				} );
			}
			frame.open();
		} );
		remove.addEventListener( 'click', function () {
			set( 0 );
			pick.focus();
		} );

		// Alta por AJAX en la lista de términos: el núcleo añade la fila y vacía sus campos; los propios, también.
		var list = document.getElementById( 'the-list' );
		if ( list && root.closest( '#addtag' ) ) {
			new MutationObserver( function ( records ) {
				if ( records.some( function ( record ) {
					return record.addedNodes.length > 0;
				} ) ) {
					set( 0 );
					if ( text ) {
						text.value = '';
					}
				}
			} ).observe( list, { childList: true } );
		}
	}

	function start() {
		var form = document.getElementById( 'addtag' ) || document.getElementById( 'edittag' );
		if ( form ) {
			setup( form );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
