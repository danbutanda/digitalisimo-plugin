<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Código global de Element Pack y de Prime Slider Pro → Código personalizado del constructor (`elementor_snippet`),
 * §3.D.7, F8-D20 y V.7.
 *
 * Origen: `ep_custom_css`/`ep_custom_js` (cabecera), `ep_custom_css_2`/`ep_custom_js_2` (pie) y `ep_excluded_pages`;
 * lo mismo con `ps_`. Destino: un fragmento por opción con `_elementor_location` (`elementor_head` o
 * `elementor_body_end`), `_elementor_priority` = 10, `_elementor_code` y `_elementor_conditions` = `include/general`
 * más una exclusión por página excluida (la portada, `exclude/singular/front_page`; la página de entradas,
 * `exclude/archive/post_archive`; el resto, `exclude/singular/<tipo>/<ID>`).
 *
 * - **Todo nace en borrador** (un borrador no se ejecuta). El JavaScript **nunca** se publica y sólo se copia si quien
 *   lanzó la tarea puede publicar código sin filtrar; si no, sólo se informa.
 * - **El CSS** se publica sólo si: el plugin estaba activo al empezar; quien lanzó la tarea tiene `unfiltered_html` (en
 *   una red, es superadministrador), revalidado al publicar; el CSS supera `validate_css()`; y cada exclusión tiene
 *   traducción exacta. Entonces entra en la lista `css` del publicador (frente G), que lo publica en cuanto Element
 *   Pack deja de estar cargado (V.5.2). En un sitio con Prime Slider sin Element Pack, lo publica `finish()`.
 */
final class Snippets {
	const CPT       = 'elementor_snippet';
	const KIND      = 'snippet';
	const USER_META = '_digitalisimo_migration_user';
	const HEAD      = 'elementor_head';
	const BODY_END  = 'elementor_body_end';
	const PRIORITY  = 10;
	const MAX_BYTES = 524288;

	/** Opciones de origen: plugin, lenguaje, ubicación y opción de páginas excluidas. */
	const SOURCES = array(
		'ep_custom_css'   => array( 'plugin' => 'element-pack', 'kind' => 'css', 'location' => self::HEAD, 'exclude' => 'ep_excluded_pages' ),
		'ep_custom_css_2' => array( 'plugin' => 'element-pack', 'kind' => 'css', 'location' => self::BODY_END, 'exclude' => 'ep_excluded_pages' ),
		'ep_custom_js'    => array( 'plugin' => 'element-pack', 'kind' => 'js', 'location' => self::HEAD, 'exclude' => 'ep_excluded_pages' ),
		'ep_custom_js_2'  => array( 'plugin' => 'element-pack', 'kind' => 'js', 'location' => self::BODY_END, 'exclude' => 'ep_excluded_pages' ),
		'ps_custom_css'   => array( 'plugin' => 'prime-slider', 'kind' => 'css', 'location' => self::HEAD, 'exclude' => 'ps_excluded_pages' ),
		'ps_custom_css_2' => array( 'plugin' => 'prime-slider', 'kind' => 'css', 'location' => self::BODY_END, 'exclude' => 'ps_excluded_pages' ),
		'ps_custom_js'    => array( 'plugin' => 'prime-slider', 'kind' => 'js', 'location' => self::HEAD, 'exclude' => 'ps_excluded_pages' ),
		'ps_custom_js_2'  => array( 'plugin' => 'prime-slider', 'kind' => 'js', 'location' => self::BODY_END, 'exclude' => 'ps_excluded_pages' ),
	);

	/* ================================================================ Permisos y validación */

	/** ¿Puede ese usuario publicar código sin filtrar? En una red, sólo un superadministrador (V.7). */
	public static function user_can_publish( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( is_multisite() ) {
			return is_super_admin( $user_id );
		}
		return user_can( $user_id, 'unfiltered_html' );
	}

	/** ¿Puede publicarse CSS en esta tarea? Revalida a quien la lanzó (`state['user']`); WP-CLI sin `--user`, nunca. */
	public static function can_publish_css(): bool {
		return self::user_can_publish( Inventory::job_user() );
	}

	/** Contrato de la v1: el mismo permiso decide si se crean borradores de JavaScript. */
	public static function allowed(): bool {
		return self::can_publish_css();
	}

