/**
 * DIGITALÍSIMO Hosting en el editor de producto: muestra sólo la sección del tipo elegido (Dominio u Hosting) y filtra
 * el desplegable de extensiones con el buscador. Sin dependencias; sirve también en las variaciones, que WooCommerce
 * carga después (eventos delegados en el documento).
 */
( function () {
	'use strict';
	function sync( box ) {
		var type = box.querySelector( '[data-dh-type]' );
		if ( ! type ) return;
		var v = type.value;
		box.querySelectorAll( '[data-dh-section]' ).forEach( function ( s ) {
			var name = s.getAttribute( 'data-dh-section' );
			// «Igual que el producto» en una variación: se ven las dos, cada campo con su «Igual que el producto».
			var show = v === name || v === 'inherit';
			s.hidden = ! show;
		} );
	}
	function all() { document.querySelectorAll( '[data-dh-box]' ).forEach( sync ); }
	function filter( input ) {
		var select = document.getElementById( input.getAttribute( 'data-dh-find' ) );
		if ( ! select ) return;
		var q = input.value.trim().toLowerCase().replace( /^\.?/, '.' );
		var first = null;
		Array.prototype.forEach.call( select.options, function ( o ) {
			var keep = q === '.' || o.value === '' || o.selected || o.value.indexOf( q ) === 0 || o.text.toLowerCase().indexOf( q ) !== -1;
			o.hidden = ! keep;
			o.disabled = ! keep && ! o.selected;
			if ( keep && ! first && o.value !== '' && o.value !== '*' ) first = o;
		} );
		// Una coincidencia exacta se elige sola (y se anuncia el cambio).
		if ( first && first.value === q && select.value !== q ) {
			select.value = q;
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}
	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.matches && e.target.matches( '[data-dh-type]' ) ) sync( e.target.closest( '[data-dh-box]' ) );
	} );
	document.addEventListener( 'input', function ( e ) {
		if ( e.target && e.target.matches && e.target.matches( '[data-dh-find]' ) ) filter( e.target );
	} );
	document.addEventListener( 'DOMContentLoaded', all );
	// WooCommerce avisa con jQuery al cargar o agregar variaciones.
	if ( window.jQuery ) window.jQuery( document ).on( 'woocommerce_variations_loaded woocommerce_variations_added', all );
	all();
}() );
