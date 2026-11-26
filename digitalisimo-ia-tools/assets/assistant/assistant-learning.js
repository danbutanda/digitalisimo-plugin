/**
 * El aprendizaje del Asistente IA (docs/ASSISTANT.md, «Lo que sé de tu sitio»): el botón «Entrenar con mi sitio web» de
 * la bienvenida y el panel con lo aprendido, para quien administra el sitio (config.learning). Piezas propias, con su
 * hoja (assistant-learning.css, clases .dga-learn-*), para no tocar el armazón de assistant.js: éste sólo pinta
 * window.digitalisimoAssistantLearning.Slot en la bienvenida (place: 'welcome') y en la barra (place: 'header').
 *
 * Habla con el servidor por un adaptador con el mismo estilo que assistant-adapters.js: si el conjunto de adaptadores
 * trae `learning` ({ view(), train(), step(), conversations(), edit( id, text ), remove( id ), clear() }), se usa ése;
 * si no, el incluido aquí (REST digitalisimo-ai/v1/assistant/learning, con los dos nonces). Lo que llega del servidor se
 * pinta como texto (hijos de React, nunca HTML).
 */
( function ( wp, window, document ) {
	'use strict';
	if ( ! wp || ! wp.element ) {
		return;
	}
	const el = wp.element;
	const h = el.createElement;
	const { useState, useEffect, useRef } = el;
	const config = window.digitalisimoAssistantConfig || {};

	function createLearningAdapter( cfg ) {
		const base = String( cfg.rest || '' ) + 'learning';
		const headers = { 'X-WP-Nonce': String( cfg.restNonce || '' ), 'X-Digitalisimo-Assistant': String( cfg.nonce || '' ), Accept: 'application/json' };
		const request = ( path, method, body ) => {
			const options = { method: method || 'GET', credentials: 'same-origin', headers: Object.assign( {}, headers ) };
			if ( undefined !== body ) {
				options.headers[ 'Content-Type' ] = 'application/json';
				options.body = JSON.stringify( body );
			}
			return window.fetch( base + path, options ).then( ( response ) => response.json().catch( () => null ).then( ( data ) => {
				if ( ! response.ok ) {
					const error = new Error( data && 'string' === typeof data.message ? data.message : 'Error ' + response.status );
					error.status = response.status;
					throw error;
				}
				return data;
			} ) );
		};
		return {
			view: () => request( '' ),
			train: () => request( '/train', 'POST', {} ),
			step: () => request( '/step', 'POST', {} ),
			conversations: () => request( '/conversations', 'POST', {} ),
			edit: ( id, text ) => request( '/items/' + encodeURIComponent( id ), 'POST', { text } ),
			remove: ( id ) => request( '/items/' + encodeURIComponent( id ) + '/delete', 'POST', {} ),
			clear: () => request( '/clear', 'POST', {} ),
		};
	}

	let adapter = null;
	const api = () => {
		if ( ! adapter ) {
			const set = window.digitalisimoAssistantAdapters && 'function' === typeof window.digitalisimoAssistantAdapters.create ? window.digitalisimoAssistantAdapters.create( config ) : null;
			adapter = set && set.learning ? set.learning : createLearningAdapter( config );
		}
		return adapter;
	};

	// Un solo estado para la tarjeta de la bienvenida, el botón de la barra y el panel.
	const store = { view: null, open: false, busy: '', note: '', error: '', listeners: [] };
	const emit = () => store.listeners.slice().forEach( ( fn ) => fn() );
	const set = ( patch ) => {
		Object.assign( store, patch );
		emit();
	};
	function useStore() {
		const [ , force ] = useState( 0 );
		useEffect( () => {
			const fn = () => force( ( n ) => n + 1 );
			store.listeners.push( fn );
			return () => {
				store.listeners = store.listeners.filter( ( x ) => x !== fn );
			};
		}, [] );
		return store;
	}

	let loading = false;
	const load = () => {
		if ( loading ) {
			return Promise.resolve( store.view );
		}
		loading = true;
		return api().view().then( ( view ) => {
			set( { view, error: '' } );
			if ( view && view.job && 'running' === view.job.status ) {
				follow();
			}
			return view;
		}, ( e ) => set( { error: e.message } ) ).then( ( v ) => {
			loading = false;
			return v;
		} );
	};

	// El entrenamiento avanza paso a paso: la pantalla pide el siguiente mientras siga «running» (y si se cierra, sigue solo).
	let following = false;
	const follow = () => {
		if ( following ) {
			return;
		}
		following = true;
		set( { busy: 'train' } );
		const next = () => api().step().then( ( view ) => {
			set( { view } );
			if ( view && view.job && 'running' === view.job.status ) {
				window.setTimeout( next, 400 );
				return;
			}
			following = false;
			set( { busy: '', note: view && view.job && 'done' === view.job.status ? view.job.message : '', error: view && view.job && 'error' === view.job.status ? view.job.error : '' } );
		}, ( e ) => {
			following = false;
			set( { busy: '', error: e.message } );
		} );
		next();
	};

	const train = () => {
		set( { busy: 'train', note: '', error: '' } );
		return api().train().then( ( view ) => {
			set( { view } );
			if ( view && view.job && 'running' === view.job.status ) {
				following = false;
				follow();
			} else {
				set( { busy: '' } );
			}
		}, ( e ) => set( { busy: '', error: e.message } ) );
	};

	const learnChats = () => {
		set( { busy: 'chats', note: '', error: '' } );
		return api().conversations().then( ( view ) => set( { view, busy: '', note: view && view.message ? view.message : '' } ), ( e ) => set( { busy: '', error: e.message } ) );
	};

	const act = ( promise ) => promise.then( ( view ) => set( { view, error: '' } ), ( e ) => set( { error: e.message } ) );

	function Progress( props ) {
		const job = props.job;
		const steps = Math.max( 1, job.steps || 7 );
		const step = Math.min( steps, Math.max( 1, job.step || 1 ) );
		return h( 'div', { className: 'dga-learn-progress', role: 'status' },
			h( 'div', { className: 'dga-learn-bar', 'aria-hidden': 'true' }, h( 'span', { style: { inlineSize: Math.round( ( step / steps ) * 100 ) + '%' } } ) ),
			h( 'p', null, job.message || 'Aprendiendo…' ) );
	}

	function TrainButton( props ) {
		const s = props.store;
		const view = s.view || {};
		return h( 'button', { type: 'button', className: 'dga-button is-primary dga-learn-train', disabled: !! s.busy, onClick: train },
			'train' === s.busy ? 'Aprendiendo…' : ( view.trained ? 'Volver a entrenar' : 'Entrenar con mi sitio web' ) );
	}

	/** La tarjeta de la bienvenida: entrenar por primera vez, o qué sabe y si ya está viejo. */
	function WelcomeCard() {
		const s = useStore();
		useEffect( () => {
			if ( ! s.view ) {
				load();
			}
		}, [] );
		const view = s.view;
		if ( ! view ) {
			return null;
		}
		const running = view.job && 'running' === view.job.status;
		return h( 'section', { className: 'dga-learn-card', 'aria-label': 'Aprendizaje del Asistente' },
			view.trained
				? h( 'p', { className: 'dga-learn-lead' }, 'Ya conozco tu sitio (lo aprendí el ' + view.date + '). ', view.summary || '' )
				: h( 'p', { className: 'dga-learn-lead' }, 'Enséñame tu sitio una vez y no tendré que consultarlo todo cada vez: tus productos, tus políticas, tu contacto y tu marca.' ),
			view.stale ? h( 'p', { className: 'dga-learn-stale' }, 'Tu sitio cambió: ' + view.stale + ' Conviene volver a entrenar.' ) : null,
			running ? h( Progress, { job: view.job } ) : null,
			s.note && ! running ? h( 'p', { className: 'dga-learn-ok', role: 'status' }, s.note ) : null,
			s.error ? h( 'p', { className: 'dga-learn-error', role: 'alert' }, s.error ) : null,
			h( 'div', { className: 'dga-learn-actions' },
				view.trained && ! view.stale ? null : h( TrainButton, { store: s } ),
				view.count ? h( 'button', { type: 'button', className: 'dga-button', onClick: () => set( { open: true } ) }, 'Ver lo que sé de tu sitio' ) : null ),
			view.trained ? null : h( 'p', { className: 'dga-learn-notice' }, view.notice || '' ) );
	}

	function Item( props ) {
		const item = props.item;
		const [ editing, setEditing ] = useState( false );
		const [ text, setText ] = useState( item.text );
		const save = () => {
			const value = text.trim();
			if ( ! value ) {
				return;
			}
			act( api().edit( item.id, value ) ).then( () => setEditing( false ) );
		};
		return h( 'li', { className: 'dga-learn-item' },
			editing
				? h( 'div', { className: 'dga-learn-edit' },
					h( 'textarea', { value: text, rows: 2, maxLength: 240, 'aria-label': 'Corregir este punto', onChange: ( e ) => setText( e.target.value ) } ),
					h( 'div', { className: 'dga-learn-actions' },
						h( 'button', { type: 'button', className: 'dga-button is-primary', onClick: save }, 'Guardar' ),
						h( 'button', { type: 'button', className: 'dga-button', onClick: () => {
							setText( item.text );
							setEditing( false );
						} }, 'Cancelar' ) ) )
				: h( 'p', null, item.text, h( 'small', null, item.sourceLabel + ( item.date ? ' · ' + item.date : '' ) ) ),
			editing ? null : h( 'div', { className: 'dga-learn-item-actions' },
				h( 'button', { type: 'button', className: 'dga-link dga-learn-fix', onClick: () => setEditing( true ) }, 'Corregir' ),
				h( 'button', { type: 'button', className: 'dga-link', onClick: () => {
					if ( window.confirm( '¿Olvidar este punto?' ) ) {
						act( api().remove( item.id ) );
					}
				} }, 'Borrar' ) ) );
	}

	/** «Lo que sé de tu sitio»: lo aprendido por secciones, con corregir y borrar cada punto, borrar todo y volver a entrenar. */
	function Panel() {
		const s = useStore();
		const ref = useRef( null );
		const close = () => set( { open: false } );
		useEffect( () => {
			load();
			const onKey = ( event ) => {
				if ( 'Escape' === event.key ) {
					close();
				}
			};
			document.addEventListener( 'keydown', onKey );
			const first = ref.current && ref.current.querySelector( 'button' );
			if ( first ) {
				first.focus();
			}
			return () => document.removeEventListener( 'keydown', onKey );
		}, [] );
		const view = s.view || { sections: [] };
		const running = view.job && 'running' === view.job.status;
		return h( 'div', { className: 'dga-learn-backdrop', onMouseDown: ( e ) => {
			if ( e.target === e.currentTarget ) {
				close();
			}
		} },
			h( 'div', { className: 'dga-learn-panel', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Lo que sé de tu sitio', ref },
				h( 'div', { className: 'dga-learn-head' },
					h( 'h2', null, 'Lo que sé de tu sitio' ),
					h( 'button', { type: 'button', className: 'dga-button', onClick: close }, 'Cerrar' ) ),
				h( 'div', { className: 'dga-learn-body' },
					view.trained ? h( 'p', { className: 'dga-learn-lead' }, 'Lo aprendí el ' + view.date + '. ' + ( view.summary || '' ) ) : h( 'p', { className: 'dga-learn-lead' }, 'Todavía no he leído tu sitio. Pulsa «Entrenar con mi sitio web» y en unos minutos sabré tus productos, tus políticas, tu contacto y tu marca.' ),
					view.stale ? h( 'p', { className: 'dga-learn-stale' }, 'Tu sitio cambió: ' + view.stale + ' Conviene volver a entrenar.' ) : null,
					running ? h( Progress, { job: view.job } ) : null,
					s.note && ! running ? h( 'p', { className: 'dga-learn-ok', role: 'status' }, s.note ) : null,
					s.error ? h( 'p', { className: 'dga-learn-error', role: 'alert' }, s.error ) : null,
					h( 'div', { className: 'dga-learn-actions' },
						h( TrainButton, { store: s } ),
						h( 'button', { type: 'button', className: 'dga-button', disabled: !! s.busy, onClick: learnChats }, 'chats' === s.busy ? 'Repasando…' : 'Aprender de mis conversaciones' ) ),
					view.sections.map( ( section ) => h( 'section', { key: section.id, className: 'dga-learn-section' },
						h( 'h3', null, section.label ),
						h( 'ul', null, section.items.map( ( item ) => h( Item, { key: item.id + ':' + item.text, item } ) ) ) ) ),
					view.count ? h( 'div', { className: 'dga-learn-actions' }, h( 'button', { type: 'button', className: 'dga-link', disabled: !! s.busy, onClick: () => {
						if ( window.confirm( '¿Borrar todo lo que aprendí de tu sitio y de tus conversaciones?' ) ) {
							act( api().clear() );
						}
					} }, 'Borrar todo lo aprendido' ) ) : null,
					h( 'p', { className: 'dga-learn-notice' }, view.notice || '' ) ) ) );
	}

	/** El botón de la barra (abre el panel) y el panel mismo. */
	function HeaderButton() {
		const s = useStore();
		return h( el.Fragment, null,
			h( 'button', { type: 'button', className: 'dga-button dga-learn-open', onClick: () => set( { open: true } ) }, 'Lo que sé de tu sitio' ),
			s.open ? h( Panel ) : null );
	}

	function Slot( props ) {
		if ( ! config.learning ) {
			return null;
		}
		return 'header' === props.place ? h( HeaderButton ) : h( WelcomeCard );
	}

	window.digitalisimoAssistantLearning = { Slot, createLearningAdapter };
}( window.wp, window, document ) );