	/**
	 * ¿CSS publicable? Sin `<` (no puede cerrar el `<style>`), sin `@import`, `expression(`, `javascript:`,
	 * `vbscript:`, `behavior:` ni `-moz-binding` (también escritos con escapes CSS), con llaves equilibradas y, si
	 * existe, aprobado por `Effects\Css_Value::block()` (frente C).
	 */
	public static function validate_css( string $css ): bool {
		if ( '' === trim( $css ) || strlen( $css ) > self::MAX_BYTES || false !== strpos( $css, '<' ) || false !== strpos( $css, "\0" ) ) {
			return false;
		}
		// Los escapes CSS (`\69mport`, `be\havior`) se deshacen antes de buscar construcciones peligrosas.
		$plain = (string) preg_replace_callback(
			'/\\\\([0-9a-fA-F]{1,6})\s?|\\\\(.)/s',
			static function ( $m ) {
				if ( isset( $m[1] ) && '' !== $m[1] ) {
					$code = hexdec( $m[1] );
					return $code > 0 && $code < 0x110000 ? mb_chr( (int) $code, 'UTF-8' ) : '';
				}
				return $m[2];
			},
			$css
		);
		$plain = (string) preg_replace( '#/\*.*?\*/#s', '', $plain );
		if ( false !== strpos( $plain, '<' ) || preg_match( '/@import|expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding/i', $plain ) ) {
			return false;
		}
		$depth = 0;
		foreach ( str_split( $plain ) as $char ) {
			if ( '{' === $char ) {
				++$depth;
			} elseif ( '}' === $char && --$depth < 0 ) {
				return false;
			}
		}
		if ( 0 !== $depth ) {
			return false;
		}
		$validator = '\\Digitalisimo\\Elements\\Legacy\\Effects\\Css_Value';
		if ( class_exists( $validator ) && method_exists( $validator, 'block' ) ) {
			$result = $validator::block( $css );
			if ( false === $result || '' === $result || null === $result ) {
				return false;
			}
		}
		return true;
	}

	/* ================================================================ Condiciones */

	/** Tipos con condición por entrada en el destino (los públicos del Theme Builder y los adjuntos). */
	private static function singular_types(): array {
		$module = '\\ElementorPro\\Modules\\ThemeBuilder\\Module';
		if ( class_exists( $module, false ) && method_exists( $module, 'get_public_post_types' ) ) {
			$types = array_keys( (array) $module::get_public_post_types() );
		} else {
			$types = array_keys( (array) get_post_types( array( 'show_in_nav_menus' => true ) ) );
			if ( class_exists( 'WooCommerce', false ) ) {
				$types = array_diff( $types, array( 'product' ) );
			}
		}
		$types[] = 'attachment';
		return array_values( array_unique( $types ) );
	}

	/**
	 * Condiciones de un fragmento a partir de las páginas excluidas: `{ conditions: string[], exact: bool }`. Una ID
	 * que ya no existe se descarta; una de un tipo sin condición por entrada deja `exact` en falso.
	 */
	public static function conditions( $excluded ): array {
		$out   = array( 'include/general' );
		$exact = true;
		$front = (int) get_option( 'page_on_front', 0 );
		$posts = (int) get_option( 'page_for_posts', 0 );
		$types = null;
		foreach ( array_slice( (array) $excluded, 0, 200 ) as $raw ) {
			$id = absint( $raw );
			if ( ! $id ) {
				continue;
			}
			if ( $id === $front ) {
				$out[] = 'exclude/singular/front_page';
				continue;
			}
			if ( $id === $posts ) {
				$out[] = 'exclude/archive/post_archive';
				continue;
			}
			$post = get_post( $id );
			if ( ! $post || 'trash' === ( $post->post_status ?? '' ) ) {
				continue;
			}
			$types = null === $types ? self::singular_types() : $types;
			if ( ! in_array( (string) $post->post_type, $types, true ) || ! preg_match( '/^[a-z0-9_-]{1,20}$/', (string) $post->post_type ) ) {
				$exact = false;
				continue;
			}
			$out[] = 'exclude/singular/' . $post->post_type . '/' . $id;
		}
		return array( 'conditions' => array_values( array_unique( $out ) ), 'exact' => $exact );
	}

	/* ================================================================ Creación */

