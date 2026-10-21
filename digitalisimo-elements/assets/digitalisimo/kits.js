/**
 * DIGITALÍSIMO Elements · kits: en «Importación completa» del importador de kits de la App, el enlace al informe de
 * la pantalla «Kits». Sólo con el núcleo incluido (lo encola Kits\Report_Page en la App) y sin peticiones propias.
 */
( function () {
	var config = window.digitalisimoKits || {};
	if ( ! config.report ) {
		return;
	}
	function add() {
		var box, line, link;
		if ( ! /^#\/import-customization\/complete/.test( window.location.hash ) || document.getElementById( 'digitalisimo-kit-report' ) ) {
			return;
		}
		box = document.querySelector( '[data-testid="import-complete-content"]' );
		if ( ! box ) {
			return;
		}
		line = document.createElement( 'p' );
		line.id = 'digitalisimo-kit-report';
		line.style.cssText = 'margin:0;font-size:14px;text-align:center;color:var(--digi-text, #52617e)';
		link = document.createElement( 'a' );
		link.href = config.report;
		link.textContent = config.label;
		link.style.cssText = 'font-weight:600;color:var(--digi-link, #355cf5)';
		line.appendChild( document.createTextNode( config.intro + ' ' ) );
		line.appendChild( link );
		box.appendChild( line );
	}
	if ( window.MutationObserver ) {
		new window.MutationObserver( add ).observe( document.documentElement, { childList: true, subtree: true } );
	}
	window.addEventListener( 'hashchange', add );
	add();
}() );
