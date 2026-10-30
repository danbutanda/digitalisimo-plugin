<?php
/**
 * Árboles de los documentos de f7b.sh (F7b, frente L): lo comparten `setup.php` (al preparar el sitio) y la sonda
 * (`?digi_f7b=tree`, para que run.cjs guarde por `elementor_ajax` lo que un escenario pide guardar como el editor).
 *
 * Forma compacta de `cases.json` (la de `design/lib.php`):
 * - `{ "c": "<id>", "s": {…}, "e": [ … ] }` contenedor; `{ "sec": "<id>", "e": [ { "col": "<id>", "e": [ … ] } ] }`
 *   sección y columna; `{ "w": "<widgetType>", "id": "<id>", "s": {…}, "e": [ … ] }` widget (con hijos, los anidados);
 *   `{ "div4": "<id>", "s": {…}, "x": {…}, "e": [ … ] }` bloque atómico (v4) y `{ "v4": "e-heading", "id": … }`
 *   widget atómico; `x` son claves del elemento fuera de `settings` (`interactions`).
 * - Marcadores en cualquier cadena: `__ID:<clave>__` (ID del documento; si es todo el valor, un entero), `__SID:<clave>__`
 *   (el mismo ID como cadena), `__URL:<clave>__`, `__POST:<n>__` (entrada de prueba n, entero), `__QID:<slug>__` (ID de
 *   la consulta guardada, cadena: así la guarda el select), `__TT:<slug>__` (term_taxonomy_id de una categoría, cadena),
 *   `__USER:<login>__` (entero), `__IMAGE__` y `__IMAGE2__` (`{ id, url }` de un adjunto), `__IMAGE2_ID__` (entero),
 *   `__SITE__` (URL del sitio sin barra final).
 */

defined( 'DIGI_F7B_DIR' ) || define( 'DIGI_F7B_DIR', '/wordpress/wp-content/probe' );

function digi_f7b_json( $file ) {
	static $cache = array();
	if ( ! isset( $cache[ $file ] ) ) {
		$raw            = @file_get_contents( DIGI_F7B_DIR . '/' . $file ); // phpcs:ignore
		$cache[ $file ] = is_string( $raw ) ? json_decode( $raw, true ) : null;
	}
	return $cache[ $file ];
}

function digi_f7b_mode() {
	$mode = digi_f7b_json( 'mode.json' );
	return is_array( $mode ) ? $mode : array();
}

function digi_f7b_cases() {
	$cases = digi_f7b_json( 'cases.json' );
	return is_array( $cases ) ? $cases : array();
}

/** Mapa de marcadores del sitio (`digi_f7b_ids`, lo escribe setup.php). */
function digi_f7b_ids() {
	$raw = get_option( 'digi_f7b_ids', array() );
	return is_array( $raw ) ? $raw : array();
}

/** Sustituye los marcadores en un valor decodificado. */
function digi_f7b_resolve( $value, array $map ) {
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ $k ] = digi_f7b_resolve( $v, $map );
		}
		return $out;
	}
	if ( ! is_string( $value ) || false === strpos( $value, '__' ) ) {
		return $value;
	}
	$docs  = (array) ( $map['docs'] ?? array() );
	$posts = (array) ( $map['posts'] ?? array() );
	$media = (array) ( $map['media'] ?? array() );
	if ( preg_match( '/^__(ID|POST|USER):([a-z0-9-]+)__$/', $value, $m ) ) {
		$table = 'ID' === $m[1] ? $docs : ( 'POST' === $m[1] ? $posts : (array) ( $map['users'] ?? array() ) );
		return isset( $table[ $m[2] ] ) ? (int) $table[ $m[2] ] : 0;
	}
	if ( '__IMAGE__' === $value || '__IMAGE2__' === $value ) {
		$key = '__IMAGE__' === $value ? 'image' : 'image2';
		return isset( $media[ $key ] ) ? $media[ $key ] : array( 'id' => '', 'url' => '' );
	}
	if ( '__IMAGE2_ID__' === $value ) {
		return isset( $media['image2']['id'] ) ? (int) $media['image2']['id'] : 0;
	}
	return preg_replace_callback(
		'/__(SID|URL|QID|TT|SITE)(?::([a-z0-9-]+))?__/',
		static function ( $m ) use ( $docs, $map ) {
			$key = isset( $m[2] ) ? $m[2] : '';
			switch ( $m[1] ) {
				case 'SID':
					return isset( $docs[ $key ] ) ? (string) (int) $docs[ $key ] : '0';
				case 'URL':
					return isset( $docs[ $key ] ) ? (string) get_permalink( (int) $docs[ $key ] ) : '';
				case 'QID':
					return isset( $map['queries'][ $key ] ) ? (string) (int) $map['queries'][ $key ] : '0';
				case 'TT':
					return isset( $map['tt'][ $key ] ) ? (string) (int) $map['tt'][ $key ] : '0';
				case 'SITE':
					return untrailingslashit( home_url() );
			}
			return $m[0];
		},
		$value
	);
}

