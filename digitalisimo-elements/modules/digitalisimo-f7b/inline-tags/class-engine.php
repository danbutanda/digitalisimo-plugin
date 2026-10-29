<?php
namespace Digitalisimo\Elements\F7b\Inline_Tags;

defined( 'ABSPATH' ) || exit;

/**
 * Etiquetas dinámicas dentro del texto (F7b, frente I; V.8 de la especificación).
 *
 * Sintaxis corta `{grupo:dato}` en el texto del usuario. Cada etiqueta se compara con la lista blanca **antes** de
 * instanciar nada (C1-18); las que pasan se resuelven con las mismas etiquetas dinámicas de PRO, instanciadas por
 * nombre con el gestor del núcleo (`Plugin::$instance->dynamic_tags->get_tag_data_content()`,
 * `elementor/core/dynamic-tags/manager.php:236`). No hay otro motor:
 *
 * | Sintaxis | Etiqueta (ajustes) |
 * |---|---|
 * | `{post:title}`, `{post:excerpt}`, `{post:author}` | `post-title`, `post-excerpt`, `author-name` |
 * | `{post:date}`, `{post:date:<formato>}` | `post-date` (`format` `default` o `custom`) |
 * | `{post:meta:<clave>}` | `post-custom-field` (`key`); sin `_` inicial, fuera de la lista negra y, con ACF, nunca un campo de un tipo no permitido |
 * | `{acf:<campo>}` | `acf-text`, leyendo siempre la entrada del contexto (`acf/pre_load_post_id`); nunca `options:`; sólo los tipos de `ACF_TYPES` |
 * | `{user:<dato>}`, `{user:<dato>|<respaldo>}` | `user-info` (`type`; el apodo, como meta `nickname`) |
 * | `{site:name}`, `{site:tagline}` | `site-title`, `site-tagline` |
 * | `{date:<formato>}` | `current-date-time` (`date_format` `custom`) |
 * | `{query:<parámetro>|<valor1>,<valor2>}` | Ninguna (C2-9, C2-27): imprime el valor de la lista que coincide exactamente con el parámetro GET, o nada |
 *
 * Reglas (V.8, C2-8 a C2-11, C2-28):
 * - Lo que no encaja en la sintaxis, lo que la lista blanca rechaza y lo que no se puede resolver (ACF ausente, etiqueta
 *   sin registrar) se deja **tal cual**: nunca se borra texto del usuario.
 * - El valor siempre pasa por `esc_html()` tras quitarle las etiquetas HTML; `[`, `]`, `{` y `}` van como entidades para
 *   que ningún shortcode ni otra etiqueta se ejecute desde un dato (el Editor de texto aplica `do_shortcode`).
 * - Sólo se sustituye en el texto, nunca dentro de una etiqueta HTML (atributos, URLs) ni en `<script>` o `<style>`.
 * - Lista negra `digitalisimo_elements_inline_tags_denied` por fragmentos separados por `_` o `-`, sin distinguir
 *   mayúsculas: `api_key` se bloquea, `keywords` no.
 * - `{user:…}` con sesión iniciada pide a las cachés de página que no guarden la página
 *   (`Conditions\Visitor_Content::flag_page()`); sin sesión, la salida es la misma para todos (C2-10).
 */
final class Engine {
	/** Una etiqueta: grupo de la lista blanca y el resto, sin llaves, ángulos ni saltos de línea. */
	const PATTERN = '/\{(post|acf|user|site|date|query):([^{}<>\r\n]{1,200})\}/';

	/** Filtro de la lista negra. */
	const FILTER_DENIED = 'digitalisimo_elements_inline_tags_denied';

	/** Fragmentos prohibidos en claves de metas, campos de ACF y parámetros de la URL (V.8). */
	const DENIED = array( 'pass', 'password', 'pwd', 'token', 'secret', 'key', 'hash', 'salt', 'nonce', 'email', 'mail', 'phone', 'tel', 'address', 'dni', 'rfc', 'curp', 'ssn', 'iban', 'card', 'name', 'apellido', 'nombre', 'telefono', 'celular', 'movil', 'correo', 'contrasena', 'clave', 'direccion', 'domicilio', 'tarjeta', 'nss' );

