<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Paso de la cabecera y el pie dinámicos del tema de origen (Hello, experimento «Header & Footer») a plantillas del
 * constructor de temas (T7), limpieza de cachés (T8) y retirada de esas plantillas al revertir.
 *
 * El tema DIGITALÍSIMO anota en `digitalisimo_theme_switch` el modo de cada parte (T6) y avisa con
 * `digitalisimo_theme_switched` después de soltar su cerrojo. Aquí sólo se escriben `generated`,
 * `header_footer.pending` y `header_footer.blocked` (y `reverted` y `state` cuando se deja el tema desde Apariencia,
 * sin pasar por su reversión), siempre sobre el registro releído, y bajo un cerrojo propio
 * (`digitalisimo_theme_convert_lock`, INSERT IGNORE con token, como Migración): dos peticiones no pueden crear dos
 * plantillas. Cada plantilla generada lleva `_digitalisimo_generated = hello_<parte>` y la huella de su
 * `_elementor_data` releído tras guardarlo: una plantilla que el usuario editó nunca se pisa, ni se borra al revertir
 * (pasa a borrador). Lo que no se puede reconstruir se declara en la bitácora (`digitalisimo_theme_switch_log`).
 *
 * El árbol (`build()`) reproduce el marcado y las medidas de Hello 3.5.1 (`template-parts/dynamic-*.php`,
 * `assets/css/{theme,header-footer}.css` e `includes/settings/settings-*.php`) con valores explícitos: Hello no está
 * cargado y sus valores por defecto no existen en el kit guardado.
 */
final class Hello_Header_Footer {
	const RECORD       = 'digitalisimo_theme_switch';
	const LOG          = 'digitalisimo_theme_switch_log';
	const LOCK         = 'digitalisimo_theme_convert_lock';
	const STALE        = 300;
	const META         = '_digitalisimo_generated';
	const ORIGIN_META  = '_digitalisimo_import_origin';
	const CPT          = 'elementor_library';
	const MAX_WARNINGS = 50;
	const PARTS        = array( 'header', 'footer' );

	/** Widgets que necesita cada parte. */
	const WIDGETS = array(
		'header' => array( 'theme-site-logo', 'theme-site-title', 'heading', 'nav-menu' ),
		'footer' => array( 'theme-site-logo', 'theme-site-title', 'heading', 'nav-menu', 'text-editor' ),
	);

	/** Sufijos responsivos que se copian con el mismo nombre. */
	const DEVICES = array( '', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' );

	/** Escalera de anchos del contenido de Hello 3.5.1 (`assets/css/theme.css`): ancho mínimo de pantalla => ancho máximo. */
	const WIDTHS = array( 576 => 500, 768 => 600, 992 => 800, 1200 => 1140 );

	/** Color de los enlaces de `assets/css/reset.css` de Hello 3.5.1, cuando el kit no define otro. */
	const LINK_COLOR       = '#c36';
	const LINK_HOVER_COLOR = '#336';

	private static $tokens = array();

	/** ¿La conversión en curso creó o publicó alguna plantilla? (para limpiar las cachés al terminar). */
	private static $changed = false;

	/* ------------------------------------------------------------------ Eventos del tema */

	/** El tema ya cambió (registro guardado y su cerrojo suelto): T8, restauración de la ida y vuelta y conversión. */
	public static function switched( array $record, $old = null ) {
		if ( 'hello' !== (string) ( $record['origin']['family'] ?? '' ) ) {
			return;
		}
		self::clear_cache();
		if ( ! empty( $record['restore'] ) && ! empty( $record['generated'] ) ) {
			self::restore();
		}
		$current = self::record();
		if ( empty( $current['header_footer']['pending'] ) ) {
			return;
		}
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			if ( self::theme_builder() ) {
				self::convert();
			}
			return;
		}
		// En el front no se crean documentos: la conversión va al cron (o a la próxima visita a la administración).
		if ( ! wp_next_scheduled( Theme_Compat::CRON ) ) {
			wp_schedule_single_event( time(), Theme_Compat::CRON );
		}
	}

	/** Genera (o reutiliza) las plantillas de cabecera y pie de las partes en modo `dynamic`. */
	public static function convert() {
		if ( ! Theme_Compat::active() || ! self::theme_builder() || ! self::lock() ) {
			return false;
		}
		$switched      = false;
		self::$changed = false;
		try {
			$record = self::record();
			$state  = isset( $record['header_footer'] ) && is_array( $record['header_footer'] ) ? $record['header_footer'] : array();
			$user   = self::acting_user( $record, $switched );
			if ( ! $user ) {
				return false; // Sin un usuario que pueda editar el tema, la tarea queda pendiente.
			}
			$generated = isset( $record['generated'] ) && is_array( $record['generated'] ) ? $record['generated'] : array();
			$parts     = array();
			foreach ( self::PARTS as $part ) {
				if ( 'dynamic' === (string) ( $state[ $part ] ?? '' ) ) {
					$parts[] = $part;
				}
			}
			$blocked = '';
			if ( $parts ) {
				$version = (string) ( $record['origin']['version'] ?? '' );
				if ( '' !== $version && 0 !== strpos( $version, '3.5.' ) && '3.5' !== $version ) {
					self::warn( 'hello_version', sprintf( 'La cabecera y el pie se reconstruyeron con los valores de Hello 3.5.1; el sitio venía de la versión %s.', $version ) );
				}
				$missing = self::missing_widget( $parts );
				if ( '' !== $missing ) {
					$blocked = 'widget:' . $missing;
					self::warn( 'widget_missing', sprintf( 'Falta el widget «%s»: la cabecera y el pie del tema anterior no se convirtieron en plantillas.', $missing ) );
				} else {
					foreach ( $parts as $part ) {
						$entry = self::convert_part( $part, $record, $user );
						if ( is_array( $entry ) ) {
							$generated[ $part ] = $entry;
						} elseif ( false === $entry ) {
							$blocked = 'create:' . $part;
						}
					}
				}
			}
			self::merge( array( 'generated' => $generated, 'pending' => false, 'blocked' => $blocked ) );
			if ( self::$changed ) {
				self::clear_cache(); // T8 después de crear o publicar: el CSS en caché era el de antes de las plantillas.
			}
			return true;
		} finally {
			if ( $switched ) {
				wp_set_current_user( 0 );
			}
			self::unlock();
		}
	}

