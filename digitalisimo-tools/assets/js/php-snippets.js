/**
 * DIGITALÍSIMO Tools · Código PHP: editor, guardado en base64 (un WAF no ve «<?php» en el cuerpo) y «Guardar y activar» en
 * tres pasos: prepare → el navegador abre los destinos de la prueba → activate (con un reintento si un destino redirigió).
 * No interpreta HTML: los mensajes se pintan con textContent y los confirm() llevan texto fijo.
 */
( function () {
	'use strict';
	var cfg = window.digitalisimoToolsPhp;
	var root = document.getElementById( 'digitalisimo-php' );
	if ( ! cfg || ! root || ! window.fetch ) return;
	var form = document.getElementById( 'digitalisimo-php-editor' );
	var area = document.getElementById( 'digitalisimo-php-code' );
	var title = document.getElementById( 'digitalisimo-php-title' );
	var activateButton = document.getElementById( 'digitalisimo-php-activate' );
	var saveButton = document.getElementById( 'digitalisimo-php-save' );
	var state = { id: cfg.id, nonces: cfg.nonces || {}, busy: false };
	var editor = null;

	if ( area && cfg.editor && window.wp && window.wp.codeEditor ) {
		try { editor = window.wp.codeEditor.initialize( area, cfg.editor ).codemirror; } catch ( e ) { editor = null; }
	}

	function code() { return editor ? editor.getValue() : ( area ? area.value : '' ); }

	function base64( text ) {
		var bytes = new TextEncoder().encode( text ), bin = '';
		for ( var i = 0; i < bytes.length; i += 0x8000 ) bin += String.fromCharCode.apply( null, bytes.subarray( i, i + 0x8000 ) );
		return window.btoa( bin );
	}

	function jump( line ) {
		line = parseInt( line, 10 );
		if ( ! line || line < 1 ) return;
		if ( editor ) { editor.focus(); editor.setCursor( { line: line - 1, ch: 0 } ); return; }
		if ( ! area ) return;
		var rows = area.value.split( '\n' ), pos = 0;
		for ( var i = 0; i < line - 1 && i < rows.length; i++ ) pos += rows[ i ].length + 1;
		area.focus();
		area.setSelectionRange( pos, pos );
	}

	function box() {
		var el = document.getElementById( 'digitalisimo-php-messages' );
		if ( ! el ) {
			el = document.createElement( 'div' );
			el.id = 'digitalisimo-php-messages';
			el.setAttribute( 'aria-live', 'polite' );
			root.insertBefore( el, root.firstChild );
		}
		return el;
	}

	function clear( el ) { el = el || box(); while ( el.firstChild ) el.removeChild( el.firstChild ); }

	function say( type, text, list ) {
		var div = document.createElement( 'div' ), p = document.createElement( 'p' );
		div.className = 'notice inline notice-' + type;
		p.textContent = text;
		div.appendChild( p );
		if ( list && list.length ) {
			var head = document.createElement( 'p' ), ul = document.createElement( 'ul' );
			head.textContent = cfg.texts.warnings;
			list.forEach( function ( item ) { var li = document.createElement( 'li' ); li.textContent = String( item ); ul.appendChild( li ); } );
			div.appendChild( head );
			div.appendChild( ul );
		}
		box().appendChild( div );
		return div;
	}

	function rescue( container, text, link ) {
		var p = document.createElement( 'p' ), line = document.createElement( 'p' ), out = document.createElement( 'code' ), copy = document.createElement( 'button' );
		p.textContent = text;
		out.textContent = link;
		copy.type = 'button';
		copy.className = 'button';
		copy.textContent = cfg.texts.copy;
		copy.addEventListener( 'click', function () {
			if ( navigator.clipboard ) navigator.clipboard.writeText( link ).then( function () { copy.textContent = cfg.texts.copied; } );
		} );
		line.appendChild( out );
		line.appendChild( document.createTextNode( ' ' ) );
		line.appendChild( copy );
		container.appendChild( p );
		container.appendChild( line );
	}

	function post( data ) {
		var body = new URLSearchParams();
		body.append( 'action', cfg.action );
		Object.keys( data ).forEach( function ( key ) { body.append( key, data[ key ] ); } );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.catch( function () { return { success: false, data: { message: cfg.texts.network } }; } );
	}

	function wait( ms ) { return new Promise( function ( resolve ) { setTimeout( resolve, ms ); } ); }

	// Paso 2: el navegador pide cada destino una vez (30 s como mucho); la verdad está en el resultado que guarda el servidor.
	// De una portada de su mismo origen anota el código HTTP en run.seen: un 401/403 sin resultado es la autenticación del
	// servidor (staging con usuario y contraseña), donde WordPress ni arranca. De otro origen la respuesta es opaca.
	function visit( targets, seen ) {
		return Promise.all( ( targets || [] ).map( function ( t ) {
			var ctl = window.AbortController ? new AbortController() : null;
			var timer = ctl ? setTimeout( function () { ctl.abort(); }, 30000 ) : 0;
			var url = new URL( t.url, location.href ), same = url.origin === location.origin;
			var token = url.searchParams.get( cfg.param ) || '';
			var opts = 'admin' === t.kind
				? { credentials: 'same-origin', cache: 'no-store', redirect: 'manual' }
				: { credentials: 'omit', cache: 'no-store', redirect: same ? 'manual' : 'follow', mode: same ? 'same-origin' : 'no-cors' };
			if ( ctl ) opts.signal = ctl.signal;
			return fetch( t.url, opts )
				.then( function ( r ) {
					if ( same && 'front' === t.kind && token && r.status ) seen[ token ] = r.status;
					return r.text().catch( function () { return ''; } );
				} )
				.catch( function () { return ''; } )
				.then( function () { clearTimeout( timer ); } );
		} ) );
	}

	function seenText( seen ) { return Object.keys( seen ).map( function ( k ) { return k + ':' + seen[ k ]; } ).join( ',' ); }

	function adopt( d ) {
		if ( ! d || ! d.id || ! d.nonces ) return;
		state.id = d.id;
		state.nonces = d.nonces;
		if ( form ) {
			if ( form.elements.id ) form.elements.id.value = d.id;
			if ( form.elements.nonce ) form.elements.nonce.value = d.nonces.save;
		}
		if ( d.url && window.history && window.history.replaceState ) window.history.replaceState( null, '', d.url );
	}

	// Paso 3, con un solo reintento para una redirección. Una portada de otro origen (no-cors) se da por terminada al llegar
	// las cabeceras, antes de que el servidor escriba su resultado: mientras falte alguno («pending»), se vuelve a preguntar
	// cada 1,5 s hasta 30 s desde que se abrieron los destinos; sólo entonces se pide el veredicto final.
	function finish( sid, ctx, run ) {
		var last = Date.now() >= run.deadline;
		return post( { do: 'activate', scope: ctx.scope, blog_id: cfg.blog, id: ctx.id(), nonce: ctx.nonce(), sid: sid, seen: seenText( run.seen ), final: last ? '1' : '' } ).then( function ( r ) {
			var d = r.data || {};
			if ( r.success && d.retry && ! run.retried ) {
				run.retried = true;
				run.deadline = Date.now() + 30000;
				return visit( d.retry, run.seen ).then( function () { return finish( sid, ctx, run ); } );
			}
			if ( ! r.success && d.pending && ! last ) return wait( 1500 ).then( function () { return finish( sid, ctx, run ); } );
			return r;
		} );
	}

	function test( first, ctx ) {
		return post( first ).then( function ( r ) {
			var d = r.data || {};
			adopt( d );
			if ( ! r.success || ! d.sid ) return r;
			var run = { seen: {}, retried: false, deadline: Date.now() + 30000 };
			return visit( d.urls, run.seen ).then( function () { return finish( d.sid, ctx, run ); } );
		} );
	}

	// La casilla «Revisé este código» cuando el servidor la pide y la página no la tenía (el borrador cambió fuera de
	// DIGITALÍSIMO Tools después de abrir el editor).
	function reviewBox() {
		if ( document.getElementById( 'digitalisimo-php-reviewed' ) || ! activateButton || ! activateButton.parentNode || ! activateButton.parentNode.parentNode ) return;
		var p = document.createElement( 'p' ), label = document.createElement( 'label' ), input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.name = 'reviewed';
		input.value = '1';
		input.id = 'digitalisimo-php-reviewed';
		label.appendChild( input );
		label.appendChild( document.createTextNode( ' ' + cfg.texts.review ) );
		p.appendChild( label );
		activateButton.parentNode.parentNode.insertBefore( p, activateButton.parentNode );
	}

	function show( r ) {
		var d = ( r && r.data ) || {};
		clear();
		if ( r && r.success ) {
			var div = say( d.line ? 'warning' : 'success', d.message || '', d.warnings );
			if ( d.rescue ) rescue( div, d.rescue_text || '', d.rescue );
			var badge = document.querySelector( '#digitalisimo-php-state .digitalisimo-effective-state' );
			if ( badge && d.state ) badge.textContent = d.state;
		} else {
			say( 'error', d.message || cfg.texts.network, d.warnings );
			if ( d.review ) reviewBox();
		}
		if ( d.line ) jump( d.line );
	}

	function busy( on ) {
		state.busy = on;
		if ( activateButton ) {
			activateButton.disabled = on || activateButton.hasAttribute( 'data-reason' );
			activateButton.textContent = on ? cfg.texts.busy : cfg.texts.activate;
		}
		if ( saveButton ) saveButton.disabled = on;
	}

	var editorCtx = { scope: cfg.scope, id: function () { return state.id; }, nonce: function () { return state.nonces.activate; } };

	if ( activateButton ) {
		activateButton.disabled = activateButton.hasAttribute( 'data-reason' );
		activateButton.addEventListener( 'click', function () {
			if ( state.busy ) return;
			var reviewed = document.getElementById( 'digitalisimo-php-reviewed' );
			busy( true );
			clear();
			test( { do: 'prepare', scope: cfg.scope, blog_id: cfg.blog, id: state.id || 'new', nonce: state.nonces.prepare, title: title ? title.value : '', code_b64: base64( code() ), reviewed: reviewed && reviewed.checked ? '1' : '' }, editorCtx )
				.then( show, function () { show( null ); } )
				.then( function () { busy( false ); } );
		} );
	}

	// «Guardar sin activar» por fetch: un error no saca de la página. Sin JavaScript, el formulario va a admin-post.php.
	if ( form ) {
		form.addEventListener( 'submit', function ( ev ) {
			ev.preventDefault();
			if ( state.busy ) return;
			busy( true );
			clear();
			post( { do: 'save', scope: cfg.scope, blog_id: cfg.blog, id: state.id || 'new', nonce: state.nonces.save, title: title ? title.value : '', code_b64: base64( code() ) } )
				.then( function ( r ) { adopt( r.data ); show( r ); } )
				.then( function () { busy( false ); } );
		} );
	}

	// «Reanudar en este sitio» y «Reanudar en todos los sitios»: la misma prueba, sin editor.
	Array.prototype.forEach.call( document.querySelectorAll( '[data-digitalisimo-php-resume]' ), function ( button ) {
		button.disabled = false;
		button.addEventListener( 'click', function () {
			if ( state.busy ) return;
			var label = button.textContent, scope = button.getAttribute( 'data-scope' ), id = button.getAttribute( 'data-id' );
			state.busy = true;
			button.disabled = true;
			button.textContent = cfg.texts.resuming;
			clear();
			test( { do: button.getAttribute( 'data-digitalisimo-php-resume' ), scope: scope, blog_id: cfg.blog, id: id, nonce: button.getAttribute( 'data-prepare' ) }, { scope: scope, id: function () { return id; }, nonce: function () { return button.getAttribute( 'data-activate' ); } } )
				.then( show, function () { show( null ); } )
				.then( function () { state.busy = false; button.textContent = label; button.disabled = false; } );
		} );
	} );

	// Confirmaciones con texto fijo, sin el título del fragmento.
	Array.prototype.forEach.call( document.querySelectorAll( 'form[data-digitalisimo-php-confirm]' ), function ( f ) {
		f.addEventListener( 'submit', function ( ev ) {
			var text = cfg.texts.confirm[ f.getAttribute( 'data-digitalisimo-php-confirm' ) ];
			if ( text && ! window.confirm( text ) ) ev.preventDefault();
		} );
	} );

	// «Crear enlace de rescate»: el enlace se muestra una vez, en la respuesta, y nunca se guarda en la página.
	Array.prototype.forEach.call( root.querySelectorAll( 'form' ), function ( f ) {
		if ( ! f.elements.do || 'rescue_new' !== f.elements.do.value ) return;
		f.addEventListener( 'submit', function ( ev ) {
			ev.preventDefault();
			var data = {};
			Array.prototype.forEach.call( f.elements, function ( el ) { if ( el.name && 'action' !== el.name ) data[ el.name ] = el.value; } );
			post( data ).then( function ( r ) {
				var out = document.getElementById( 'digitalisimo-php-rescue-out' ) || box(), d = r.data || {};
				clear( out );
				if ( r.success && d.link ) { rescue( out, d.message || '', d.link ); return; }
				var p = document.createElement( 'p' );
				p.textContent = d.message || cfg.texts.network;
				out.appendChild( p );
			} );
		} );
	} );

	jump( new URLSearchParams( window.location.search ).get( 'line' ) );
}() );