	/** Datos del usuario permitidos y los ajustes de `user-info` que los leen (`nickname` no es un `type`: es una meta). */
	const USER_FIELDS = array(
		'display_name' => array( 'type' => 'display_name' ),
		'first_name'   => array( 'type' => 'first_name' ),
		'last_name'    => array( 'type' => 'last_name' ),
		'nickname'     => array(
			'type'     => 'meta',
			'meta_key' => 'nickname', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		),
		'description'  => array( 'type' => 'description' ),
		'url'          => array( 'type' => 'url' ),
	);

	/** Tipos de campo de ACF que se pueden imprimir (C2-8): nunca `password` ni `email`. */
	const ACF_TYPES = array( 'text', 'textarea', 'number', 'url', 'select', 'radio', 'true_false', 'date_picker' );

	/** Parámetros de la URL que nunca se leen: campañas y clics (C2-9). */
	const QUERY_DENIED_PREFIXES = array( 'utm_', 'mc_' );
	const QUERY_DENIED          = array( 'gclid', 'fbclid' );

	/** Clave de meta o campo: empieza por letra o número (nunca `_`), hasta 64 caracteres. */
	const NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/';

	/** Formato de fecha de PHP: letras, números, espacios, `, . / : -` y la barra invertida que escapa. */
	const FORMAT = '/^[A-Za-z0-9 ,.\/:\\\\-]{1,40}$/';

	/** Parámetro de la URL. */
	const PARAM = '/^[A-Za-z0-9_-]{1,40}$/';

	const FALLBACK_MAX = 60;
	const LIST_MAX     = 20;
	const VALUE_MAX    = 60;

	/** Identificador de las instancias de etiqueta (no se imprime: ninguna de estas etiquetas envuelve su valor). */
	const TAG_ID = 'digitalisimo-inline';

	/**
	 * ¿Contiene el texto alguna etiqueta con la sintaxis corta?
	 *
	 * @param mixed $text Texto guardado.
	 */
	public static function has_tokens( $text ): bool {
		return is_string( $text ) && false !== strpos( $text, '{' ) && 1 === preg_match( self::PATTERN, $text );
	}

