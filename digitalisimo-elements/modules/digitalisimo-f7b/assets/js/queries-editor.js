/**
 * DIGITALÍSIMO Elements · F7b · Consultas guardadas en el editor (V.4; C2-17, C2-18, C2-26). Sólo en el editor.
 *
 * - Resumen de sólo lectura bajo el select «Consulta guardada» (`[data-digi-sq-summary]`), con «Editar en Consultas
 *   (N usos)». La lista llega una vez por sesión con la acción
 *   `digitalisimo_f7b_queries` (sólo lectura, `edit_post` sobre el documento).
 * - «Desvincular»: lee los ajustes con `digitalisimo_f7b_query_settings` (sólo lectura), confirma (y avisa si la
 *   consulta usa contexto o relación) y los copia con `$e.run( 'document/elements/settings' )`: un registro del
 *   historial, con su «Deshacer». El servidor nunca escribe el documento.
 */
( function () {
	'use strict';

	var cfg = window.digitalisimoSavedQueries;
	if ( ! cfg || ! window.elementor ) {
		return;
	}
	var t = cfg.i18n;
	var list = null;
	var waiting = [];
	var bound = null;

	function ajax( action, data, ok, fail ) {
		window.elementorCommon.ajax.addRequest( action, {
			data: data || {},
			success: ok,
			error: fail || function () {},
		} );
	}

	function load( done ) {
		if ( list ) {
			done( list );
			return;
		}
		waiting.push( done );
		if ( waiting.length > 1 ) {
			return;
		}
		var flush = function ( data ) {
			list = data && data.queries ? data : { queries: {} };
			var queue = waiting;
			waiting = [];
			queue.forEach( function ( fn ) {
				fn( list );
			} );
		};
		ajax( cfg.list, {}, flush, function () {
			flush( null );
			list = null;
		} );
	}

	function el( tag, text, cls ) {
		var node = document.createElement( tag );
		if ( text ) {
			node.textContent = text;
		}
		if ( cls ) {
			node.className = cls;
		}
		return node;
	}

	function panelRoot( panel ) {
		var view = window.elementor.getPanelView ? window.elementor.getPanelView() : null;
		var page = view && view.getCurrentPageView ? view.getCurrentPageView() : null;
		if ( page && page.el ) {
			return page.el;
		}
		return panel && panel.$el ? panel.$el[ 0 ] : null;
	}

	function render( panel, settings ) {
		var root = panelRoot( panel );
		var box = root ? root.querySelector( '[data-digi-sq-summary]' ) : null;
		if ( ! box ) {
			return;
		}
		var id = String( settings.get( cfg.control ) || '' );
		box.textContent = id ? t.loading : '';
		if ( ! id ) {
			return;
		}
		load( function ( data ) {
			if ( String( settings.get( cfg.control ) || '' ) !== id ) {
				return;
			}
			var q = data.queries[ id ];
			box.textContent = '';
			if ( ! q || ! q.valid ) {
				box.appendChild( el( 'p', t.missing, 'elementor-panel-alert elementor-panel-alert-warning' ) );
				return;
			}
			box.appendChild( el( 'p', q.summary ) );
			var uses = t.edit.replace( '%d', String( q.uses ) );
			if ( q.edit ) {
				var link = el( 'a', uses );
				link.href = q.edit;
				link.target = '_blank';
				link.rel = 'noopener';
				var p = el( 'p' );
				p.appendChild( link );
				box.appendChild( p );
			} else {
				box.appendChild( el( 'p', t.uses.replace( '%d', String( q.uses ) ) ) );
			}
		} );
	}

	function open( panel, model ) {
		if ( bound ) {
			bound.settings.off( 'change:' + cfg.control, bound.fn );
			bound = null;
		}
		if ( ! model || ! cfg.prefixes[ model.get( 'widgetType' ) ] ) {
			return;
		}
		var settings = model.get( 'settings' );
		var fn = function () {
			setTimeout( function () {
				render( panel, settings );
			} );
		};
		bound = { settings: settings, fn: fn, panel: panel };
		settings.on( 'change:' + cfg.control, fn );
		fn();
	}

	function esc( text ) {
		var node = document.createElement( 'span' );
		node.textContent = text;
		return node.innerHTML;
	}

	function confirmBox( message, onConfirm ) {
		var dialogs = window.elementorCommon && window.elementorCommon.dialogsManager;
		if ( ! dialogs ) {
			if ( window.confirm( message ) ) {
				onConfirm();
			}
			return;
		}
		dialogs.createWidget( 'confirm', {
			id: 'digitalisimo-saved-query-unlink',
			headerMessage: esc( t.title ),
			message: esc( message ),
			position: { my: 'center center', at: 'center center' },
			strings: { confirm: esc( t.ok ), cancel: esc( t.cancel ) },
			onConfirm: onConfirm,
		} ).show();
	}

	function perPageKey( container ) {
		if ( 'posts' === container.model.get( 'widgetType' ) ) {
			return ( container.settings.get( '_skin' ) || 'classic' ) + '_posts_per_page';
		}
		return 'posts_per_page';
	}

	function values( container, prefix, q ) {
		var out = {};
		cfg.fields.forEach( function ( field ) {
			if ( ! Object.prototype.hasOwnProperty.call( q.settings, field ) ) {
				return;
			}
			var value = q.settings[ field ];
			out[ prefix + field ] = Array.isArray( value ) ? value.map( String ) : value;
		} );
		out[ cfg.control ] = '';
		var key = perPageKey( container );
		var current = parseInt( container.settings.get( key ), 10 );
		if ( undefined !== container.settings.get( key ) && ( ! ( current > 0 ) || current > q.per_page ) ) {
			out[ key ] = q.per_page;
		}
		return out;
	}

	function unlink( view ) {
		var container = view && view.container;
		if ( ! container || ! container.settings ) {
			return;
		}
		var prefix = cfg.prefixes[ container.model.get( 'widgetType' ) ];
		var id = String( container.settings.get( cfg.control ) || '' );
		if ( ! prefix || ! id ) {
			return;
		}
		ajax( cfg.read, { id: id }, function ( q ) {
			if ( ! q || ! q.settings ) {
				return;
			}
			var changes = 'none' !== q.context || 'none' !== q.relation;
			confirmBox( t.confirm + ( changes ? ' ' + t.changes : '' ), function () {
				window.$e.run( 'document/elements/settings', {
					container: container,
					settings: values( container, prefix, q ),
					options: { external: true },
				} );
			} );
		}, function () {
			if ( window.elementor.notifications && window.elementor.notifications.showToast ) {
				window.elementor.notifications.showToast( { message: t.error } );
			}
		} );
	}

	function boot() {
		window.elementor.hooks.addAction( 'panel/open_editor/widget', open );
		window.elementor.channels.editor.on( cfg.event, unlink );
		window.elementor.channels.editor.on( 'section:activated', function () {
			if ( bound ) {
				bound.fn();
			}
		} );
	}

	if ( window.elementor.hooks && window.elementor.channels ) {
		boot();
	} else {
		window.addEventListener( 'elementor/init', boot, { once: true } );
	}
}() );
