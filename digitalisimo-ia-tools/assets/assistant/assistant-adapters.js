/**
 * Adaptadores del Asistente IA (docs/ASSISTANT.md): lo único que sabe a dónde se habla. La pantalla
 * (assistant.js) sólo usa este contrato, así que puede pasar a @digitalisimo/core-sdk —o la pantalla a la versión
 * React de @digitalisimo/ui— sin reescribir la otra mitad.
 *
 *   window.digitalisimoAssistantAdapters.create( config ) → {
 *     history: { list(), get( id ), remove( id ), clear() },
 *     model:   { providers(), send( payload, onEvent ), confirm( payload, onEvent ) },
 *     tools:   { list(), log() },
 *   }
 *
 * Todo devuelve promesas. `send` y `confirm` entregan eventos ({ type: conversation | status | message | tool |
 * confirm | done }) a medida que llegan: el servidor los manda en flujo (NDJSON) y, si algo en medio guarda la
 * respuesta entera, llegan juntos. Esta implementación habla con la REST de IA Tools (digitalisimo-ai/v1/assistant)
 * con la sesión de WordPress: nonce wp_rest y el nonce propio del Asistente. Otra implementación se registra
 * definiendo window.digitalisimoAssistantAdapters antes que este archivo (filtro PHP
 * digitalisimo_ai_assistant_adapters_handle).
 */
( function ( window ) {
	'use strict';
	if ( window.digitalisimoAssistantAdapters && 'function' === typeof window.digitalisimoAssistantAdapters.create ) {
		return;
	}

	function createWpAdapters( config ) {
		var base = String( config.rest || '' );
		var headers = { 'X-WP-Nonce': String( config.restNonce || '' ), 'X-Digitalisimo-Assistant': String( config.nonce || '' ) };

		function url( path ) {
			return base + path;
		}

		function failure( response, data ) {
			var message = data && 'string' === typeof data.message ? data.message : 'Error ' + response.status;
			var error = new Error( message );
			error.status = response.status;
			return error;
		}

		function request( path, method, body ) {
			var options = { method: method || 'GET', credentials: 'same-origin', headers: Object.assign( { Accept: 'application/json' }, headers ) };
			if ( undefined !== body ) {
				options.headers[ 'Content-Type' ] = 'application/json';
				options.body = JSON.stringify( body );
			}
			return window.fetch( url( path ), options ).then( function ( response ) {
				return response.json().catch( function () {
					return null;
				} ).then( function ( data ) {
					if ( ! response.ok ) {
						throw failure( response, data );
					}
					return data;
				} );
			} );
		}

		function emitAll( data, onEvent ) {
			( data && Array.isArray( data.events ) ? data.events : [] ).forEach( function ( event ) {
				onEvent( event );
			} );
		}

		function stream( path, payload, onEvent ) {
			var options = {
				method: 'POST',
				credentials: 'same-origin',
				headers: Object.assign( { 'Content-Type': 'application/json', Accept: 'application/x-ndjson, application/json' }, headers ),
				body: JSON.stringify( Object.assign( {}, payload, { stream: true } ) ),
			};
			return window.fetch( url( path ), options ).then( function ( response ) {
				var type = response.headers.get( 'Content-Type' ) || '';
				if ( ! response.ok || -1 === type.indexOf( 'ndjson' ) || ! response.body || ! response.body.getReader ) {
					return response.text().then( function ( text ) {
						var data = null;
						try {
							data = JSON.parse( text );
						} catch ( e ) {
							data = null;
						}
						if ( ! response.ok ) {
							throw failure( response, data );
						}
						if ( data ) {
							emitAll( data, onEvent );
						} else {
							lines( text, onEvent );
						}
					} );
				}
				var reader = response.body.getReader();
				var decoder = new window.TextDecoder();
				var buffer = '';
				function pump() {
					return reader.read().then( function ( chunk ) {
						if ( chunk.done ) {
							lines( buffer, onEvent );
							return;
						}
						buffer += decoder.decode( chunk.value, { stream: true } );
						var cut = buffer.lastIndexOf( '\n' );
						if ( cut >= 0 ) {
							lines( buffer.slice( 0, cut ), onEvent );
							buffer = buffer.slice( cut + 1 );
						}
						return pump();
					} );
				}
				return pump();
			} );
		}

		function lines( text, onEvent ) {
			String( text ).split( '\n' ).forEach( function ( line ) {
				if ( ! line.trim() ) {
					return;
				}
				try {
					onEvent( JSON.parse( line ) );
				} catch ( e ) {
					// Una línea que no es JSON (un aviso de PHP impreso antes) no rompe el resto.
				}
			} );
		}

		return {
			history: {
				list: function () {
					return request( 'conversations' ).then( function ( data ) {
						return data && Array.isArray( data.conversations ) ? data.conversations : [];
					} );
				},
				get: function ( id ) {
					return request( 'conversations/' + encodeURIComponent( id ) );
				},
				remove: function ( id ) {
					return request( 'conversations/' + encodeURIComponent( id ) + '/delete', 'POST', {} );
				},
				clear: function () {
					return request( 'conversations/delete-all', 'POST', {} );
				},
			},
			model: {
				providers: function () {
					return Promise.resolve( Array.isArray( config.providers ) ? config.providers : [] );
				},
				send: function ( payload, onEvent ) {
					return stream( 'message', payload, onEvent );
				},
				confirm: function ( payload, onEvent ) {
					return stream( 'confirm', payload, onEvent );
				},
			},
			tools: {
				list: function () {
					return request( 'tools' );
				},
				log: function () {
					return request( 'log' ).then( function ( data ) {
						return data && Array.isArray( data.rows ) ? data.rows : [];
					} );
				},
			},
		};
	}

	window.digitalisimoAssistantAdapters = { create: createWpAdapters };
}( window ) );