	/**
	 * Sustituye las etiquetas del texto, sólo fuera de las etiquetas HTML y de `<script>`, `<style>` y `<textarea>`.
	 *
	 * @param string $text Texto de un control (puede llevar HTML: el Editor de texto, la descripción).
	 */
	public static function render( string $text ): string {
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}
		$parts = preg_split( '/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $text;
		}
		$skip = '';
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				if ( '' === $skip && preg_match( '/^<(script|style|textarea)\b/i', $part, $m ) ) {
					$skip = strtolower( $m[1] );
				} elseif ( '' !== $skip && preg_match( '/^<\/' . $skip . '\b/i', $part ) ) {
					$skip = '';
				}
				continue;
			}
			if ( '' !== $skip || false === strpos( $part, '{' ) ) {
				continue;
			}
			$parts[ $i ] = preg_replace_callback( self::PATTERN, array( __CLASS__, 'replace' ), $part );
		}
		return implode( '', $parts );
	}

	/**
	 * Una etiqueta encontrada: su valor escapado, o la etiqueta tal cual si no se puede sustituir.
	 *
	 * @param array $m Coincidencia de `PATTERN`.
	 */
	public static function replace( array $m ): string {
		$spec  = self::parse( $m[1], $m[2] );
		$value = null === $spec ? null : self::resolve( $spec );
		return null === $value ? $m[0] : $value;
	}

	/**
	 * Lista blanca: qué etiqueta y con qué ajustes, sin instanciar nada.
	 *
	 * Devuelve `null` si no es una etiqueta conocida, `[ 'kind' => 'blocked' ]` si lo es pero las reglas la rechazan,
	 * o la descripción de lo que hay que resolver.
	 *
	 * @param string $group Grupo (`post`, `acf`, `user`, `site`, `date`, `query`).
	 * @param string $rest  Lo que sigue a los dos puntos.
	 */
	public static function parse( string $group, string $rest ): ?array {
		switch ( $group ) {
			case 'post':
				return self::parse_post( $rest );
			case 'acf':
				if ( 0 === stripos( $rest, 'options:' ) || false !== strpos( $rest, ':' ) ) {
					return self::blocked();
				}
				if ( ! preg_match( self::NAME, $rest ) ) {
					return null;
				}
				return self::denied( $rest ) ? self::blocked() : array(
					'kind'  => 'acf',
					'field' => $rest,
				);
			case 'user':
				$bar      = strpos( $rest, '|' );
				$field    = false === $bar ? $rest : substr( $rest, 0, $bar );
				$fallback = false === $bar ? '' : trim( substr( $rest, $bar + 1 ) );
				if ( ! isset( self::USER_FIELDS[ $field ] ) || false !== strpos( $fallback, '|' ) || strlen( $fallback ) > self::FALLBACK_MAX ) {
					return null;
				}
				return self::tag( 'user-info', self::USER_FIELDS[ $field ] ) + array(
					'user'     => true,
					'fallback' => $fallback,
				);
			case 'site':
				$tags = array(
					'name'    => 'site-title',
					'tagline' => 'site-tagline',
				);
				return isset( $tags[ $rest ] ) ? self::tag( $tags[ $rest ] ) : null;
			case 'date':
				return preg_match( self::FORMAT, $rest ) ? self::tag(
					'current-date-time',
					array(
						'date_format'   => 'custom',
						'custom_format' => $rest,
					)
				) : null;
			case 'query':
				return self::parse_query( $rest );
		}
		return null;
	}

	/**
	 * Valor de una etiqueta ya aceptada por `parse()`: escapado, o `null` para dejarla tal cual.
	 *
	 * @param array $spec Lo que devolvió `parse()`.
	 * @param bool  $flag Si `{user:…}` con sesión debe sacar la página de la caché (falso en la vista previa).
	 */
	public static function resolve( array $spec, bool $flag = true ): ?string {
		$kind = isset( $spec['kind'] ) ? $spec['kind'] : '';
		if ( 'query' === $kind ) {
			return self::resolve_query( $spec );
		}
		if ( 'acf' === $kind ) {
			$raw = self::resolve_acf( (string) $spec['field'] );
		} elseif ( 'tag' === $kind ) {
			if ( isset( $spec['meta'] ) && ! self::meta_allowed( (string) $spec['meta'] ) ) {
				return null;
			}
			$raw = self::content( (string) $spec['tag'], (array) $spec['settings'] );
		} else {
			return null;
		}
		if ( null === $raw ) {
			return null;
		}
		$value = self::escape( $raw );
		if ( ! empty( $spec['user'] ) ) {
			if ( '' === $value && '' !== $spec['fallback'] ) {
				$value = self::escape( $spec['fallback'] );
			}
			if ( $flag && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
				self::flag_page();
			}
		}
		return $value;
	}

	/**
	 * ¿Está bloqueado este nombre por la lista negra? Fragmentos separados por `_` o `-`, sin distinguir mayúsculas.
	 *
	 * @param string $name Clave, campo o parámetro.
	 */
	public static function denied( string $name ): bool {
		$fragments = preg_split( '/[_-]+/', strtolower( $name ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $fragments ) && array() !== array_intersect( $fragments, self::denied_list() );
	}

	/** Lista negra vigente (filtrable), en minúsculas. */
	public static function denied_list(): array {
		$list = function_exists( 'apply_filters' ) ? apply_filters( self::FILTER_DENIED, self::DENIED ) : self::DENIED;
		$out  = array();
		foreach ( (array) $list as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$out[] = strtolower( trim( $item ) );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Valor listo para el texto: sin HTML, con `esc_html()` y sin nada que otro analizador pueda ejecutar.
	 *
	 * @param mixed $value Valor de la etiqueta.
	 */
	public static function escape( $value ): string {
		$text = esc_html( wp_strip_all_tags( is_scalar( $value ) ? (string) $value : '' ) );
		return strtr(
			$text,
			array(
				'[' => '&#91;',
				']' => '&#93;',
				'{' => '&#123;',
				'}' => '&#125;',
			)
		);
	}

	/**
	 * Vista previa para el editor («Insertar dato»): estado y valor en texto plano, con la entrada indicada como contexto.
	 *
	 * Estados: `ok`, `empty`, `blocked` (la lista blanca o el tipo la rechazan), `unknown` (no es una etiqueta) y `url`
	 * (`{query:…}`: depende de la URL; el valor es la lista).
	 *
	 * @param string $token   Una sola etiqueta, con llaves.
	 * @param int    $post_id Documento del editor.
	 */
	public static function preview( string $token, int $post_id ): array {
		$inner = substr( self::PATTERN, 1, -1 );
		if ( ! preg_match( '/^' . $inner . '$/', $token, $m ) ) {
			return self::state( 'unknown' );
		}
		$spec = self::parse( $m[1], $m[2] );
		if ( null === $spec ) {
			return self::state( 'unknown' );
		}
		if ( 'blocked' === $spec['kind'] ) {
			return self::state( 'blocked' );
		}
		if ( 'query' === $spec['kind'] ) {
			return self::state( 'url', implode( ', ', $spec['values'] ) );
		}
		$previous = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$post     = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( $post ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
		}
		try {
			$value = self::resolve( $spec, false );
		} finally {
			$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $previous ) {
				setup_postdata( $previous );
			}
		}
		if ( null === $value ) {
			return self::state( 'blocked' );
		}
		$plain = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
		return self::state( '' === $plain ? 'empty' : 'ok', $plain );
	}

	/**
	 * Opciones de «Insertar dato»: sólo la lista blanca. `value` es la etiqueta sin llaves; `sep` une el argumento;
	 * `arg` es la pista del argumento (vacía si no lleva) y `required` si es obligatorio.
	 */
	public static function choices(): array {
		$date   = __( 'Formato de fecha de PHP, por ejemplo j F Y', 'pro-elements' );
		$choice = static function ( string $value, string $label, string $arg = '', bool $required = false, string $sep = ':' ): array {
			return compact( 'value', 'label', 'arg', 'required', 'sep' );
		};
		$fallback = __( 'Texto si no hay sesión (opcional)', 'pro-elements' );
		$groups   = array(
			array(
				'label'   => __( 'Contenido', 'pro-elements' ),
				'options' => array(
					$choice( 'post:title', __( 'Título', 'pro-elements' ) ),
					$choice( 'post:excerpt', __( 'Extracto', 'pro-elements' ) ),
					$choice( 'post:author', __( 'Autor', 'pro-elements' ) ),
					$choice( 'post:date', __( 'Fecha de publicación', 'pro-elements' ), $date . ' ' . __( '(opcional)', 'pro-elements' ) ),
					$choice( 'post:meta', __( 'Campo personalizado', 'pro-elements' ), __( 'Clave del campo', 'pro-elements' ), true ),
				),
			),
		);
		if ( function_exists( 'get_field_object' ) ) {
			$groups[] = array(
				'label'   => __( 'Campos de ACF', 'pro-elements' ),
				'options' => array( $choice( 'acf', __( 'Campo de ACF de este contenido', 'pro-elements' ), __( 'Nombre del campo', 'pro-elements' ), true ) ),
			);
		}
		$groups[] = array(
			'label'   => __( 'Usuario con sesión', 'pro-elements' ),
			'options' => array(
				$choice( 'user:display_name', __( 'Nombre público', 'pro-elements' ), $fallback, false, '|' ),
				$choice( 'user:first_name', __( 'Nombre', 'pro-elements' ), $fallback, false, '|' ),
				$choice( 'user:last_name', __( 'Apellidos', 'pro-elements' ), $fallback, false, '|' ),
				$choice( 'user:nickname', __( 'Apodo', 'pro-elements' ), $fallback, false, '|' ),
				$choice( 'user:description', __( 'Biografía', 'pro-elements' ), $fallback, false, '|' ),
				$choice( 'user:url', __( 'Sitio web', 'pro-elements' ), $fallback, false, '|' ),
			),
		);
		$groups[] = array(
			'label'   => __( 'Sitio', 'pro-elements' ),
			'options' => array(
				$choice( 'site:name', __( 'Nombre del sitio', 'pro-elements' ) ),
				$choice( 'site:tagline', __( 'Descripción corta del sitio', 'pro-elements' ) ),
				$choice( 'date', __( 'Fecha de hoy', 'pro-elements' ), $date, true ),
			),
		);
		$groups[] = array(
			'label'   => __( 'Dirección de la página', 'pro-elements' ),
			'options' => array( $choice( 'query', __( 'Parámetro de la URL', 'pro-elements' ), __( 'parámetro|valor1,valor2 (sólo se imprime uno de esos valores)', 'pro-elements' ), true ) ),
		);
		return $groups;
	}

	// -----------------------------------------------------------------------------------------------------------------

	/** @param string $rest Lo que sigue a `post:`. */
	private static function parse_post( string $rest ): ?array {
		$simple = array(
			'title'   => 'post-title',
			'excerpt' => 'post-excerpt',
			'author'  => 'author-name',
		);
		if ( isset( $simple[ $rest ] ) ) {
			return self::tag( $simple[ $rest ] );
		}
		if ( 'date' === $rest ) {
			return self::tag(
				'post-date',
				array(
					'type'   => 'post_date_gmt',
					'format' => 'default',
				)
			);
		}
		if ( 0 === strpos( $rest, 'date:' ) ) {
			$format = substr( $rest, 5 );
			return preg_match( self::FORMAT, $format ) ? self::tag(
				'post-date',
				array(
					'type'          => 'post_date_gmt',
					'format'        => 'custom',
					'custom_format' => $format,
				)
			) : null;
		}
		if ( 0 === strpos( $rest, 'meta:' ) ) {
			$key = substr( $rest, 5 );
			if ( '' !== $key && '_' === $key[0] ) {
				return self::blocked();
			}
			if ( ! preg_match( self::NAME, $key ) ) {
				return null;
			}
			return self::denied( $key ) ? self::blocked() : self::tag( 'post-custom-field', array( 'key' => $key ) ) + array( 'meta' => $key );
		}
		return null;
	}

	/** @param string $rest Lo que sigue a `query:`. */
	private static function parse_query( string $rest ): ?array {
		$bar   = strpos( $rest, '|' );
		$param = false === $bar ? $rest : substr( $rest, 0, $bar );
		if ( ! preg_match( self::PARAM, $param ) ) {
			return null;
		}
		$lower = strtolower( $param );
		foreach ( self::QUERY_DENIED_PREFIXES as $prefix ) {
			if ( 0 === strpos( $lower, $prefix ) ) {
				return self::blocked();
			}
		}
		if ( in_array( $lower, self::QUERY_DENIED, true ) || self::denied( $param ) || false === $bar ) {
			return self::blocked();
		}
		$values = array();
		foreach ( explode( ',', substr( $rest, $bar + 1 ) ) as $value ) {
			$value = trim( $value );
			if ( '' === $value ) {
				continue;
			}
			if ( strlen( $value ) > self::VALUE_MAX || false !== strpos( $value, '|' ) ) {
				return self::blocked();
			}
			$values[] = $value;
		}
		$values = array_values( array_unique( $values ) );
		if ( ! $values || count( $values ) > self::LIST_MAX ) {
			return self::blocked();
		}
		return array(
			'kind'   => 'query',
			'param'  => $param,
			'values' => $values,
		);
	}

	/**
	 * `{query:…}`: el valor de la lista que coincide exactamente con el parámetro GET, o nada. Nunca lo que llega.
	 *
	 * @param array $spec Parámetro y lista.
	 */
	private static function resolve_query( array $spec ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara con la lista del administrador; nunca se imprime.
		$raw = isset( $_GET[ $spec['param'] ] ) ? $_GET[ $spec['param'] ] : null;
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$raw = function_exists( 'wp_unslash' ) ? wp_unslash( $raw ) : $raw;
		foreach ( $spec['values'] as $value ) {
			if ( $raw === $value ) {
				return self::escape( $value );
			}
		}
		return '';
	}

	/**
	 * `{acf:campo}`: el campo de la entrada del contexto, si su tipo está permitido; con la etiqueta `acf-text`.
	 *
	 * @param string $name Nombre del campo.
	 */
	private static function resolve_acf( string $name ): ?string {
		if ( ! function_exists( 'get_field_object' ) || ! function_exists( 'get_the_ID' ) ) {
			return null;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 ) {
			return null;
		}
		$field = get_field_object( $name, $post_id, false, false );
		if ( ! is_array( $field ) && function_exists( 'acf_get_field' ) ) {
			// ACF busca por nombre sólo entre los campos que esta entrada ya guardó; uno definido pero vacío aquí no
			// debe quedar como texto literal: su definición decide el tipo y la etiqueta imprime vacío.
			$field = acf_get_field( $name );
		}
		if ( ! is_array( $field ) || empty( $field['key'] )|| ! is_string( $field['key'] ) || ! self::acf_type_allowed( $field ) ) {
			return null;
		}
		$real = isset( $field['name'] ) && is_string( $field['name'] ) ? $field['name'] : $name;
		if ( self::denied( $real ) ) {
			return null;
		}
		// La etiqueta lee el objeto consultado; aquí siempre la entrada del contexto (nunca opciones ni otro objeto).
		$force = static function () use ( $post_id ) {
			return $post_id;
		};
		add_filter( 'acf/pre_load_post_id', $force, 99 );
		try {
			return self::content( 'acf-text', array( 'key' => $field['key'] . ':' . $real ) );
		} finally {
			remove_filter( 'acf/pre_load_post_id', $force, 99 );
		}
	}

	/**
	 * Con ACF, una clave de meta que es un campo de ACF sólo se lee si su tipo está permitido (un campo `password`
	 * guardado con otro nombre no se cuela por `{post:meta:…}`).
	 *
	 * @param string $key Clave de la meta.
	 */
	private static function meta_allowed( string $key ): bool {
		if ( ! function_exists( 'acf_get_field' ) || ! function_exists( 'get_the_ID' ) || ! function_exists( 'get_post_meta' ) ) {
			return true;
		}
		$reference = get_post_meta( (int) get_the_ID(), '_' . $key, true );
		if ( ! is_string( $reference ) || 0 !== strpos( $reference, 'field_' ) ) {
			return true;
		}
		$field = acf_get_field( $reference );
		return ! is_array( $field ) || self::acf_type_allowed( $field );
	}

	/** @param array $field Definición del campo de ACF. */
	private static function acf_type_allowed( array $field ): bool {
		return isset( $field['type'] ) && in_array( $field['type'], self::ACF_TYPES, true );
	}

	/**
	 * Contenido de una etiqueta del gestor del núcleo, o `null` si no está registrada (por ejemplo, ACF sin activar).
	 *
	 * @param string $name     Nombre de la etiqueta.
	 * @param array  $settings Ajustes.
	 */
	private static function content( string $name, array $settings ): ?string {
		$plugin  = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
		$manager = $plugin && isset( $plugin->dynamic_tags ) ? $plugin->dynamic_tags : null;
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_tag_data_content' ) ) {
			return null;
		}
		if ( method_exists( $manager, 'get_tag_info' ) && ! $manager->get_tag_info( $name ) ) {
			return null;
		}
		$value = $manager->get_tag_data_content( self::TAG_ID, $name, $settings );
		return is_scalar( $value ) ? (string) $value : null;
	}

	/** Saca la página de las cachés de página con las señales que ya usan las condiciones de visitante. */
	private static function flag_page(): void {
		$class = 'Digitalisimo\\Elements\\Conditions\\Visitor_Content';
		if ( class_exists( $class ) && method_exists( $class, 'flag_page' ) ) {
			$class::flag_page();
		}
	}

	/**
	 * @param string $name     Etiqueta.
	 * @param array  $settings Ajustes.
	 */
	private static function tag( string $name, array $settings = array() ): array {
		return array(
			'kind'     => 'tag',
			'tag'      => $name,
			'settings' => $settings,
		);
	}

	private static function blocked(): array {
		return array( 'kind' => 'blocked' );
	}

	/**
	 * @param string $state Estado.
	 * @param string $value Valor en texto plano.
	 */
	private static function state( string $state, string $value = '' ): array {
		return array(
			'state' => $state,
			'value' => $value,
		);
	}
}
