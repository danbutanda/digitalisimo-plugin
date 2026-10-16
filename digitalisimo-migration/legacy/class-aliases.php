<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Widgets propios fusionados (DIGITALÍSIMO Elements, class-widget-alias.php): un widget absorbido por otro sigue
 * existiendo como alias oculto, y el contenido guardado conserva su nombre de antes. Aquí ese contenido pasa al
 * widget que lo absorbió: el nombre del alias se sustituye por el del widget fusionado, los ajustes se traducen con
 * `upgrade_settings()` del propio alias (la misma traducción que aplica al pintarse) y, en el CSS personalizado del
 * elemento, las clases de antes (prefijo y nombres de elemento de LEGACY_CLASSES) y la clase del envoltorio pasan a
 * las del widget fusionado, que ya sólo imprime las suyas. Tras convertir, el documento no depende del alias.
 *
 * Un CSS que no es el del propio elemento (CSS adicional, archivos del tema, otros elementos, ajustes de página o del
 * kit, fragmentos de código) puede nombrar esas clases: al convertir dejaría de aplicarse, y reescribirlo lo
 * aplicaría también a los widgets que ya eran el fusionado. Ese tipo de alias no se convierte en el sitio y
 * references() dice dónde está el CSS; el alias sigue pintándolo igual.
 *
 * Los alias se leen de los widgets registrados en Elementor: un alias nuevo de DIGITALÍSIMO Elements queda cubierto
 * sin cambiar nada aquí.
 */
final class Aliases {
	const TRAIT = 'Digitalisimo\\Elements\\Widget_Alias';
	/** Metadatos que repiten el contenido pintado o son de la propia migración: no son CSS escrito por nadie. */
	const SKIP_META = array( '_elementor_data', '_elementor_css', '_elementor_element_cache', '_elementor_page_assets', '_elementor_controls_usage', '_elementor_version', '_elementor_pro_version', '_digitalisimo_ep_backup' );
	/** Tope de archivos del tema que se revisan y de bytes por archivo. */
	const THEME_FILES = 3000;
	const THEME_BYTES = 2097152;

	/** Alias que DIGITALÍSIMO Elements tenía al publicarse esta versión: sólo para avisar si no puede leerse el mapa. */
	const KNOWN = array( 'digitalisimo-brand-carousel', 'digitalisimo-logo-carousel', 'digitalisimo-brand-grid', 'digitalisimo-logo-grid', 'digitalisimo-creative-button', 'digitalisimo-icon-mobile-menu', 'digitalisimo-content-switcher' );

	/** @var array|null */
	private static $map = null;

