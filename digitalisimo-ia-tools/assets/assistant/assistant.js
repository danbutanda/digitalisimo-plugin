/**
 * Asistente IA de DIGITALÍSIMO IA Tools (docs/ASSISTANT.md): la interfaz del chat asistente de @digitalisimo/ui
 * (AssistantApp, AssistantHistory, AssistantWelcome, ChatThread, ChatComposer y
 * AssistantAppearance) portada a wp.element, el React que ya trae WordPress. Sin compilación ni dependencias: este
 * archivo es el que se sirve.
 *
 * Todo lo que habla con el servidor pasa por window.digitalisimoAssistantAdapters (assistant-adapters.js). Lo que
 * llega del servidor o del modelo se pinta como texto (hijos de React, nunca HTML).
 */
( function ( wp, window, document ) {
	'use strict';
	if ( ! wp || ! wp.element || ! window.digitalisimoAssistantAdapters ) {
		return;
	}
	const el = wp.element;
	const h = el.createElement;
	const { Fragment, useState, useEffect, useRef, useMemo, useCallback } = el;
	const config = window.digitalisimoAssistantConfig || {};
	const mount = document.getElementById( 'digitalisimo-assistant-root' );
	if ( ! mount ) {
		return;
	}
	const adapters = window.digitalisimoAssistantAdapters.create( config );

	const ICONS = {
		sidebar: 'M4 5h16v14H4z M9 5v14',
		pen: 'M4 20h4L19 9l-4-4L4 16z M14 6l4 4',
		search: 'M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z M20 20l-4-4',
		palette: 'M12 3a9 9 0 1 0 0 18c1 0 1.5-.8 1.5-1.6 0-1-.8-1.4-.8-2.4 0-.9.7-1.5 1.6-1.5H17a4 4 0 0 0 4-4c0-4.7-4-8.5-9-8.5z M7.5 11.5h.01 M10 7.5h.01 M14.5 7.5h.01',
		tools: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
		send: 'M4 12l16-8-6 16-2.5-6.5z M11.5 13.5L20 4',
		trash: 'M4 7h16 M9 7V4h6v3 M6 7l1 13h10l1-13',
		check: 'M5 12l5 5 9-10',
		close: 'M6 6l12 12 M18 6L6 18',
		alert: 'M12 3l10 18H2z M12 10v5 M12 18h.01',
		sparkle: 'M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2z',
		clip: 'M20 11.5l-8 8a5 5 0 0 1-7-7l8.5-8.5a3.3 3.3 0 0 1 4.7 4.7L9.7 17a1.7 1.7 0 0 1-2.4-2.4L15 7',
		file: 'M6 3h8l4 4v14H6z M14 3v4h4',
		upload: 'M12 16V4 M7 9l5-5 5 5 M4 20h16',
	};
	const SCHEMES = [
		[ 'digitalisimo', 'DIGITALÍSIMO', '' ],
		[ 'azure', 'Azul', 'linear-gradient(135deg,#487fff,#1e40af)' ],
		[ 'emerald', 'Esmeralda', 'linear-gradient(135deg,#34d399,#047857)' ],
		[ 'purple', 'Morado', 'linear-gradient(135deg,#aa72d8,#5c32a3)' ],
		[ 'sunset', 'Atardecer', 'linear-gradient(135deg,#fb923c,#c2410c)' ],
		[ 'red', 'Rojo', 'linear-gradient(135deg,#f86776,#b92536)' ],
		[ 'petrol', 'Petróleo', 'linear-gradient(135deg,#4da6a9,#003a40)' ],
		[ 'graphite', 'Grafito', 'linear-gradient(135deg,#8a94a3,#364050)' ],
	];
	const STORE = 'digitalisimo-assistant-appearance';
	const DEFAULT_LOOK = { variant: 'classic', mode: 'light', scheme: 'digitalisimo' };
	// En palabras de quien usa el sitio (caso plomerito 2: «arroja información técnica que el usuario nunca va a entender»).
	const VERBS = { read: 'Consulté', draft: 'Borrador', write: 'Cambio', destructive: 'Cambio importante', memory: 'Recordé', unknown: 'Paso' };
	const STATES = { ok: 'listo', error: 'no se pudo', denied: 'sin permiso', blocked: 'apagado en ajustes', cancelled: 'cancelado', unknown: 'por revisar', skipped: 'no se hizo' };
	const SUGGESTIONS = [
		[ 'Créame una landing con una oferta de uno de mis productos', 'Crear una landing de oferta' ],
		[ 'Revisa cómo me encuentran en Google y dime qué mejorar', 'Mejorar cómo me encuentran en Google' ],
		[ '¿Cómo van mis ventas y pedidos de esta semana?', 'Ver mis ventas' ],
	];

	/* La apariencia es de quien mira, en este navegador: si el almacenamiento falla, la pantalla funciona igual. */
	const readLook = () => {
		try {
			const saved = JSON.parse( window.localStorage.getItem( STORE ) || 'null' );
			const look = Object.assign( {}, DEFAULT_LOOK, saved && 'object' === typeof saved ? saved : {} );
			return {
				variant: [ 'classic', 'modern' ].indexOf( look.variant ) >= 0 ? look.variant : DEFAULT_LOOK.variant,
				mode: [ 'light', 'dark', 'system' ].indexOf( look.mode ) >= 0 ? look.mode : DEFAULT_LOOK.mode,
				scheme: SCHEMES.some( ( s ) => s[ 0 ] === look.scheme ) ? look.scheme : DEFAULT_LOOK.scheme,
			};
		} catch ( e ) {
			return Object.assign( {}, DEFAULT_LOOK );
		}
	};
	const saveLook = ( look ) => {
		try {
			window.localStorage.setItem( STORE, JSON.stringify( look ) );
		} catch ( e ) {
			// Sin almacenamiento la elección dura lo que la página.
		}
	};
	const media = ( query ) => !! ( window.matchMedia && window.matchMedia( query ).matches );
	const timeOf = ( seconds ) => ( seconds ? new Date( seconds * 1000 ).toLocaleTimeString( 'es-MX', { hour: '2-digit', minute: '2-digit' } ) : '' );
	const dayOf = ( iso ) => {
		const date = new Date( iso );
		if ( isNaN( date.getTime() ) ) {
			return 'Antes';
		}
		const today = new Date();
		const yesterday = new Date();
		yesterday.setDate( today.getDate() - 1 );
		if ( date.toDateString() === today.toDateString() ) {
			return 'Hoy';
		}
		if ( date.toDateString() === yesterday.toDateString() ) {
			return 'Ayer';
		}
		const options = { day: 'numeric', month: 'long' };
		if ( date.getFullYear() !== today.getFullYear() ) {
			options.year = 'numeric';
		}
		return date.toLocaleDateString( 'es-MX', options );
	};
	// Sólo se enlaza lo del propio sitio (vista previa, editor): lo demás se queda como texto.
	const siteHosts = ( () => {
		const out = [];
		[ ( config.site || {} ).home, config.pageUrl, window.location.href ].forEach( ( u ) => {
			try {
				if ( u ) {
					out.push( new window.URL( u, window.location.href ).host );
				}
			} catch ( e ) {
				// Una dirección mal formada no aporta un host.
			}
		} );
		return out;
	} )();
	const safeUrl = ( raw ) => {
		try {
			const url = new window.URL( String( raw ), window.location.href );
			return ( 'https:' === url.protocol || 'http:' === url.protocol ) && siteHosts.indexOf( url.host ) >= 0 ? url.toString() : '';
		} catch ( e ) {
			return '';
		}
	};
	let serial = 0;
	const note = ( text, tone ) => ( { id: 'local-' + Date.now() + '-' + ( serial++ ), role: 'system', tone: tone || 'info', text: String( text ) } );

	/** «640 KB», «4.2 MB»: el peso como lo entiende cualquiera (el mismo cálculo que el servidor). Puro. */
	function formatBytes( value ) {
		const bytes = Math.max( 0, Number( value ) || 0 );
		if ( bytes < 1024 ) {
			return bytes + ' bytes';
		}
		if ( bytes < 1048576 ) {
			return Math.max( 1, Math.round( bytes / 1024 ) ) + ' KB';
		}
		const mb = bytes / 1048576;
		return ( mb >= 100 ? String( Math.round( mb ) ) : String( Math.round( mb * 10 ) / 10 ) ) + ' MB';
	}

	/**
	 * Lo que se subió, en palabras llanas: «Subí 3 imágenes a tu biblioteca (de 4.2 MB a 640 KB en total).», por qué
	 * alguna quedó sin comprimir y qué no se subió. `done`: lo que devolvió el servidor; `failed`: { name, message }. Puro.
	 */
	function uploadSummary( done, failed ) {
		const lines = [];
		const images = done.filter( ( f ) => 'image' === f.kind ).length;
		const others = done.length - images;
		if ( done.length ) {
			const what = [];
			if ( images ) {
				what.push( images + ( 1 === images ? ' imagen' : ' imágenes' ) );
			}
			if ( others ) {
				what.push( others + ( 1 === others ? ' archivo' : ' archivos' ) );
			}
			const before = done.reduce( ( n, f ) => n + ( Number( f.before ) || Number( f.bytes ) || 0 ), 0 );
			const after = done.reduce( ( n, f ) => n + ( Number( f.bytes ) || 0 ), 0 );
			const total = done.length > 1 ? ' en total' : '';
			const weight = after < before ? '(de ' + formatBytes( before ) + ' a ' + formatBytes( after ) + total + ')' : '(' + formatBytes( after ) + total + ')';
			lines.push( 'Subí ' + what.join( ' y ' ) + ' a tu biblioteca ' + weight + '.' );
			const count = ( reason ) => done.filter( ( f ) => reason === f.reason ).length;
			if ( count( 'tools' ) ) {
				lines.push( ( 1 === count( 'tools' ) ? 'La imagen quedó' : 'Las imágenes quedaron' ) + ' sin comprimir: para eso hace falta activar DIGITALÍSIMO Tools en tu sitio.' );
			}
			if ( count( 'gif' ) ) {
				lines.push( 1 === count( 'gif' ) ? 'El GIF animado quedó como estaba para no perder la animación.' : 'Los GIF animados quedaron como estaban para no perder la animación.' );
			}
			if ( count( 'error' ) ) {
				lines.push( 1 === count( 'error' ) ? 'Una imagen no se pudo comprimir y quedó como estaba.' : count( 'error' ) + ' imágenes no se pudieron comprimir y quedaron como estaban.' );
			}
		}
		failed.forEach( ( f ) => lines.push( 'No subí «' + f.name + '»: ' + f.message ) );
		return lines.join( ' ' );
	}

	/**
	 * El texto del Asistente en trozos: texto, negritas («**así**») y enlaces. «[Ver la landing](URL)» es un enlace con
	 * ese texto y una URL suelta del sitio, «Abrir»; sólo direcciones http(s) del propio sitio (las demás quedan como
	 * texto). Puro: la prueba lo usa sin navegador.
	 */
	function richParts( value ) {
		const text = String( value || '' );
		const out = [];
		const plain = ( chunk ) => {
			String( chunk ).split( /\*\*([^*\n]{1,200})\*\*/ ).forEach( ( part, i ) => {
				if ( part ) {
					out.push( i % 2 ? { bold: part } : { text: part } );
				}
			} );
		};
		const re = /\[([^\]\n]{1,100})\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s<>"')\]]+)/g;
		let last = 0;
		let m;
		while ( ( m = re.exec( text ) ) ) {
			plain( text.slice( last, m.index ) );
			const href = safeUrl( m[ 2 ] || m[ 3 ] );
			if ( href ) {
				out.push( { href, label: m[ 1 ] || 'Abrir' } );
			} else {
				plain( m[ 1 ] || m[ 3 ] );
			}
			last = re.lastIndex;
		}
		plain( text.slice( last ) );
		return out;
	}

	/** richParts() con nodos de React, nunca HTML. */
	function RichText( props ) {
		return h( Fragment, null, richParts( props.text ).map( ( part, i ) => {
			if ( part.href ) {
				return h( 'a', { key: i, className: 'dga-inline-link', href: part.href, target: '_blank', rel: 'noopener noreferrer' }, part.label );
			}
			return part.bold ? h( 'strong', { key: i }, part.bold ) : part.text;
		} ) );
	}

	/** Miniaturas de lo subido (sólo direcciones del propio sitio), con su nombre y su peso. */
	function FileThumbs( props ) {
		const files = Array.isArray( props.files ) ? props.files : [];
		if ( ! files.length ) {
			return null;
		}
		return h( 'ul', { className: 'dga-files', 'aria-label': 'Archivos subidos' }, files.map( ( f, i ) => {
			const thumb = 'image' === f.kind ? safeUrl( f.thumb || f.url ) : '';
			return h( 'li', { key: f.id || i, className: 'dga-file', 'data-kind': f.kind },
				thumb ? h( 'img', { className: 'dga-file-thumb', src: thumb, alt: '', loading: 'lazy' } ) : h( 'span', { className: 'dga-file-thumb is-icon', 'aria-hidden': 'true' }, h( Icon, { name: 'file' } ) ),
				h( 'span', { className: 'dga-file-name' }, String( f.name || '' ) ),
				h( 'span', { className: 'dga-file-size' }, formatBytes( f.bytes ) ) );
		} ) );
	}

	function Icon( props ) {
		return h( 'svg', { className: 'dga-icon', viewBox: '0 0 24 24', 'aria-hidden': 'true', focusable: 'false' }, h( 'path', { d: ICONS[ props.name ] || '' } ) );
	}

	/** Cierra un menú o un diálogo con Escape o al pulsar fuera. */
	function useDismiss( open, close, ref ) {
		useEffect( () => {
			if ( ! open ) {
				return undefined;
			}
			const onKey = ( event ) => {
				if ( 'Escape' === event.key ) {
					close();
				}
			};
			const onDown = ( event ) => {
				if ( ref.current && ! ref.current.contains( event.target ) ) {
					close();
				}
			};
			document.addEventListener( 'keydown', onKey );
			document.addEventListener( 'mousedown', onDown );
			return () => {
				document.removeEventListener( 'keydown', onKey );
				document.removeEventListener( 'mousedown', onDown );
			};
		}, [ open ] );
	}

	function Dropdown( props ) {
		const ref = useRef( null );
		useDismiss( props.open, () => props.setOpen( false ), ref );
		return h( 'div', { className: 'dga-dropdown ' + ( props.className || '' ), ref },
			h( 'button', { type: 'button', className: 'dga-trigger', 'aria-haspopup': 'true', 'aria-expanded': props.open ? 'true' : 'false', 'aria-label': props.label, onClick: () => props.setOpen( ! props.open ) }, props.trigger ),
			props.open ? h( 'div', { className: 'dga-menu', role: props.role || 'menu', 'aria-label': props.label }, props.children ) : null );
	}

	// Sin selector de IA (pedido del dueño del 2026-11-27): el Asistente usa la IA de DIGITALÍSIMO, fija en el servidor.

	/** Las herramientas del MCP por categoría, para encender o apagar grupos; y la bitácora para quien administra. */
	function ToolsMenu( props ) {
		const [ open, setOpen ] = useState( false );
		useEffect( () => {
			if ( open ) {
				props.onLoad();
			}
		}, [ open ] );
		const catalog = props.catalog;
		const categories = catalog ? catalog.categories : [];
		const on = ( id ) => ! props.selected || props.selected.indexOf( id ) >= 0;
		const active = categories.filter( ( c ) => on( c.id ) ).reduce( ( n, c ) => n + c.count, 0 );
		const total = catalog ? catalog.tools.length : 0;
		return h( Dropdown, {
			className: 'dga-tools',
			label: 'Qué puede usar el Asistente',
			open,
			setOpen,
			role: 'group',
			trigger: h( Fragment, null, h( Icon, { name: 'tools' } ), h( 'span', { className: 'dga-trigger-label' }, h( 'strong', null, 'Funciones' ), h( 'small', null, ! catalog || active === total ? 'todas' : active + ' de ' + total ) ) ),
		},
		h( 'p', { className: 'dga-menu-note' }, 'Lo que el Asistente puede usar en tu sitio. Consultar lo hace solo; antes de cambiar algo publicado te pide confirmación. Apaga lo que no quieras que use.' ),
		! catalog ? h( 'p', { className: 'dga-menu-note', role: 'status' }, 'Cargando…' ) : null,
		catalog && ! categories.length ? h( 'p', { className: 'dga-menu-note' }, 'Este sitio todavía no tiene funciones para el Asistente.' ) : null,
		categories.map( ( c ) => h( 'label', { key: c.id, className: 'dga-menu-check' },
			h( 'input', { type: 'checkbox', checked: on( c.id ), onChange: () => props.onToggle( c.id ), 'data-category': c.id } ),
			h( 'span', null, c.label ),
			h( 'small', null, String( c.count ) ) ) ),
		props.canViewLog ? h( 'button', {
			type: 'button',
			className: 'dga-menu-item dga-open-log',
			onClick: () => {
				setOpen( false );
				props.onOpenLog();
			},
		}, h( 'strong', null, 'Ver la bitácora' ), h( 'small', null, 'Todo lo que hizo el Asistente, quién lo pidió y cómo terminó' ) ) : null );
	}

	function Dialog( props ) {
		const ref = useRef( null );
		useDismiss( true, props.onClose, ref );
		useEffect( () => {
			const first = ref.current && ref.current.querySelector( 'button, [href], input' );
			if ( first ) {
				first.focus();
			}
		}, [] );
		return h( 'div', { className: 'dga-dialog-backdrop' },
			h( 'div', { className: 'dga-dialog' + ( props.wide ? ' is-wide' : '' ), role: 'dialog', 'aria-modal': 'true', 'aria-label': props.title, ref },
				h( 'div', { className: 'dga-dialog-head' }, h( 'h2', null, props.title ), h( 'button', { type: 'button', className: 'dga-icon-button dga-close', 'aria-label': 'Cerrar', onClick: props.onClose }, h( Icon, { name: 'close' } ) ) ),
				h( 'div', { className: 'dga-dialog-body' }, props.children ) ) );
	}

	/** AssistantAppearance: acabado Classic o Modern, modo y paleta, como la pantalla de apariencia del tema. */
	function Appearance( props ) {
		const look = props.look;
		const choice = ( key, value, label ) => h( 'button', {
			key: value,
			type: 'button',
			className: 'dga-choice',
			'data-choice': key + ':' + value,
			'aria-pressed': look[ key ] === value ? 'true' : 'false',
			onClick: () => props.setLook( Object.assign( {}, look, { [ key ]: value } ) ),
		}, label );
		return h( Dialog, { title: 'Apariencia', onClose: props.onClose, wide: true },
			h( 'fieldset', { className: 'dga-field' }, h( 'legend', null, 'Acabado' ), h( 'div', { className: 'dga-choices' }, choice( 'variant', 'classic', 'Classic' ), choice( 'variant', 'modern', 'Modern' ) ) ),
			h( 'fieldset', { className: 'dga-field' }, h( 'legend', null, 'Modo' ), h( 'div', { className: 'dga-choices' }, choice( 'mode', 'light', 'Claro' ), choice( 'mode', 'dark', 'Oscuro' ), choice( 'mode', 'system', 'Como el sistema' ) ) ),
			h( 'fieldset', { className: 'dga-field' }, h( 'legend', null, 'Paleta' ), h( 'div', { className: 'dga-swatches' }, SCHEMES.map( ( s ) => h( 'button', {
				key: s[ 0 ],
				type: 'button',
				className: 'dga-swatch',
				'data-color-scheme': s[ 0 ],
				'aria-pressed': look.scheme === s[ 0 ] ? 'true' : 'false',
				onClick: () => props.setLook( Object.assign( {}, look, { scheme: s[ 0 ] } ) ),
			}, h( 'span', { className: 'dga-swatch-dot', 'aria-hidden': 'true', style: s[ 2 ] ? { background: s[ 2 ] } : undefined } ), s[ 1 ] ) ) ) ) );
	}

	function LogDialog( props ) {
		const [ rows, setRows ] = useState( null );
		const [ error, setError ] = useState( '' );
		useEffect( () => {
			adapters.tools.log().then( setRows, ( e ) => setError( e.message ) );
		}, [] );
		let body;
		if ( error ) {
			body = h( 'p', null, error );
		} else if ( ! rows ) {
			body = h( 'p', { role: 'status' }, 'Cargando…' );
		} else if ( ! rows.length ) {
			body = h( 'p', null, 'Todavía no hay llamadas a herramientas.' );
		} else {
			body = h( 'table', { className: 'dga-log' },
				h( 'thead', null, h( 'tr', null, [ 'Fecha (UTC)', 'Usuario', 'Herramienta', 'Clase', 'Entrada', 'Resultado' ].map( ( t ) => h( 'th', { key: t, scope: 'col' }, t ) ) ) ),
				h( 'tbody', null, rows.map( ( r, i ) => h( 'tr', { key: i },
					h( 'td', null, r.time ), h( 'td', null, r.user_name ), h( 'td', null, r.ability ), h( 'td', null, r.kind ),
					h( 'td', null, h( 'code', null, r.input ) ), h( 'td', null, r.result + ( r.detail ? ' · ' + r.detail : '' ) ) ) ) ) );
		}
		return h( Dialog, { title: 'Bitácora del Asistente', onClose: props.onClose, wide: true }, body );
	}

	/**
	 * Lo que el Asistente quiere cambiar, dicho en palabras del dueño (caso plomerito 4: la tarjeta mostraba JSON crudo,
	 * «Agrandar los datos» y una casilla de «no tiene vuelta atrás» para una simple revisión). La frase, el «Puedes
	 * deshacerlo después», lo que no tiene vuelta atrás y la casilla los arma el servidor a partir de lo que se hará;
	 * la casilla sólo aparece en lo que borra o mueve dinero. Los datos completos, con nombres legibles y sin JSON, van
	 * plegados en «Ver detalles» para el soporte. Una acción en curso (o cortada) no se puede confirmar.
	 */
	function ConfirmCard( props ) {
		const action = props.action;
		const [ ack, setAck ] = useState( false );
		const needsAck = !! action.ack;
		const text = action.text || 'Voy a hacer un cambio en tu sitio. Lo que lleva está en «Ver detalles».';
		if ( 'running' === action.state ) {
			return h( 'div', { className: 'dga-confirm is-destructive', role: 'alert', 'data-action': action.id, 'data-state': 'running' },
				h( 'p', { className: 'dga-card-text' }, h( Icon, { name: 'alert' } ), 'Este cambio empezó y no sé si terminó.' ),
				h( 'p', null, text.replace( /^Voy a /, 'Era para ' ) ),
				h( 'p', null, 'Revisa en tu sitio si se hizo antes de pedirlo otra vez.' ),
				props.onRefresh ? h( 'div', { className: 'dga-actions' }, h( 'button', { type: 'button', className: 'dga-button dga-refresh', onClick: props.onRefresh }, 'Actualizar' ) ) : null );
		}
		const warnings = Array.isArray( action.warnings ) ? action.warnings : [];
		const details = Array.isArray( action.details ) ? action.details : [];
		return h( 'div', { className: 'dga-confirm' + ( needsAck ? ' is-destructive' : '' ), role: 'group', 'aria-label': text, 'data-action': action.id, 'data-kind': action.kind },
			h( 'p', { className: 'dga-card-text' }, h( Icon, { name: needsAck ? 'alert' : 'check' } ), h( 'span', null, text ) ),
			action.note ? h( 'p', { className: 'dga-warn dga-note' }, action.note ) : null,
			warnings.length ? h( 'ul', { className: 'dga-warn dga-risks', 'aria-label': 'Ojo' }, warnings.map( ( r, i ) => h( 'li', { key: i }, r ) ) ) : null,
			action.undo ? h( 'p', { className: 'dga-hint dga-undo' }, 'Puedes deshacerlo después.' ) : null,
			action.final ? h( 'p', { className: 'dga-warn dga-final' }, action.final ) : null,
			action.after_read ? h( 'p', { className: 'dga-warn dga-after-read' }, 'Esto lo propuse después de leer textos de tu sitio: mira «Ver detalles» antes de decir que sí.' ) : null,
			needsAck ? h( 'label', { className: 'dga-ack-label' }, h( 'input', { type: 'checkbox', className: 'dga-ack', checked: ack, onChange: ( e ) => setAck( e.target.checked ) } ), action.ack_label || 'Sí, entiendo y quiero hacerlo.' ) : null,
			h( 'div', { className: 'dga-actions' },
				h( 'button', { type: 'button', className: 'dga-button ' + ( needsAck ? 'is-danger' : 'is-primary' ), 'data-decision': 'approve', disabled: props.busy || ( needsAck && ! ack ), onClick: () => props.onDecide( action, 'approve', ack ) }, h( Icon, { name: 'check' } ), 'Sí, hazlo' ),
				h( 'button', { type: 'button', className: 'dga-button', 'data-decision': 'deny', disabled: props.busy, onClick: () => props.onDecide( action, 'deny', false ) }, h( Icon, { name: 'close' } ), 'No' ) ),
			details.length ? h( 'details', { className: 'dga-details', open: !! action.after_read },
				h( 'summary', null, 'Ver detalles' ),
				h( 'dl', null, details.map( ( row, i ) => h( Fragment, { key: i }, h( 'dt', null, String( row[ 0 ] ) ), h( 'dd', null, String( row[ 1 ] ) ) ) ) ) ) : null );
	}

	/** Un paso del trabajo: qué se hizo y cómo salió, en llano (los datos de entrada y de salida quedan en la bitácora; aquí sólo el motivo si algo no salió). */
	function ToolChip( props ) {
		const m = props.message;
		return h( 'details', { className: 'dga-tool', 'data-status': m.status, 'data-kind': m.kind, 'data-ability': m.ability },
			h( 'summary', null, h( Icon, { name: 'tools' } ), h( 'span', null, ( VERBS[ m.kind ] || 'Paso' ) + ': ' + ( m.label || 'una función de tu sitio' ) ), h( 'span', { className: 'dga-tool-state' }, STATES[ m.status ] || m.status ) ),
			m.detail && 'ok' !== m.status ? h( 'p', { className: 'dga-tool-detail' }, m.detail ) : null );
	}

	/**
	 * Lo que se revisó, en palabras de quien usa el sitio: «Revisé tu tienda y tus páginas (19)» o «Preparé el borrador
	 * y revisé tu tienda (5)».
	 */
	const AREAS = [
		[ /^(digitalisimo-woo|woocommerce)\//, 'tu tienda' ],
		[ /^(elementor|digitalisimo-sales|core)\/|^digitalisimo-ia\/read-page-text$/, 'tus páginas' ],
		[ /^digitalisimo-ia\/site-contact$/, 'tus datos de contacto' ],
		[ /seo/, 'cómo te encuentra Google' ],
	];
	const areaOf = ( m ) => {
		const found = AREAS.find( ( a ) => a[ 0 ].test( String( m.ability || '' ) ) );
		return found ? found[ 1 ] : 'tu sitio';
	};
	function workTitle( list ) {
		const tools = list.filter( ( m ) => 'tool' === m.role );
		const reads = tools.filter( ( m ) => 'read' === m.kind );
		const areas = [];
		reads.forEach( ( m ) => {
			if ( areas.indexOf( areaOf( m ) ) < 0 ) {
				areas.push( areaOf( m ) );
			}
		} );
		const seen = areas.length > 1 ? areas.slice( 0, -1 ).join( ', ' ) + ' y ' + areas[ areas.length - 1 ] : areas[ 0 ] || 'tu sitio';
		const drafted = tools.some( ( m ) => 'draft' === m.kind && 'ok' === m.status );
		let head = 'Lo que hice';
		if ( drafted ) {
			head = reads.length ? 'Preparé el borrador y revisé ' + seen : 'Preparé el borrador';
		} else if ( reads.length ) {
			head = 'Revisé ' + seen;
		}
		return head + ' (' + tools.length + ')';
	}

	/** El trabajo de un turno (consultas, borradores y notas de avance), plegado en una sola línea. */
	function WorkGroup( props ) {
		const list = props.items;
		const failed = list.some( ( m ) => 'tool' === m.role && [ 'error', 'denied', 'blocked', 'unknown' ].indexOf( m.status ) >= 0 );
		return h( 'details', { className: 'dga-work', 'data-count': String( list.filter( ( m ) => 'tool' === m.role ).length ) },
			h( 'summary', null, h( Icon, { name: 'check' } ), h( 'span', null, workTitle( list ) ), failed ? h( 'span', { className: 'dga-tool-state', 'data-status': 'unknown' }, 'algo no salió' ) : null ),
			h( 'div', { className: 'dga-work-list' }, list.map( ( m ) => ( 'tool' === m.role
				? h( ToolChip, { key: m.id, message: m } )
				: h( 'p', { key: m.id, className: 'dga-work-note' }, h( RichText, { text: m.text } ) ) ) ) ) );
	}

	/** Agrupa los mensajes: cada tramo de herramientas (con el texto de avance que las acompaña) va en un WorkGroup. */
	function groupMessages( messages ) {
		const ahead = new Array( messages.length ).fill( false );
		let tool = false;
		for ( let i = messages.length - 1; i >= 0; i-- ) {
			if ( 'me' === messages[ i ].role ) {
				tool = false;
			} else if ( 'tool' === messages[ i ].role ) {
				tool = true;
			}
			ahead[ i ] = tool;
		}
		// ¿Hay una respuesta final (un mensaje del Asistente sin llamadas) después, en el mismo turno? Si no, el turno se
		// detuvo en una tarjeta o se cortó: lo que el Asistente escribió junto a sus llamadas es la respuesta y queda a la
		// vista, no plegado (corridas reales del caso plomerito 4: el informe de Skydropx quedaba escondido).
		const later = new Array( messages.length ).fill( false );
		let said = false;
		for ( let i = messages.length - 1; i >= 0; i-- ) {
			later[ i ] = said;
			if ( 'me' === messages[ i ].role ) {
				said = false;
			} else if ( 'assistant' === messages[ i ].role && ! messages[ i ].work ) {
				said = true;
			}
		}
		const out = [];
		messages.forEach( ( m, i ) => {
			const work = 'tool' === m.role || ( 'assistant' === m.role && m.work && ahead[ i ] && later[ i ] && i + 1 < messages.length );
			if ( ! work ) {
				out.push( { key: m.id, message: m } );
				return;
			}
			const last = out[ out.length - 1 ];
			if ( last && last.work ) {
				last.work.push( m );
			} else {
				out.push( { key: 'w-' + m.id, work: [ m ] } );
			}
		} );
		return out;
	}

	/** ChatThread: burbujas, el trabajo plegado, confirmaciones y el indicador de espera. */
	function Thread( props ) {
		return h( 'div', { className: 'dga-thread', role: 'log', 'aria-live': 'polite', 'aria-label': 'Conversación' },
			groupMessages( props.messages ).map( ( item ) => {
				if ( item.work ) {
					return h( WorkGroup, { key: item.key, items: item.work } );
				}
				const m = item.message;
				if ( 'system' === m.role && m.memory && LearnMemory ) {
					return h( LearnMemory, { key: m.id, memory: m.memory } );
				}
				if ( 'system' === m.role ) {
					return h( 'div', { key: m.id, className: 'dga-system' + ( 'error' === m.tone ? ' is-error' : '' ), role: 'error' === m.tone ? 'alert' : undefined }, m.text, m.files ? h( FileThumbs, { files: m.files } ) : null );
				}
				if ( 'tool' === m.role ) {
					return h( ToolChip, { key: m.id, message: m } );
				}
				const me = 'me' === m.role;
				return h( 'article', { key: m.id, className: 'dga-message ' + ( me ? 'is-me' : 'is-assistant' ) },
					me ? null : h( 'span', { className: 'dga-avatar', 'aria-hidden': 'true' }, 'IA' ),
					h( 'div', { className: 'dga-message-column' },
						me ? null : h( 'span', { className: 'dga-author' }, 'Asistente' ),
						h( 'div', { className: 'dga-bubble' }, me ? m.text : h( RichText, { text: m.text } ), me && m.files ? h( FileThumbs, { files: m.files } ) : null ),
						m.time ? h( 'span', { className: 'dga-meta' }, timeOf( m.time ) ) : null ) );
			} ),
			props.pending.map( ( a ) => h( ConfirmCard, { key: a.id, action: a, busy: props.busy, onDecide: props.onDecide, onRefresh: props.onRefresh } ) ),
			props.busy ? h( 'div', { className: 'dga-message is-assistant' },
				h( 'span', { className: 'dga-avatar', 'aria-hidden': 'true' }, 'IA' ),
				h( 'div', { className: 'dga-bubble' }, h( 'span', { className: 'dga-pending', role: 'status' }, h( 'span', { className: 'dga-spinner', 'aria-hidden': 'true' } ), props.status || 'Pensando…' ) ) ) : null );
	}

	/** Lo que espera ir con el próximo mensaje: subiendo, listo (con su miniatura) o con su motivo si no se subió. */
	function Tray( props ) {
		if ( ! props.items.length ) {
			return null;
		}
		return h( 'ul', { className: 'dga-tray', 'aria-label': 'Archivos para tu mensaje' }, props.items.map( ( a ) => {
			const thumb = a.item && 'image' === a.item.kind ? safeUrl( a.item.thumb || a.item.url ) : '';
			return h( 'li', { key: a.key, className: 'dga-tray-item', 'data-status': a.status },
				thumb ? h( 'img', { className: 'dga-file-thumb', src: thumb, alt: '' } ) : h( 'span', { className: 'dga-file-thumb is-icon', 'aria-hidden': 'true' }, 'uploading' === a.status ? h( 'span', { className: 'dga-spinner' } ) : h( Icon, { name: 'error' === a.status ? 'alert' : 'file' } ) ),
				h( 'span', { className: 'dga-file-name' }, a.name ),
				h( 'span', { className: 'dga-file-size' }, 'uploading' === a.status ? 'Subiendo…' : ( 'error' === a.status ? a.error : formatBytes( a.item ? a.item.bytes : a.size ) ) ),
				'uploading' === a.status ? null : h( 'button', { type: 'button', className: 'dga-icon-button is-small dga-tray-remove', 'aria-label': 'Quitar «' + a.name + '» del mensaje', onClick: () => props.onRemove( a.key ) }, h( Icon, { name: 'close' } ) ) );
		} ) );
	}

	/** ChatComposer: Enter envía, Mayús+Enter salta de línea y crece hasta seis líneas; el clip sube archivos. */
	function Composer( props ) {
		const ref = useRef( null );
		const picker = useRef( null );
		useEffect( () => {
			const area = ref.current;
			if ( ! area ) {
				return;
			}
			area.style.height = 'auto';
			const line = parseFloat( window.getComputedStyle( area ).lineHeight ) || 20;
			area.style.height = Math.min( area.scrollHeight, line * 6 + 16 ) + 'px';
		}, [ props.value ] );
		const submit = ( event ) => {
			if ( event ) {
				event.preventDefault();
			}
			if ( ! props.disabled && ( props.value.trim() || props.ready ) ) {
				props.onSubmit();
			}
		};
		return h( 'form', { className: 'dga-composer', onSubmit: submit },
			h( Tray, { items: props.attachments || [], onRemove: props.onRemove } ),
			h( 'label', { className: 'dga-visually-hidden', htmlFor: 'dga-input' }, props.placeholder ),
			h( 'textarea', {
				id: 'dga-input',
				ref,
				className: 'dga-textarea',
				rows: 1,
				maxLength: props.max,
				value: props.value,
				placeholder: props.placeholder,
				onChange: ( e ) => props.onChange( e.target.value ),
				onPaste: ( e ) => {
					const files = e.clipboardData && e.clipboardData.files;
					if ( props.onFiles && files && files.length ) {
						e.preventDefault();
						props.onFiles( files );
					}
				},
				onKeyDown: ( e ) => {
					if ( 'Enter' === e.key && ! e.shiftKey && ! ( e.nativeEvent && e.nativeEvent.isComposing ) ) {
						e.preventDefault();
						submit();
					}
				},
			} ),
			h( 'div', { className: 'dga-composer-actions' },
				h( 'div', { className: 'dga-composer-tools' },
					props.onFiles ? h( Fragment, null,
						h( 'input', { ref: picker, type: 'file', multiple: true, className: 'dga-visually-hidden dga-file-input', tabIndex: -1, 'aria-hidden': 'true', onChange: ( e ) => {
							if ( e.target.files && e.target.files.length ) {
								props.onFiles( e.target.files );
							}
							e.target.value = '';
						} } ),
						h( 'button', { type: 'button', className: 'dga-icon-button dga-attach', 'aria-label': 'Subir archivos a tu biblioteca', title: 'Subir archivos (también puedes arrastrarlos aquí)', onClick: () => picker.current && picker.current.click() }, h( Icon, { name: 'clip' } ) ) ) : null,
					props.hint ? h( 'span', { className: 'dga-composer-hint' }, props.hint ) : null ),
				h( 'button', { type: 'submit', className: 'dga-send', 'aria-label': 'Enviar', disabled: props.disabled || ! ( props.value.trim() || props.ready ) }, h( Icon, { name: 'send' } ) ) ) );
	}

	/** AssistantHistory: «Nueva conversación», búsqueda y la lista por día, con borrar. */
	function History( props ) {
		const [ query, setQuery ] = useState( '' );
		const shown = useMemo( () => {
			const text = query.trim().toLowerCase();
			return text ? props.conversations.filter( ( c ) => String( c.title ).toLowerCase().indexOf( text ) >= 0 ) : props.conversations;
		}, [ query, props.conversations ] );
		const groups = useMemo( () => {
			const order = [];
			const byDay = {};
			shown.forEach( ( c ) => {
				const day = dayOf( c.date );
				if ( ! byDay[ day ] ) {
					byDay[ day ] = [];
					order.push( day );
				}
				byDay[ day ].push( c );
			} );
			return order.map( ( day ) => ( { day, items: byDay[ day ] } ) );
		}, [ shown ] );
		return h( 'div', { className: 'dga-history' },
			h( 'button', { type: 'button', className: 'dga-new', onClick: props.onNew, disabled: props.busy }, h( Icon, { name: 'pen' } ), h( 'span', null, 'Nueva conversación' ) ),
			h( 'label', { className: 'dga-search' }, h( Icon, { name: 'search' } ), h( 'span', { className: 'dga-visually-hidden' }, 'Buscar en el historial' ), h( 'input', { type: 'search', placeholder: 'Buscar en el historial', value: query, onChange: ( e ) => setQuery( e.target.value ) } ) ),
			h( 'div', { className: 'dga-list' },
				groups.map( ( g ) => h( 'div', { key: g.day, className: 'dga-group' },
					h( 'p', { className: 'dga-group-title' }, g.day ),
					h( 'ul', { className: 'dga-items', 'aria-label': g.day }, g.items.map( ( c ) => h( 'li', { key: c.id, className: 'dga-row' + ( c.id === props.activeId ? ' is-active' : '' ) },
						h( 'button', { type: 'button', className: 'dga-item', 'aria-current': c.id === props.activeId ? 'true' : undefined, disabled: props.busy, onClick: () => props.onSelect( c.id ) }, h( 'strong', null, c.title ) ),
						h( 'div', { className: 'dga-row-actions' }, h( 'button', { type: 'button', className: 'dga-icon-button is-small', 'aria-label': 'Borrar «' + c.title + '»', disabled: props.busy, onClick: () => props.onDelete( c ) }, h( Icon, { name: 'trash' } ) ) ) ) ) ) ) ),
				! shown.length ? h( 'div', { className: 'dga-list-empty' }, props.conversations.length ? 'Nada coincide con la búsqueda.' : 'Aquí aparecerán tus conversaciones.' ) : null,
				props.conversations.length ? h( 'div', { className: 'dga-list-footer' }, h( 'button', { type: 'button', className: 'dga-link', onClick: props.onClear, disabled: props.busy }, 'Borrar todo el historial' ) ) : null ) );
	}

	// El aprendizaje («Entrenar con mi sitio web», «Lo que sé de tu sitio»): piezas propias de assistant-learning.js.
	const LearnSlot = window.digitalisimoAssistantLearning && window.digitalisimoAssistantLearning.Slot ? window.digitalisimoAssistantLearning.Slot : () => null;
	const LearnMemory = window.digitalisimoAssistantLearning && window.digitalisimoAssistantLearning.MemoryCard ? window.digitalisimoAssistantLearning.MemoryCard : null;

	function Welcome( props ) {
		return h( 'div', { className: 'dga-welcome' },
			h( 'span', { className: 'dga-welcome-icon' }, h( Icon, { name: 'sparkle' } ) ),
			h( 'h2', null, '¿En qué te ayudo hoy?' ),
			h( 'p', null, 'Pídeme lo que necesites de tu sitio. Primero lo preparo en borrador para que lo veas. Nada se publica ni cambia en tu sitio sin que tú lo confirmes, y si un borrador tiene cambios tuyos, te pregunto antes de rehacerlo.' ),
			h( 'div', { className: 'dga-suggestions' }, SUGGESTIONS.map( ( s ) => h( 'button', { key: s[ 1 ], type: 'button', className: 'dga-suggestion', onClick: () => props.onSuggestion( s[ 0 ] ) }, h( Icon, { name: 'sparkle' } ), s[ 1 ] ) ) ),
			h( LearnSlot, { place: 'welcome' } ) );
	}

	/** AssistantApp: el armazón (historial, barra, hilo y redactor) y el estado de la conversación. */
	function App() {
		const providers = Array.isArray( config.providers ) ? config.providers : [];
		const initial = config.initial || {};
		const [ look, setLookState ] = useState( readLook );
		const [ systemDark, setSystemDark ] = useState( () => media( '(prefers-color-scheme: dark)' ) );
		const [ wide, setWide ] = useState( () => media( '(min-width: 60rem)' ) );
		const [ historyChoice, setHistoryChoice ] = useState( null );
		const [ conversations, setConversations ] = useState( [] );
		const [ activeId, setActiveId ] = useState( initial.newConversation ? 0 : ( initial.conversation || 0 ) );
		const [ title, setTitle ] = useState( '' );
		const [ messages, setMessages ] = useState( [] );
		const [ pending, setPending ] = useState( [] );
		const [ draft, setDraft ] = useState( initial.prompt || '' );
		const [ busy, setBusy ] = useState( false );
		const [ status, setStatus ] = useState( '' );
		const [ catalog, setCatalog ] = useState( null );
		const [ categories, setCategories ] = useState( null );
		const [ dialog, setDialog ] = useState( '' );
		// Archivos para el próximo mensaje: { key, name, size, status: uploading | done | error, item, error }.
		const [ attachments, setAttachments ] = useState( [] );
		const [ dragging, setDragging ] = useState( false );
		const dragDepth = useRef( 0 );
		// Cuántos hay ya en la bandeja, al instante (dos soltadas seguidas no esperan a que se vuelva a pintar).
		const trayCount = useRef( 0 );
		trayCount.current = attachments.filter( ( a ) => 'error' !== a.status ).length;
		const threadRef = useRef( null );
		const activeRef = useRef( activeId );
		// El servidor pausó un turno largo (evento «continue»): la pantalla lo sigue sola, sin que la persona escriba.
		const continueRef = useRef( false );

		const setLook = ( next ) => {
			setLookState( next );
			saveLook( next );
		};
		const theme = 'system' === look.mode ? ( systemDark ? 'dark' : 'light' ) : look.mode;

		useEffect( () => {
			if ( ! window.matchMedia ) {
				return undefined;
			}
			const widthQuery = window.matchMedia( '(min-width: 60rem)' );
			const darkQuery = window.matchMedia( '(prefers-color-scheme: dark)' );
			const onWidth = () => {
				setWide( widthQuery.matches );
				setHistoryChoice( null );
			};
			const onDark = () => setSystemDark( darkQuery.matches );
			widthQuery.addEventListener( 'change', onWidth );
			darkQuery.addEventListener( 'change', onDark );
			return () => {
				widthQuery.removeEventListener( 'change', onWidth );
				darkQuery.removeEventListener( 'change', onDark );
			};
		}, [] );

		const refreshList = useCallback( () => adapters.history.list().then( ( list ) => {
			setConversations( list );
			return list;
		}, () => [] ), [] );

		const open = useCallback( ( id ) => {
			activeRef.current = id;
			setActiveId( id );
			setMessages( [] );
			setPending( [] );
			setTitle( '' );
			if ( ! id ) {
				return Promise.resolve();
			}
			return adapters.history.get( id ).then( ( data ) => {
				if ( activeRef.current !== id || ! data ) {
					return;
				}
				setMessages( Array.isArray( data.messages ) ? data.messages : [] );
				setPending( Array.isArray( data.pending ) ? data.pending : [] );
				setTitle( data.title || '' );
			}, ( e ) => setMessages( [ note( e.message, 'error' ) ] ) );
		}, [] );

		// Al entrar: la conversación pedida, una nueva (desde el MCP o «Nueva») o la más reciente.
		useEffect( () => {
			refreshList().then( ( list ) => {
				if ( initial.conversation && ! initial.newConversation ) {
					open( initial.conversation );
				} else if ( ! initial.newConversation && list.length ) {
					open( list[ 0 ].id );
				}
			} );
		}, [] );

		// La dirección sigue a la conversación abierta, sin el mensaje inicial: recargar no lo vuelve a escribir.
		useEffect( () => {
			try {
				const url = new window.URL( window.location.href );
				url.searchParams.delete( 'prompt' );
				url.searchParams.delete( 'new' );
				if ( activeId ) {
					url.searchParams.set( 'conversation', String( activeId ) );
				} else {
					url.searchParams.delete( 'conversation' );
				}
				window.history.replaceState( null, '', url.toString() );
			} catch ( e ) {
				// Sin History API la dirección no cambia.
			}
		}, [ activeId ] );

		// Autodesplazamiento al último mensaje.
		useEffect( () => {
			const area = threadRef.current;
			if ( area ) {
				area.scrollTop = area.scrollHeight;
			}
		}, [ messages.length, pending.length, busy, status ] );

		const onEvent = ( event ) => {
			if ( ! event || 'object' !== typeof event ) {
				return;
			}
			if ( 'conversation' === event.type ) {
				activeRef.current = event.id;
				setActiveId( event.id );
				setTitle( event.title || '' );
			} else if ( 'status' === event.type ) {
				setStatus( event.text || '' );
			} else if ( ( 'message' === event.type || 'tool' === event.type ) && event.message ) {
				setMessages( ( list ) => list.concat( [ event.message ] ) );
			} else if ( 'confirm' === event.type && event.action ) {
				setPending( ( list ) => list.concat( [ event.action ] ) );
			} else if ( 'continue' === event.type ) {
				continueRef.current = true;
			}
		};

		// Mientras el servidor diga «continue», se pide la continuación del mismo turno (otros adaptadores sin resume: no).
		const follow = () => {
			if ( ! continueRef.current || ! activeRef.current || 'function' !== typeof adapters.model.resume ) {
				continueRef.current = false;
				return null;
			}
			continueRef.current = false;
			setStatus( 'Sigo trabajando…' );
			return adapters.model.resume( { conversation: activeRef.current }, onEvent ).then( follow );
		};

		const run = ( call, onError ) => {
			setBusy( true );
			setStatus( 'Pensando…' );
			continueRef.current = false;
			return call().then( follow ).catch( ( e ) => {
				setMessages( ( list ) => list.concat( [ note( e && e.message ? e.message : 'Error de conexión.', 'error' ) ] ) );
				if ( onError ) {
					onError( e );
				}
			} ).then( () => {
				setBusy( false );
				setStatus( '' );
				refreshList();
			} );
		};

		// Lo que llegó escrito en un enlace (?prompt=, open-assistant) sólo se precarga: nunca se envía solo, y un atajo
		// /herramienta precargado hay que editarlo antes de enviarlo.
		const prefilled = String( initial.prompt || '' ).trim();
		const fromLink = '' !== prefilled && draft.trim() === prefilled;
		const isShortcut = ( value ) => '/' === value.trim().charAt( 0 );
		const uploading = attachments.some( ( a ) => 'uploading' === a.status );
		const ready = attachments.filter( ( a ) => 'done' === a.status && a.item );
		const send = () => {
			const text = draft.trim();
			if ( ( ! text && ! ready.length ) || busy || uploading || ( ! providers.length && ! isShortcut( text ) ) ) {
				return;
			}
			if ( fromLink && isShortcut( text ) ) {
				setMessages( ( list ) => list.concat( [ note( 'Ese atajo llegó escrito en un enlace: edítalo antes de enviarlo.', 'error' ) ] ) );
				return;
			}
			const before = pending;
			const local = 'local-' + Date.now();
			const kept = attachments;
			const files = ready.map( ( a ) => a.item );
			setDraft( '' );
			setPending( [] );
			setAttachments( [] );
			setMessages( ( list ) => list.concat( [ { id: local, role: 'me', text: text || 'Subí estos archivos a la biblioteca.', files: files.length ? files : undefined, time: Math.floor( Date.now() / 1000 ) } ] ) );
			// Ocupado o al límite (409/429): nada se envió; el texto y los archivos vuelven al cuadro y lo pendiente sigue.
			run( () => adapters.model.send( Object.assign( { conversation: activeRef.current || 0, message: text, categories }, files.length ? { files: files.map( ( f ) => f.id ) } : {} ), onEvent ), ( e ) => {
				if ( e && ( 409 === e.status || 429 === e.status ) ) {
					setDraft( text );
					setPending( before );
					setAttachments( kept );
					setMessages( ( list ) => list.filter( ( m ) => m.id !== local ) );
				}
			} );
		};

		const decide = ( action, decision, explicit ) => {
			setPending( ( list ) => list.filter( ( a ) => a.id !== action.id ) );
			// Ya no pendiente, en curso u ocupado (404/409/429): se vuelve a leer la conversación, nunca se repone la tarjeta.
			run( () => adapters.model.confirm( { conversation: activeRef.current, action: action.id, decision, explicit: !! explicit }, onEvent ), ( e ) => {
				if ( e && ( 404 === e.status || 409 === e.status || 429 === e.status ) ) {
					open( activeRef.current );
				} else {
					setPending( ( list ) => list.concat( [ action ] ) );
				}
			} );
		};

		/**
		 * Archivos soltados, pegados o elegidos con el clip: hasta maxFiles por mensaje, el tamaño del servidor revisado
		 * antes de mandar nada, y uno por petición, en orden. Al terminar, el resumen llano con sus miniaturas.
		 */
		const upload = config.upload && adapters.media && 'function' === typeof adapters.media.upload ? config.upload : null;
		const addFiles = ( list ) => {
			const files = Array.prototype.slice.call( list || [] );
			if ( ! files.length ) {
				return;
			}
			if ( ! upload ) {
				setMessages( ( m ) => m.concat( [ note( 'Tu usuario no puede subir archivos a la biblioteca. Pide a quien administra el sitio que te dé ese permiso.', 'error' ) ] ) );
				return;
			}
			const max = Number( upload.maxFiles ) || 10;
			const room = Math.max( 0, max - trayCount.current );
			const take = files.slice( 0, room );
			const left = files.length - take.length;
			if ( left > 0 ) {
				setMessages( ( m ) => m.concat( [ note( 'Puedes subir hasta ' + max + ' archivos por vez: ' + ( 1 === left ? 'dejé fuera 1.' : 'dejé fuera ' + left + '.' ), 'error' ) ] ) );
			}
			const limit = Number( upload.maxBytes ) || 0;
			const entries = take.map( ( file ) => ( {
				key: 'up-' + Date.now() + '-' + ( serial++ ),
				name: String( file.name || 'archivo' ),
				size: Number( file.size ) || 0,
				file,
				status: limit && file.size > limit ? 'error' : 'uploading',
				error: limit && file.size > limit ? 'pesa ' + formatBytes( file.size ) + ' y tu servidor deja subir hasta ' + formatBytes( limit ) + ' por archivo.' : '',
			} ) );
			if ( ! entries.length ) {
				return;
			}
			trayCount.current += entries.filter( ( a ) => 'error' !== a.status ).length;
			setAttachments( ( current ) => current.concat( entries.map( ( a ) => Object.assign( {}, a, { file: undefined } ) ) ) );
			const done = [];
			const failed = entries.filter( ( a ) => 'error' === a.status ).map( ( a ) => ( { name: a.name, message: a.error } ) );
			const patch = ( key, change ) => setAttachments( ( current ) => current.map( ( a ) => ( a.key === key ? Object.assign( {}, a, change ) : a ) ) );
			entries.filter( ( a ) => 'uploading' === a.status ).reduce( ( chain, a ) => chain.then( () => adapters.media.upload( a.file ).then( ( data ) => {
				const item = data && Array.isArray( data.files ) ? data.files[ 0 ] : null;
				if ( item ) {
					done.push( item );
					patch( a.key, { status: 'done', item } );
					return;
				}
				const why = data && Array.isArray( data.errors ) && data.errors[ 0 ] ? data.errors[ 0 ].message : 'no se pudo subir.';
				failed.push( { name: a.name, message: why } );
				patch( a.key, { status: 'error', error: why } );
			}, ( e ) => {
				const why = e && e.message ? e.message : 'no se pudo subir.';
				failed.push( { name: a.name, message: why } );
				patch( a.key, { status: 'error', error: why } );
			} ) ), Promise.resolve() ).then( () => {
				const summary = uploadSummary( done, failed );
				if ( summary ) {
					const shown = note( summary, done.length ? 'info' : 'error' );
					shown.files = done.length ? done : undefined;
					setMessages( ( m ) => m.concat( [ shown ] ) );
				}
			} );
		};
		const removeAttachment = ( key ) => setAttachments( ( current ) => current.filter( ( a ) => a.key !== key ) );
		const hasFiles = ( event ) => {
			const types = event.dataTransfer && event.dataTransfer.types;
			return !! types && Array.prototype.indexOf.call( types, 'Files' ) >= 0;
		};
		const drop = upload ? {
			onDragEnter: ( event ) => {
				if ( hasFiles( event ) ) {
					event.preventDefault();
					dragDepth.current++;
					setDragging( true );
				}
			},
			onDragOver: ( event ) => {
				if ( hasFiles( event ) ) {
					event.preventDefault();
					if ( event.dataTransfer ) {
						event.dataTransfer.dropEffect = 'copy';
					}
				}
			},
			onDragLeave: ( event ) => {
				if ( hasFiles( event ) ) {
					dragDepth.current = Math.max( 0, dragDepth.current - 1 );
					if ( ! dragDepth.current ) {
						setDragging( false );
					}
				}
			},
			onDrop: ( event ) => {
				if ( ! hasFiles( event ) ) {
					return;
				}
				event.preventDefault();
				dragDepth.current = 0;
				setDragging( false );
				addFiles( event.dataTransfer.files );
			},
		} : {};

		const startNew = () => {
			activeRef.current = 0;
			setActiveId( 0 );
			setMessages( [] );
			setPending( [] );
			setTitle( '' );
			setDraft( '' );
			setAttachments( [] );
			if ( ! wide ) {
				setHistoryChoice( false );
			}
		};
		const choose = ( id ) => {
			open( id );
			if ( ! wide ) {
				setHistoryChoice( false );
			}
		};
		const remove = ( c ) => {
			if ( ! window.confirm( '¿Borrar la conversación «' + c.title + '»?' ) ) {
				return;
			}
			adapters.history.remove( c.id ).then( () => {
				if ( c.id === activeRef.current ) {
					startNew();
				}
				refreshList();
			}, ( e ) => setMessages( ( list ) => list.concat( [ note( e.message, 'error' ) ] ) ) );
		};
		const clear = () => {
			if ( ! window.confirm( '¿Borrar todo tu historial del Asistente en este sitio?' ) ) {
				return;
			}
			adapters.history.clear().then( () => {
				startNew();
				refreshList();
			}, ( e ) => setMessages( ( list ) => list.concat( [ note( e.message, 'error' ) ] ) ) );
		};
		const loadCatalog = () => {
			if ( ! catalog ) {
				adapters.tools.list().then( ( data ) => setCatalog( data && Array.isArray( data.tools ) ? data : { tools: [], categories: [] } ), () => setCatalog( { tools: [], categories: [] } ) );
			}
		};
		const toggleCategory = ( id ) => {
			const all = catalog ? catalog.categories.map( ( c ) => c.id ) : [];
			const current = categories || all;
			const next = current.indexOf( id ) >= 0 ? current.filter( ( x ) => x !== id ) : current.concat( [ id ] );
			setCategories( next.length === all.length ? null : next );
		};

		const historyOpen = null === historyChoice ? wide : historyChoice;
		const historyState = null === historyChoice ? 'auto' : ( historyChoice ? 'open' : 'closed' );
		const site = config.site || {};
		const siteName = String( site.name || '' ).trim();
		const mark = ( siteName || 'Asistente' ).charAt( 0 ).toUpperCase();
		const noAi = ! providers.length;
		const showThread = messages.length || pending.length || busy;

		// Se puede soltar en cualquier parte del Asistente (el chat, el cuadro de texto o el historial): nunca abre el archivo.
		return h( 'div', Object.assign( { className: 'dga-root', 'data-history': historyState, 'data-theme': theme, 'data-interface-variant': look.variant, 'data-color-scheme': look.scheme }, drop ),
			h( 'aside', { className: 'dga-side', id: 'dga-history', 'aria-label': 'Tus conversaciones' },
				h( 'div', { className: 'dga-brand' },
					site.logo ? h( 'img', { className: 'dga-logo', src: site.logo, alt: '' } ) : h( 'span', { className: 'dga-mark', 'aria-hidden': 'true' }, mark ),
					h( 'span', { className: 'dga-brand-text' }, h( 'span', { className: 'dga-brand-name' }, 'Asistente IA' ), siteName ? h( 'span', { className: 'dga-brand-site' }, siteName ) : null ) ),
				h( History, { conversations, activeId, busy, onSelect: choose, onNew: startNew, onDelete: remove, onClear: clear } ) ),
			historyOpen && ! wide ? h( 'button', { type: 'button', className: 'dga-backdrop', 'aria-label': 'Ocultar el historial', onClick: () => setHistoryChoice( false ) } ) : null,
			h( 'div', { className: 'dga-main' + ( dragging ? ' is-dropping' : '' ) },
				dragging ? h( 'div', { className: 'dga-drop', 'aria-hidden': 'true' }, h( Icon, { name: 'upload' } ), h( 'strong', null, 'Suelta aquí tus archivos' ), h( 'span', null, 'Se suben a tu biblioteca y las imágenes se comprimen.' ) ) : null,
				h( 'header', { className: 'dga-top' },
					h( 'button', { type: 'button', className: 'dga-icon-button', 'aria-controls': 'dga-history', 'aria-expanded': historyOpen ? 'true' : 'false', 'aria-label': historyOpen ? 'Ocultar el historial' : 'Mostrar el historial', onClick: () => setHistoryChoice( ! historyOpen ) }, h( Icon, { name: 'sidebar' } ) ),
					h( 'span', { className: 'dga-bar-mark', 'aria-hidden': 'true' }, mark ),
					h( 'h1', { className: 'dga-title' }, title || 'Asistente IA' ),
					h( 'div', { className: 'dga-toolbar' },
						h( ToolsMenu, { catalog, selected: categories, onToggle: toggleCategory, onLoad: loadCatalog, canViewLog: !! config.canViewLog, onOpenLog: () => setDialog( 'log' ) } ) ),
					h( 'div', { className: 'dga-trailing' }, h( LearnSlot, { place: 'header' } ), h( 'button', { type: 'button', className: 'dga-icon-button is-outline dga-appearance', 'aria-label': 'Apariencia', onClick: () => setDialog( 'appearance' ) }, h( Icon, { name: 'palette' } ) ) ) ),
				noAi ? h( 'div', { className: 'dga-notice-area' }, h( 'div', { className: 'dga-notice', role: 'alert' }, h( Icon, { name: 'alert' } ), h( 'span', null, 'El Asistente IA aún no está activo en este sitio.' ), config.settingsUrl ? h( 'a', { href: config.settingsUrl }, 'Revisar la conexión' ) : h( 'span', null, 'Pide a quien administra el sitio que lo active.' ) ) ) : null,
				h( 'div', { className: 'dga-thread-area', ref: threadRef },
					h( 'div', { className: 'dga-column' }, showThread
						? h( Thread, { messages, pending, busy, status, onDecide: decide, onRefresh: () => open( activeRef.current ) } )
						: h( Welcome, { onSuggestion: setDraft } ) ) ),
				h( 'div', { className: 'dga-composer-area' },
					h( 'div', { className: 'dga-column' },
						h( Composer, { value: draft, onChange: setDraft, onSubmit: send, disabled: busy || ( noAi && ! isShortcut( draft ) ) || uploading, ready: ready.length > 0, attachments, onFiles: upload ? addFiles : null, onRemove: removeAttachment, placeholder: noAi ? 'El Asistente aún no está activo: usa /ayuda para los atajos' : ( ready.length ? 'Dime qué hago con estos archivos…' : 'Escribe una instrucción…' ), max: ( config.limits && config.limits.message ) || 4000, hint: fromLink ? 'Este mensaje vino en un enlace: revísalo antes de enviarlo.' : ( pending.some( ( a ) => 'running' !== a.state ) ? 'Hay una acción esperando tu confirmación.' : ( uploading ? 'Subiendo archivos…' : null ) ) } ),
						h( 'p', { className: 'dga-footnote' }, 'El Asistente puede equivocarse: revisa lo importante antes de publicar. Lo que consulta de tu sitio (también datos de clientes y pedidos) se envía a la IA que te responde.' ) ) ) ),
			'appearance' === dialog ? h( Appearance, { look, setLook, onClose: () => setDialog( '' ) } ) : null,
			'log' === dialog ? h( LogDialog, { onClose: () => setDialog( '' ) } ) : null );
	}

	// Para la prueba sin navegador (tests/ia-assistant-mostrar-ui.mjs): las piezas puras, sólo si la prueba las pide.
	if ( 'function' === typeof window.digitalisimoAssistantExpose ) {
		window.digitalisimoAssistantExpose( { groupMessages, workTitle, richParts, VERBS, STATES, SUGGESTIONS, formatBytes, uploadSummary } );
	}

	const app = h( App );
	if ( 'function' === typeof el.createRoot ) {
		el.createRoot( mount ).render( app );
	} else {
		el.render( app, mount );
	}
}( window.wp, window, document ) );
