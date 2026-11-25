<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * La identidad del sitio para las páginas del kit de ventas: colores, tipografías y botones salen DEL SITIO, nunca de
 * DIGITALÍSIMO. Caso plomerito 3 (2026-11-25, IA Tools 1.0.73 + Elements 4.3.0.197): la landing salió gris oscuro y
 * verde menta (la paleta DIGITALÍSIMO) en un sitio azul rey y amarillo, y el Asistente terminó pidiendo «activar el
 * Editor atómico» para leer los colores.
 *
 * - `read()` lee, sin depender de ningún ajuste que el dueño tenga que prender, el kit activo (`elementor_active_kit`:
 *   system_colors, custom_colors, system_typography, custom_typography, las fuentes del cuerpo y los títulos y el estilo
 *   de los botones) y el diseño real: la cabecera y el pie en uso del constructor de temas y la portada (sus ajustes en
 *   `_elementor_data`, con los globales resueltos) y, si no hay nada más, el color del logo.
 * - `resolve()` (pura) decide los papeles: `primary` (la marca: fondos fuertes, títulos, iconos), `accent` (botones y
 *   resaltes) y `text` (el texto). Si el kit tiene valores de fábrica o sus colores no aparecen en lo que se ve, manda el
 *   diseño real; cada papel apunta al color global del kit que coincide con él (`global`) o, si no hay, va literal.
 * - `apply()` (pura) cambia en una página del kit los globales de las plantillas (`globals/colors?id=primary|text|accent`)
 *   y cualquier color de la paleta DIGITALÍSIMO que quedara por los del sitio, corrige el contraste (texto claro sobre un
 *   fondo claro, el texto de los botones) y pone las tipografías del sitio.
 * - `look()` (pura) dice qué colores y tipografías lleva de verdad una página: lo que el Asistente comprueba antes de
 *   decir «ya usa tus colores».
 */
final class Brand {
	/** Los colores de fábrica del kit de Elementor (`Global_Colors`). */
	const FACTORY_COLORS = array(
		'primary'   => '#6EC1E4',
		'secondary' => '#54595F',
		'text'      => '#7A7A7A',
		'accent'    => '#61CE70',
	);

	/** Las tipografías de fábrica del kit (`Global_Typography`). */
	const FACTORY_FONTS = array( 'roboto', 'roboto slab' );

	/** La paleta del Manual de identidad de DIGITALÍSIMO (scripts/sales-kit/kit_lib.py) → su papel. */
	const DIGITALISIMO = array(
		'#0F141A' => 'primary',
		'#0B2A45' => 'primary',
		'#161D25' => 'primary',
		'#26313C' => 'primary',
		'#1592DA' => 'primary',
		'#0A6FCF' => 'primary',
		'#1F252C' => 'primary',
		'#4E5863' => 'text',
		'#00A86B' => 'accent',
		'#00FF99' => 'accent',
		'#F5F8FB' => '#F7F7F7',
		'#E1E6EC' => '#E6E6E6',
		'#E9EEF3' => '#F2F2F2',
		'#9AA6B2' => '#BDBDBD',
		'#DCEBFA' => '#F2F2F2',
	);

	/** Claves de ajustes que son un color. */
	const COLOR_KEY = '/(^|_)color(_b)?$/';

	/** Fallbacks neutros cuando el sitio no dice nada. */
	const DARK  = '#222222';
	const WHITE = '#FFFFFF';

	/* ------------------------------------------------------------------ */
	/* Lectura (WordPress).                                                 */
	/* ------------------------------------------------------------------ */

	/**
	 * La identidad del sitio (ver `resolve()`). Sólo lee.
	 */
	public static function read(): array {
		$kit   = self::kit();
		$docs  = self::design_docs();
		$brand = self::resolve( $kit, $docs );
		// El logo sólo si ni el diseño ni el kit dicen nada (decodificarlo cuesta memoria).
		return 'ninguna' === $brand['source'] ? self::resolve( $kit, $docs, self::logo_color() ) : $brand;
	}

	/**
	 * El kit activo: ['id', 'colors' => [id => ['color', 'title', 'system']], 'fonts' => [id => familia],
	 * 'theme_fonts' => ['heading' => familia, 'body' => familia], 'button' => ['background', 'text']].
	 */
	public static function kit(): array {
		$out = array(
			'id'          => 0,
			'colors'      => array(),
			'fonts'       => array(),
			'theme_fonts' => array(
				'heading' => '',
				'body'    => '',
			),
			'button'      => array(
				'background' => '',
				'text'       => '',
			),
		);
		$id = function_exists( 'get_option' ) ? (int) get_option( 'elementor_active_kit' ) : 0;
		if ( $id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return $out;
		}
		$out['id']  = $id;
		$settings   = get_post_meta( $id, '_elementor_page_settings', true );
		return self::kit_from_settings( is_array( $settings ) ? $settings : array(), $out );
	}

