/**
 * Asistente IA de DIGITALÍSIMO IA Tools (docs/ASSISTANT.md): la interfaz del chat asistente de @digitalisimo/ui
 * (AssistantApp, AssistantHistory, AssistantWelcome, ChatThread, ChatComposer, AssistantAiSwitcher y
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
		cpu: 'M7 7h10v10H7z M10 3v4 M14 3v4 M10 17v4 M14 17v4 M3 10h4 M3 14h4 M17 10h4 M17 14h4',
		magic: 'M5 19L15 9 M14 3l1 2 2 1-2 1-1 2-1-2-2-1 2-1z M19 10l.7 1.3 1.3.7-1.3.7L19 14l-.7-1.3-1.3-.7 1.3-.7z',
		palette: 'M12 3a9 9 0 1 0 0 18c1 0 1.5-.8 1.5-1.6 0-1-.8-1.4-.8-2.4 0-.9.7-1.5 1.6-1.5H17a4 4 0 0 0 4-4c0-4.7-4-8.5-9-8.5z M7.5 11.5h.01 M10 7.5h.01 M14.5 7.5h.01',
		tools: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
		send: 'M4 12l16-8-6 16-2.5-6.5z M11.5 13.5L20 4',
		trash: 'M4 7h16 M9 7V4h6v3 M6 7l1 13h10l1-13',
		check: 'M5 12l5 5 9-10',
		close: 'M6 6l12 12 M18 6L6 18',
		alert: 'M12 3l10 18H2z M12 10v5 M12 18h.01',
		sparkle: 'M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2z',
	};
	const AI_NAMES = { openai: 'ChatGPT', anthropic: 'Claude', google: 'Gemini', xai: 'Grok', deepseek: 'DeepSeek' };
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
	const VERBS = { read: 'Consulté', draft: 'Borrador', write: 'Cambio', destructive: 'Cambio importante', unknown: 'Paso' };
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
	const aiNameOf = ( provider ) => provider.aiName || AI_NAMES[ provider.key ] || provider.name;
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

	/** AssistantAiSwitcher: Auto (IA Tools decide) o una IA por el nombre con que la gente la conoce. Nunca un modelo. */
	function AiSwitcher( props ) {
		const [ open, setOpen ] = useState( false );
		const current = props.providers.find( ( p ) => p.key === props.value ) || null;
		const name = current ? aiNameOf( current ) : 'IA automática';
		const item = ( key, title, hint, active, disabled, select ) => h( 'button', {
			key,
			type: 'button',
			role: 'menuitemradio',
			'aria-checked': active ? 'true' : 'false',
			className: 'dga-menu-item' + ( active ? ' is-active' : '' ),
			disabled,
			onClick: () => {
				select();
				setOpen( false );
			},
		}, h( 'strong', null, title ), hint ? h( 'small', null, hint ) : null );
		return h( Dropdown, {
			className: 'dga-ai',
			label: 'IA: ' + name,
			open,
			setOpen,
			trigger: h( Fragment, null, h( Icon, { name: current ? 'cpu' : 'magic' } ), h( 'span', { className: 'dga-trigger-label' }, h( 'strong', null, name ), current ? null : h( 'small', null, 'la mejor disponible' ) ) ),
		},
		item( 'auto', 'Automática', 'La mejor disponible', ! current, false, () => props.onChange( null ) ),
		props.providers.map( ( p ) => item( p.key, aiNameOf( p ), 'unavailable' === p.status ? 'No disponible' : '', !! current && current.key === p.key, 'unavailable' === p.status, () => props.onChange( p.key ) ) ) );
	}

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
	 * Lo que el modelo quiere cambiar: qué y con exactamente qué datos, completos y con los invisibles marcados, más un
	 * resumen de lo arriesgado. «Confirmar» se enciende sólo cuando los datos caben enteros o se desplazaron hasta el
	 * final. Lo destructivo pide además una casilla explícita. Una acción en curso (o cortada) no se puede confirmar.
	 */
	function ConfirmCard( props ) {
		const action = props.action;
		const [ ack, setAck ] = useState( false );
		const [ seen, setSeen ] = useState( false );
		const [ full, setFull ] = useState( false );
		const box = useRef( null );
		const destructive = 'destructive' === action.kind;
		const check = () => {
			const el = box.current;
			if ( el && el.scrollTop + el.clientHeight >= el.scrollHeight - 4 ) {
				setSeen( true );
			}
		};
		useEffect( check, [ full, action.input ] );
		if ( 'running' === action.state ) {
			return h( 'div', { className: 'dga-confirm is-destructive', role: 'alert', 'data-action': action.id, 'data-state': 'running' },
				h( 'h3', null, h( Icon, { name: 'alert' } ), 'En curso o cortada' ),
				h( 'p', null, h( 'strong', null, action.label ), ' empezó y no sabemos si terminó. No se puede confirmar otra vez: revisa en tu sitio si se hizo antes de pedirlo de nuevo.' ),
				props.onRefresh ? h( 'div', { className: 'dga-actions' }, h( 'button', { type: 'button', className: 'dga-button dga-refresh', onClick: props.onRefresh }, 'Actualizar' ) ) : null );
		}
		const risks = Array.isArray( action.risks ) ? action.risks : [];
		return h( 'div', { className: 'dga-confirm' + ( destructive ? ' is-destructive' : '' ), role: 'group', 'aria-label': 'Confirmación: ' + action.label, 'data-action': action.id, 'data-kind': action.kind },
			h( 'h3', null, h( Icon, { name: destructive ? 'alert' : 'check' } ), destructive ? 'Este cambio no tiene vuelta atrás: confírmalo' : 'Confirma este cambio' ),
			h( 'p', null, h( 'strong', null, action.label ) ),
			action.note ? h( 'p', { className: 'dga-warn dga-note' }, action.note ) : null,
			action.after_read ? h( 'p', { className: 'dga-warn' }, 'Revisa que sea exactamente lo que pediste antes de confirmar.' ) : null,
			risks.length ? h( 'ul', { className: 'dga-warn dga-risks', 'aria-label': 'Lo arriesgado de esta acción' }, risks.map( ( r, i ) => h( 'li', { key: i }, r ) ) ) : null,
			action.idempotent ? null : h( 'p', { className: 'dga-warn' }, 'Si falla, puede haberse aplicado igual: no la repitas sin revisar.' ),
			h( 'p', { className: 'dga-hint' }, 'Datos exactos que se usarán:' ),
			h( 'pre', { className: 'dga-input' + ( full ? ' is-full' : '' ), ref: box, onScroll: check, 'aria-label': 'Datos completos de la acción', tabIndex: 0 }, action.input ),
			seen ? null : h( 'p', { className: 'dga-hint' }, 'Desplázate hasta el final de los datos para poder confirmar.' ),
			full ? null : h( 'button', { type: 'button', className: 'dga-button dga-see-all', onClick: () => setFull( true ) }, 'Agrandar los datos (' + String( action.input ).length + ' caracteres)' ),
			action.simulation ? h( Fragment, null, h( 'p', null, 'Simulación (no cambió nada):' ), h( 'pre', null, action.simulation ) ) : null,
			destructive ? h( 'label', null, h( 'input', { type: 'checkbox', className: 'dga-ack', checked: ack, onChange: ( e ) => setAck( e.target.checked ) } ), 'Entiendo que puede no tener vuelta atrás y quiero hacerlo.' ) : null,
			h( 'div', { className: 'dga-actions' },
				h( 'button', { type: 'button', className: 'dga-button ' + ( destructive ? 'is-danger' : 'is-primary' ), 'data-decision': 'approve', disabled: props.busy || ! seen || ( destructive && ! ack ), onClick: () => props.onDecide( action, 'approve', ack ) }, h( Icon, { name: 'check' } ), destructive ? 'Sí, hacerlo' : 'Confirmar' ),
				h( 'button', { type: 'button', className: 'dga-button', 'data-decision': 'deny', disabled: props.busy, onClick: () => props.onDecide( action, 'deny', false ) }, h( Icon, { name: 'close' } ), 'Cancelar' ) ) );
	}

	function ToolChip( props ) {
		const m = props.message;
		const shown = m.summary && '[]' !== m.summary && '{}' !== m.summary;
		return h( 'details', { className: 'dga-tool', 'data-status': m.status, 'data-kind': m.kind, 'data-ability': m.ability },
			h( 'summary', null, h( Icon, { name: 'tools' } ), h( 'span', null, ( VERBS[ m.kind ] || 'Herramienta' ) + ': ' + ( m.label || m.ability ) ), h( 'span', { className: 'dga-tool-state' }, STATES[ m.status ] || m.status ) ),
			shown ? h( 'pre', { 'aria-label': 'Entrada' }, m.summary ) : null,
			m.detail ? h( 'pre', { 'aria-label': 'Resultado' }, m.detail ) : null );
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
		const out = [];
		messages.forEach( ( m, i ) => {
			const work = 'tool' === m.role || ( 'assistant' === m.role && m.work && ahead[ i ] && i + 1 < messages.length );
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
				if ( 'system' === m.role ) {
					return h( 'div', { key: m.id, className: 'dga-system' + ( 'error' === m.tone ? ' is-error' : '' ), role: 'error' === m.tone ? 'alert' : undefined }, m.text );
				}
				if ( 'tool' === m.role ) {
					return h( ToolChip, { key: m.id, message: m } );
				}
				const me = 'me' === m.role;
				return h( 'article', { key: m.id, className: 'dga-message ' + ( me ? 'is-me' : 'is-assistant' ) },
					me ? null : h( 'span', { className: 'dga-avatar', 'aria-hidden': 'true' }, 'IA' ),
					h( 'div', { className: 'dga-message-column' },
						me ? null : h( 'span', { className: 'dga-author' }, 'Asistente' ),
						h( 'div', { className: 'dga-bubble' }, me ? m.text : h( RichText, { text: m.text } ) ),
						m.time ? h( 'span', { className: 'dga-meta' }, timeOf( m.time ) ) : null ) );
			} ),
			props.pending.map( ( a ) => h( ConfirmCard, { key: a.id, action: a, busy: props.busy, onDecide: props.onDecide, onRefresh: props.onRefresh } ) ),
			props.busy ? h( 'div', { className: 'dga-message is-assistant' },
				h( 'span', { className: 'dga-avatar', 'aria-hidden': 'true' }, 'IA' ),
				h( 'div', { className: 'dga-bubble' }, h( 'span', { className: 'dga-pending', role: 'status' }, h( 'span', { className: 'dga-spinner', 'aria-hidden': 'true' } ), props.status || 'Pensando…' ) ) ) : null );
	}

	/** ChatComposer: Enter envía, Mayús+Enter salta de línea y crece hasta seis líneas. */
	function Composer( props ) {
		const ref = useRef( null );
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
			if ( ! props.disabled && props.value.trim() ) {
				props.onSubmit();
			}
		};
		return h( 'form', { className: 'dga-composer', onSubmit: submit },
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
				onKeyDown: ( e ) => {
					if ( 'Enter' === e.key && ! e.shiftKey && ! ( e.nativeEvent && e.nativeEvent.isComposing ) ) {
						e.preventDefault();
						submit();
					}
				},
			} ),
			h( 'div', { className: 'dga-composer-actions' },
				h( 'div', { className: 'dga-composer-tools' }, props.hint || null ),
				h( 'button', { type: 'submit', className: 'dga-send', 'aria-label': 'Enviar', disabled: props.disabled || ! props.value.trim() }, h( Icon, { name: 'send' } ) ) ) );
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

	function Welcome( props ) {
		return h( 'div', { className: 'dga-welcome' },
			h( 'span', { className: 'dga-welcome-icon' }, h( Icon, { name: 'sparkle' } ) ),
			h( 'h2', null, '¿En qué te ayudo hoy?' ),
			h( 'p', null, 'Pídeme lo que necesites de tu sitio. Primero lo preparo en borrador para que lo veas. Nada se publica ni cambia en tu sitio sin que tú lo confirmes, y si un borrador tiene cambios tuyos, te pregunto antes de rehacerlo.' ),
			h( 'div', { className: 'dga-suggestions' }, SUGGESTIONS.map( ( s ) => h( 'button', { key: s[ 1 ], type: 'button', className: 'dga-suggestion', onClick: () => props.onSuggestion( s[ 0 ] ) }, h( Icon, { name: 'sparkle' } ), s[ 1 ] ) ) ) );
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
		const [ ai, setAi ] = useState( null );
		const [ catalog, setCatalog ] = useState( null );
		const [ categories, setCategories ] = useState( null );
		const [ dialog, setDialog ] = useState( '' );
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
				setAi( data.provider && providers.some( ( p ) => p.key === data.provider ) ? data.provider : null );
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
		const send = () => {
			const text = draft.trim();
			if ( ! text || busy || ( ! providers.length && ! isShortcut( text ) ) ) {
				return;
			}
			if ( fromLink && isShortcut( text ) ) {
				setMessages( ( list ) => list.concat( [ note( 'Ese atajo llegó escrito en un enlace: edítalo antes de enviarlo.', 'error' ) ] ) );
				return;
			}
			const before = pending;
			const local = 'local-' + Date.now();
			setDraft( '' );
			setPending( [] );
			setMessages( ( list ) => list.concat( [ { id: local, role: 'me', text, time: Math.floor( Date.now() / 1000 ) } ] ) );
			// Ocupado o al límite (409/429): nada se envió; el texto vuelve al cuadro y lo pendiente sigue como estaba.
			run( () => adapters.model.send( { conversation: activeRef.current || 0, message: text, provider: ai || 'auto', categories }, onEvent ), ( e ) => {
				if ( e && ( 409 === e.status || 429 === e.status ) ) {
					setDraft( text );
					setPending( before );
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

		const startNew = () => {
			activeRef.current = 0;
			setActiveId( 0 );
			setMessages( [] );
			setPending( [] );
			setTitle( '' );
			setDraft( '' );
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

		return h( 'div', { className: 'dga-root', 'data-history': historyState, 'data-theme': theme, 'data-interface-variant': look.variant, 'data-color-scheme': look.scheme },
			h( 'aside', { className: 'dga-side', id: 'dga-history', 'aria-label': 'Tus conversaciones' },
				h( 'div', { className: 'dga-brand' },
					site.logo ? h( 'img', { className: 'dga-logo', src: site.logo, alt: '' } ) : h( 'span', { className: 'dga-mark', 'aria-hidden': 'true' }, mark ),
					h( 'span', { className: 'dga-brand-text' }, h( 'span', { className: 'dga-brand-name' }, 'Asistente IA' ), siteName ? h( 'span', { className: 'dga-brand-site' }, siteName ) : null ) ),
				h( History, { conversations, activeId, busy, onSelect: choose, onNew: startNew, onDelete: remove, onClear: clear } ) ),
			historyOpen && ! wide ? h( 'button', { type: 'button', className: 'dga-backdrop', 'aria-label': 'Ocultar el historial', onClick: () => setHistoryChoice( false ) } ) : null,
			h( 'div', { className: 'dga-main' },
				h( 'header', { className: 'dga-top' },
					h( 'button', { type: 'button', className: 'dga-icon-button', 'aria-controls': 'dga-history', 'aria-expanded': historyOpen ? 'true' : 'false', 'aria-label': historyOpen ? 'Ocultar el historial' : 'Mostrar el historial', onClick: () => setHistoryChoice( ! historyOpen ) }, h( Icon, { name: 'sidebar' } ) ),
					h( 'span', { className: 'dga-bar-mark', 'aria-hidden': 'true' }, mark ),
					h( 'h1', { className: 'dga-title' }, title || 'Asistente IA' ),
					h( 'div', { className: 'dga-toolbar' },
						h( AiSwitcher, { providers, value: ai, onChange: setAi } ),
						h( ToolsMenu, { catalog, selected: categories, onToggle: toggleCategory, onLoad: loadCatalog, canViewLog: !! config.canViewLog, onOpenLog: () => setDialog( 'log' ) } ) ),
					h( 'div', { className: 'dga-trailing' }, h( 'button', { type: 'button', className: 'dga-icon-button is-outline dga-appearance', 'aria-label': 'Apariencia', onClick: () => setDialog( 'appearance' ) }, h( Icon, { name: 'palette' } ) ) ) ),
				noAi ? h( 'div', { className: 'dga-notice-area' }, h( 'div', { className: 'dga-notice', role: 'alert' }, h( Icon, { name: 'alert' } ), h( 'span', null, 'No hay ninguna IA configurada en IA Tools.' ), config.settingsUrl ? h( 'a', { href: config.settingsUrl }, 'Configurar un proveedor' ) : h( 'span', null, 'Pide a quien administra el sitio que active un proveedor.' ) ) ) : null,
				h( 'div', { className: 'dga-thread-area', ref: threadRef },
					h( 'div', { className: 'dga-column' }, showThread
						? h( Thread, { messages, pending, busy, status, onDecide: decide, onRefresh: () => open( activeRef.current ) } )
						: h( Welcome, { onSuggestion: setDraft } ) ) ),
				h( 'div', { className: 'dga-composer-area' },
					h( 'div', { className: 'dga-column' },
						h( Composer, { value: draft, onChange: setDraft, onSubmit: send, disabled: busy || ( noAi && ! isShortcut( draft ) ), placeholder: noAi ? 'Sin IA configurada: usa /ayuda para los atajos' : 'Escribe una instrucción…', max: ( config.limits && config.limits.message ) || 4000, hint: fromLink ? 'Este mensaje vino en un enlace: revísalo antes de enviarlo.' : ( pending.some( ( a ) => 'running' !== a.state ) ? 'Hay una acción esperando tu confirmación.' : null ) } ),
						h( 'p', { className: 'dga-footnote' }, 'El Asistente puede equivocarse: revisa lo importante antes de publicar. Lo que consulta de tu sitio (también datos de clientes y pedidos) se envía a la IA que te responde.' ) ) ) ),
			'appearance' === dialog ? h( Appearance, { look, setLook, onClose: () => setDialog( '' ) } ) : null,
			'log' === dialog ? h( LogDialog, { onClose: () => setDialog( '' ) } ) : null );
	}

	// Para la prueba sin navegador (tests/ia-assistant-mostrar-ui.mjs): las piezas puras, sólo si la prueba las pide.
	if ( 'function' === typeof window.digitalisimoAssistantExpose ) {
		window.digitalisimoAssistantExpose( { groupMessages, workTitle, richParts, VERBS, STATES, SUGGESTIONS } );
	}

	const app = h( App );
	if ( 'function' === typeof el.createRoot ) {
		el.createRoot( mount ).render( app );
	} else {
		el.render( app, mount );
	}
}( window.wp, window, document ) );
