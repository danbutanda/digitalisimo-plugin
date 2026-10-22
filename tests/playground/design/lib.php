<?php
/**
 * Árboles de los documentos de design.sh (F7a, frente M): lo comparten `setup.php` (al preparar el sitio) y la sonda
 * (`?digi_design=tree`, para que run.cjs guarde por `elementor_ajax` lo que el escenario pide guardar como un usuario).
 *
 * Forma compacta de `cases.json`:
 * - `{ "c": "<id>", "s": {…}, "e": [ … ] }` contenedor; `{ "sec": "<id>", "e": [ { "col": "<id>", "e": [ … ] } ] }`
 *   sección y columna; `{ "w": "<widgetType>", "id": "<id>", "s": {…} }` widget; `{ "div4": "<id>", "e": [ … ] }`
 *   bloque atómico (v4) y `{ "v4": "e-heading", "id": "<id>", "s": {…} }` widget atómico.
 * - En los ajustes, `"$conditions": [ [ {condición}, … ], … ]` se guarda como `e_display_conditions` (v3, la cadena
 *   JSON que guarda el editor) y `"$conditions4"` como el prop `display-conditions` de v4.
 * - `"$gradient": "<nombre del caso>"` funde los ajustes del caso de `tests/sources/design/gradients.json`.
 * - Marcadores en cualquier cadena: `__ID:<clave>__` (el ID del documento; si es todo el valor, un entero),
 *   `__URL:<clave>__`, `__MENU__`, `__IMAGE__` y `__SVG:<nombre>__` (`{ id, url }` de un adjunto).
 * - `"generate"` en un documento construye el árbol con los fixtures (`presets`, `presets-rtl`, `gradients`).
 */

defined( 'DIGI_DS_DIR' ) || define( 'DIGI_DS_DIR', '/wordpress/wp-content/probe' );

function digi_ds_json( $file ) {
	static $cache = array();
	if ( ! isset( $cache[ $file ] ) ) {
		$raw            = @file_get_contents( DIGI_DS_DIR . '/' . $file ); // phpcs:ignore
		$cache[ $file ] = is_string( $raw ) ? json_decode( $raw, true ) : null;
	}
	return $cache[ $file ];
}

function digi_ds_mode() {
	$mode = digi_ds_json( 'mode.json' );
	return is_array( $mode ) ? $mode : array();
}

function digi_ds_cases() {
	$cases = digi_ds_json( 'cases.json' );
	return is_array( $cases ) ? $cases : array();
}

/** IDs del sitio actual (`ids-<blog>.json`, lo escribe setup.php). */
function digi_ds_ids() {
	$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
	$raw  = get_option( 'digi_ds_ids', array() );
	return is_array( $raw ) ? $raw : array();
}

/** Sustituye los marcadores en un valor decodificado. */
function digi_ds_resolve( $value, array $ids, array $media = array() ) {
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ $k ] = digi_ds_resolve( $v, $ids, $media );
		}
		return $out;
	}
	if ( ! is_string( $value ) || false === strpos( $value, '__' ) ) {
		return $value;
	}
	if ( preg_match( '/^__ID:([a-z0-9-]+)__$/', $value, $m ) ) {
		return isset( $ids[ $m[1] ] ) ? (int) $ids[ $m[1] ] : 0;
	}
	if ( preg_match( '/^__SVG:([a-z0-9-]+)__$/', $value, $m ) ) {
		return isset( $media[ 'svg-' . $m[1] ] ) ? $media[ 'svg-' . $m[1] ] : array( 'id' => '', 'url' => '' );
	}
	if ( '__IMAGE__' === $value ) {
		return isset( $media['image'] ) ? $media['image'] : array( 'id' => '', 'url' => '' );
	}
	$value = preg_replace_callback(
		'/__(ID|URL):([a-z0-9-]+)__/',
		static function ( $m ) use ( $ids ) {
			$id = isset( $ids[ $m[2] ] ) ? (int) $ids[ $m[2] ] : 0;
			return 'ID' === $m[1] ? (string) $id : ( $id ? (string) get_permalink( $id ) : '' );
		},
		$value
	);
	return str_replace( '__MENU__', 'digi-ds-menu', $value );
}