	/**
	 * Se dejó el tema desde Apariencia (o el Personalizador), sin su reversión de WP-CLI: la misma retirada que al
	 * revertir y el registro queda en `reverted`, como lo deja la reversión.
	 */
	public static function left( array $record ) {
		return self::reverted( $record, true );
	}

	/**
	 * Revertir: cada plantilla generada que sigue publicada va a la papelera si no cambió, o a borrador si se editó.
	 * Con `$mark`, además anota `reverted` y `state`; un registro que ya está en `reverted` no se vuelve a tocar.
	 */
	public static function reverted( array $record, $mark = false ) {
		if ( ! self::lock() ) {
			self::warn( 'convert_locked', 'Otra petición estaba convirtiendo la cabecera y el pie: las plantillas generadas no se retiraron.' );
			return false;
		}
		try {
			$current = self::record();
			if ( $mark && ! empty( $current['reverted'] ) ) {
				return true;
			}
			$generated = isset( $current['generated'] ) && is_array( $current['generated'] ) ? $current['generated'] : ( isset( $record['generated'] ) && is_array( $record['generated'] ) ? $record['generated'] : array() );
			foreach ( self::PARTS as $part ) {
				$entry = $generated[ $part ] ?? null;
				if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
					continue;
				}
				$post = get_post( (int) $entry['id'] );
				if ( ! $post || 'publish' !== $post->post_status ) {
					continue;
				}
				$hash = (string) ( $entry['hash'] ?? '' );
				if ( '' !== $hash && hash_equals( $hash, self::hash( (int) $post->ID ) ) ) {
					wp_trash_post( (int) $post->ID );
					$generated[ $part ]['retired'] = 'trash';
				} else {
					wp_update_post( array( 'ID' => (int) $post->ID, 'post_status' => 'draft' ) );
					$generated[ $part ]['retired'] = 'draft';
				}
			}
			self::regenerate_conditions();
			self::clear_cache();
			$changes = array( 'generated' => $generated );
			if ( $mark ) {
				$changes['reverted'] = time();
				$changes['state']    = 'reverted';
			}
			self::merge( $changes );
			return true;
		} finally {
			self::unlock();
		}
	}

	/* ------------------------------------------------------------------ Árbol de la plantilla */

	/**
	 * Árbol de elementos de la plantilla de `$part` a partir de los ajustes guardados del kit (`_elementor_page_settings`).
	 * Función pura: todo lo que depende del sitio llega en `$context` (`has_logo`, `site_name`, `tagline`, `menu` = slug
	 * del menú de la ubicación, `copyright` = texto por defecto, `link`/`link_hover` = ['value'|'global' => …] del kit,
	 * `reset_css` y `theme_css` = si Hello cargaba esas hojas). Con todos sus interruptores apagados, la cabecera
	 * devuelve [] (Hello no la imprime: `hello_get_header_display()`) y el pie sólo su contenedor vacío, porque Hello
	 * imprime `footer.site-footer.dynamic-footer` aunque oculte todo lo de dentro.
	 */
	public static function build( $part, array $settings, array $context = array() ) {
		$part     = 'footer' === $part ? 'footer' : 'header';
		$p        = 'hello_' . $part . '_';
		$s        = $settings + self::defaults( $part, $context );
		$on       = static function ( $key ) use ( $s, $p ) {
			return 'yes' === (string) ( $s[ $p . $key ] ?? '' );
		};
		if ( 'header' === $part && ! in_array( true, array_map( $on, array( 'logo_display', 'tagline_display', 'menu_display' ) ), true ) ) {
			return array();
		}
		$context += array( 'has_logo' => false, 'site_name' => '', 'tagline' => '', 'menu' => '', 'link' => array(), 'link_hover' => array(), 'reset_css' => true, 'theme_css' => true );
		$layout   = in_array( (string) $s[ $p . 'layout' ], array( 'inverted', 'stacked' ), true ) ? (string) $s[ $p . 'layout' ] : 'default';

		$branding = array();
		if ( $on( 'logo_display' ) ) {
			if ( 'title' !== (string) $s[ $p . 'logo_type' ] ) {
				if ( ! empty( $context['has_logo'] ) ) {
					$branding[] = self::logo( $part, $s );
				}
			} elseif ( '' !== (string) $context['site_name'] ) {
				$branding[] = self::title( $part, $s, $context );
			}
		}
		if ( $on( 'tagline_display' ) && '' !== (string) $context['tagline'] ) {
			$branding[] = self::tagline( $part, $s );
		}

		$children = array();
		if ( $branding ) {
			$children[] = self::branding( $part, $s, $branding, $layout, $on( 'logo_display' ) && $on( 'tagline_display' ) );
		}
		if ( $on( 'menu_display' ) && '' !== (string) $context['menu'] ) {
			$children[] = self::menu( $part, $s, $context );
		}
		if ( 'footer' === $part && $on( 'copyright_display' ) && '' !== (string) $s[ $p . 'copyright_text' ] ) {
			$children[] = self::copyright( $s, $layout );
		}
		return array( self::container( $part, $s, $children, $layout, $context ) );
	}

	/** Valores por defecto de los controles de Hello 3.5.1 que el kit no guarda hasta que se editan. */
	private static function defaults( $part, array $context ) {
		$p        = 'hello_' . $part . '_';
		$defaults = array(
			$p . 'logo_display'     => 'yes',
			$p . 'tagline_display'  => 'yes',
			$p . 'menu_display'     => 'yes',
			$p . 'layout'           => 'default',
			$p . 'tagline_position' => 'below',
			$p . 'width'            => 'boxed',
			$p . 'logo_type'        => ( 'header' === $part && empty( $context['has_logo'] ) ) ? 'title' : 'logo',
		);
		if ( 'header' === $part ) {
			$defaults[ $p . 'menu_layout' ]   = 'horizontal';
			$defaults[ $p . 'menu_dropdown' ] = 'tablet';
		} else {
			$defaults[ $p . 'copyright_display' ] = 'yes';
			$defaults[ $p . 'copyright_text' ]    = (string) ( $context['copyright'] ?? 'All rights reserved' );
		}
		return $defaults;
	}

	/** Contenedor exterior: `.site-header.dynamic-header` (o el pie) con la disposición, el ancho y el fondo de Hello. */
	private static function container( $part, array $s, array $children, $layout, array $context ) {
		$p        = 'hello_' . $part . '_';
		$full     = 'full-width' === (string) $s[ $p . 'width' ];
		$classes  = array( 'site-' . $part, 'dynamic-' . $part );
		if ( 'default' !== $layout ) {
			$classes[] = $part . '-' . $layout;
		}
		if ( $full ) {
			$classes[] = $part . '-full-width';
		}
		if ( 'header' === $part ) {
			$dropdown = (string) $s[ $p . 'menu_dropdown' ];
			if ( in_array( $dropdown, array( 'tablet', 'mobile', 'none' ), true ) ) {
				$classes[] = 'menu-dropdown-' . $dropdown;
			}
			if ( 'dropdown' === (string) $s[ $p . 'menu_layout' ] ) {
				$classes[] = 'menu-layout-dropdown';
			}
		} elseif ( '' !== (string) ( $s[ $p . 'copyright_display' ] ?? '' ) && '' !== (string) $s[ $p . 'copyright_text' ] ) {
			$classes[] = 'footer-has-copyright';
		}

		$settings = array(
			'content_width'        => $full ? 'full' : 'boxed',
			'flex_direction'       => 'inverted' === $layout ? 'row-reverse' : ( 'stacked' === $layout ? 'column' : 'row' ),
			'flex_justify_content' => 'space-between',
			'flex_wrap'            => 'wrap',
			'flex_gap'             => array( 'unit' => 'px', 'column' => '0', 'row' => '0', 'isLinked' => true ),
			'css_classes'          => implode( ' ', $classes ),
		);
		if ( 'stacked' === $layout ) {
			$settings['flex_align_items'] = 'center';
		}
		// Relleno: 1rem arriba y abajo (`.site-header` de header-footer.css) y, a los lados, los «márgenes laterales» de Hello.
		$sides = 'stacked' === $layout ? array() : self::responsive_css( $s, $p . 'gap' );
		$settings['padding'] = self::padding( '1rem', $sides[''] ?? '0px' );
		foreach ( $sides as $suffix => $value ) {
			if ( '' !== $suffix ) {
				$settings[ 'padding' . $suffix ] = self::padding( '1rem', $value );
			}
		}
		$custom_width = false;
		if ( ! $full ) {
			foreach ( self::DEVICES as $suffix ) {
				$value = $s[ $p . 'custom_width' . $suffix ] ?? null;
				if ( is_array( $value ) && isset( $value['size'] ) && '' !== (string) $value['size'] ) {
					$settings[ 'boxed_width' . $suffix ] = self::slider( $value );
					$custom_width                         = true;
				}
			}
		}
		self::map_prefix( $s, $p . 'background_', 'background_', $settings );

		// Las hojas de Hello que siguen cargadas en paridad ponen relleno al `.site-header`: aquí lo da el propio contenedor.
		$css = array( $full ? 'selector{padding-block:var(--padding-block-start) var(--padding-block-end)}' : 'selector{padding-block:0}' );
		if ( ! empty( $context['theme_css'] ) ) {
			$css[] = $full
				? '@media(max-width:575px){selector{padding-inline:calc(var(--padding-inline-start) + 10px) calc(var(--padding-inline-end) + 10px)}}'
				: '@media(max-width:575px){selector .e-con-inner{padding-inline:10px}}';
			if ( ! $full && ! $custom_width ) {
				foreach ( self::WIDTHS as $min => $max ) {
					$css[] = sprintf( '@media(min-width:%dpx){selector .e-con-inner{max-width:%dpx}}', $min, $max );
				}
			}
		}
		if ( 'footer' === $part && 'stacked' !== $layout ) {
			// header-footer.css: en pantallas de 576 px o menos, cada parte del pie ocupa una fila centrada.
			$css[] = '@media(max-width:576px){selector .elementor-element{max-width:none;text-align:center;width:100%}selector .e-con{display:block}selector .elementor-nav-menu{justify-content:center;margin-inline:auto}}';
		}
		$settings['custom_css'] = implode( '', $css );

		return self::element( $part, 'container', 'container', $settings, $children, false );
	}

	/** `.site-branding`: logo o título y lema, en columna (o en fila según la posición del lema). */
	private static function branding( $part, array $s, array $children, $layout, $both ) {
		$p        = 'hello_' . $part . '_';
		$settings = array(
			'content_width'        => 'full',
			'width'                => array( 'unit' => 'custom', 'size' => 'auto', 'sizes' => array() ),
			'width_mobile'         => array( 'unit' => 'custom', 'size' => 'auto', 'sizes' => array() ), // El ancho de escritorio no llega a móvil.
			'flex_direction'       => 'column',
			'flex_align_items'     => 'stretch',
			'flex_justify_content' => 'center',
			'flex_gap'             => array( 'unit' => 'rem', 'column' => '0.5', 'row' => '0.5', 'isLinked' => true ),
			'padding'              => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
			'_flex_align_self'     => 'center',
		);
		if ( $both ) {
			$positions = array(
				'before' => array( 'row-reverse', 'center' ),
				'below'  => array( 'column', 'stretch' ),
				'after'  => array( 'row', 'center' ),
			);
			foreach ( self::DEVICES as $suffix ) {
				$position = (string) ( $s[ $p . 'tagline_position' . $suffix ] ?? '' );
				if ( isset( $positions[ $position ] ) ) {
					$settings[ 'flex_direction' . $suffix ]   = $positions[ $position ][0];
					$settings[ 'flex_align_items' . $suffix ] = $positions[ $position ][1];
				}
			}
			foreach ( self::DEVICES as $suffix ) {
				$gap = $s[ $p . 'tagline_gap' . $suffix ] ?? null;
				if ( is_array( $gap ) && isset( $gap['size'] ) && '' !== (string) $gap['size'] ) {
					$unit                               = trim( (string) ( $gap['unit'] ?? 'px' ) );
					$settings[ 'flex_gap' . $suffix ] = array( 'unit' => '' === $unit ? 'px' : $unit, 'column' => (string) $gap['size'], 'row' => (string) $gap['size'], 'isLinked' => true );
				}
			}
		}
		if ( 'inverted' === $layout ) {
			$settings['custom_css'] = 'selector{text-align:end}';
		} elseif ( 'stacked' === $layout ) {
			$settings['custom_css'] = 'selector{text-align:center}';
		}
		return self::element( $part, 'branding', 'container', $settings, $children, true );
	}

	/** Logo del sitio (`the_custom_logo()` de Hello), con su ancho. */
	private static function logo( $part, array $s ) {
		$p        = 'hello_' . $part . '_';
		$settings = array(
			'__dynamic__' => array( 'image' => self::tag( $part, 'site-logo' ) ),
			'image_size'  => 'full',
			'link_to'     => 'site_url',
			'custom_css'  => 'selector img{display:block}',
		);
		foreach ( self::DEVICES as $suffix ) {
			$width = $s[ $p . 'logo_width' . $suffix ] ?? null;
			if ( is_array( $width ) && isset( $width['size'] ) && '' !== (string) $width['size'] ) {
				$settings[ 'width' . $suffix ] = self::slider( $width );
				$settings[ 'space' . $suffix ] = self::slider( $width );
			}
		}
		return self::element( $part, 'logo', 'widget', $settings, array(), false, 'theme-site-logo' );
	}

	/** Título del sitio (`.site-title`): 2.5rem en la cabecera y 1.5rem en el pie, peso 500, interlineado 1.2. */
	private static function title( $part, array $s, array $context ) {
		$p        = 'hello_' . $part . '_title_';
		$settings = array(
			'__dynamic__'            => array( 'title' => self::tag( $part, 'site-title' ), 'link' => self::tag( $part, 'site-url' ) ),
			'title'                  => '',
			'link'                   => array( 'url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => 'rel|home' ),
			'header_size'            => 'div',
			'typography_typography'  => 'custom',
			'typography_font_size'   => array( 'unit' => 'rem', 'size' => 'header' === $part ? 2.5 : 1.5, 'sizes' => array() ),
			'typography_font_weight' => '500',
			'typography_line_height' => array( 'unit' => 'em', 'size' => 1.2, 'sizes' => array() ),
			'__globals__'            => array( 'typography_typography' => '', 'title_color' => '' ),
		);
		self::map_prefix( $s, $p . 'typography_', 'typography_', $settings );
		self::map_prefix( $s, $p . 'text_shadow_', 'text_shadow_', $settings );
		self::map_prefix( $s, $p . 'text_stroke_', 'text_stroke_', $settings );
		self::map_key( $s, $p . 'color', 'title_color', $settings );
		self::map_key( $s, $p . 'hover_color', 'title_hover_color', $settings );
		self::map_key( $s, $p . 'hover_color_transition_duration', 'title_hover_color_transition_duration', $settings );
		// El enlace del título hereda el color del título (widget-heading.css): se le da el de los enlaces, como en Hello.
		self::link_color( $settings, 'title_color', 'title_hover_color', $context );
		$css = self::inherit_css( $settings, 'typography', array( 'font_family' ) );
		if ( '' !== $css ) {
			$settings['custom_css'] = 'selector .elementor-heading-title{' . $css . '}';
		}
		return self::element( $part, 'title', 'widget', $settings, array(), false, 'theme-site-title' );
	}

	/** Lema (`p.site-description`): hereda color, fuente e interlineado del cuerpo, como en Hello. */
	private static function tagline( $part, array $s ) {
		$p        = 'hello_' . $part . '_tagline_';
		$settings = array(
			'__dynamic__' => array( 'title' => self::tag( $part, 'site-tagline' ) ),
			'title'       => '',
			'header_size' => 'p',
			'__globals__' => array( 'typography_typography' => '', 'title_color' => '' ),
		);
		self::map_key( $s, $p . 'color', 'title_color', $settings );
		self::map_prefix( $s, $p . 'typography_', 'typography_', $settings );
		self::map_prefix( $s, $p . 'text_shadow_', 'text_shadow_', $settings );
		$css = self::inherit_css( $settings, 'typography', array( 'font_family', 'font_size', 'font_weight', 'line_height' ), 'title_color' );
		if ( '' !== $css ) {
			$settings['custom_css'] = 'selector .elementor-heading-title{' . $css . '}';
		}
		return self::element( $part, 'tagline', 'widget', $settings, array(), false, 'heading' );
	}

	/** Menú de la ubicación `menu-1` (cabecera) o `menu-2` (pie), con los enlaces de Hello (8px 15px; 5px 15px en el pie). */
	private static function menu( $part, array $s, array $context ) {
		$p        = 'hello_' . $part . '_';
		$header   = 'header' === $part;
		$dropdown = (string) ( $s[ $p . 'menu_dropdown' ] ?? '' );
		$settings = array(
			'menu'                         => (string) $context['menu'],
			'layout'                       => $header && 'dropdown' === (string) ( $s[ $p . 'menu_layout' ] ?? '' ) ? 'dropdown' : 'horizontal',
			'pointer'                      => 'none',
			'dropdown'                     => $header ? ( in_array( $dropdown, array( 'tablet', 'mobile', 'none' ), true ) ? $dropdown : 'tablet' ) : 'none',
			'padding_horizontal_menu_item' => array( 'unit' => 'px', 'size' => 15, 'sizes' => array() ),
			'padding_vertical_menu_item'   => array( 'unit' => 'px', 'size' => $header ? 8 : 5, 'sizes' => array() ),
			'menu_space_between'           => array( 'unit' => 'px', 'size' => 0, 'sizes' => array() ),
			'_flex_align_self'             => 'center',
			'__globals__'                  => array( 'menu_typography_typography' => '', 'color_menu_item' => '', 'color_menu_item_hover' => '' ),
		);
		self::map_key( $s, $p . 'menu_color', 'color_menu_item', $settings );
		self::map_prefix( $s, $p . 'menu_typography_', 'menu_typography_', $settings );
		if ( $header ) {
			self::map_key( $s, $p . 'menu_toggle_color', 'toggle_color', $settings );
			self::map_key( $s, $p . 'menu_toggle_background_color', 'toggle_background_color', $settings );
		}
		self::link_color( $settings, 'color_menu_item', 'color_menu_item_hover', $context );
		// Hello no distingue la página actual: su enlace conserva el color normal, y el del ratón sólo al pasar por encima.
		// El widget aplica el color «al pasar el ratón» también al enlace activo, así que el activo copia el normal.
		self::copy_color( $settings, 'color_menu_item', 'color_menu_item_active' );
		// widget-nav-menu.css fija `line-height:normal` en la lista y 20px en los enlaces: en Hello heredaban el del cuerpo.
		$settings['custom_css'] = 'selector .elementor-nav-menu,selector .elementor-nav-menu li{line-height:inherit}';
		$hover                  = self::css_color( $settings, 'color_menu_item_hover' );
		if ( '' !== $hover ) {
			$settings['custom_css'] .= 'selector .elementor-nav-menu--main .elementor-item.elementor-item-active:hover,selector .elementor-nav-menu--main .elementor-item.elementor-item-active:focus{color:' . $hover . ';fill:' . $hover . '}';
		}
		$css                    = self::inherit_css( $settings, 'menu_typography', array( 'font_family', 'font_size', 'font_weight', 'line_height' ) );
		if ( '' !== $css ) {
			$settings['custom_css'] .= 'selector .elementor-nav-menu--main .elementor-item{' . $css . '}';
		}
		return self::element( $part, 'menu', 'widget', $settings, array(), false, 'nav-menu' );
	}

	/** Copyright del pie (`.copyright p`). */
	private static function copyright( array $s, $layout ) {
		$p        = 'hello_footer_copyright_';
		$settings = array(
			'editor'      => '<p>' . wp_kses_post( (string) $s[ $p . 'text' ] ) . '</p>', // Hello lo imprime en un párrafo.
			'__globals__' => array( 'typography_typography' => '', 'text_color' => '' ),
		);
		if ( 'stacked' === $layout ) {
			$settings['align'] = 'center';
		}
		self::map_key( $s, $p . 'color', 'text_color', $settings );
		self::map_prefix( $s, $p . 'typography_', 'typography_', $settings );
		self::map_prefix( $s, $p . 'text_shadow_', 'text_shadow_', $settings );
		$settings['_flex_align_self'] = 'center';
		$settings['custom_css']       = 'selector p{margin:0}';
		$css                          = self::inherit_css( $settings, 'typography', array( 'font_family', 'font_size', 'font_weight', 'line_height' ), 'text_color' );
		if ( '' !== $css ) {
			$settings['custom_css'] .= 'selector{' . $css . '}';
		}
		return self::element( 'footer', 'copyright', 'widget', $settings, array(), false, 'text-editor' );
	}

	private static function element( $part, $name, $type, array $settings, array $children, $inner, $widget = '' ) {
		$element = array(
			'id'       => self::id( $part, $name ),
			'elType'   => $type,
			'isInner'  => (bool) $inner,
			'settings' => $settings,
			'elements' => $children,
		);
		if ( 'widget' === $type ) {
			$element['widgetType'] = $widget;
		}
		return $element;
	}

	/** Identificador estable de siete caracteres, como los del editor. */
	private static function id( $part, $name ) {
		return substr( md5( 'digitalisimo-hello-' . $part . '-' . $name ), 0, 7 );
	}

	/** Etiqueta dinámica sin ajustes (el mismo texto que `tag_data_to_tag_text()`). */
	private static function tag( $part, $name ) {
		return sprintf( '[elementor-tag id="%s" name="%s" settings="%s"]', self::id( $part, 'tag-' . $name ), $name, rawurlencode( '{}' ) );
	}

	/** Copia `$from*` como `$to*` (sufijos de grupo y responsivos incluidos), con sus globales. */
	private static function map_prefix( array $s, $from, $to, array &$out ) {
		$length = strlen( $from );
		foreach ( $s as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, $from ) && '' !== substr( $key, $length ) ) {
				$out[ $to . substr( $key, $length ) ] = $value;
			}
		}
		foreach ( (array) ( $s['__globals__'] ?? array() ) as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, $from ) && '' !== substr( $key, $length ) && '' !== (string) $value ) {
				$out['__globals__'][ $to . substr( $key, $length ) ] = $value;
			}
		}
	}

	/** Copia la clave exacta `$from` (y sus sufijos responsivos) como `$to`, con su global. */
	private static function map_key( array $s, $from, $to, array &$out ) {
		foreach ( self::DEVICES as $suffix ) {
			if ( isset( $s[ $from . $suffix ] ) && '' !== $s[ $from . $suffix ] && array() !== $s[ $from . $suffix ] ) {
				$out[ $to . $suffix ] = $s[ $from . $suffix ];
			}
			$global = (string) ( $s['__globals__'][ $from . $suffix ] ?? '' );
			if ( '' !== $global ) {
				$out['__globals__'][ $to . $suffix ] = $global;
			}
		}
	}

	private static function has_value( array $settings, $key ) {
		$value = $settings[ $key ] ?? '';
		if ( is_array( $value ) ) {
			return isset( $value['size'] ) ? '' !== (string) $value['size'] : array() !== $value;
		}
		return '' !== (string) $value || '' !== (string) ( $settings['__globals__'][ $key ] ?? '' );
	}

	/**
	 * Color del enlace y de su estado activo cuando Hello no fijó otro, con la misma cascada que en Hello: el color
	 * propio de la parte vale también al pasar el ratón; si no, el del kit (y su color activo, o el normal si el kit
	 * no define uno) y, si el kit no define ninguno, el de reset.css.
	 */
	private static function link_color( array &$settings, $normal, $hover, array $context ) {
		if ( self::has_value( $settings, $normal ) ) {
			if ( ! self::has_value( $settings, $hover ) ) {
				if ( '' !== (string) ( $settings['__globals__'][ $normal ] ?? '' ) ) {
					$settings['__globals__'][ $hover ] = $settings['__globals__'][ $normal ];
				} else {
					$settings[ $hover ] = $settings[ $normal ];
				}
			}
			return;
		}
		$reset = ! empty( $context['reset_css'] );
		$link  = (array) $context['link'];
		self::apply_color( $settings, $normal, $link, $reset ? self::LINK_COLOR : '' );
		if ( ! self::has_value( $settings, $hover ) ) {
			$kit_hover = (array) $context['link_hover'];
			self::apply_color( $settings, $hover, $kit_hover ? $kit_hover : $link, $reset ? self::LINK_HOVER_COLOR : '' );
		}
	}

	/** Copia un color (valor o global) de un control a otro que no tenga uno propio. */
	private static function copy_color( array &$settings, $from, $to ) {
		if ( self::has_value( $settings, $to ) ) {
			return;
		}
		if ( '' !== (string) ( $settings['__globals__'][ $from ] ?? '' ) ) {
			$settings['__globals__'][ $to ] = $settings['__globals__'][ $from ];
		} elseif ( is_string( $settings[ $from ] ?? null ) && '' !== $settings[ $from ] ) {
			$settings[ $to ] = $settings[ $from ];
		}
	}

	/** Valor CSS de un color: el global del kit como su variable, o el valor tal cual (sólo caracteres de color). */
	private static function css_color( array $settings, $key ) {
		$global = (string) ( $settings['__globals__'][ $key ] ?? '' );
		if ( '' !== $global ) {
			return preg_match( '/[?&]id=([A-Za-z0-9_-]+)/', $global, $match ) ? 'var(--e-global-color-' . $match[1] . ')' : '';
		}
		$value = $settings[ $key ] ?? '';
		return is_string( $value ) && preg_match( '/^[#A-Za-z0-9(),.%\s-]+$/', $value ) ? $value : '';
	}

	private static function apply_color( array &$settings, $key, array $kit, $fallback ) {
		if ( '' !== (string) ( $kit['global'] ?? '' ) ) {
			$settings['__globals__'][ $key ] = (string) $kit['global'];
		} elseif ( '' !== (string) ( $kit['value'] ?? '' ) ) {
			$settings[ $key ] = (string) $kit['value'];
		} elseif ( '' !== $fallback ) {
			$settings[ $key ] = $fallback;
		}
	}

	/**
	 * Propiedades que el widget no fija pero sus estilos globales sí (fuente y color principales del kit): en Hello se
	 * heredaban del cuerpo. Sólo se declaran las que Hello no fijó, para no pisar lo que el usuario eligió.
	 */
	private static function inherit_css( array $settings, $group, array $properties, $color = '' ) {
		$css = array();
		if ( '' !== $color && ! self::has_value( $settings, $color ) ) {
			$css[] = 'color:inherit';
		}
		if ( '' !== (string) ( $settings['__globals__'][ $group . '_typography' ] ?? '' ) ) {
			return implode( ';', $css );
		}
		$custom = 'custom' === (string) ( $settings[ $group . '_typography' ] ?? '' );
		foreach ( $properties as $property ) {
			if ( ! $custom || ! self::has_value( $settings, $group . '_' . $property ) ) {
				$css[] = str_replace( '_', '-', $property ) . ':inherit';
			}
		}
		return implode( ';', $css );
	}

	/** Valores CSS (tamaño y unidad) de un deslizador responsivo de Hello, por sufijo. */
	private static function responsive_css( array $s, $key ) {
		$out = array();
		foreach ( self::DEVICES as $suffix ) {
			$value = $s[ $key . $suffix ] ?? null;
			if ( is_array( $value ) && isset( $value['size'] ) && '' !== (string) $value['size'] ) {
				$unit           = trim( (string) ( $value['unit'] ?? 'px' ) );
				$unit           = '' === $unit ? '%' : $unit; // El primer tipo de unidad del control de Hello.
				$out[ $suffix ] = (string) $value['size'] . ( 'custom' === $unit ? '' : $unit );
			}
		}
		return $out;
	}

	private static function slider( array $value ) {
		$unit = trim( (string) ( $value['unit'] ?? 'px' ) );
		return array( 'unit' => '' === $unit ? 'px' : $unit, 'size' => $value['size'], 'sizes' => array() );
	}

	private static function padding( $block, $inline ) {
		return array( 'unit' => 'custom', 'top' => $block, 'right' => $inline, 'bottom' => $block, 'left' => $inline, 'isLinked' => false );
	}

	/* ------------------------------------------------------------------ Conversión de una parte */

	/**
	 * Reutiliza la plantilla generada si existe (publicándola si no cambió) o la crea. Devuelve la entrada de
	 * `generated`, null si no hay nada que generar y false si el documento no pudo crearse.
	 */
	private static function convert_part( $part, array $record, $user ) {
		$existing = self::find( $part );
		if ( $existing ) {
			$stored = self::stored_hash( $existing, $record, $part );
			$hash   = self::hash( (int) $existing->ID );
			if ( '' !== $stored && hash_equals( $stored, $hash ) ) {
				if ( 'publish' !== $existing->post_status ) {
					self::publish( (int) $existing->ID, (string) $existing->post_status );
					self::regenerate_conditions();
					self::$changed = true;
				}
				return array( 'id' => (int) $existing->ID, 'hash' => $hash );
			}
			self::warn( 'generated_edited', sprintf( 'La plantilla «%s» generada desde el tema anterior se editó: se conserva tal como está.', get_the_title( $existing ) ) );
			return array( 'id' => (int) $existing->ID, 'hash' => $stored );
		}

		$tree = self::build( $part, self::kit_settings(), self::context( $part, $record ) );
		if ( ! $tree ) {
			return null;
		}
		$origin   = array( 'origin' => 'hello', 'version' => (string) ( $record['origin']['version'] ?? '' ) );
		$document = \Elementor\Plugin::$instance->documents->create(
			$part,
			array(
				'post_title'  => 'header' === $part ? 'Cabecera' : 'Pie',
				'post_status' => 'publish',
				'post_author' => (int) $user,
			),
			array(
				self::META        => 'hello_' . $part,
				self::ORIGIN_META => $origin,
			)
		);
		if ( ! is_object( $document ) || is_wp_error( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			self::warn( 'generated_failed', sprintf( 'No se pudo crear la plantilla de %s del tema anterior.', 'header' === $part ? 'cabecera' : 'pie' ) );
			return false;
		}
		$id = (int) $document->get_main_id();
		$document->save( array( 'elements' => $tree ) );
		// La huella se toma del JSON releído: save() normaliza el árbol.
		$hash = self::hash( $id );
		update_post_meta( $id, self::ORIGIN_META, $origin + array( 'hash' => $hash ) );
		\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions( $id, array( array( 'type' => 'include', 'name' => 'general' ) ) );
		self::$changed = true;
		return array( 'id' => $id, 'hash' => $hash );
	}

	/** Plantillas en papelera o borrador por una reversión anterior vuelven a publicarse (ida y vuelta). */
	private static function restore() {
		if ( ! self::lock() ) {
			return;
		}
		try {
			$record    = self::record();
			$generated = isset( $record['generated'] ) && is_array( $record['generated'] ) ? $record['generated'] : array();
			$pending   = ! empty( $record['header_footer']['pending'] );
			$changed   = false;
			foreach ( self::PARTS as $part ) {
				$entry = $generated[ $part ] ?? null;
				if ( ! is_array( $entry ) || empty( $entry['retired'] ) ) {
					continue;
				}
				$changed = true;
				$retired = (string) $entry['retired'];
				$post    = empty( $entry['id'] ) ? null : get_post( (int) $entry['id'] );
				unset( $generated[ $part ]['retired'] );
				if ( $post && in_array( $retired, array( 'trash', 'draft' ), true ) && $retired === $post->post_status ) {
					self::publish( (int) $post->ID, $retired );
				} else {
					unset( $generated[ $part ] ); // Ya no existe o el usuario la movió: esa parte vuelve a convertirse.
					$pending = true;
				}
			}
			if ( $changed ) {
				self::regenerate_conditions();
				self::merge( array( 'generated' => $generated, 'pending' => $pending ) );
				self::clear_cache(); // T8 otra vez: el CSS en caché se generó sin las plantillas que se acaban de publicar.
			}
		} finally {
			self::unlock();
		}
	}

	private static function publish( $id, $status ) {
		if ( 'trash' === $status ) {
			wp_untrash_post( $id ); // Restaura como borrador (wp_untrash_post_status): se publica a continuación.
		}
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	}

	/** Plantilla generada de la parte, en cualquier estado (también en la papelera). */
	private static function find( $part ) {
		$posts = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'meta_key'         => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'       => 'hello_' . $part, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
				'posts_per_page'   => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		return $posts ? $posts[0] : null;
	}

	private static function stored_hash( $post, array $record, $part ) {
		$origin = get_post_meta( (int) $post->ID, self::ORIGIN_META, true );
		if ( is_array( $origin ) && '' !== (string) ( $origin['hash'] ?? '' ) ) {
			return (string) $origin['hash'];
		}
		$entry = $record['generated'][ $part ] ?? array();
		return is_array( $entry ) && (int) ( $entry['id'] ?? 0 ) === (int) $post->ID ? (string) ( $entry['hash'] ?? '' ) : '';
	}

	private static function hash( $id ) {
		return md5( (string) get_post_meta( (int) $id, '_elementor_data', true ) );
	}

	/** Datos del sitio que `build()` no lee por sí mismo. */
	private static function context( $part, array $record ) {
		$hello     = isset( $record['hello'] ) && is_array( $record['hello'] ) ? $record['hello'] : array();
		$locations = (array) get_nav_menu_locations();
		$menu_id   = (int) ( $locations[ 'header' === $part ? 'menu-1' : 'menu-2' ] ?? 0 );
		$menu      = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;
		// Hello no imprime el menú si la ubicación no tiene uno con elementos (wp_nav_menu con fallback_cb false).
		$slug      = $menu && ! is_wp_error( $menu ) && wp_get_nav_menu_items( (int) $menu->term_id ) ? (string) $menu->slug : '';
		$kit       = self::kit_settings();
		return array(
			'has_logo'   => (bool) has_custom_logo(),
			'site_name'  => (string) get_bloginfo( 'name' ),
			'tagline'    => (string) get_bloginfo( 'description' ),
			'menu'       => $slug,
			'copyright'  => __( 'All rights reserved', 'hello-elementor' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- el texto por defecto del tema de origen, con su traducción si el sitio la tiene.
			'link'       => self::kit_color( $kit, 'link_normal_color' ),
			'link_hover' => self::kit_color( $kit, 'link_hover_color' ),
			'reset_css'  => empty( $hello['hello_style'] ),
			'theme_css'  => empty( $hello['hello_theme'] ),
		);
	}

	private static function kit_settings() {
		$kit      = (int) get_option( 'elementor_active_kit' );
		$settings = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();
		return is_array( $settings ) ? $settings : array();
	}

	private static function kit_color( array $kit, $key ) {
		$global = (string) ( $kit['__globals__'][ $key ] ?? '' );
		if ( '' !== $global ) {
			return array( 'global' => $global );
		}
		$value = $kit[ $key ] ?? '';
		return is_string( $value ) && '' !== $value ? array( 'value' => $value ) : array();
	}

	/* ------------------------------------------------------------------ Constructor, usuario, registro y bitácora */

	private static function theme_builder() {
		return class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents );
	}

	private static function missing_widget( array $parts ) {
		$manager = \Elementor\Plugin::$instance->widgets_manager ?? null;
		foreach ( $parts as $part ) {
			foreach ( self::WIDGETS[ $part ] as $type ) {
				if ( ! is_object( $manager ) || ! $manager->get_widget_types( $type ) ) {
					return $type;
				}
			}
		}
		return '';
	}

	/** Quién crea las plantillas: el usuario actual si puede editar el tema; sin usuario (cron), el del registro. */
	private static function acting_user( array $record, &$switched ) {
		$current = (int) get_current_user_id();
		if ( $current ) {
			return current_user_can( 'edit_theme_options' ) ? $current : 0;
		}
		$user = (int) ( $record['user'] ?? 0 );
		if ( ! $user ) {
			$user = (int) get_option( Theme_Compat::SWITCHER, 0 ); // El cambio se hizo en la administración y el tema lo registró en una visita.
		}
		if ( ! $user ) {
			$log  = get_option( self::LOG );
			$user = is_array( $log ) ? (int) ( $log['user'] ?? 0 ) : 0;
		}
		if ( $user && user_can( $user, 'edit_theme_options' ) ) {
			wp_set_current_user( $user );
			$switched = true;
			return $user;
		}
		return 0;
	}

	private static function record() {
		wp_cache_delete( self::RECORD, 'options' );
		$record = get_option( self::RECORD, array() );
		return is_array( $record ) ? $record : array();
	}

	/** Fusiona sólo lo que escribe Elements en el registro releído. */
	private static function merge( array $changes ) {
		$record = self::record();
		if ( array_key_exists( 'generated', $changes ) ) {
			if ( $changes['generated'] ) {
				$record['generated'] = $changes['generated'];
			} else {
				unset( $record['generated'] );
			}
		}
		foreach ( array( 'pending', 'blocked' ) as $key ) {
			if ( array_key_exists( $key, $changes ) ) {
				if ( ! isset( $record['header_footer'] ) || ! is_array( $record['header_footer'] ) ) {
					$record['header_footer'] = array();
				}
				$record['header_footer'][ $key ] = $changes[ $key ];
			}
		}
		foreach ( array( 'reverted', 'state' ) as $key ) {
			if ( array_key_exists( $key, $changes ) ) {
				$record[ $key ] = $changes[ $key ];
			}
		}
		update_option( self::RECORD, $record, true );
		return $record;
	}

	/** Aviso en la bitácora, una vez por código y con un máximo de 50. */
	private static function warn( $code, $message ) {
		$log = get_option( self::LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$warnings = isset( $log['warnings'] ) && is_array( $log['warnings'] ) ? $log['warnings'] : array();
		foreach ( $warnings as $key => $warning ) {
			if ( $code === $key || ( is_array( $warning ) && $code === (string) ( $warning['code'] ?? '' ) ) ) {
				return false;
			}
		}
		if ( count( $warnings ) >= self::MAX_WARNINGS ) {
			return false;
		}
		$warnings[]       = array( 'code' => $code, 'message' => $message );
		$log['warnings'] = $warnings;
		update_option( self::LOG, $log, false );
		return true;
	}

	/** T8: CSS generado del constructor y cachés de página de terceros. */
	private static function clear_cache() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return;
		}
		$files = \Elementor\Plugin::$instance->files_manager ?? null;
		if ( is_object( $files ) && method_exists( $files, 'clear_cache' ) ) {
			$files->clear_cache();
		}
		if ( class_exists( '\Elementor\Compatibility' ) && method_exists( '\Elementor\Compatibility', 'clear_3rd_party_cache' ) ) {
			\Elementor\Compatibility::clear_3rd_party_cache();
		}
	}

	private static function regenerate_conditions() {
		if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_cache()->regenerate();
		}
	}

	/* ------------------------------------------------------------------ Cerrojo (§4.5) */

	/** INSERT IGNORE con token propio; un cerrojo de más de 300 s se roba sólo si nadie lo cambió entre la lectura y el robo. */
	private static function lock() {
		global $wpdb;
		$blog = self::blog();
		if ( isset( self::$tokens[ $blog ] ) ) {
			return false; // Ya lo tiene esta petición: no se reentra.
		}
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
		$value = time() . '|' . $token;
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", self::LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '' !== $held && time() - (int) $held < self::STALE ) {
				return false;
			}
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::LOCK, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return false;
			}
		}
		wp_cache_delete( self::LOCK, 'options' );
		if ( ! self::$tokens ) {
			register_shutdown_function( array( __CLASS__, 'unlock_all' ) );
		}
		self::$tokens[ $blog ] = $token;
		return true;
	}

	/** Suelta el cerrojo propio; nunca el de otra petición. */
	private static function unlock() {
		global $wpdb;
		$blog  = self::blog();
		$token = self::$tokens[ $blog ] ?? '';
		unset( self::$tokens[ $blog ] );
		if ( '' !== $token ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s", self::LOCK, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_cache_delete( self::LOCK, 'options' );
		}
	}

	/** Si la petición termina con un error fatal, suelta los cerrojos propios que quedaran tomados (en su sitio). */
	public static function unlock_all() {
		foreach ( array_keys( self::$tokens ) as $blog ) {
			$switch = function_exists( 'is_multisite' ) && is_multisite() && (int) $blog && (int) $blog !== (int) self::blog();
			if ( $switch ) {
				switch_to_blog( (int) $blog );
			}
			self::unlock();
			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	private static function blog() {
		return function_exists( 'get_current_blog_id' ) ? (string) get_current_blog_id() : '0';
	}
}
