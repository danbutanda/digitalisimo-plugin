<?php
/**
 * Lo que enviaría un navegador al pulsar el botón de un formulario real (reglas del envío de HTML): campos de texto,
 * contraseñas, números, ocultos, textareas, selects y casillas marcadas, en orden, y sólo el botón pulsado. No se envían
 * los controles deshabilitados ni los que están dentro de un <fieldset disabled>. Lo usan las pruebas de paridad
 * sitio/red de los ajustes (tests/ia-settings-save.php, tests/seo-settings-parity.php): pintan la pantalla, la envían
 * como un navegador y comprueban lo guardado.
 *
 *   digi_browser_form( $html, $fill, $button, $form )
 *     $fill   nombre => valor que «escribe» la persona. Una casilla se marca con un valor no vacío y se desmarca con ''
 *             o false; un select toma el valor indicado. Un campo oculto sólo se llena si no hay otro control visible
 *             con el mismo nombre (el valor de un campo de medios viaja en un oculto).
 *     $button nombre del botón pulsado (null: el primero del formulario).
 *     $form   índice del formulario en la página, una cadena que debe aparecer en su atributo action, o
 *             'field:<prefijo>' para el formulario que tiene un campo cuyo nombre empieza así.
 *   Devuelve array( 'action' => …, 'post' => array como $_POST, 'names' => nombres enviados, 'disabled' => nombres
 *   que no se enviaron por estar deshabilitados ).
 */

function digi_browser_form( $html, array $fill = array(), $button = null, $form = 0 ) {
	$dom = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors(); libxml_use_internal_errors( $prev );
	$forms = $dom->getElementsByTagName( 'form' );
	$target = null;
	$xpath = new DOMXPath( $dom );
	if ( is_int( $form ) ) $target = $forms->item( $form );
	elseif ( 0 === strpos( $form, 'field:' ) ) { foreach ( $forms as $candidate ) foreach ( $xpath->query( './/*[@name]', $candidate ) as $named ) if ( 0 === strpos( $named->getAttribute( 'name' ), substr( $form, 6 ) ) ) { $target = $candidate; break 2; } }
	else foreach ( $forms as $candidate ) if ( false !== strpos( (string) $candidate->getAttribute( 'action' ), $form ) ) { $target = $candidate; break; }
	if ( ! $target ) throw new RuntimeException( 'No se encontró el formulario ' . var_export( $form, true ) );
	$controls = $xpath->query( './/input|.//select|.//textarea|.//button', $target );
	$visible = array();
	foreach ( $controls as $control ) {
		$name = $control->getAttribute( 'name' );
		if ( '' !== $name && ! ( 'input' === $control->nodeName && 'hidden' === strtolower( $control->getAttribute( 'type' ) ) ) ) $visible[ $name ] = true;
	}
	$pairs = array(); $names = array(); $disabled = array(); $pressed = false;
	foreach ( $controls as $control ) {
		$name = $control->getAttribute( 'name' );
		if ( '' === $name ) continue;
		if ( digi_browser_disabled( $control ) ) { $disabled[ $name ] = true; continue; }
		$tag = $control->nodeName;
		$type = 'button' === $tag ? strtolower( $control->getAttribute( 'type' ) ?: 'submit' ) : strtolower( $control->getAttribute( 'type' ) ?: 'text' );
		if ( 'button' === $tag || in_array( $type, array( 'submit', 'image', 'reset', 'button' ), true ) ) {
			if ( ! in_array( $type, array( 'submit', 'image' ), true ) || $pressed ) continue;
			if ( null === $button || $button === $name ) { $pairs[] = array( $name, $control->getAttribute( 'value' ) ); $pressed = true; }
			continue;
		}
		if ( 'select' === $tag ) {
			$options = $xpath->query( './/option', $control ); $values = array(); $first = null;
			foreach ( $options as $option ) {
				$value = $option->hasAttribute( 'value' ) ? $option->getAttribute( 'value' ) : $option->textContent;
				if ( null === $first ) $first = $value;
				if ( $option->hasAttribute( 'selected' ) ) $values[] = $value;
			}
			if ( array_key_exists( $name, $fill ) ) $values = (array) $fill[ $name ];
			elseif ( ! $values && null !== $first && ! $control->hasAttribute( 'multiple' ) ) $values = array( $first );
			foreach ( $values as $value ) $pairs[] = array( $name, (string) $value );
		} elseif ( 'textarea' === $tag ) {
			$pairs[] = array( $name, array_key_exists( $name, $fill ) ? (string) $fill[ $name ] : $control->textContent );
		} elseif ( 'checkbox' === $type || 'radio' === $type ) {
			$value = $control->hasAttribute( 'value' ) ? $control->getAttribute( 'value' ) : 'on';
			$checked = $control->hasAttribute( 'checked' );
			if ( array_key_exists( $name, $fill ) ) $checked = 'radio' === $type ? (string) $fill[ $name ] === $value : ( '' !== (string) $fill[ $name ] && false !== $fill[ $name ] && '0' !== (string) $fill[ $name ] );
			if ( $checked ) $pairs[] = array( $name, $value );
		} elseif ( 'file' === $type ) {
			continue;
		} else {
			$value = $control->getAttribute( 'value' );
			if ( array_key_exists( $name, $fill ) && ( 'hidden' !== $type || empty( $visible[ $name ] ) ) ) $value = (string) $fill[ $name ];
			$pairs[] = array( $name, $value );
		}
	}
	foreach ( $pairs as $pair ) $names[ $pair[0] ] = true;
	$query = implode( '&', array_map( function ( $pair ) { return rawurlencode( $pair[0] ) . '=' . rawurlencode( (string) $pair[1] ); }, $pairs ) );
	parse_str( $query, $post );
	return array( 'action' => $target->getAttribute( 'action' ), 'post' => $post, 'names' => array_keys( $names ), 'disabled' => array_keys( array_diff_key( $disabled, $names ) ), 'pressed' => $pressed );
}

/** Un control está deshabilitado por sí mismo o por un <fieldset disabled> que lo contiene (salvo en su primer <legend>). */
function digi_browser_disabled( DOMElement $control ) {
	if ( $control->hasAttribute( 'disabled' ) ) return true;
	for ( $node = $control->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
		if ( 'fieldset' !== $node->nodeName || ! $node->hasAttribute( 'disabled' ) ) continue;
		$legend = null;
		foreach ( $node->childNodes as $child ) if ( $child instanceof DOMElement && 'legend' === $child->nodeName ) { $legend = $child; break; }
		if ( $legend && digi_browser_inside( $control, $legend ) ) continue;
		return true;
	}
	return false;
}

function digi_browser_inside( DOMNode $node, DOMNode $ancestor ) {
	for ( ; $node; $node = $node->parentNode ) if ( $node === $ancestor ) return true;
	return false;
}

/** Nombres de campo de opciones bajo un prefijo: 'ai[chat_enabled]' => 'chat_enabled'. */
function digi_browser_keys( array $names, $prefix ) {
	$keys = array();
	foreach ( $names as $name ) if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '\[([^\]]+)\]/', $name, $m ) ) $keys[ $m[1] ] = true;
	return array_keys( $keys );
}