/** Ajustes de un nodo: `$gradient`, `$conditions` y `$conditions4`. */
function digi_ds_settings( array $s, array $ids, array $media ) {
	if ( isset( $s['$gradient'] ) ) {
		$fixtures = digi_ds_json( 'gradients.json' );
		foreach ( (array) ( $fixtures['cases'] ?? array() ) as $case ) {
			if ( $case['name'] === $s['$gradient'] ) {
				$s = array_merge( $case['settings'], $s );
			}
		}
		unset( $s['$gradient'] );
		// Una fila de repetidor que no es un objeto («texto» en «filas no válidas descartadas») la rechaza ya el
		// `sanitize_settings()` del núcleo con un TypeError al leer el documento (REST global-classes/usage, editor),
		// con cualquier repetidor: en Playground sólo se prueban las filas que el editor puede guardar.
		foreach ( $s as $k => $v ) {
			if ( is_array( $v ) && '_digi_stops' === substr( (string) $k, -11 ) ) {
				$s[ $k ] = array_values( array_filter( $v, 'is_array' ) );
			}
		}
	}
	$s = digi_ds_resolve( $s, $ids, $media );
	if ( isset( $s['$conditions'] ) ) {
		$s['e_display_conditions'] = array( wp_json_encode( $s['$conditions'] ) );
		unset( $s['$conditions'] );
	}
	if ( isset( $s['$conditions4'] ) ) {
		$groups = array();
		foreach ( (array) $s['$conditions4'] as $group ) {
			$items = array();
			foreach ( (array) $group as $condition ) {
				$items[] = array( '$$type' => 'string', 'value' => wp_json_encode( $condition ) );
			}
			$groups[] = array( '$$type' => 'condition-group', 'value' => $items );
		}
		$s['display-conditions'] = array( '$$type' => 'display-conditions', 'value' => $groups );
		unset( $s['$conditions4'] );
	}
	return $s;
}

/** Un nodo compacto como elemento del constructor. */
function digi_ds_node( array $n, array $ids, array $media ) {
	$settings = digi_ds_settings( isset( $n['s'] ) && is_array( $n['s'] ) ? $n['s'] : array(), $ids, $media );
	$children = array();
	foreach ( (array) ( $n['e'] ?? array() ) as $child ) {
		$children[] = digi_ds_node( (array) $child, $ids, $media );
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
		return array( 'id' => $n['div4'], 'elType' => 'e-div-block', 'isInner' => false, 'settings' => (array) $settings, 'elements' => $children );
	}
	if ( isset( $n['v4'] ) ) {
		return array( 'id' => $n['id'], 'elType' => 'widget', 'widgetType' => $n['v4'], 'isInner' => false, 'settings' => (array) $settings, 'elements' => array() );
	}
	return array( 'id' => (string) $n['id'], 'elType' => 'widget', 'widgetType' => (string) $n['w'], 'isInner' => false, 'settings' => (array) $settings, 'elements' => array() );
}

/** Ajustes de un elemento de prueba de presets según su tipo (con un hijo visible en los contenedores). */
function digi_ds_preset_element( $type, $id, array $settings, $title ) {
	if ( 'container' === $type ) {
		$settings = array_merge( array( 'min_height' => array( 'unit' => 'px', 'size' => 60, 'sizes' => array() ) ), $settings );
		return array( 'c' => $id, 's' => $settings, 'e' => array( array( 'w' => 'heading', 'id' => $id . 't', 's' => array( 'title' => $title ) ) ) );
	}
	if ( 'heading' === $type ) {
		$settings = array_merge( array( 'title' => $title ), $settings );
	}
	return array( 'w' => $type, 'id' => $id, 's' => $settings );
}

/**
 * Casos de presets (`tests/sources/design/presets.json`): por caso, un contenedor con L (vinculado, con lo local del
 * caso), R (referencia: lo local más lo que el preset aporta, `fill`, sin vínculo) y N (sólo lo local). En `tarjeta`,
 * además P (vinculado, sin nada local, para «Usar sólo el preset») y U (vinculado, para «Desvincular y copiar»).
 * Devuelve `{ elements, meta }`; `meta` dice a run.cjs qué comparar.
 */