/** Un nodo compacto como elemento del constructor. */
function digi_f7b_node( array $n, array $map ) {
	$settings = digi_f7b_resolve( isset( $n['s'] ) && is_array( $n['s'] ) ? $n['s'] : array(), $map );
	$children = array();
	foreach ( (array) ( $n['e'] ?? array() ) as $child ) {
		$children[] = digi_f7b_node( (array) $child, $map );
	}
	if ( isset( $n['c'] ) ) {
		return array( 'id' => $n['c'], 'elType' => 'container', 'isInner' => ! empty( $n['inner'] ), 'settings' => (array) $settings, 'elements' => $children );
	}
	if ( isset( $n['sec'] ) ) {
		return array( 'id' => $n['sec'], 'elType' => 'section', 'isInner' => false, 'settings' => (array) $settings, 'elements' => $children );
	}
	if ( isset( $n['col'] ) ) {
		$settings = array_merge( array( '_column_size' => 100 ), (array) $settings );
		return array( 'id' => $n['col'], 'elType' => 'column', 'isInner' => false, 'settings' => $settings, 'elements' => $children );
	}
	if ( isset( $n['div4'] ) ) {
		$el = array( 'id' => $n['div4'], 'elType' => 'e-div-block', 'isInner' => false, 'settings' => (array) $settings, 'elements' => $children );
		return array_merge( $el, (array) digi_f7b_resolve( (array) ( $n['x'] ?? array() ), $map ) );
	}
	if ( isset( $n['v4'] ) ) {
		$el = array( 'id' => $n['id'], 'elType' => 'widget', 'widgetType' => $n['v4'], 'isInner' => false, 'settings' => (array) $settings, 'elements' => array() );
		return array_merge( $el, (array) digi_f7b_resolve( (array) ( $n['x'] ?? array() ), $map ) );
	}
	return array( 'id' => (string) $n['id'], 'elType' => 'widget', 'widgetType' => (string) $n['w'], 'isInner' => false, 'settings' => (array) $settings, 'elements' => $children );
}

/**
 * Árbol de un documento de `cases.json` ya resuelto.
 *
 * @return array `{ elements, settings }`.
 */
function digi_f7b_doc_tree( $key, array $map ) {
	$cases    = digi_f7b_cases();
	$doc      = isset( $cases['docs'][ $key ] ) ? (array) $cases['docs'][ $key ] : array();
	$elements = array();
	foreach ( (array) ( $doc['elements'] ?? array() ) as $node ) {
		$elements[] = digi_f7b_node( (array) $node, $map );
	}
	$settings = digi_f7b_resolve( (array) ( $doc['settings'] ?? array() ), $map );
	return array( 'elements' => $elements, 'settings' => $settings );
}

/** Recorre un árbol de elementos y devuelve el primero con ese ID (o `null`). */
function digi_f7b_find( array $elements, $id ) {
	foreach ( $elements as $el ) {
		if ( is_array( $el ) && isset( $el['id'] ) && (string) $el['id'] === (string) $id ) {
			return $el;
		}
		if ( is_array( $el ) && ! empty( $el['elements'] ) ) {
			$found = digi_f7b_find( (array) $el['elements'], $id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}