	/**
	 * Alias registrados: nombre del alias => widget fusionado, clase del alias, prefijo CSS de antes, prefijo CSS del
	 * fusionado y mapa de nombres de elemento (unificado => de antes, o false si no existía).
	 *
	 * @return array<string, array{target:string, class:string, prefix:string, target_prefix:string, elements:array}>
	 */
	public static function map() {
		if ( null !== self::$map ) {
			return self::$map;
		}
		$map = array();
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			$types    = (array) \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
			$by_class = array();
			foreach ( $types as $name => $widget ) {
				if ( is_object( $widget ) ) {
					$by_class[ get_class( $widget ) ] = (string) $name;
				}
			}
			foreach ( $types as $name => $widget ) {
				if ( ! is_object( $widget ) || ! in_array( self::TRAIT, (array) class_uses( $widget ), true ) ) {
					continue;
				}
				$parent = (string) get_parent_class( $widget );
				$target = $by_class[ $parent ] ?? '';
				if ( '' !== $target && $target !== (string) $name ) {
					$map[ (string) $name ] = self::spec( $target, get_class( $widget ), $parent );
				}
			}
		}
		self::$map = $map;
		return $map;
	}

	/** Descripción de un alias a partir de sus clases. */
	public static function spec( $target, $class, $parent ) {
		$legacy = defined( $class . '::LEGACY_CLASSES' ) ? (array) constant( $class . '::LEGACY_CLASSES' ) : array();
		return array(
			'target'        => (string) $target,
			'class'         => (string) $class,
			'prefix'        => (string) ( $legacy['prefix'] ?? '' ),
			'target_prefix' => defined( $parent . '::CSS_PREFIX' ) ? (string) constant( $parent . '::CSS_PREFIX' ) : '',
			'elements'      => is_array( $legacy['elements'] ?? null ) ? $legacy['elements'] : array(),
		);
	}

	/** Fija el mapa sin consultar a Elementor (pruebas); null vuelve a leerlo. */
	public static function set_map( ?array $map ) {
		self::$map = $map;
	}

	/**
	 * Textos que delatan que un CSS, un marcado o un script cuenta con un alias: sus clases de antes, su envoltorio
	 * (`elementor-widget-<alias>`, `data-widget_type="<alias>…"`) y, en código ($code), su nombre entre comillas
	 * (`attachHandler( 'digitalisimo-brand-carousel', … )`). También los del widget fusionado: un CSS que nombra el
	 * envoltorio del fusionado pasaría a aplicarse a los alias convertidos.
	 */
	public static function needles( $type, $code = false ) {
		$spec    = self::map()[ $type ] ?? array();
		$needles = array( 'elementor-widget-' . $type, $type . '.default', '="' . $type, "='" . $type, '=' . $type . ']' );
		if ( ! empty( $spec['prefix'] ) ) {
			$needles[] = (string) $spec['prefix'];
		}
		if ( ! empty( $spec['target'] ) ) {
			$needles[] = 'elementor-widget-' . $spec['target'];
			$needles[] = $spec['target'] . '.default';
		}
		if ( $code ) {
			$needles[] = "'" . $type . "'";
			$needles[] = '"' . $type . '"';
			if ( ! empty( $spec['target'] ) ) {
				$needles[] = "'" . $spec['target'] . "'";
				$needles[] = '"' . $spec['target'] . '"';
			}
		}
		return $needles;
	}

	/**
	 * Documentos del sitio, revisiones incluidas, con algún widget alias (de los tipos indicados, o de cualquiera).
	 *
	 * @return int[]
	 */
	public static function documents( ?array $types = null, $after = 0, $limit = 0 ) {
		$aliases = null === $types ? array_keys( self::map() ) : array_values( array_intersect( $types, array_keys( self::map() ) ) );
		return self::documents_with( $aliases, $after, $limit );
	}

	/**
	 * Documentos con alguno de los nombres de widget indicados, desde el id $after y hasta $limit (0: todos).
	 *
	 * @return int[]
	 */
	public static function documents_with( array $names, $after = 0, $limit = 0 ) {
		global $wpdb;
		if ( ! $names ) {
			return array();
		}
		$like = array();
		foreach ( $names as $name ) {
			$like[] = '%' . $wpdb->esc_like( '"widgetType":"' . $name . '"' ) . '%';
		}
		$sql = "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND post_id > %d AND ( " . implode( ' OR ', array_fill( 0, count( $like ), 'meta_value LIKE %s' ) ) . ' ) ORDER BY post_id' . ( $limit ? ' LIMIT ' . max( 1, (int) $limit ) : '' );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( array( (int) $after ), $like ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** ¿Hay documentos con alias aunque Elementor no exponga el mapa en esta petición? (para avisar en vez de callar) */
	public static function unreadable() {
		return ! self::map() && (bool) self::documents_with( self::KNOWN, 0, 1 );
	}

	/**
	 * Tipos de alias cuyas clases nombra algún CSS o marcado que la conversión no puede reescribir con seguridad, con
	 * hasta cinco lugares por tipo.
	 *
	 * @return array<string, string[]> tipo de alias => lugares.
	 */
	public static function references() {
		global $wpdb;
		$types = array_keys( self::map() );
		$found = array();
		if ( ! $types ) {
			return $found;
		}
		$note = static function ( $type, $place ) use ( &$found ) {
			$found[ $type ] = $found[ $type ] ?? array();
			if ( count( $found[ $type ] ) < 5 && ! in_array( $place, $found[ $type ], true ) ) {
				$found[ $type ][] = $place;
			}
		};
		$scan = static function ( $text, $place, $code = false ) use ( $types, $note ) {
			if ( ! is_string( $text ) || '' === $text ) {
				return;
			}
			foreach ( $types as $type ) {
				foreach ( self::needles( $type, $code ) as $needle ) {
					if ( false !== strpos( $text, $needle ) ) {
						$note( $type, $place );
						break;
					}
				}
			}
		};
		$all = array();
		foreach ( $types as $type ) {
			$all = array_merge( $all, self::needles( $type ) );
		}
		$like  = array();
		foreach ( $all as $needle ) {
			$like[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$any = '( ' . implode( ' OR ', array_fill( 0, count( $like ), '%1$s LIKE %%s' ) ) . ' )';
		// CSS adicional del tema activo (Personalizar) y del editor de sitio.
		if ( function_exists( 'wp_get_custom_css' ) ) {
			$scan( (string) wp_get_custom_css(), 'Personalizar → CSS adicional' );
		}
		// El CSS adicional y los estilos globales que se aplican (los del tema activo), los fragmentos de código publicados
		// (Simple Custom CSS and JS, WPCode) y los bloques, plantillas y partes del editor de sitio.
		$theme = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		// Los fragmentos son código: también el nombre entre comillas (`attachHandler( 'digitalisimo-…' )`).
		$code  = array();
		foreach ( $types as $type ) {
			foreach ( self::needles( $type, true ) as $needle ) {
				$code[] = '%' . $wpdb->esc_like( $needle ) . '%';
			}
		}
		$code  = array_values( array_unique( $code ) );
		$sql   = "SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE ( ( post_type = 'custom_css' AND post_name = %s ) OR ( post_type = 'wp_global_styles' AND post_name = %s ) OR ( post_type IN ( 'custom-css-js', 'wpcode' ) AND post_status = 'publish' ) OR ( post_type IN ( 'wp_block', 'wp_template', 'wp_template_part' ) AND post_status <> 'trash' ) ) AND ( " . implode( ' OR ', array_fill( 0, count( $code ), 'post_content LIKE %s' ) ) . ' )';
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $theme, 'wp-global-styles-' . $theme ), $code ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as $row ) {
			$scan( (string) $row->post_content, sprintf( '%s #%d', 'custom_css' === $row->post_type ? 'CSS adicional' : (string) $row->post_type, (int) $row->ID ), in_array( $row->post_type, array( 'custom-css-js', 'wpcode' ), true ) );
		}
		// Metadatos (ajustes de página y del kit, fragmentos de código…) de entradas que no son revisiones.
		$skip = implode( ', ', array_fill( 0, count( self::SKIP_META ), '%s' ) );
		$sql  = "SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type <> 'revision' AND m.meta_key NOT IN ( {$skip} ) AND " . sprintf( $any, 'm.meta_value' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( self::SKIP_META, $like ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as $row ) {
			$scan( (string) $row->meta_value, self::place( (int) $row->post_id, (string) $row->meta_key ) );
		}
		// Opciones (widgets HTML, CSS personalizado de DIGITALÍSIMO SEO y de otros plugins), salvo cachés y las de la
		// propia migración; en una red, también las de la red (los valores que heredan los sitios).
		// Fuera: cachés, la propia migración y los registros de la auditoría de calidad de DIGITALÍSIMO SEO (guardan las
		// clases que vio en el HTML pintado, no CSS escrito por nadie).
		$own  = array( $wpdb->esc_like( '_transient' ) . '%', $wpdb->esc_like( '_site_transient' ) . '%', $wpdb->esc_like( 'digitalisimo_migration_' ) . '%', $wpdb->esc_like( 'digitalisimo_ep_' ) . '%', $wpdb->esc_like( 'digitalisimo_quality_audit' ) . '%' );
		$sql  = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND " . sprintf( $any, 'option_value' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $own, $like ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as $row ) {
			$scan( (string) $row->option_value, 'opción ' . (string) $row->option_name );
		}
		if ( is_multisite() && ! empty( $wpdb->sitemeta ) ) {
			$sql  = "SELECT meta_key, meta_value FROM {$wpdb->sitemeta} WHERE meta_key NOT LIKE %s AND meta_key NOT LIKE %s AND meta_key NOT LIKE %s AND meta_key NOT LIKE %s AND meta_key NOT LIKE %s AND " . sprintf( $any, 'meta_value' );
			$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $own, $like ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $rows as $row ) {
				$scan( (string) $row->meta_value, 'opción de red ' . (string) $row->meta_key );
			}
		}
		// Contenido de Elementor: todo menos el CSS propio de cada alias, que se reescribe si sólo se aplica a él. En el
		// JSON guardado las comillas van escapadas (`=\"digitalisimo-…`), y todo documento con un alias se revisa.
		$data = $like;
		foreach ( $types as $type ) {
			$data[] = '%' . $wpdb->esc_like( '"widgetType":"' . $type . '"' ) . '%';
			foreach ( self::needles( $type ) as $needle ) {
				if ( false !== strpos( $needle, '"' ) ) {
					$data[] = '%' . $wpdb->esc_like( str_replace( '"', '\\"', $needle ) ) . '%';
				}
			}
		}
		$data = array_values( array_unique( $data ) );
		$sql  = "SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND ( " . implode( ' OR ', array_fill( 0, count( $data ), 'm.meta_value LIKE %s' ) ) . ' )';
		foreach ( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $data ) ) ) as $post_id ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data = json_decode( (string) get_metadata( 'post', $post_id, '_elementor_data', true ), true );
			if ( is_array( $data ) ) {
				self::scan_elements( $data, $post_id, $note );
			}
		}
		// Hojas, scripts y plantillas PHP del tema activo y de su tema padre, y los plugins imprescindibles (mu-plugins).
		foreach ( self::code_files() as $file ) {
			$scan( (string) @file_get_contents( $file ), self::relative( $file ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local.
		}
		ksort( $found );
		return $found;
	}

	/**
	 * Revisa los elementos de un documento. Cualquier ajuste de cualquier elemento que nombre un alias (sus clases, su
	 * envoltorio o el del fusionado) bloquea ese alias, salvo el CSS propio del alias cuando sólo usa las formas que la
	 * conversión reescribe exactamente: sus clases en reglas que empiezan por `selector`, `selector.elementor-widget-<alias>`
	 * y `[data-widget_type="<alias>.default"]`.
	 */
	public static function scan_elements( array $elements, $post_id, callable $note ) {
		$map = self::map();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type     = (string) ( $element['widgetType'] ?? '' );
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$place    = self::place( (int) $post_id, '' ) . ' · elemento ' . (string) ( $element['id'] ?? '?' );
			$own_css  = '';
			if ( 'widget' === ( $element['elType'] ?? '' ) && isset( $map[ $type ] ) && isset( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) ) {
				$own_css = $settings['custom_css'];
				unset( $settings['custom_css'] );
				if ( ! self::own_css_ok( $type, $own_css, $settings ) ) {
					$note( $type, $place . ' (CSS propio)' );
				}
			}
			// El widget fusionado puede nombrar su propio envoltorio en su CSS propio: sólo se aplica a él mismo.
			if ( '' === $own_css && isset( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) && in_array( $type, array_column( $map, 'target' ), true ) ) {
				$settings['custom_css'] = str_replace( array( 'selector.elementor-widget-' . $type, 'selector[data-widget_type="' . $type . '.default"]' ), 'selector', $settings['custom_css'] );
			}
			$text = (string) wp_json_encode( $settings ) . "\n" . $own_css;
			foreach ( array_keys( $map ) as $other ) {
				foreach ( self::needles( $other ) as $needle ) {
					// El CSS propio del alias ya se revisó para su propio tipo.
					$haystack = $other === $type ? (string) wp_json_encode( $settings ) : $text;
					if ( false !== strpos( $haystack, $needle ) ) {
						$note( $other, $place );
						break;
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::scan_elements( $element['elements'], $post_id, $note );
			}
		}
	}

	/** ¿Puede reescribirse exactamente el CSS propio de un alias de este tipo? */
	public static function own_css_ok( $type, $css, array $settings = array() ) {
		$spec = self::map()[ $type ] ?? array();
		if ( '' === trim( (string) $css ) ) {
			return true;
		}
		$prefix = (string) ( $spec['prefix'] ?? '' );
		// Un alias que pinta plantillas guardadas (paneles del alternador) puede contener otro widget del mismo tipo
		// fusionado: su CSS, reescrito a las clases del fusionado, lo alcanzaría también.
		if ( '' !== $prefix && false !== strpos( $css, $prefix ) && ! empty( $spec['class'] ) && is_callable( array( $spec['class'], 'upgrade_settings' ) ) ) {
			$upgraded = (array) call_user_func( array( $spec['class'], 'upgrade_settings' ), $settings );
			if ( false !== strpos( (string) wp_json_encode( $upgraded ), '"template_id"' ) ) {
				return false;
			}
		}
		foreach ( array( 'elementor-widget-' . $type => false, $type . '.default' => true ) as $form => $attribute ) {
			if ( false !== strpos( $css, $form ) && ! self::scoped( $css, $form, $attribute ) ) {
				return false;
			}
		}
		if ( '' !== $prefix && false !== strpos( $css, $prefix ) && ! self::scoped( $css, $prefix ) ) {
			return false;
		}
		// Otra mención del nombre del alias (`[data-widget_type^="<alias>"]`) no se reescribe.
		$rest = str_replace( array( 'elementor-widget-' . $type, $type . '.default' ), '', $css );
		return false === strpos( $rest, $type );
	}

	/**
	 * ¿Cada aparición de $needle en el CSS es una clase (precedida de punto, o el valor de un selector de atributo si
	 * $attribute) dentro de un selector que empieza por `selector`? Lo que aparezca en un valor de declaración no es un
	 * selector y no cuenta.
	 */
	public static function scoped( $css, $needle, $attribute = false ) {
		$css    = (string) preg_replace_callback( '#/\*.*?\*/#s', static function ( $m ) {
			return str_repeat( ' ', strlen( $m[0] ) );
		}, (string) $css );
		$offset = 0;
		while ( false !== ( $p = strpos( $css, $needle, $offset ) ) ) {
			$offset     = $p + strlen( $needle );
			$before     = substr( $css, 0, $p );
			$open       = strrpos( $before, '{' );
			$close      = strrpos( $before, '}' );
			$next_open  = strpos( $css, '{', $p );
			$next_close = strpos( $css, '}', $p );
			// Dentro de un bloque de declaraciones (`content: "…"`): no nombra ninguna clase.
			if ( false !== $open && ( false === $close || $open > $close ) && false !== $next_close && ( false === $next_open || $next_close < $next_open ) ) {
				continue;
			}
			if ( false === $next_open ) {
				return false;
			}
			$start   = max( false === $open ? -1 : $open, false === $close ? -1 : $close ) + 1;
			$segment = substr( $css, $start, $next_open - $start );
			if ( 0 === strpos( ltrim( $segment ), '@' ) ) {
				return false;
			}
			if ( ! $attribute && ( $p < 1 || '.' !== $css[ $p - 1 ] ) ) {
				return false;
			}
			$at = $p - $start;
			foreach ( explode( ',', $segment ) as $piece ) {
				if ( $at <= strlen( $piece ) ) {
					// Con `~` o `+` la regla alcanza a elementos hermanos, que pueden ser otros widgets.
					if ( ! preg_match( '/^selector(?![A-Za-z0-9_-])/', ltrim( $piece ) ) || preg_match( '/[~+]/', $piece ) ) {
						return false;
					}
					break;
				}
				$at -= strlen( $piece ) + 1;
			}
		}
		return true;
	}

	/**
	 * Conversión en memoria de los elementos de un documento.
	 *
	 * @param string[] $skip tipos de alias que no se convierten (references()).
	 * @return array{0:array,1:int} elementos convertidos y número de widgets cambiados.
	 */
	public static function convert_data( array $data, array $skip = array() ) {
		$changes = 0;
		foreach ( $data as $index => $element ) {
			if ( is_array( $element ) ) {
				list( $data[ $index ], $count ) = self::element( $element, $skip );
				$changes += $count;
			}
		}
		return array( $data, $changes );
	}

	/**
	 * Convierte un elemento y sus hijos.
	 *
	 * @return array{0:array,1:int}
	 */
	public static function element( array $element, array $skip = array() ) {
		$changes = 0;
		$map     = self::map();
		$type    = (string) ( $element['widgetType'] ?? '' );
		if ( 'widget' === ( $element['elType'] ?? '' ) && isset( $map[ $type ] ) && ! in_array( $type, $skip, true ) ) {
			$spec     = $map[ $type ];
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			if ( '' !== $spec['class'] && is_callable( array( $spec['class'], 'upgrade_settings' ) ) ) {
				$settings = (array) call_user_func( array( $spec['class'], 'upgrade_settings' ), $settings );
			}
			if ( isset( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) && '' !== $settings['custom_css'] ) {
				$css = str_replace( array( 'elementor-widget-' . $type, $type . '.default' ), array( 'elementor-widget-' . $spec['target'], $spec['target'] . '.default' ), $settings['custom_css'] );
				if ( '' !== $spec['prefix'] && '' !== $spec['target_prefix'] ) {
					$css = self::rewrite_css( $css, $spec['prefix'], $spec['target_prefix'], $spec['elements'] );
				}
				$settings['custom_css'] = $css;
			}
			$element['widgetType'] = $spec['target'];
			$element['settings']   = $settings;
			++$changes;
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $index => $child ) {
				if ( is_array( $child ) ) {
					list( $element['elements'][ $index ], $count ) = self::element( $child, $skip );
					$changes += $count;
				}
			}
		}
		return array( $element, $changes );
	}

	/**
	 * Reescribe las clases del prefijo de antes (`.digi-brand-carousel`, `.digi-brand-carousel__slide--activo`) con el
	 * prefijo del widget fusionado y el nombre unificado de cada elemento (`.digi-logos__item--activo`). Una clase que
	 * el alias nunca imprimía (su nombre unificado se imprime con otro nombre) se deja igual: tampoco coincidía antes.
	 * Los modificadores (`--x`) se conservan.
	 */
	public static function rewrite_css( $css, $from, $to, array $elements ) {
		$old_to_new = array();
		foreach ( $elements as $unified => $old ) {
			if ( is_string( $old ) && '' !== $old ) {
				$old_to_new[ $old ] = (string) $unified;
			}
		}
		$pattern = '/\.' . preg_quote( (string) $from, '/' ) . '(?:__([a-z0-9]+(?:-[a-z0-9]+)*))?(?![A-Za-z0-9_]|-[^-])/';
		return (string) preg_replace_callback( $pattern, static function ( $m ) use ( $to, $old_to_new, $elements ) {
			if ( empty( $m[1] ) ) {
				return '.' . $to;
			}
			if ( isset( $old_to_new[ $m[1] ] ) ) {
				return '.' . $to . '__' . $old_to_new[ $m[1] ];
			}
			return array_key_exists( $m[1], $elements ) ? $m[0] : '.' . $to . '__' . $m[1];
		}, (string) $css );
	}

	/**
	 * Convierte un documento (o una revisión) y guarda una copia del original si aún no la tiene; las escrituras van al
	 * id indicado, nunca al padre de una revisión.
	 *
	 * @return int widgets cambiados.
	 */
	public static function convert_document( $post_id, array $skip = array() ) {
		$raw = get_metadata( 'post', $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return 0;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return 0;
		}
		list( $data, $changes ) = self::convert_data( $data, $skip );
		if ( ! $changes ) {
			return 0;
		}
		if ( '' === (string) get_metadata( 'post', $post_id, Migration::BACKUP, true ) ) {
			add_metadata( 'post', $post_id, Migration::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => $raw ) ) ), true );
		}
		update_metadata( 'post', $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		Migration::clear_css( $post_id );
		return $changes;
	}

	private static function place( $post_id, $meta_key ) {
		$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '';
		return ( '' !== $title ? '«' . $title . '» (#' . $post_id . ')' : '#' . $post_id ) . ( '' !== $meta_key ? ' · ' . $meta_key : '' );
	}

	/** Archivos .css, .js y .php del tema activo y de su padre y de los mu-plugins, sin dependencias empaquetadas. */
	private static function code_files() {
		$dirs = function_exists( 'get_stylesheet_directory' ) ? array( get_stylesheet_directory(), get_template_directory() ) : array();
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$dirs[] = WPMU_PLUGIN_DIR;
		}
		$files = array();
		foreach ( array_unique( $dirs ) as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveCallbackFilterIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), static function ( $file ) {
				return ! ( $file->isDir() && in_array( $file->getFilename(), array( 'node_modules', 'vendor', '.git' ), true ) );
			} ) );
			foreach ( $iterator as $file ) {
				if ( count( $files ) >= self::THEME_FILES ) {
					break 2;
				}
				if ( $file->isFile() && preg_match( '/\.(css|js|php)$/i', $file->getFilename() ) && $file->getSize() <= self::THEME_BYTES ) {
					$files[] = $file->getPathname();
				}
			}
		}
		return $files;
	}

	private static function relative( $file ) {
		$file = wp_normalize_path( $file );
		$root = function_exists( 'get_theme_root' ) ? trailingslashit( wp_normalize_path( get_theme_root() ) ) : '';
		if ( '' !== $root && 0 === strpos( $file, $root ) ) {
			return 'tema: ' . substr( $file, strlen( $root ) );
		}
		return 'mu-plugins: ' . basename( $file );
	}
}