	/**
	 * Borrador de Código personalizado (contrato C8; también lo usa la extensión de JavaScript de página, frente C).
	 * `$code` es el código sin etiquetas: va dentro de `<script>` o, con `$origin['kind'] = 'css'`, de `<style>` (un
	 * código que ya empieza por la etiqueta no se envuelve otra vez). Devuelve 0 si quien lanzó la tarea no puede
	 * publicar código sin filtrar, si la conversión es pura o si la ubicación no es válida.
	 */
	public static function draft( string $title, string $code, string $location, array $conditions, array $origin ): int {
		if ( Inventory::pure() || ! self::allowed() || ! in_array( $location, array( self::HEAD, self::BODY_END, 'elementor_body_start' ), true ) || '' === trim( $code ) ) {
			return 0;
		}
		$kind = 'css' === ( $origin['kind'] ?? '' ) ? 'css' : 'js';
		$tag  = 'pagecode:' . substr( md5( wp_json_encode( $origin ) . '|' . $location . '|' . $code ), 0, 16 );
		$id   = Inventory::created( self::CPT, $tag );
		if ( $id ) {
			return $id;
		}
		$conditions = array_values(
			array_filter(
				array_map( 'strval', $conditions ),
				static function ( $condition ) {
					return 1 === preg_match( '#^(include|exclude)/[a-z0-9_/-]{1,120}$#', $condition );
				}
			)
		);
		$id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'draft',
				'post_title'  => wp_slash( Inventory::plain( $title, 120 ) ),
				'post_author' => Inventory::job_user(),
				'meta_input'  => array( Inventory::TAG => $tag ),
			),
			true
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		$id = (int) $id;
		update_post_meta( $id, '_elementor_template_type', 'code_snippet' );
		update_post_meta( $id, '_elementor_location', $location );
		update_post_meta( $id, '_elementor_priority', self::PRIORITY );
		update_post_meta( $id, '_elementor_code', wp_slash( self::wrap( $code, $kind ) ) );
		update_post_meta( $id, '_elementor_conditions', $conditions ? $conditions : array( 'include/general' ) );
		update_post_meta( $id, self::USER_META, Inventory::job_user() );
		return $id;
	}

	/** El código dentro de su etiqueta. */
	private static function wrap( string $code, string $kind ): string {
		$code = trim( $code );
		if ( 'css' === $kind ) {
			return preg_match( '/^<style[\s>]/i', $code ) ? $code : '<style>' . "\n" . $code . "\n" . '</style>';
		}
		return preg_match( '/^<script[\s>]/i', $code ) ? $code : '<script>' . "\n" . $code . "\n" . '</script>';
	}

	/** Título neutro (sin el nombre del proveedor: el fragmento viaja en los kits). */
	private static function title( array $spec ): string {
		$where = self::HEAD === $spec['location'] ? __( 'cabecera', 'digitalisimo-migration' ) : __( 'pie', 'digitalisimo-migration' );
		$what  = 'css' === $spec['kind'] ? __( 'CSS global migrado', 'digitalisimo-migration' ) : __( 'JavaScript global migrado (revisar antes de publicar)', 'digitalisimo-migration' );
		return sprintf( '%1$s (%2$s)', $what, $where );
	}

	/** Código guardado en una opción de origen (vacío si no hay). */
	private static function source_code( string $option ): string {
		$value = get_option( $option, '' );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/** Análisis previo: qué se convertirá y qué JavaScript dejará de ejecutarse (`plat.code.js_lost`). */
	public static function analyze(): array {
		$out     = array();
		$allowed = self::can_publish_css();
		foreach ( self::SOURCES as $option => $spec ) {
			$code = self::source_code( $option );
			if ( '' === $code || self::done( $option ) ) {
				continue;
			}
			$detail = array( 'type' => $spec['kind'], 'target' => $spec['location'], 'note' => self::where_note( $spec ) );
			if ( 'js' === $spec['kind'] ) {
				$out[] = array( 'plat.code.js_lost', $detail );
				continue;
			}
			$conditions = self::conditions( get_option( $spec['exclude'], array() ) );
			$out[]      = array( self::css_code( $spec, $code, $allowed, $conditions['exact'] ), $detail );
		}
		return $out;
	}

	/** Dónde se ejecutaba y cuántas páginas excluía. */
	private static function where_note( array $spec ): string {
		$excluded = array_filter( array_map( 'absint', (array) get_option( $spec['exclude'], array() ) ) );
		$where    = self::HEAD === $spec['location'] ? __( 'en la cabecera', 'digitalisimo-migration' ) : __( 'en el pie', 'digitalisimo-migration' );
		return $excluded ? sprintf( __( '%1$s de todas las páginas salvo %2$d', 'digitalisimo-migration' ), $where, count( $excluded ) ) : sprintf( __( '%s de todas las páginas', 'digitalisimo-migration' ), $where );
	}

	/** Código del informe para un CSS según lo que impide publicarlo. */
	private static function css_code( array $spec, string $code, bool $allowed, bool $exact ): string {
		$active = 'element-pack' === $spec['plugin'] ? Inventory::ep_was_active() : Inventory::ps_was_active();
		if ( ! $active ) {
			return 'plat.code.inactive';
		}
		if ( ! $allowed || ! self::validate_css( $code ) ) {
			return 'plat.code.css_draft';
		}
		return $exact ? 'plat.code.css_published' : 'plat.code.exclusions';
	}

	private static function done( string $option ): bool {
		$row = Inventory::reg_get( self::KIND, $option );
		return $row && in_array( $row['state'] ?? '', array( 'done', 'draft' ), true ) && Inventory::exists( (int) ( $row['dest'] ?? 0 ) );
	}

	/** Etapa `platform_prepare` (Element Pack cargado): crea los borradores y apunta el CSS publicable. */
	public static function prepare( array &$cursor, float $deadline ): bool {
		$allowed = self::can_publish_css();
		foreach ( self::SOURCES as $option => $spec ) {
			$code = self::source_code( $option );
			if ( '' === $code || self::done( $option ) ) {
				continue;
			}
			if ( Inventory::late( $deadline ) ) {
				return false;
			}
			$detail = array( 'type' => $spec['kind'], 'target' => $spec['location'], 'note' => self::where_note( $spec ) );
			if ( 'js' === $spec['kind'] && ! $allowed ) {
				Inventory::site_code( 'plat.code.js_lost', $detail );
				continue;
			}
			$conditions = self::conditions( get_option( $spec['exclude'], array() ) );
			$id         = self::create( $option, $spec, $code, $conditions['conditions'] );
			if ( ! $id ) {
				continue;
			}
			$cursor['created'] = 1;
			$detail           += array( 'id' => $id );
			if ( 'js' === $spec['kind'] ) {
				self::register( $option, $id, 'draft', array() );
				Inventory::site_code( 'plat.code.js', $detail );
				continue;
			}
			$code_status = self::css_code( $spec, $code, $allowed, $conditions['exact'] );
			$extra       = array();
			if ( 'plat.code.css_published' === $code_status ) {
				if ( 'element-pack' === $spec['plugin'] || Inventory::ep_was_active() ) {
					// Lo publica el publicador (frente G) en cuanto Element Pack deja de estar cargado, revalidando.
					if ( class_exists( __NAMESPACE__ . '\\Publisher' ) ) {
						Publisher::track( 'css', $id );
						$extra['tracked'] = 1;
					} else {
						$code_status = 'plat.code.css_draft';
					}
				} else {
					$extra['publish'] = 1;
				}
			}
			self::register( $option, $id, 'draft', $extra );
			Inventory::site_code( $code_status, $detail );
		}
		// La caché de condiciones (que incluye los borradores) se regenera una vez, aunque la etapa ocupe varias peticiones.
		if ( ! empty( $cursor['created'] ) ) {
			self::regenerate_conditions();
		}
		return true;
	}

	/** Crea (o recupera, si una petición anterior murió) el fragmento de una opción de origen. */
	private static function create( string $option, array $spec, string $code, array $conditions ): int {
		$tag = self::KIND . ':' . $option;
		$id  = Inventory::created( self::CPT, $tag );
		if ( $id ) {
			return $id;
		}
		Inventory::reg_put( self::KIND, $option, array( 'state' => 'pending' ) );
		$id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'draft',
				'post_title'  => wp_slash( self::title( $spec ) ),
				'post_author' => Inventory::job_user(),
				'meta_input'  => array( Inventory::TAG => $tag ),
			),
			true
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		$id = (int) $id;
		update_post_meta( $id, '_elementor_template_type', 'code_snippet' );
		update_post_meta( $id, '_elementor_location', $spec['location'] );
		update_post_meta( $id, '_elementor_priority', self::PRIORITY );
		update_post_meta( $id, '_elementor_code', wp_slash( self::wrap( $code, $spec['kind'] ) ) );
		update_post_meta( $id, '_elementor_conditions', $conditions );
		update_post_meta( $id, self::USER_META, Inventory::job_user() );
		return $id;
	}

	private static function register( string $option, int $id, string $state, array $extra ): void {
		Inventory::reg_put(
			self::KIND,
			$option,
			array(
				'dest'  => $id,
				'hash'  => self::hash( $id ),
				'state' => $state,
				'extra' => $extra,
			)
		);
	}

	/** Huella del código de un fragmento. */
	public static function hash( int $id ): string {
		return md5( (string) get_post_meta( $id, '_elementor_code', true ) . '|' . wp_json_encode( get_post_meta( $id, '_elementor_conditions', true ) ) );
	}

	/** Caché de condiciones del constructor (incluye los borradores): una vez, tras crear fragmentos. */
	private static function regenerate_conditions(): void {
		$module = '\\ElementorPro\\Modules\\ThemeBuilder\\Module';
		if ( ! class_exists( $module, false ) ) {
			return;
		}
		try {
			$manager = $module::instance()->get_conditions_manager();
			if ( method_exists( $manager, 'get_cache' ) ) {
				$manager->get_cache()->regenerate();
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}

	/**
	 * ¿Puede publicarse ahora este fragmento de CSS? Para el publicador (frente G) al publicar: es un fragmento de la
	 * migración, de CSS, quien lanzó la tarea sigue teniendo permiso y el código guardado sigue siendo válido.
	 */
	public static function revalidate( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type || 0 !== strpos( (string) get_post_meta( $id, Inventory::TAG, true ), self::KIND . ':' ) ) {
			return false;
		}
		if ( ! self::user_can_publish( (int) get_post_meta( $id, self::USER_META, true ) ) ) {
			return false;
		}
		$code = trim( (string) get_post_meta( $id, '_elementor_code', true ) );
		if ( ! preg_match( '#^<style>\s*(.*?)\s*</style>$#s', $code, $m ) ) {
			return false;
		}
		return self::validate_css( $m[1] );
	}

	/** Etapa `platform`: publica el CSS de un sitio con Prime Slider y sin Element Pack (el publicador no lo hará). */
	public static function finish( array &$cursor, float $deadline ): bool {
		foreach ( Inventory::reg_all( self::KIND ) as $option => $row ) {
			$id = (int) ( $row['dest'] ?? 0 );
			if ( empty( $row['extra']['publish'] ) || 'draft' !== ( $row['state'] ?? '' ) || ! Inventory::exists( $id ) ) {
				continue;
			}
			if ( 'draft' === get_post_status( $id ) && self::revalidate( $id ) && self::hash( $id ) === (string) ( $row['hash'] ?? '' ) ) {
				wp_publish_post( $id );
				$row['state'] = 'done';
				Inventory::reg_put( self::KIND, (string) $option, $row );
			} else {
				Inventory::site_code( 'plat.code.css_draft', array( 'id' => $id ) );
			}
		}
		return true;
	}

	/** `--revert`: retira los fragmentos creados que nadie cambió. */
	public static function revert( bool $force = false ): array {
		$out = array();
		foreach ( Inventory::reg_all( self::KIND ) as $option => $row ) {
			$id = (int) ( $row['dest'] ?? 0 );
			if ( ! Inventory::exists( $id ) ) {
				Inventory::reg_forget( self::KIND, (string) $option );
				continue;
			}
			if ( ! $force && self::hash( $id ) !== (string) ( $row['hash'] ?? '' ) ) {
				$out[] = array( 'core.revert_skipped', array( 'type' => self::CPT, 'id' => $id ) );
				continue;
			}
			if ( ! empty( $row['extra']['tracked'] ) && class_exists( __NAMESPACE__ . '\\Publisher' ) && method_exists( __NAMESPACE__ . '\\Publisher', 'untrack' ) ) {
				Publisher::untrack( $id );
			}
			Inventory::retire_post( $id );
			Inventory::reg_forget( self::KIND, (string) $option );
			$out[] = array( 'plat.code.reverted', array( 'id' => $id ) );
		}
		return $out;
	}
}
