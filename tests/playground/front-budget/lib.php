<?php
/**
 * Documentos de la medición del front, en funciones puras: los usan `setup.php` (Playground) y
 * `tests/elements-front-cases.php` (CI), así que la prueba de CI recorre exactamente lo que el arnés crea.
 *
 * Un caso de `cases.json` da una página `digi-fb-<caso>`:
 *
 * - `widgets`: lista de widgets dentro de un contenedor (`container`: sus ajustes), precedidos del encabezado de
 *   referencia común (salvo `bare` y el caso `empty`, que sólo lleva el encabezado).
 * - `elements`: árbol crudo en lugar del contenedor (páginas sólo v4).
 * - `classic`: página sin contenido del constructor.
 * - `extra`: documentos de la biblioteca (popups, plantillas, plantillas de bucle, cabeceras, widget global), cada uno
 *   con `key`, `type`, `widgets` o `elements`, y opcionalmente `conditions`, `triggers` y `timing`.
 *
 * Cada widget es `{ "type": …, "settings": {…} }`, o `{ "elType": "container", "settings": {…}, "elements": [ … ] }`,
 * o `{ "type": "global", "templateID": "__TEMPLATE__" }`. Marcadores: `__SELF__` (la página), `__POPUP__` (el primer
 * popup del caso), `__TEMPLATE__` (la primera plantilla que no es popup, bucle ni cabecera), `__LOOP__` (la plantilla
 * de bucle), `__DOC:<key>__`, `__MENU__`, `__IMAGE__` y `__SITE__`.
 */

/** ID de elemento determinista de 7 caracteres (los mismos que usaba la medición de 2026-10-16). */
function digi_fb_eid( $prefix, $seed ) {
	return $prefix . substr( md5( $seed ), 0, 6 );
}

/** Slug de la página de un caso. */
function digi_fb_page_slug( $slug ) {
	return 'digi-fb-' . $slug;
}

/** Slug del documento `extra` de un caso (sin «--»: WordPress lo reduce a un guion al guardar el slug). */
function digi_fb_extra_slug( $slug, $key ) {
	return 'digi-fb-' . $slug . '-x-' . $key;
}

/**
 * Un elemento a partir de su definición.
 *
 * @param array  $def  Definición del caso.
 * @param string $seed Semilla del ID.
 */
function digi_fb_element( array $def, $seed ) {
	$el_type = isset( $def['elType'] ) ? (string) $def['elType'] : 'widget';
	$element = array(
		'id'       => isset( $def['id'] ) ? (string) $def['id'] : digi_fb_eid( 'w', $seed ),
		'elType'   => $el_type,
		'settings' => (object) ( isset( $def['settings'] ) && is_array( $def['settings'] ) ? $def['settings'] : array() ),
		'elements' => array(),
	);
	if ( 'widget' === $el_type ) {
		$element['widgetType'] = (string) $def['type'];
		if ( isset( $def['templateID'] ) ) {
			$element['templateID'] = $def['templateID'];
		}
	}
	foreach ( (array) ( $def['elements'] ?? array() ) as $index => $child ) {
		$element['elements'][] = digi_fb_element( (array) $child, $seed . '-' . $index );
	}
	return $element;
}

/**
 * Árbol de la página de un caso, o `null` para una página clásica (sin contenido del constructor).
 *
 * @param string $slug Caso.
 * @param array  $case Definición.
 */
function digi_fb_page_tree( $slug, array $case ) {
	if ( ! empty( $case['classic'] ) ) {
		return null;
	}
	$page = digi_fb_page_slug( $slug );
	if ( isset( $case['elements'] ) ) {
		$tree = array();
		foreach ( (array) $case['elements'] as $index => $def ) {
			$tree[] = digi_fb_element( (array) $def, $page . 'r' . $index );
		}
		return $tree;
	}
	// El mismo encabezado de referencia en todas las páginas: la fuente del kit no es coste del widget medido.
	$widgets = array();
	if ( empty( $case['bare'] ) ) {
		$widgets[] = array( 'id' => digi_fb_eid( 'h', $page ), 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => (object) array( 'title' => 'Página de medición' ), 'elements' => array() );
	}
	if ( 'empty' !== $slug ) {
		foreach ( (array) ( $case['widgets'] ?? array() ) as $index => $def ) {
			$widgets[] = digi_fb_element( (array) $def, $page . $index );
		}
	}
	return array( array( 'id' => digi_fb_eid( 'c', $page ), 'elType' => 'container', 'settings' => (object) ( isset( $case['container'] ) && is_array( $case['container'] ) ? $case['container'] : array() ), 'elements' => $widgets ) );
}

/**
 * Árbol de un documento `extra`. Un widget global (`type: widget`) guarda el widget suelto, como la biblioteca.
 *
 * @param string $slug  Caso.
 * @param array  $extra Definición.
 */