	/** Las partes del kit desde sus ajustes guardados (pura). */
	public static function kit_from_settings( array $settings, array $out = array() ): array {
		$out += array(
			'id'          => 0,
			'colors'      => array(),
			'fonts'       => array(),
			'theme_fonts' => array(
				'heading' => '',
				'body'    => '',
			),
			'button'      => array(
				'background' => '',
				'text'       => '',
			),
		);
		foreach ( array( 'system_colors' => true, 'custom_colors' => false ) as $key => $system ) {
			foreach ( (array) ( $settings[ $key ] ?? array() ) as $item ) {
				$hex = is_array( $item ) ? self::hex( (string) ( $item['color'] ?? '' ) ) : '';
				$cid = is_array( $item ) ? (string) ( $item['_id'] ?? '' ) : '';
				if ( '' !== $hex && preg_match( '/^[a-z0-9_-]{1,40}$/i', $cid ) ) {
					$out['colors'][ $cid ] = array(
						'color'  => $hex,
						'title'  => (string) ( $item['title'] ?? $cid ),
						'system' => $system,
					);
				}
			}
		}
		foreach ( array( 'system_typography', 'custom_typography' ) as $key ) {
			foreach ( (array) ( $settings[ $key ] ?? array() ) as $item ) {
				$cid    = is_array( $item ) ? (string) ( $item['_id'] ?? '' ) : '';
				$family = is_array( $item ) ? self::family( (string) ( $item['typography_font_family'] ?? '' ) ) : '';
				if ( '' !== $family && preg_match( '/^[a-z0-9_-]{1,40}$/i', $cid ) ) {
					$out['fonts'][ $cid ] = $family;
				}
			}
		}
		// El estilo del tema del kit: la fuente del cuerpo y la de los títulos.
		$out['theme_fonts']['body'] = self::family( (string) ( $settings['body_typography_font_family'] ?? '' ) );
		foreach ( array( 'h1', 'h2', 'h3' ) as $h ) {
			$f = self::family( (string) ( $settings[ $h . '_typography_font_family'] ?? '' ) );
			if ( '' !== $f ) {
				$out['theme_fonts']['heading'] = $f;
				break;
			}
		}
		$globals = (array) ( $settings['__globals__'] ?? array() );
		foreach ( array( 'background' => 'button_background_color', 'text' => 'button_text_color' ) as $role => $key ) {
			$hex = self::resolve_global( (string) ( $globals[ $key ] ?? '' ), $out['colors'] );
			$out['button'][ $role ] = '' !== $hex ? $hex : self::hex( (string) ( $settings[ $key ] ?? '' ) );
		}
		foreach ( (array) ( $globals ) as $key => $ref ) {
			if ( preg_match( '/^(body|h[1-3])_typography_typography$/', (string) $key, $m ) ) {
				$fid    = self::global_id( (string) $ref, 'typography' );
				$family = '' !== $fid ? (string) ( $out['fonts'][ $fid ] ?? '' ) : '';
				if ( '' !== $family ) {
					if ( 'body' === $m[1] ) {
						$out['theme_fonts']['body'] = $family;
					} elseif ( '' === $out['theme_fonts']['heading'] ) {
						$out['theme_fonts']['heading'] = $family;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Lo que se ve en todo el sitio: la cabecera y el pie EN USO del constructor de temas (con condiciones) y la
	 * portada. ['header' => [elementos…], 'footer' => […], 'front' => […]] (cada parte, una lista de árboles).
	 */
	public static function design_docs(): array {
		$out = array(
			'header' => array(),
			'footer' => array(),
			'front'  => array(),
		);
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post_meta' ) ) {
			return $out;
		}
		$parts = get_posts(
			array(
				'post_type'        => 'elementor_library',
				'post_status'      => 'publish',
				'posts_per_page'   => 20,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_elementor_template_type',
						'value'   => array( 'header', 'footer' ),
						'compare' => 'IN',
					),
				),
			)
		);
		foreach ( (array) $parts as $post ) {
			$id   = (int) ( is_object( $post ) ? $post->ID : $post );
			$type = (string) get_post_meta( $id, '_elementor_template_type', true );
			if ( ! isset( $out[ $type ] ) || ! Site::in_use( get_post_meta( $id, '_elementor_conditions', true ) ) ) {
				continue;
			}
			$data = self::elements_of( $id );
			if ( $data ) {
				$out[ $type ][] = $data;
			}
		}
		$front = function_exists( 'get_option' ) && 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		if ( $front > 0 ) {
			$data = self::elements_of( $front );
			if ( $data ) {
				$out['front'][] = $data;
			}
		}
		return $out;
	}

	/** ¿La cabecera en uso del sitio es fija (sticky del constructor)? */
	public static function sticky_header( array $docs ): bool {
		$walk = static function ( array $els, int $depth ) use ( &$walk ): bool {
			foreach ( $els as $el ) {
				if ( ! is_array( $el ) || $depth > 20 ) {
					continue;
				}
				if ( '' !== (string) ( $el['settings']['sticky'] ?? '' ) || ( isset( $el['settings']['position'] ) && in_array( $el['settings']['position'], array( 'fixed' ), true ) ) || ( isset( $el['settings']['_position'] ) && 'fixed' === $el['settings']['_position'] ) ) {
					return true;
				}
				if ( $walk( (array) ( $el['elements'] ?? array() ), $depth + 1 ) ) {
					return true;
				}
			}
			return false;
		};
		foreach ( (array) ( $docs['header'] ?? array() ) as $tree ) {
			if ( $walk( (array) $tree, 0 ) ) {
				return true;
			}
		}
		return false;
	}

	/** Los elementos guardados de un documento del constructor, o []. */
	private static function elements_of( int $id ): array {
		$raw  = get_post_meta( $id, '_elementor_data', true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $data ) ? $data : array();
	}

	/** El color que más se ve en el logo del sitio (sin blancos, negros ni grises), o ''. Sólo con GD. */
	public static function logo_color(): string {
		if ( ! function_exists( 'get_theme_mod' ) || ! function_exists( 'get_attached_file' ) ) {
			return '';
		}
		$id   = (int) get_theme_mod( 'custom_logo' );
		$file = $id > 0 ? (string) get_attached_file( $id ) : '';
		if ( '' === $file || ! is_readable( $file ) ) {
			return '';
		}
		// El análisis se guarda por la URL del logo y su fecha de modificación: se hace una vez por logo, no en cada
		// página que se crea o se lee.
		$url    = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $id ) : $file;
		$key    = 'digi_sales_logo_' . md5( $url . '|' . (string) @filemtime( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$cached = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( is_string( $cached ) && ( 'none' === $cached || preg_match( '/^#[0-9A-F]{6}$/', $cached ) ) ) {
			return 'none' === $cached ? '' : $cached;
		}
		$color = self::analyze_logo( $file );
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, '' !== $color ? $color : 'none', 30 * 86400 );
		}
		return $color;
	}

	/** El color con tono que más se ve en una imagen (GD), o ''. */
	public static function analyze_logo( string $file ): string {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! is_readable( $file ) || filesize( $file ) > 4 * 1024 * 1024 ) {
			return '';
		}
		$size = function_exists( 'getimagesize' ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $size || (int) $size[0] * (int) $size[1] > 4000000 ) {
			return ''; // Un logo enorme no se decodifica (unos 4 bytes por píxel en memoria).
		}
		$im = @imagecreatefromstring( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $im ) {
			return '';
		}
		$small = imagecreatetruecolor( 24, 24 );
		imagealphablending( $small, false );
		imagecopyresampled( $small, $im, 0, 0, 0, 0, 24, 24, imagesx( $im ), imagesy( $im ) );
		$count = array();
		for ( $x = 0; $x < 24; $x++ ) {
			for ( $y = 0; $y < 24; $y++ ) {
				$rgba = imagecolorat( $small, $x, $y );
				if ( ( ( $rgba >> 24 ) & 0x7F ) > 60 ) {
					continue;
				}
				$hex = sprintf( '#%02X%02X%02X', ( $rgba >> 16 ) & 0xFF, ( $rgba >> 8 ) & 0xFF, $rgba & 0xFF );
				if ( self::vivid( $hex ) ) {
					$key           = self::bucket( $hex );
					$count[ $key ] = ( $count[ $key ] ?? 0 ) + 1;
				}
			}
		}
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $small ); // Desde PHP 8 la libera el recolector (y en 8.5 avisa de obsoleta).
		}
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $im ); // Desde PHP 8 la libera el recolector (y en 8.5 avisa de obsoleta).
		}
		if ( ! $count ) {
			return '';
		}
		arsort( $count );
		return (string) key( $count );
	}

	/* ------------------------------------------------------------------ */
	/* Decisión (pura).                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * La identidad: ['colors' => ['primary'|'accent'|'text'|'light' => ['color', 'global' (id del kit o '')]],
	 * 'on_primary', 'on_accent', 'fonts' => ['heading', 'body'], 'source' ('kit', 'diseño', 'kit y diseño', 'logo' o
	 * 'ninguna'), 'kit_factory' (bool), 'kit' (resumen), 'seen' (los colores del diseño, de más a menos)].
	 */
	public static function resolve( array $kit, array $docs, string $logo = '' ): array {
		$kit   = self::kit_from_settings( array(), $kit );
		$found = array();
		$fonts = array(
			'heading' => array(),
			'body'    => array(),
		);
		$weights = array(
			'header' => 3,
			'footer' => 2,
			'front'  => 1,
		);
		foreach ( $weights as $where => $weight ) {
			foreach ( (array) ( $docs[ $where ] ?? array() ) as $tree ) {
				self::collect( (array) $tree, $weight, $kit, $found, $fonts, 0 );
			}
		}
		$factory_colors = self::kit_is_factory( $kit );
		$kit_fonts      = array_filter( array_map( 'strtolower', array_values( $kit['fonts'] ) ) );
		$factory_fonts  = ! $kit_fonts || ! array_diff( $kit_fonts, self::FACTORY_FONTS );

		// Los colores del diseño con su puntaje por papel.
		$score = array();
		foreach ( $found as $item ) {
			list( $hex, $kind, $weight ) = $item;
			if ( ! isset( $score[ $hex ] ) ) {
				$score[ $hex ] = array(
					'brand'  => 0,
					'button' => 0,
					'text'   => 0,
					'light'  => 0,
					'solid'  => 0,
					'all'    => 0,
				);
			}
			$score[ $hex ]['all'] += $weight;
			if ( self::vivid( $hex ) ) {
				$score[ $hex ]['brand']  += $weight * ( 'bg' === $kind ? 3 : ( 'icon' === $kind ? 2 : ( 'button' === $kind ? 1 : 1.5 ) ) );
				$score[ $hex ]['button'] += $weight * ( 'button' === $kind ? 4 : 1 );
			} elseif ( 'text' === $kind && self::luminance( $hex ) < 0.12 ) {
				$score[ $hex ]['text'] += $weight;
			} elseif ( 'bg' === $kind && self::luminance( $hex ) > 0.8 && self::WHITE !== $hex ) {
				$score[ $hex ]['light'] += $weight;
			}
			if ( ! self::vivid( $hex ) && ( 'bg' === $kind || 'button' === $kind ) && self::luminance( $hex ) < 0.6 ) {
				// Un fondo o un botón fuerte sin tono (negro, gris oscuro): también es la marca de un sitio monocromo.
				$score[ $hex ]['solid'] += $weight * ( 'bg' === $kind ? 3 : 1 );
			}
		}
		$best = static function ( string $role, ?callable $ok = null ) use ( $score ): string {
			$top   = '';
			$value = 0;
			foreach ( $score as $hex => $s ) {
				if ( $s[ $role ] > $value && ( null === $ok || $ok( (string) $hex ) ) ) {
					$top   = (string) $hex;
					$value = $s[ $role ];
				}
			}
			return $top;
		};
		$primary = $best( 'brand' );
		$accent  = '' !== $primary ? $best(
			'button',
			static function ( $hex ) use ( $primary ) {
				return self::hue_distance( $hex, $primary ) >= 35;
			}
		) : '';
		$text    = $best( 'text' );
		$light   = $best( 'light' );
		if ( '' === $primary ) {
			// Sin colores con tono: la marca de un sitio en negro y gris es su fondo fuerte (cabecera, pie).
			$primary = $best( 'solid' );
			$accent  = '' !== $primary ? $best(
				'solid',
				static function ( $hex ) use ( $primary ) {
					return self::distance( $hex, $primary ) >= 60;
				}
			) : '';
		}
		$source  = '' !== $primary ? 'diseño' : '';

		// Sin colores de marca en lo que se ve: el kit (si no es de fábrica) o el logo.
		$by_kit = static function ( string $id ) use ( $kit ): string {
			return (string) ( $kit['colors'][ $id ]['color'] ?? '' );
		};
		if ( '' === $primary && ! $factory_colors ) {
			foreach ( array( 'primary', 'accent', 'secondary' ) as $id ) {
				if ( self::vivid( $by_kit( $id ) ) ) {
					$primary = $by_kit( $id );
					break;
				}
			}
			// Un kit propio sin tonos (negro y gris) también es la identidad del sitio: su primario, si no es casi blanco.
			foreach ( '' === $primary ? array( 'primary', 'secondary', 'accent', 'text' ) : array() as $id ) {
				if ( '' !== $by_kit( $id ) && self::luminance( $by_kit( $id ) ) < 0.85 ) {
					$primary = $by_kit( $id );
					break;
				}
			}
			$source = '' !== $primary ? 'kit' : '';
		}
		if ( '' === $primary && '' !== $logo && self::vivid( $logo ) ) {
			$primary = $logo;
			$source  = 'logo';
		}
		if ( '' === $accent && ! $factory_colors && '' !== $primary ) {
			foreach ( array( 'accent', 'secondary', 'primary' ) as $id ) {
				$hex = $by_kit( $id );
				if ( self::vivid( $hex ) && self::hue_distance( $hex, $primary ) >= 35 ) {
					$accent = $hex;
					break;
				}
			}
			// Un kit sin tonos: el acento es el color del kit que más se distingue del primario.
			foreach ( '' === $accent && ! self::vivid( $primary ) ? array( 'accent', 'secondary', 'text' ) : array() as $id ) {
				$hex = $by_kit( $id );
				if ( '' !== $hex && self::distance( $hex, $primary ) >= 60 && self::luminance( $hex ) < 0.85 ) {
					$accent = $hex;
					break;
				}
			}
		}
		if ( '' === $accent && '' !== $kit['button']['background'] && self::vivid( $kit['button']['background'] ) ) {
			$accent = $kit['button']['background'];
		}
		if ( '' === $text && ! $factory_colors && '' !== $by_kit( 'text' ) && self::luminance( $by_kit( 'text' ) ) < 0.2 ) {
			$text = $by_kit( 'text' );
		}
		$primary = '' !== $primary ? $primary : self::DARK;
		$accent  = '' !== $accent ? $accent : $primary;
		$text    = '' !== $text ? $text : ( self::luminance( $primary ) < 0.08 ? $primary : '#333333' );
		$light   = '' !== $light ? $light : '#F5F5F5';

		$colors = array();
		foreach ( array(
			'primary' => $primary,
			'accent'  => $accent,
			'text'    => $text,
			'light'   => $light,
		) as $role => $hex ) {
			$colors[ $role ] = array(
				'color'  => $hex,
				// El global del kit que es ese color (sistema primero), para que la página siga al kit del sitio.
				'global' => $factory_colors && in_array( $hex, self::FACTORY_COLORS, true ) ? '' : self::global_for( $hex, $kit['colors'] ),
			);
		}
		$with_global = count(
			array_filter(
				array( $colors['primary'], $colors['accent'] ),
				static function ( $c ) {
					return '' !== $c['global'];
				}
			)
		);
		if ( 'diseño' === $source && 2 === $with_global ) {
			$source = 'kit y diseño';
		} elseif ( '' === $source ) {
			$source = 'ninguna';
		}

		// Tipografías: las que se ven y, si no hay, las del kit cuando no son las de fábrica.
		$top_font = static function ( array $list ): string {
			if ( ! $list ) {
				return '';
			}
			arsort( $list );
			return (string) key( $list );
		};
		$heading = $top_font( $fonts['heading'] );
		$body    = $top_font( $fonts['body'] );
		if ( '' === $heading && '' !== $kit['theme_fonts']['heading'] ) {
			$heading = $kit['theme_fonts']['heading'];
		}
		if ( '' === $body && '' !== $kit['theme_fonts']['body'] ) {
			$body = $kit['theme_fonts']['body'];
		}
		if ( ! $factory_fonts ) {
			if ( '' === $heading ) {
				$heading = (string) ( $kit['fonts']['primary'] ?? '' );
			}
			if ( '' === $body ) {
				$body = (string) ( $kit['fonts']['text'] ?? '' );
			}
		}
		if ( '' === $body ) {
			$body = $heading;
		}
		if ( '' === $heading ) {
			$heading = $body;
		}

		$seen = array();
		uasort(
			$score,
			static function ( $a, $b ) {
				return $b['all'] <=> $a['all'];
			}
		);
		foreach ( array_slice( array_keys( $score ), 0, 12 ) as $hex ) {
			$seen[] = (string) $hex;
		}
		return array(
			'colors'      => $colors,
			'on_primary'  => self::on( $primary, $text ),
			'on_accent'   => '' !== $kit['button']['text'] && $kit['button']['background'] === $accent && self::contrast( $kit['button']['text'], $accent ) >= 3 ? $kit['button']['text'] : self::on( $accent, $text ),
			'fonts'       => array(
				'heading' => $heading,
				'body'    => $body,
			),
			'source'      => $source,
			'kit_factory' => $factory_colors,
			'kit_colors'  => $kit['colors'],
			'kit'         => array(
				'id'      => (int) $kit['id'],
				'factory' => $factory_colors,
				'colors'  => array_map(
					static function ( $c ) {
						return $c['color'];
					},
					$kit['colors']
				),
				'fonts'   => $kit['fonts'],
			),
			'seen'        => $seen,
		);
	}

	/** ¿El kit tiene sus colores de fábrica (o ninguno)? */
	public static function kit_is_factory( array $kit ): bool {
		$system = array();
		foreach ( (array) ( $kit['colors'] ?? array() ) as $id => $c ) {
			if ( ! empty( $c['system'] ) ) {
				$system[ $id ] = $c['color'];
			}
		}
		if ( ! $system ) {
			return true;
		}
		foreach ( $system as $id => $hex ) {
			if ( ! isset( self::FACTORY_COLORS[ $id ] ) || self::FACTORY_COLORS[ $id ] !== $hex ) {
				return false;
			}
		}
		return true;
	}

	/** Recorre un árbol del constructor y anota sus colores ([hex, tipo, peso]) y sus tipografías. */
	private static function collect( array $elements, int $weight, array $kit, array &$found, array &$fonts, int $depth ): void {
		if ( $depth > 30 ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$widget   = (string) ( $element['widgetType'] ?? '' );
			$globals  = is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : array();
			$keys     = array_unique( array_merge( array_keys( $settings ), array_keys( $globals ) ) );
			foreach ( $keys as $key ) {
				$key = (string) $key;
				if ( '__globals__' === $key || preg_match( '/hover|_tablet$|_mobile$|_laptop$|_widescreen$|overlay|shadow|border|divider|separator/', $key ) ) {
					continue;
				}
				if ( preg_match( self::COLOR_KEY, $key ) ) {
					$hex = isset( $globals[ $key ] ) ? self::resolve_global( (string) $globals[ $key ], $kit['colors'] ) : self::hex( is_string( $settings[ $key ] ?? null ) ? $settings[ $key ] : '' );
					if ( '' === $hex ) {
						continue;
					}
					if ( 'background_color' === $key && 'classic' !== ( $settings['background_background'] ?? '' ) && 'gradient' !== ( $settings['background_background'] ?? '' ) && 'widget' !== ( $element['elType'] ?? '' ) ) {
						continue; // Un color de fondo guardado que no se usa (sin «clásico» o «degradado»).
					}
					if ( false !== strpos( $key, 'background' ) ) {
						$kind = in_array( $widget, array( 'button', 'search-form', 'form', 'woocommerce-menu-cart', 'call-to-action', 'price-table' ), true ) || false !== strpos( $key, 'button' ) ? 'button' : 'bg';
					} elseif ( preg_match( '/^(primary_color|secondary_color|icon_color|icon_primary_color|selected_icon_color)$/', $key ) || false !== strpos( $key, 'icon' ) ) {
						$kind = 'icon';
					} else {
						$kind = 'text';
					}
					$found[] = array( $hex, $kind, $weight );
				} elseif ( preg_match( '/(^|_)typography_font_family$/', $key ) || preg_match( '/(^|_)typography_typography$/', $key ) ) {
					$family = '';
					if ( isset( $globals[ $key ] ) && preg_match( '/typography_typography$/', $key ) ) {
						$fid    = self::global_id( (string) $globals[ $key ], 'typography' );
						$family = '' !== $fid ? (string) ( $kit['fonts'][ $fid ] ?? '' ) : '';
					} elseif ( preg_match( '/font_family$/', $key ) ) {
						$family = self::family( is_string( $settings[ $key ] ?? null ) ? $settings[ $key ] : '' );
					}
					if ( '' === $family ) {
						continue;
					}
					$is_heading = 'heading' === $widget && ! in_array( (string) ( $settings['header_size'] ?? 'h2' ), array( 'p', 'div', 'span', 'h6' ), true )
						|| preg_match( '/^(title|heading|headline|main_title)_typography/', $key );
					$bucket                       = $is_heading ? 'heading' : 'body';
					$fonts[ $bucket ][ $family ] = ( $fonts[ $bucket ][ $family ] ?? 0 ) + $weight;
				}
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect( $element['elements'], $weight, $kit, $found, $fonts, $depth + 1 );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Aplicar a una página del kit (pura).                                 */
	/* ------------------------------------------------------------------ */

	/** Cambia en los elementos de una página los colores y las tipografías por los del sitio (`resolve()`). */
	public static function apply( array $elements, array $brand ): array {
		$elements = self::apply_list( $elements, $brand, self::WHITE, 0 );
		$issues   = array();
		return self::contrast_pass( $elements, $brand, self::WHITE, true, $issues, 0 );
	}

	/** Contraste mínimo WCAG AA: 4.5 en texto normal; 3 en botones, cifras, iconos y títulos grandes. */
	const AA_TEXT  = 4.5;
	const AA_LARGE = 3.0;

	/**
	 * El último paso, siempre (no sólo con colores de marca): cada texto contra el fondo sobre el que de verdad se pinta
	 * (el de su botón, su caja del contador, su pregunta o su sección). Si no llega al mínimo de WCAG AA, con `$fix` se
	 * cambia por blanco o negro (el que dé más contraste); sin él, sólo se anota en `$issues`.
	 */
	public static function contrast_pass( array $elements, array $brand, string $bg, bool $fix, array &$issues, int $depth ): array {
		foreach ( $elements as $i => $el ) {
			if ( ! is_array( $el ) || $depth > 40 ) {
				continue;
			}
			$s       = is_array( $el['settings'] ?? null ) ? $el['settings'] : array();
			$globals = is_array( $s['__globals__'] ?? null ) ? $s['__globals__'] : array();
			$widget  = (string) ( $el['widgetType'] ?? '' );
			$here    = $bg;
			if ( 'widget' !== ( $el['elType'] ?? '' ) && in_array( (string) ( $s['background_background'] ?? '' ), array( 'classic', 'gradient' ), true ) ) {
				$own = self::color_value( $s, $globals, 'background_color', $brand );
				if ( '' !== $own ) {
					$here = $own;
				}
			}
			$pairs = self::text_pairs( $widget, $s, $globals, $brand, $here );
			foreach ( $pairs as $pair ) {
				list( $key, $against, $min ) = $pair;
				$color = self::color_value( $s, $globals, $key, $brand );
				if ( '' === $color || '' === $against ) {
					continue;
				}
				if ( self::contrast( $color, $against ) + 1e-9 < $min ) {
					$issues[] = sprintf( '%s.%s %s sobre %s (%.2f < %.1f)', '' !== $widget ? $widget : (string) ( $el['elType'] ?? '' ), $key, $color, $against, self::contrast( $color, $against ), $min );
					if ( $fix ) {
						unset( $globals[ $key ] );
						$s[ $key ] = self::best_bw( $against );
					}
				}
			}
			// El resalte de un título (`selector span{color:…}`) contra la sección.
			if ( 'heading' === $widget && is_string( $s['custom_css'] ?? null ) && preg_match( '/span\{color:(#[0-9A-Fa-f]{6})/', $s['custom_css'], $m ) ) {
				$min = self::large_heading( $s ) ? self::AA_LARGE : self::AA_TEXT;
				if ( self::contrast( strtoupper( $m[1] ), $here ) + 1e-9 < $min ) {
					$issues[] = sprintf( 'heading.resalte %s sobre %s', strtoupper( $m[1] ), $here );
					if ( $fix ) {
						$s['custom_css'] = str_replace( $m[1], self::best_bw( $here ), $s['custom_css'] );
					}
				}
			}
			if ( $fix ) {
				if ( $globals ) {
					$s['__globals__'] = $globals;
				} else {
					unset( $s['__globals__'] );
				}
				$el['settings'] = $s;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::contrast_pass( $el['elements'], $brand, $here, $fix, $issues, $depth + 1 );
			}
			$elements[ $i ] = $el;
		}
		return $elements;
	}

	/** Los textos de un widget: [clave del color, fondo contra el que se lee, contraste mínimo]. */
	private static function text_pairs( string $widget, array $s, array $globals, array $brand, string $here ): array {
		$bg_of = static function ( string $key ) use ( $s, $globals, $brand, $here ): string {
			$hex = self::color_value( $s, $globals, $key, $brand );
			return '' !== $hex ? $hex : $here;
		};
		switch ( $widget ) {
			case 'heading':
				return array( array( 'title_color', $here, self::large_heading( $s ) ? self::AA_LARGE : self::AA_TEXT ) );
			case 'text-editor':
				return array( array( 'text_color', $here, self::AA_TEXT ), array( 'link_color', $here, self::AA_TEXT ) );
			case 'button':
				return array( array( 'button_text_color', $bg_of( 'background_color' ), self::AA_LARGE ) );
			case 'countdown':
				$box = $bg_of( 'box_background_color' );
				return array( array( 'digits_color', $box, self::AA_LARGE ), array( 'label_color', $box, self::AA_TEXT ) );
			case 'accordion':
			case 'toggle':
				$title = $bg_of( 'title_background' );
				return array( array( 'title_color', $title, self::AA_TEXT ), array( 'tab_active_color', $title, self::AA_TEXT ), array( 'content_color', $bg_of( 'content_background_color' ), self::AA_TEXT ) );
			case 'icon-box':
			case 'image-box':
				return array( array( 'title_color', $here, self::AA_TEXT ), array( 'description_color', $here, self::AA_TEXT ), array( 'primary_color', $here, self::AA_LARGE ) );
			case 'icon-list':
				return array( array( 'text_color', $here, self::AA_TEXT ), array( 'icon_color', $here, self::AA_LARGE ) );
		}
		$out = array();
		foreach ( array_unique( array_merge( array_keys( $s ), array_keys( $globals ) ) ) as $key ) {
			$key = (string) $key;
			if ( preg_match( self::COLOR_KEY, $key ) && ! preg_match( '/background|border|divider|separator|shadow|overlay|hover|line|_b$/', $key ) ) {
				$out[] = array( $key, $here, self::AA_TEXT );
			}
		}
		return $out;
	}

	/** ¿Un título grande (WCAG: 24 px o más, o 18.66 px en negritas; h1 a h3 sin tamaño)? */
	private static function large_heading( array $s ): bool {
		$size   = (float) ( $s['typography_font_size']['size'] ?? 0 );
		$weight = (int) ( $s['typography_font_weight'] ?? 400 );
		if ( $size > 0 ) {
			return $size >= 24 || ( $size >= 18.66 && $weight >= 700 );
		}
		return in_array( (string) ( $s['header_size'] ?? 'h2' ), array( 'h1', 'h2', 'h3' ), true );
	}

	/** Blanco o negro: el que más contraste dé sobre ese fondo. */
	public static function best_bw( string $bg ): string {
		return self::contrast( self::WHITE, $bg ) >= self::contrast( '#000000', $bg ) ? self::WHITE : '#000000';
	}

	private static function apply_list( array $elements, array $brand, string $bg, int $depth ): array {
		foreach ( $elements as $i => $element ) {
			if ( is_array( $element ) && $depth < 40 ) {
				$elements[ $i ] = self::apply_one( $element, $brand, $bg, $depth );
			}
		}
		return $elements;
	}

	private static function apply_one( array $element, array $brand, string $bg, int $depth ): array {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$widget   = (string) ( $element['widgetType'] ?? '' );
		$globals  = is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : array();
		$keys     = array_unique( array_merge( array_keys( $settings ), array_keys( $globals ) ) );

		// 1 · El fondo propio (decide el contraste de lo de adentro).
		$own_bg = '';
		foreach ( array( 'background_color', 'background_color_b' ) as $key ) {
			if ( isset( $globals[ $key ] ) || isset( $settings[ $key ] ) ) {
				list( $settings, $globals, $hex ) = self::recolor( $settings, $globals, $key, $brand, 'bg', $bg );
				if ( 'background_color' === $key && '' !== $hex && 'widget' !== ( $element['elType'] ?? '' ) ) {
					$own_bg = $hex;
				}
			}
		}
		$here = '' !== $own_bg ? $own_bg : $bg;
		// Las cajas del contador tienen su propio fondo: sus números y etiquetas se leen contra él, no contra la sección.
		if ( 'countdown' === $widget && ( isset( $globals['box_background_color'] ) || isset( $settings['box_background_color'] ) ) ) {
			list( $settings, $globals, $box ) = self::recolor( $settings, $globals, 'box_background_color', $brand, 'bg', $here );
			if ( '' !== $box ) {
				$here = $box;
			}
		}

		// 2 · Los demás colores.
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( '__globals__' === $key || 'background_color' === $key || 'background_color_b' === $key || ( 'countdown' === $widget && 'box_background_color' === $key ) ) {
				continue;
			}
			if ( preg_match( self::COLOR_KEY, $key ) ) {
				$kind = false !== strpos( $key, 'background' ) ? 'bg' : 'fg';
				list( $settings, $globals ) = self::recolor( $settings, $globals, $key, $brand, $kind, $here );
			} elseif ( is_array( $settings[ $key ] ?? null ) ) {
				$settings[ $key ] = self::neutral_deep( $settings[ $key ], $brand );
			}
		}
		// El texto de un botón contra su fondo.
		if ( 'button' === $widget ) {
			$btn_bg = self::color_value( $settings, $globals, 'background_color', $brand );
			if ( '' !== $btn_bg && self::distance( $btn_bg, '' !== $here ? $here : self::WHITE ) < 40 ) {
				// Un botón del mismo color que su sección (una marca de un solo color) no se vería: se invierte.
				$flip = self::on( '' !== $here ? $here : self::WHITE, $brand['colors']['text']['color'] );
				unset( $globals['background_color'], $globals['button_text_color'] );
				$settings['background_color']  = $flip;
				$primary                       = $brand['colors']['primary']['color'];
				$settings['button_text_color'] = self::contrast( $primary, $flip ) >= 3 ? $primary : self::on( $flip, $brand['colors']['text']['color'] );
			} elseif ( '' !== $btn_bg && self::vivid( $btn_bg ) ) {
				unset( $globals['button_text_color'] );
				$settings['button_text_color'] = $btn_bg === $brand['colors']['accent']['color'] ? $brand['on_accent'] : self::on( $btn_bg, $brand['colors']['text']['color'] );
			}
		}
		// El resalte de un título (`selector span{color:…}`).
		if ( isset( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) ) {
			$settings['custom_css'] = self::css( $settings['custom_css'], $brand, $here );
		}
		// 3 · Las tipografías del sitio.
		$settings = self::fonts( $settings, $widget, $brand );

		if ( $globals ) {
			$settings['__globals__'] = $globals;
		} else {
			unset( $settings['__globals__'] );
		}
		$element['settings'] = $settings;
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = self::apply_list( $element['elements'], $brand, $here, $depth + 1 );
		}
		return $element;
	}

	/**
	 * Un ajuste de color: el global de una plantilla (`primary`, `text`, `accent`, `secondary`) o un color de la paleta
	 * DIGITALÍSIMO → el papel del sitio (global del kit o literal), con contraste contra el fondo `$bg`.
	 *
	 * @return array [settings, globals, hex final]
	 */
	private static function recolor( array $settings, array $globals, string $key, array $brand, string $kind, string $bg ): array {
		$role = '';
		if ( isset( $globals[ $key ] ) ) {
			$gid = self::global_id( (string) $globals[ $key ], 'colors' );
			if ( in_array( $gid, array( 'primary', 'text', 'accent', 'secondary' ), true ) && empty( $brand['_keep_globals'] ) ) {
				$role = 'secondary' === $gid ? 'primary' : $gid;
			} else {
				return array( $settings, $globals, self::resolve_global( (string) $globals[ $key ], (array) ( $brand['kit_colors'] ?? array() ) ) );
			}
		} else {
			$value = is_string( $settings[ $key ] ?? null ) ? strtoupper( trim( $settings[ $key ] ) ) : '';
			if ( isset( self::DIGITALISIMO[ $value ] ) ) {
				$to = self::DIGITALISIMO[ $value ];
				if ( '#' === $to[0] ) {
					$settings[ $key ] = $to;
					return array( $settings, $globals, $to );
				}
				$role = $to;
			} elseif ( 'fg' === $kind && '' !== $value && ! preg_match( '/border|divider|separator|shadow|overlay|line/', $key ) && self::is_whiteish( $value ) && self::luminance( $bg ) > 0.45 ) {
				// Texto claro de la plantilla sobre un fondo que en este sitio es claro: el texto del sitio.
				$settings[ $key ] = $brand['colors']['text']['color'];
				return array( $settings, $globals, $settings[ $key ] );
			} else {
				return array( $settings, $globals, self::hex( $value ) );
			}
		}
		if ( 'fg' === $kind && 'text' !== $role ) {
			$hex = $brand['colors'][ $role ]['color'];
			if ( self::contrast( $hex, '' !== $bg ? $bg : self::WHITE ) < 3 ) {
				// Un título o un resalte que no se leería sobre ese fondo: el otro color de marca o el del texto.
				$other = 'accent' === $role ? 'primary' : 'accent';
				if ( self::contrast( $brand['colors'][ $other ]['color'], '' !== $bg ? $bg : self::WHITE ) >= 3 ) {
					$role = $other;
				} elseif ( self::luminance( $bg ) < 0.3 ) {
					unset( $globals[ $key ] );
					$settings[ $key ] = self::WHITE;
					return array( $settings, $globals, self::WHITE );
				} else {
					$role = 'text';
				}
			}
		}
		if ( 'fg' === $kind && 'text' === $role && self::contrast( $brand['colors']['text']['color'], '' !== $bg ? $bg : self::WHITE ) < 3 ) {
			unset( $globals[ $key ] );
			$settings[ $key ] = self::on( $bg, $brand['colors']['text']['color'] );
			return array( $settings, $globals, $settings[ $key ] );
		}
		$target = $brand['colors'][ $role ];
		if ( '' !== $target['global'] ) {
			$globals[ $key ]  = 'globals/colors?id=' . $target['global'];
			$settings[ $key ] = '';
		} else {
			unset( $globals[ $key ] );
			$settings[ $key ] = $target['color'];
		}
		return array( $settings, $globals, $target['color'] );
	}

	/** El color que de verdad tiene un ajuste (global del sitio resuelto o literal). */
	private static function color_value( array $settings, array $globals, string $key, array $brand ): string {
		if ( isset( $globals[ $key ] ) ) {
			$gid = self::global_id( (string) $globals[ $key ], 'colors' );
			foreach ( $brand['colors'] as $c ) {
				if ( $c['global'] === $gid ) {
					return $c['color'];
				}
			}
			return self::resolve_global( (string) $globals[ $key ], (array) ( $brand['kit_colors'] ?? array() ) );
		}
		return self::hex( is_string( $settings[ $key ] ?? null ) ? $settings[ $key ] : '' );
	}

	/** Los colores DIGITALÍSIMO dentro de un ajuste compuesto (sombras): sus neutros o el papel del sitio. */
	private static function neutral_deep( $value, array $brand ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::neutral_deep( $v, $brand );
			}
			return $value;
		}
		if ( is_string( $value ) ) {
			$up = strtoupper( trim( $value ) );
			if ( isset( self::DIGITALISIMO[ $up ] ) ) {
				$to = self::DIGITALISIMO[ $up ];
				return '#' === $to[0] ? $to : $brand['colors'][ $to ]['color'];
			}
			if ( preg_match( '/^rgba\(\s*(0\s*,\s*168\s*,\s*107|15\s*,\s*20\s*,\s*26)\s*,/i', $value ) ) {
				return 'rgba(0, 0, 0, 0.15)';
			}
		}
		return $value;
	}

	/** El CSS propio de un widget: los globales de las plantillas y la paleta DIGITALÍSIMO → los colores del sitio. */
	private static function css( string $css, array $brand, string $bg ): string {
		$css = (string) preg_replace_callback(
			'/var\(--e-global-color-(primary|text|accent|secondary)\)/',
			static function ( $m ) use ( $brand, $bg ) {
				$role = 'secondary' === $m[1] ? 'primary' : $m[1];
				$hex  = $brand['colors'][ $role ]['color'];
				if ( 'text' !== $role && self::contrast( $hex, '' !== $bg ? $bg : self::WHITE ) < 3 ) {
					$other = 'accent' === $role ? 'primary' : 'accent';
					$role  = self::contrast( $brand['colors'][ $other ]['color'], '' !== $bg ? $bg : self::WHITE ) >= 3 ? $other : 'text';
					$hex   = $brand['colors'][ $role ]['color'];
					if ( 'text' === $role && self::contrast( $hex, '' !== $bg ? $bg : self::WHITE ) < 3 ) {
						return self::on( $bg, $hex );
					}
				}
				$gid = $brand['colors'][ $role ]['global'];
				return '' !== $gid ? 'var(--e-global-color-' . $gid . ')' : $hex;
			},
			$css
		);
		foreach ( self::DIGITALISIMO as $hex => $to ) {
			if ( false !== stripos( $css, $hex ) ) {
				$css = str_ireplace( $hex, '#' === $to[0] ? $to : $brand['colors'][ $to ]['color'], $css );
			}
		}
		return $css;
	}

	/** Las tipografías del sitio en un widget de la página. */
	private static function fonts( array $settings, string $widget, array $brand ): array {
		$heading = (string) ( $brand['fonts']['heading'] ?? '' );
		$body    = (string) ( $brand['fonts']['body'] ?? '' );
		$set     = static function ( array $s, string $prefix, string $family ): array {
			if ( '' === $family ) {
				return $s;
			}
			$s[ $prefix . '_typography' ]  = 'custom';
			$s[ $prefix . '_font_family' ] = $family;
			if ( isset( $s['__globals__'][ $prefix . '_typography' ] ) ) {
				unset( $s['__globals__'][ $prefix . '_typography' ] );
			}
			return $s;
		};
		switch ( $widget ) {
			case 'heading':
				$big = ! in_array( (string) ( $settings['header_size'] ?? 'h2' ), array( 'p', 'div', 'span', 'h5', 'h6' ), true );
				return $set( $settings, 'typography', $big ? $heading : $body );
			case 'text-editor':
			case 'button':
				return $set( $settings, 'typography', $body );
			case 'icon-list':
				return $set( $settings, 'icon_typography', $body );
			case 'icon-box':
			case 'image-box':
				$settings = $set( $settings, 'title_typography', $heading );
				return $set( $settings, 'description_typography', $body );
			case 'accordion':
			case 'toggle':
				$settings = $set( $settings, 'title_typography', $heading );
				return $set( $settings, 'content_typography', $body );
			case 'price-list':
			case 'countdown':
				return $settings;
		}
		return $settings;
	}

	/* ------------------------------------------------------------------ */
	/* Lo que lleva de verdad una página (pura).                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Los colores y las tipografías que lleva una página (con los globales del kit resueltos) y si son del sitio:
	 * ['colors' => [...], 'fonts' => [...], 'digitalisimo' => [colores de la paleta DIGITALÍSIMO hallados],
	 * 'brand_used' => [papeles del sitio que se usan], 'matches_site' => bool].
	 */
	public static function look( array $elements, array $brand, array $kit_colors = array() ): array {
		$colors = array();
		$fonts  = array();
		$kit    = array(
			'colors' => array(),
		);
		foreach ( $kit_colors as $id => $hex ) {
			$kit['colors'][ $id ] = array( 'color' => self::hex( (string) $hex ) );
		}
		self::look_walk( $elements, $kit, $colors, $fonts, 0 );
		$contrast = array();
		self::contrast_pass( $elements, $brand + array( 'kit_colors' => $kit['colors'] ), self::WHITE, false, $contrast, 0 );
		$colors = array_values( array_unique( $colors ) );
		$fonts  = array_values( array_unique( $fonts ) );
		// Los colores DEL SITIO: los papeles de su marca, los de su kit y los que se ven en su cabecera, pie y portada. Un
		// color de la paleta DIGITALÍSIMO sólo es ajeno si el sitio no lo usa (digitalisimo.lat y .mx sí la usan).
		$site = array();
		foreach ( (array) ( $brand['colors'] ?? array() ) as $c ) {
			$site[] = (string) $c['color'];
		}
		foreach ( (array) ( $brand['kit_colors'] ?? array() ) as $c ) {
			$site[] = (string) ( is_array( $c ) ? ( $c['color'] ?? '' ) : $c );
		}
		foreach ( $kit['colors'] as $c ) {
			$site[] = (string) $c['color'];
		}
		foreach ( (array) ( $brand['seen'] ?? array() ) as $hex ) {
			$site[] = (string) $hex;
		}
		$site = array_values( array_unique( array_filter( $site ) ) );
		$bad  = array();
		foreach ( $colors as $hex ) {
			if ( isset( self::DIGITALISIMO[ $hex ] ) && ! self::near_any( $hex, $site ) ) {
				$bad[] = $hex;
			}
		}
		// La marca aparece si su primario o su acento está en la página (en un texto, un fondo, un borde o un icono).
		$used = array();
		foreach ( (array) ( $brand['colors'] ?? array() ) as $role => $c ) {
			if ( self::near_any( (string) $c['color'], $colors ) ) {
				$used[] = $role;
			}
		}
		$want     = array_filter( array( (string) ( $brand['fonts']['heading'] ?? '' ), (string) ( $brand['fonts']['body'] ?? '' ) ) );
		$fonts_ok = ! $want || ! array_diff( $fonts, $want );
		$identity = 'ninguna' !== ( $brand['source'] ?? '' );
		return array(
			'colors'        => $colors,
			'fonts'         => $fonts,
			'digitalisimo'  => $bad,
			'brand_used'    => $used,
			'fonts_ok'      => $fonts_ok,
			'contrast'      => array_values( array_unique( $contrast ) ),
			'site_identity' => $identity,
			'matches_site'  => ! $bad && (bool) array_intersect( array( 'primary', 'accent' ), $used ) && $fonts_ok && $identity,
		);
	}

	private static function look_walk( array $elements, array $kit, array &$colors, array &$fonts, int $depth ): void {
		if ( $depth > 40 ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$globals  = is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : array();
			foreach ( array_unique( array_merge( array_keys( $settings ), array_keys( $globals ) ) ) as $key ) {
				$key = (string) $key;
				if ( preg_match( self::COLOR_KEY, $key ) ) {
					$hex = isset( $globals[ $key ] ) ? self::resolve_global( (string) $globals[ $key ], $kit['colors'] ) : self::hex( is_string( $settings[ $key ] ?? null ) ? $settings[ $key ] : '' );
					if ( '' !== $hex ) {
						$colors[] = $hex;
					}
				} elseif ( preg_match( '/font_family$/', $key ) && is_string( $settings[ $key ] ?? null ) && '' !== $settings[ $key ] ) {
					$fonts[] = self::family( $settings[ $key ] );
				} elseif ( 'custom_css' === $key && is_string( $settings[ $key ] ?? null ) && preg_match_all( '/#[0-9a-f]{6}\b/i', $settings[ $key ], $m ) ) {
					foreach ( $m[0] as $hex ) {
						$colors[] = strtoupper( $hex );
					}
				}
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::look_walk( $element['elements'], $kit, $colors, $fonts, $depth + 1 );
			}
		}
	}

	/** Resumen corto para la respuesta de create-page. */
	public static function summary( array $brand ): array {
		$out = array(
			'source' => $brand['source'],
			'colors' => array(),
			'fonts'  => $brand['fonts'],
		);
		foreach ( $brand['colors'] as $role => $c ) {
			$out['colors'][ $role ] = $c['color'] . ( '' !== $c['global'] ? ' (color global «' . $c['global'] . '» del kit)' : '' );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Colores (puros).                                                     */
	/* ------------------------------------------------------------------ */

	/** `#RRGGBB` en mayúsculas de un color CSS (#rgb, #rrggbb, #rrggbbaa, rgb[a]() opaco), o ''. */
	public static function hex( string $value ): string {
		$v = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $v, $m ) ) {
			return strtoupper( '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] );
		}
		if ( preg_match( '/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $v, $m ) ) {
			if ( isset( $m[2] ) && '' !== $m[2] && hexdec( $m[2] ) < 150 ) {
				return '';
			}
			return strtoupper( '#' . $m[1] );
		}
		if ( preg_match( '/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*(?:,\s*([0-9.]+)\s*)?\)$/', $v, $m ) ) {
			if ( isset( $m[4] ) && '' !== $m[4] && (float) $m[4] < 0.6 ) {
				return '';
			}
			return sprintf( '#%02X%02X%02X', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}
		return '';
	}

	/** El id de un global (`globals/colors?id=xxx`), o ''. */
	public static function global_id( string $ref, string $kind ): string {
		return preg_match( '#^globals/' . $kind . '\?id=([A-Za-z0-9_-]{1,40})$#', $ref, $m ) ? $m[1] : '';
	}

	/** El color de un global de color del kit, o ''. */
	public static function resolve_global( string $ref, array $kit_colors ): string {
		$id = self::global_id( $ref, 'colors' );
		return '' !== $id ? (string) ( $kit_colors[ $id ]['color'] ?? '' ) : '';
	}

	/** El global del kit que es (casi) ese color: sistema primero. */
	public static function global_for( string $hex, array $kit_colors ): string {
		$best = '';
		$dist = 13.0;
		foreach ( array( true, false ) as $system ) {
			foreach ( $kit_colors as $id => $c ) {
				if ( (bool) $c['system'] !== $system ) {
					continue;
				}
				$d = self::distance( $hex, (string) $c['color'] );
				if ( $d < $dist ) {
					$dist = $d;
					$best = (string) $id;
				}
			}
			if ( '' !== $best ) {
				return $best;
			}
		}
		return $best;
	}

	/** Una familia tipográfica limpia (sin comillas ni respaldos), o ''. */
	public static function family( string $value ): string {
		$first = trim( explode( ',', $value )[0] );
		$first = trim( $first, "\"' " );
		if ( '' === $first || ! preg_match( '/^[\p{L}0-9 ._-]{2,60}$/u', $first ) || in_array( strtolower( $first ), array( 'inherit', 'initial', 'default', 'sans-serif', 'serif', 'var' ), true ) ) {
			return '';
		}
		return $first;
	}

	private static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/** Luminancia relativa (WCAG). */
	public static function luminance( string $hex ): float {
		if ( ! preg_match( '/^#[0-9A-F]{6}$/i', $hex ) ) {
			return 1.0;
		}
		$l = 0.0;
		foreach ( array_map( null, self::rgb( $hex ), array( 0.2126, 0.7152, 0.0722 ) ) as $pair ) {
			$c  = $pair[0] / 255;
			$c  = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			$l += $c * $pair[1];
		}
		return $l;
	}

	/** Contraste WCAG entre dos colores (1–21). */
	public static function contrast( string $a, string $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/** El color de texto que se lee sobre un fondo: blanco o el oscuro del sitio. */
	public static function on( string $bg, string $dark ): string {
		$dark = self::luminance( $dark ) < 0.2 ? $dark : self::DARK;
		return self::contrast( self::WHITE, $bg ) >= self::contrast( $dark, $bg ) ? self::WHITE : $dark;
	}

	/** Matiz (0–360), saturación y luz (0–1). */
	public static function hsl( string $hex ): array {
		list( $r, $g, $b ) = array_map(
			static function ( $c ) {
				return $c / 255;
			},
			self::rgb( $hex )
		);
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;
		if ( $max === $min ) {
			return array( 0.0, 0.0, $l );
		}
		$d = $max - $min;
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
		if ( $max === $r ) {
			$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}
		return array( $h * 60, $s, $l );
	}

	/** ¿Un color con tono (de marca), ni blanco, ni negro, ni gris? */
	public static function vivid( string $hex ): bool {
		if ( ! preg_match( '/^#[0-9A-F]{6}$/i', $hex ) ) {
			return false;
		}
		list( , $s, $l ) = self::hsl( $hex );
		return $s >= 0.35 && $l >= 0.15 && $l <= 0.85;
	}

	public static function hue_distance( string $a, string $b ): float {
		$d = abs( self::hsl( $a )[0] - self::hsl( $b )[0] );
		return min( $d, 360 - $d );
	}

	private static function distance( string $a, string $b ): float {
		if ( ! preg_match( '/^#[0-9A-F]{6}$/i', $a ) || ! preg_match( '/^#[0-9A-F]{6}$/i', $b ) ) {
			return 999.0;
		}
		list( $r1, $g1, $b1 ) = self::rgb( $a );
		list( $r2, $g2, $b2 ) = self::rgb( $b );
		return sqrt( ( $r1 - $r2 ) ** 2 + ( $g1 - $g2 ) ** 2 + ( $b1 - $b2 ) ** 2 );
	}

	/** ¿Está ese color (casi igual) en la lista? */
	public static function near_any( string $hex, array $list, float $tolerance = 8.0 ): bool {
		foreach ( $list as $other ) {
			if ( self::distance( $hex, (string) $other ) < $tolerance ) {
				return true;
			}
		}
		return false;
	}

	private static function is_whiteish( string $value ): bool {
		if ( preg_match( '/^rgba?\(\s*255\s*,\s*255\s*,\s*255/i', $value ) ) {
			return true;
		}
		$hex = self::hex( $value );
		return '' !== $hex && self::luminance( $hex ) > 0.75;
	}

	/** Agrupa tonos parecidos del logo (pasos de 32). */
	private static function bucket( string $hex ): string {
		list( $r, $g, $b ) = self::rgb( $hex );
		$q = static function ( $c ) {
			return min( 255, (int) ( floor( $c / 32 ) * 32 + 16 ) );
		};
		return sprintf( '#%02X%02X%02X', $q( $r ), $q( $g ), $q( $b ) );
	}
}
