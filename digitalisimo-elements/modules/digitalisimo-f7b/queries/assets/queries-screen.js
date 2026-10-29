/**
 * DIGITALÍSIMO Elements · F7b · Pantalla «Consultas»: selectores con búsqueda (términos, autores y entradas) y la
 * confirmación de «Borrar». Sin dependencias. La búsqueda se lanza con «Buscar» o Intro (nunca al teclear) y sus
 * resultados son botones «Añadir «X»»; lo elegido son campos ocultos dentro de la lista, con «Quitar».
 */
( function () {
	'use strict';

	var cfg = window.digitalisimoQueriesScreen;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;

	function fmt( text, value ) {
		return text.replace( '%s', value ).replace( '%d', value );
	}

	function button( text, label, cls ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = cls;
		b.textContent = text;
		if ( label ) {
			b.setAttribute( 'aria-label', label );
		}
		return b;
	}

	function choose( picker, item ) {
		var chosen = picker.querySelector( '[data-chosen]' );
		var name = picker.getAttribute( 'data-name' );
		var exists = chosen.querySelector( 'input[value="' + String( item.id ).replace( /[^0-9]/g, '' ) + '"]' );
		if ( exists ) {
			return;
		}
		if ( picker.hasAttribute( 'data-single' ) ) {
			chosen.textContent = '';
		}
		var li = document.createElement( 'li' );
		var span = document.createElement( 'span' );
		span.textContent = item.text;
		var input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = name;
		input.value = String( item.id );
		li.appendChild( span );
		li.appendChild( document.createTextNode( ' ' ) );
		li.appendChild( input );
		li.appendChild( button( t.removeT, fmt( t.remove, item.text ), 'button-link' ) ).setAttribute( 'data-remove', '' );
		chosen.appendChild( li );
		picker.querySelector( '[data-status]' ).textContent = fmt( t.added, item.text );
	}

	function search( picker ) {
		var field = picker.querySelector( '[data-search]' );
		var results = picker.querySelector( '[data-results]' );
		var status = picker.querySelector( '[data-status]' );
		var term = field.value.trim();
		results.textContent = '';
		if ( term.length < 2 ) {
			status.textContent = t.short;
			return;
		}
		var type = document.querySelector( '[data-digi-sq-type]' );
		var body = new URLSearchParams( {
			action: cfg.action,
			nonce: cfg.nonce,
			object: picker.getAttribute( 'data-object' ),
			q: term,
			post_type: type ? type.value : '',
		} );
		picker.setAttribute( 'aria-busy', 'true' );
		fetch( cfg.url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				var list = json && json.success && json.data && Array.isArray( json.data.results ) ? json.data.results : null;
				if ( ! list ) {
					throw new Error( 'search' );
				}
				list.forEach( function ( item ) {
					var li = document.createElement( 'li' );
					var add = button( fmt( t.add, item.text ), '', 'button-link' );
					add.addEventListener( 'click', function () {
						choose( picker, item );
						li.remove();
					} );
					li.appendChild( add );
					results.appendChild( li );
				} );
				status.textContent = list.length ? fmt( t.found, String( list.length ) ) : t.none;
			} )
			.catch( function () {
				status.textContent = t.error;
			} )
			.then( function () {
				picker.removeAttribute( 'aria-busy' );
			} );
	}

	document.querySelectorAll( '[data-digi-sq-picker]' ).forEach( function ( picker ) {
		picker.querySelector( '[data-go]' ).addEventListener( 'click', function () {
			search( picker );
		} );
		picker.querySelector( '[data-search]' ).addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				search( picker );
			}
		} );
		picker.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '[data-remove]' );
			if ( remove && picker.contains( remove ) ) {
				var li = remove.closest( 'li' );
				var field = picker.querySelector( '[data-search]' );
				li.remove();
				field.focus();
			}
		} );
	} );

	document.querySelectorAll( 'button[data-confirm]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function ( event ) {
			if ( ! window.confirm( b.getAttribute( 'data-confirm' ) ) ) {
				event.preventDefault();
			}
		} );
	} );
}() );