function digi_fb_extra_tree( $slug, array $extra ) {
	$seed = digi_fb_extra_slug( $slug, (string) $extra['key'] );
	if ( isset( $extra['elements'] ) ) {
		$tree = array();
		foreach ( (array) $extra['elements'] as $index => $def ) {
			$tree[] = digi_fb_element( (array) $def, $seed . 'r' . $index );
		}
		return $tree;
	}
	$widgets = array();
	foreach ( (array) ( $extra['widgets'] ?? array() ) as $index => $def ) {
		$widgets[] = digi_fb_element( (array) $def, $seed . $index );
	}
	if ( 'widget' === ( $extra['type'] ?? '' ) ) {
		return array_slice( $widgets, 0, 1 );
	}
	return array( array( 'id' => digi_fb_eid( 'c', $seed ), 'elType' => 'container', 'settings' => (object) ( isset( $extra['container'] ) && is_array( $extra['container'] ) ? $extra['container'] : array() ), 'elements' => $widgets ) );
}

/**
 * Sustituye los marcadores de IDs de un caso en un valor ya decodificado (cadenas, también dentro de otras).
 *
 * @param mixed $value Valor.
 * @param array $map   Marcador → reemplazo.
 */
function digi_fb_replace( $value, array $map ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = digi_fb_replace( $item, $map );
		}
		return $value;
	}
	if ( is_object( $value ) ) {
		$out = new stdClass();
		foreach ( get_object_vars( $value ) as $key => $item ) {
			$out->$key = digi_fb_replace( $item, $map );
		}
		return $out;
	}
	if ( is_string( $value ) && false !== strpos( $value, '__' ) ) {
		return strtr( $value, $map );
	}
	return $value;
}

/**
 * Marcadores de IDs de un caso.
 *
 * @param int   $self   Página del caso.
 * @param array $extras `key` → `[ 'id' => int, 'type' => string ]`.
 */
function digi_fb_markers( $self, array $extras ) {
	$map = array( '__SELF__' => (string) $self );
	foreach ( $extras as $key => $extra ) {
		$map[ '__DOC:' . $key . '__' ] = (string) $extra['id'];
		$type = (string) $extra['type'];
		if ( 'popup' === $type && ! isset( $map['__POPUP__'] ) ) {
			$map['__POPUP__'] = (string) $extra['id'];
		} elseif ( 'loop-item' === $type && ! isset( $map['__LOOP__'] ) ) {
			$map['__LOOP__'] = (string) $extra['id'];
		} elseif ( ! in_array( $type, array( 'popup', 'loop-item', 'header', 'footer' ), true ) && ! isset( $map['__TEMPLATE__'] ) ) {
			$map['__TEMPLATE__'] = (string) $extra['id'];
		}
	}
	return $map;
}

/** ¿Imprime la URL de la página del caso este documento por sus condiciones (popups y Theme Builder)? */
function digi_fb_printed_on_self( array $extra ) {
	$include = false;
	foreach ( (array) ( $extra['conditions'] ?? array() ) as $condition ) {
		$condition = (string) $condition;
		if ( 0 === strpos( $condition, 'exclude/' ) ) {
			return false;
		}
		if ( in_array( $condition, array( 'include/general', 'include/singular', 'include/singular/page', 'include/singular/page/__SELF__' ), true ) ) {
			$include = true;
		}
	}
	return $include;
}

/**
 * Expectativa de un caso en un modo (o en `*`), sin las instantáneas (`pre`, `warm`, `before`, `after`).
 *
 * @param array    $case  Definición.
 * @param string[] $parts Componentes del modo, en orden (p. ej. `[ 'old-applied', 'cache' ]`).
 * @param string   $at    Instantánea: '' (carga), `pre`, `warm`, `before` o `after`.
 */
function digi_fb_expect( array $case, array $parts, $at = '' ) {
	$expect = isset( $case['expect'] ) && is_array( $case['expect'] ) ? $case['expect'] : array();
	$keys   = array_merge( array( '*' ), $parts, count( $parts ) > 1 ? array( implode( '+', $parts ) ) : array() );
	$out    = array();
	foreach ( $keys as $key ) {
		$level = isset( $expect[ $key ] ) && is_array( $expect[ $key ] ) ? $expect[ $key ] : array();
		if ( '*' === $key ) {
			// Las instantáneas también pueden ir en la raíz de `expect` (valen para todos los modos).
			$level = array_merge( $level, array_intersect_key( $expect, array_flip( array( 'pre', 'warm', 'before', 'after' ) ) ) );
		}
		if ( '' === $at || 'before' === $at ) {
			foreach ( $level as $name => $value ) {
				if ( ! in_array( $name, array( 'pre', 'warm', 'before', 'after' ), true ) ) {
					$out[ $name ] = $value;
				}
			}
		}
		if ( '' !== $at && isset( $level[ $at ] ) && is_array( $level[ $at ] ) ) {
			$out = array_merge( $out, $level[ $at ] );
		}
	}
	return $out;
}
