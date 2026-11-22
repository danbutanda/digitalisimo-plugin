<?php
/**
 * Envía un formulario pintado como lo haría un navegador, para las pruebas de paridad sitio/red: toma el `<form>` real
 * de la pantalla y arma `$_POST` (y la acción) con lo que el navegador mandaría al pulsar un botón.
 *
 * - `input` sin `disabled`: hidden, text, password, number…; checkbox y radio sólo si están marcados.
 * - `select` sin `disabled`: la opción con `selected` o, si no hay, la primera (los `multiple`, las marcadas).
 * - `textarea` sin `disabled`: su texto.
 * - Botones: sólo el pulsado lleva su `name`/`value` (el primero `type="submit"` si no se dice cuál).
 * - El orden es el del documento, así que un oculto a 0 seguido de su casilla marcada envía 1, como PHP con un navegador.
 *
 * `$fill` cambia valores por `name` (un `true`/`false` marca o desmarca una casilla, un texto la marca con ese valor);
 * `$options['disabled']` lista los `name` que el script de la pantalla deshabilita al cargar (p. ej. los heredados) y
 * `$options['enable']` los que habilita (al desmarcar «Heredar de la red»);
 * `$options['button']` elige el botón por su texto, id o name; `$options['slash']` añade las barras de WordPress
 * (`wp_magic_quotes()`), para comprobar que el manejador las quita.
 */

if ( ! function_exists( 'browser_form_find' ) ) {
	/** Los formularios del HTML como array de [ 'attrs' => string, 'html' => string ]. */
	function browser_forms( $html ) {
		preg_match_all( '#<form\b([^>]*)>(.*?)</form>#is', (string) $html, $m, PREG_SET_ORDER );
		return array_map( static function ( $f ) { return array( 'attrs' => $f[1], 'html' => $f[2] ); }, $m );
	}

	/** El formulario que contiene $needle (texto exacto: un id, `value="mi_accion"`…), o null. */
	function browser_form_find( $html, $needle ) {
		foreach ( browser_forms( $html ) as $form ) {
			if ( false !== strpos( $form['attrs'] . $form['html'], $needle ) ) return $form;
		}
		return null;
	}

	function browser_form_attr( $attrs, $name ) {
		if ( preg_match( '#(?<![\w-])' . preg_quote( $name, '#' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $attrs, $m ) ) return html_entity_decode( '' !== $m[1] ? $m[1] : ( $m[2] ?? '' ), ENT_QUOTES, 'UTF-8' );
		return null;
	}

	function browser_form_flag( $attrs, $name ) {
		$bare = preg_replace( '#"[^"]*"|\'[^\']*\'#', '""', (string) $attrs );
		return 1 === preg_match( '#(?<![\w-])' . preg_quote( $name, '#' ) . '(?![\w-])#i', $bare );
	}

	/**
	 * Lo que el navegador envía: array( 'method' => …, 'action' => URL, 'pairs' => [[name, value]…], 'post' => array ).
	 */
	function browser_form_submit( array $form, array $fill = array(), array $options = array() ) {
		$disabled = (array) ( $options['disabled'] ?? array() );
		$enable   = (array) ( $options['enable'] ?? array() );
		$button   = $options['button'] ?? null;
		$pairs    = array();
		$pressed  = false;
		preg_match_all( '#<(input|select|textarea|button)\b([^>]*)>(?:(.*?)</\1>)?#is', $form['html'], $controls, PREG_SET_ORDER );
		foreach ( $controls as $c ) {
			$tag   = strtolower( $c[1] );
			$attrs = $c[2];
			$inner = $c[3] ?? '';
			$name  = browser_form_attr( $attrs, 'name' );
			$type  = strtolower( (string) browser_form_attr( $attrs, 'type' ) );
			if ( 'button' === $tag || ( 'input' === $tag && in_array( $type, array( 'submit', 'image' ), true ) ) ) {
				if ( 'button' === $tag && '' === $type ) $type = 'submit';
				if ( 'submit' !== $type || $pressed || browser_form_flag( $attrs, 'disabled' ) ) continue;
				$label = 'button' === $tag ? trim( html_entity_decode( strip_tags( $inner ), ENT_QUOTES, 'UTF-8' ) ) : (string) browser_form_attr( $attrs, 'value' );
				if ( null !== $button && ! in_array( $button, array( $label, browser_form_attr( $attrs, 'id' ), $name ), true ) ) continue;
				$pressed = true;
				if ( null !== $name && '' !== $name ) $pairs[] = array( $name, (string) browser_form_attr( $attrs, 'value' ) );
				continue;
			}
			if ( null === $name || '' === $name || in_array( $name, $disabled, true ) || ( browser_form_flag( $attrs, 'disabled' ) && ! in_array( $name, $enable, true ) ) ) continue;
			if ( 'input' === $tag ) {
				if ( in_array( $type, array( 'button', 'reset', 'file' ), true ) ) continue;
				$value = browser_form_attr( $attrs, 'value' );
				if ( in_array( $type, array( 'checkbox', 'radio' ), true ) ) {
					$on = browser_form_flag( $attrs, 'checked' );
					if ( array_key_exists( $name, $fill ) ) {
						$want = $fill[ $name ];
						$on   = is_bool( $want ) ? $want : ( 'radio' === $type ? (string) $want === (string) ( $value ?? 'on' ) : true );
					}
					if ( $on ) $pairs[] = array( $name, null === $value ? 'on' : $value );
					continue;
				}
				$pairs[] = array( $name, array_key_exists( $name, $fill ) ? (string) $fill[ $name ] : (string) $value );
				continue;
			}
			if ( 'textarea' === $tag ) {
				$pairs[] = array( $name, array_key_exists( $name, $fill ) ? (string) $fill[ $name ] : html_entity_decode( preg_replace( '#^\r?\n#', '', $inner ), ENT_QUOTES, 'UTF-8' ) );
				continue;
			}
			// select
			preg_match_all( '#<option\b([^>]*)>(.*?)(?=<option\b|$)#is', $inner, $opts, PREG_SET_ORDER );
			$values = array();
			$chosen = array();
			foreach ( $opts as $o ) {
				$v = browser_form_attr( $o[1], 'value' );
				$v = null === $v ? trim( html_entity_decode( strip_tags( $o[2] ), ENT_QUOTES, 'UTF-8' ) ) : $v;
				$values[] = $v;
				if ( browser_form_flag( $o[1], 'selected' ) ) $chosen[] = $v;
			}
			if ( array_key_exists( $name, $fill ) ) $chosen = (array) $fill[ $name ];
			elseif ( ! $chosen && $values && ! browser_form_flag( $attrs, 'multiple' ) ) $chosen = array( $values[0] );
			foreach ( $chosen as $v ) $pairs[] = array( $name, (string) $v );
		}
		if ( null !== $button && ! $pressed ) throw new RuntimeException( 'El formulario no tiene el botón «' . $button . '».' );
		$query = implode( '&', array_map( static function ( $p ) { return rawurlencode( $p[0] ) . '=' . rawurlencode( $p[1] ); }, $pairs ) );
		parse_str( $query, $post );
		if ( ! empty( $options['slash'] ) ) {
			$slash = static function ( $v ) use ( &$slash ) { return is_array( $v ) ? array_map( $slash, $v ) : addslashes( (string) $v ); };
			$post  = $slash( $post );
		}
		return array( 'method' => strtolower( (string) ( browser_form_attr( $form['attrs'], 'method' ) ?? 'get' ) ), 'action' => (string) browser_form_attr( $form['attrs'], 'action' ), 'pairs' => $pairs, 'post' => $post );
	}
}