function digi_ds_generate_presets( $rtl ) {
	$fixtures = digi_ds_json( 'presets.json' );
	$elements = array();
	$meta     = array();
	foreach ( (array) ( $fixtures['cases'] ?? array() ) as $index => $case ) {
		$is_rtl = 'desplazado' === $case['slug'];
		if ( $is_rtl !== (bool) $rtl ) {
			continue;
		}
		$base  = 'p' . sprintf( '%02d', $index );
		$local = is_array( $case['local'] ) ? $case['local'] : array();
		$fill  = (array) ( $case['fill'] ?? array() );
		$ref   = array_merge( $local, (array) ( $fill['values'] ?? array() ) );
		$glob  = array_merge( (array) ( $local['__globals__'] ?? array() ), (array) ( $fill['globals'] ?? array() ) );
		if ( $glob ) {
			$ref['__globals__'] = $glob;
		}
		$title = 'Título de prueba';
		$row   = array(
			digi_ds_preset_element( $case['type'], $base . 'l', array_merge( $local, array( 'digitalisimo_preset' => $case['slug'] ) ), $title ),
			digi_ds_preset_element( $case['type'], $base . 'r', $ref, $title ),
			digi_ds_preset_element( $case['type'], $base . 'n', $local, $title ),
		);
		$entry = array( 'slug' => $case['slug'], 'type' => $case['type'], 'L' => $base . 'l', 'R' => $base . 'r', 'N' => $base . 'n', 'changes' => (bool) ( $fill['values'] ?? array() ) || (bool) ( $fill['globals'] ?? array() ) );
		if ( 'tarjeta' === $case['slug'] ) {
			$row[]          = digi_ds_preset_element( $case['type'], $base . 'p', array( 'digitalisimo_preset' => $case['slug'] ), $title );
			$row[]          = digi_ds_preset_element( $case['type'], $base . 'u', array( 'digitalisimo_preset' => $case['slug'] ), $title );
			$entry['P']     = $base . 'p';
			$entry['U']     = $base . 'u';
			$entry['local'] = $local;
		}
		$container = array( 'flex_direction' => 'column', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12', 'isLinked' => true ) );
		if ( $is_rtl ) {
			$container['min_height'] = array( 'unit' => 'px', 'size' => 260, 'sizes' => array() );
		}
		$elements[] = array( 'c' => $base . 'c', 's' => $container, 'e' => $row );
		$meta[]     = $entry;
	}
	return array( 'elements' => $elements, 'meta' => $meta );
}

/** Un contenedor por caso de degradado elegido (en widgets, un encabezado). */
function digi_ds_generate_gradients( array $names ) {
	$fixtures = digi_ds_json( 'gradients.json' );
	$elements = array();
	$meta     = array();
	foreach ( (array) ( $fixtures['cases'] ?? array() ) as $index => $case ) {
		if ( $names && ! in_array( $case['name'], $names, true ) ) {
			continue;
		}
		$id    = 'g' . sprintf( '%02d', $index ) . 'aaa';
		$id    = substr( $id, 0, 7 );
		$title = array( 'w' => 'heading', 'id' => substr( 'g' . sprintf( '%02d', $index ) . 'ttt', 0, 7 ), 's' => array( 'title' => 'Degradado ' . $index ) );
		if ( '_background_' === $case['prefix'] || '_background_hover_' === $case['prefix'] ) {
			$elements[] = array( 'c' => substr( 'g' . sprintf( '%02d', $index ) . 'ccc', 0, 7 ), 'e' => array( array( 'w' => 'heading', 'id' => $id, 's' => array_merge( array( 'title' => 'Degradado ' . $index, '_padding' => array( 'unit' => 'px', 'top' => 30, 'right' => 30, 'bottom' => 30, 'left' => 30, 'isLinked' => true ) ), array( '$gradient' => $case['name'] ) ) ) ) );
			$kind       = 'widget';
		} else {
			$elements[] = array( 'c' => $id, 's' => array( '$gradient' => $case['name'], 'min_height' => array( 'unit' => 'px', 'size' => 80, 'sizes' => array() ) ), 'e' => array( $title ) );
			$kind       = 'container';
		}
		$meta[] = array( 'index' => $index, 'name' => $case['name'], 'prefix' => $case['prefix'], 'id' => $id, 'kind' => $kind );
	}
	return array( 'elements' => $elements, 'meta' => $meta );
}

/**
 * Árbol de un documento de `cases.json` ya resuelto, con su `meta` (lo que el escenario compara).
 *
 * @return array `{ elements, settings, meta }`.
 */
function digi_ds_doc_tree( $key, array $ids, array $media ) {
	$cases = digi_ds_cases();
	$doc   = isset( $cases['docs'][ $key ] ) ? (array) $cases['docs'][ $key ] : array();
	$meta  = array();
	$nodes = (array) ( $doc['elements'] ?? array() );
	if ( isset( $doc['generate'] ) ) {
		if ( 'presets' === $doc['generate'] || 'presets-rtl' === $doc['generate'] ) {
			$gen = digi_ds_generate_presets( 'presets-rtl' === $doc['generate'] );
		} else {
			$gen = digi_ds_generate_gradients( (array) ( $doc['gradients'] ?? array() ) );
		}
		$nodes = array_merge( $gen['elements'], $nodes );
		$meta  = $gen['meta'];
	}
	$elements = array();
	foreach ( $nodes as $node ) {
		$elements[] = digi_ds_node( (array) $node, $ids, $media );
	}
	$settings = digi_ds_resolve( (array) ( $doc['settings'] ?? array() ), $ids, $media );
	return array( 'elements' => $elements, 'settings' => $settings, 'meta' => $meta );
}
