<?php
/**
 * P2 · Element Pack sintético en un sitio (f8.sh, F8 frente J). También lo usan P3 (cada sitio de la red) y P9.
 *
 * Plugins falsos con sólo la cabecera (f8/fake-plugins/: «Element Pack Pro» 10.0.1, que define BDTEP_VER, «Element
 * Pack Addon», «Prime Slider Pro» y «Prime Slider»; con PRO=1 también «Elementor Pro») y la cabecera simulada del
 * origen (f8/mu-ep-header.php, clean-room). Datos sintéticos con el formato que guarda el origen (research-ep.md §2):
 *
 * - Theme Builder: dos cabeceras (la general excluye la página especial; la otra sólo la incluye), un pie con la
 *   activación heredada, entrada, archivo, búsqueda, 404, tienda y carrito (con WooCommerce);
 * - una fuente propia (WOFF de bytes ficticios en Medios), un conjunto de iconos de dos glifos inventados
 *   (tests/sources/migration/icons/set-glifos), ajustes del kit con la pestaña global del origen, CSS y JavaScript
 *   globales, una barra lateral con el widget de plantilla, una contraseña de aplicación «Element Pack AI — …»;
 * - una página por cada una de las 18 extensiones que se convierten (primer caso de tests/legacy/ext-*.json) y dos
 *   widgets heredados (bdt-accordion y bdt-creative-button, caso `custom`).
 *
 * Nada es real ni del proveedor. Los IDs se anotan en `digi_f8_ids` (clave → ID).
 */

defined( 'ABSPATH' ) || exit;

/** Las 18 extensiones que la etapa convierte (effects/keys.php, `treatment` distinto de `dropped`). */
const DIGI_F8_EXT = array( 'tooltip', 'transform-effects', 'parallax-effects', 'background-parallax', 'image-parallax', 'background-overlay', 'background-expand', 'section-sticky', 'equal-height', 'reveal-effects', 'scroll-fill-effect', 'scroll-box', 'threed-text', 'grid-line', 'global-badge', 'image-hover-effects', 'animated-gradient-background', 'custom-js' );
/** Las 28 claves de extensión del origen, todas encendidas. */
const DIGI_F8_EXT_ALL = array( 'tooltip', 'transform-effects', 'parallax-effects', 'background-parallax', 'background-image-parallax', 'image-parallax', 'background-overlay', 'background-expand', 'section-sticky', 'particles', 'equal-height', 'cursor-effects', 'reveal-effects', 'ripple-effects', 'scroll-fill-effect', 'scroll-box', 'threed-text', 'tile-scroll', 'grid-line', 'global-badge', 'hash-link', 'image-hover-effects', 'confetti-effects', 'sound-effects', 'animated-gradient-background', 'content-protector', 'custom-js', 'adblock-detector' );

/** Plugins del origen simulados (y el conflicto con PRO=1). */
function digi_f8_p2_fakes( array $mode ) {
	$fakes = array( 'bdthemes-element-pack/bdthemes-element-pack.php', 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php', 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php', 'bdthemes-prime-slider/bdthemes-prime-slider.php' );
	if ( ! empty( $mode['pro'] ) ) {
		$fakes[] = 'elementor-pro/elementor-pro.php';
	}
	return $fakes;
}

/** Activación de un sitio (P2, P9): WooCommerce, Elements, Migración, el tema y los plugins falsos en el sitio. */
function digi_f8_p2_activate( array $mode ) {
	$plugins = array();
	if ( ! empty( $mode['woo'] ) ) {
		$plugins[] = 'woocommerce/woocommerce.php';
	}
	$plugins = array_merge( $plugins, array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' ), digi_f8_p2_fakes( $mode ) );
	foreach ( $plugins as $plugin ) {
		digi_f8_activate_plugin( $plugin );
	}
	if ( file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
		switch_theme( 'digitalisimo-theme' );
	} else {
		digi_f8_fail( 'Falta el tema digitalisimo-theme montado' );
	}
	update_option( 'blogname', 'Sitio F8' );
}

/** Adjunto con un archivo de bytes ficticios en uploads (fuentes). */
function digi_f8_fake_file( $name, $bytes, $mime ) {
	// Directamente en uploads (como lo dejó la subida del origen): la lista de tipos de WordPress no admite fuentes.
	$uploads = wp_upload_dir();
	$file    = trailingslashit( $uploads['path'] ) . $name;
	if ( false === file_put_contents( $file, $bytes ) ) { // phpcs:ignore
		digi_f8_fail( 'No se pudo escribir ' . $file );
		return 0;
	}
	$id = wp_insert_attachment( array( 'post_title' => $name, 'post_mime_type' => $mime, 'post_status' => 'inherit', 'guid' => trailingslashit( $uploads['url'] ) . $name ), $file );
	return (int) $id;
}

/** Plantilla del Theme Builder del origen: entrada `bdt-template-builder`, su tipo, sus condiciones y su activación. */
function digi_f8_ep_template( $slug, $title, $type, array $rows, $activation = 'id', array $elements = array() ) {
	$elements = $elements ? $elements : array( array( 'id' => digi_f8_eid( 'tb' . $slug ), 'elType' => 'container', 'settings' => new stdClass(), 'elements' => array( array( 'id' => digi_f8_eid( 'tbw' . $slug ), 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $title ), 'elements' => array() ) ) ) );
	$meta     = array(
		'_bdthemes_builder_template_type' => $type,
		'_bdthemes_builder_edit_with'     => 'elementor',
		'_wp_page_template'               => 'elementor_header_footer',
	);
	if ( $rows ) {
		$meta['_bdthemes_builder_template_conditions'] = wp_json_encode( $rows );
	}
	$id = digi_f8_document( $slug, $elements, array( 'post_type' => 'bdt-template-builder', 'title' => $title, 'template_type' => '', 'meta' => $meta ) );
	if ( 'id' === $activation ) {
		update_option( '_bdthemes_builder_' . $type . '__' . $id, (string) $id );
	} elseif ( 'legacy' === $activation ) {
		update_option( '_bdthemes_builder_' . $type, (string) $id );
	}
	return $id;
}

/** Contenido de un sitio con el origen (P2, P3 en cada sitio, P9). `$full` = plataforma completa (P2, P9). */
function digi_f8_p2_content( array $mode, $full = true ) {
	$ids = array();
	// Páginas y entradas.
	$home    = digi_f8_document( 'digi-f8-inicio', array( digi_f8_wrap( 'heading', array( 'title' => 'Portada F8' ), 'inicio' ) ), array( 'title' => 'Inicio' ) );
	$special = digi_f8_document( 'digi-f8-especial', array( digi_f8_wrap( 'heading', array( 'title' => 'Página especial' ), 'especial' ) ), array( 'title' => 'Especial' ) );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home );
	$cat  = wp_create_category( 'Noticias F8' );
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada F8', 'post_name' => 'digi-f8-entrada', 'post_content' => 'Contenido de la entrada F8.', 'post_category' => array( $cat ) ) );
	digi_f8_set_id( 'digi-f8-entrada', $post );
	update_option( 'digi_f8_category', (int) $cat );

	// Theme Builder del origen (research-ep.md §2.1).
	$h1 = digi_f8_ep_template( 'digi-f8-tb-cabecera', 'Cabecera principal', 'themes|header', array( array( 'polarity' => 'include', 'condition_a' => 'entire_site' ), array( 'polarity' => 'exclude', 'condition_a' => 'singular', 'condition_singular' => 'selective', 'condition_singular_id' => (string) $special ) ) );
	$h2 = digi_f8_ep_template( 'digi-f8-tb-cabecera-especial', 'Cabecera especial', 'themes|header', array( array( 'polarity' => 'include', 'condition_a' => 'singular', 'condition_singular' => 'selective', 'condition_singular_id' => (string) $special ) ) );
	$f1 = digi_f8_ep_template( 'digi-f8-tb-pie', 'Pie del sitio', 'themes|footer', array(), 'legacy' );
	if ( $full ) {
		digi_f8_ep_template( 'digi-f8-tb-entrada', 'Plantilla de entrada', 'post|single', array( array( 'polarity' => 'include', 'condition_a' => 'singular', 'condition_singular' => 'all_posts' ) ) );
		digi_f8_ep_template( 'digi-f8-tb-archivo', 'Plantilla de archivo', 'post|archive', array( array( 'polarity' => 'include', 'condition_a' => 'archive', 'condition_archive' => 'all' ) ) );
		digi_f8_ep_template( 'digi-f8-tb-busqueda', 'Plantilla de búsqueda', 'page|search', array() );
		digi_f8_ep_template( 'digi-f8-tb-404', 'Plantilla 404', 'page|404', array() );
		if ( class_exists( 'WooCommerce' ) ) {
			digi_f8_ep_template( 'digi-f8-tb-tienda', 'Plantilla de tienda', 'product|shop', array() );
			digi_f8_ep_template( 'digi-f8-tb-carrito', 'Plantilla de carrito', 'product|cart', array() );
		}
	}
	update_option( 'digi_f8_tb', array( 'header' => $h1, 'header_special' => $h2, 'footer' => $f1, 'special' => $special ), false );

	// Extensiones del origen, todas encendidas como las deja su pantalla de módulos.
	$on = array();
	foreach ( DIGI_F8_EXT_ALL as $ext ) {
		$on[ $ext ] = 'on';
	}
	update_option( 'element_pack_elementor_extend', $on );
	update_option( 'element_pack_active_modules', array( 'accordion' => 'on', 'creative-button' => 'on' ) );

	// Dos widgets heredados (conversión que ya existía).
	foreach ( array( 'bdt-accordion', 'bdt-creative-button' ) as $widget ) {
		$case = digi_f8_case( $widget, 'custom' );
		digi_f8_document( 'digi-f8-' . $widget, array( digi_f8_wrap( $case['type'], $case['settings'], $widget ) ) );
	}
	if ( ! $full ) {
		return;
	}
	// Una página por extensión que se convierte (el primer caso del fixture).
	foreach ( DIGI_F8_EXT as $ext ) {
		$cases = digi_f8_fixture( 'ext-' . $ext );
		$name  = (string) array_key_first( $cases );
		$case  = digi_f8_case( 'ext-' . $ext, $name );
		$type  = 'heading' === $case['type'] || '' === $case['type'] ? 'heading' : $case['type'];
		$set   = $case['settings'];
		if ( 'heading' === $type && ! isset( $set['title'] ) ) {
			$set['title'] = 'Extensión ' . $ext;
		}
		$legacy = (bool) $case['section'];
		digi_f8_document( 'digi-f8-ext-' . $ext, array( digi_f8_wrap( $type, $set, 'ext' . $ext, $case['section'], $legacy ) ), array( 'page_settings' => $case['page'] ) );
	}

	// Fuente propia (ep_custom_font + ep_font_files) con un WOFF de bytes ficticios.
	$woff = digi_f8_fake_file( 'letra-prueba.woff', 'wOFF' . str_repeat( "\0", 60 ) . 'digi-f8', 'font/woff' );
	$font = wp_insert_post( array( 'post_type' => 'ep_custom_font', 'post_status' => 'publish', 'post_title' => 'Letra Prueba' ) );
	update_post_meta( $font, 'ep_font_files', array( array( 'font_weight' => '400', 'font_style' => 'normal', 'woff' => array( 'id' => (string) $woff, 'url' => wp_get_attachment_url( $woff ) ), 'woff2' => '', 'ttf' => '', 'otf' => '', 'svg' => '', 'eot' => '' ) ) );
	digi_f8_set_id( 'font-source', $font );
	digi_f8_document( 'digi-f8-fuente', array( digi_f8_wrap( 'heading', array( 'title' => 'Con la fuente propia', 'typography_typography' => 'custom', 'typography_font_family' => 'Letra Prueba' ), 'fuente' ) ) );

	// Conjunto de iconos propio: entrada, configuración y archivos en uploads/element-pack/custom-icons/<ID>/.
	$icons_src = json_decode( (string) file_get_contents( DIGI_F8_SOURCES . '/icons.json' ), true );
	$icon_set  = wp_insert_post( array( 'post_type' => 'bdt_custom_icons', 'post_status' => 'publish', 'post_title' => 'Glifos' ) );
	update_post_meta( $icon_set, 'bdt_custom_icon_set_config', wp_slash( wp_json_encode( $icons_src['config'] ) ) );
	$uploads = wp_upload_dir();
	$dest    = $uploads['basedir'] . '/element-pack/custom-icons/' . $icon_set;
	wp_mkdir_p( $dest . '/fonts' );
	foreach ( array( 'style.css', 'selection.json', 'demo.html', 'fonts/glifos.woff', 'fonts/glifos.ttf', 'fonts/glifos.svg' ) as $file ) {
		copy( DIGI_F8_SOURCES . '/icons/set-glifos/' . $file, $dest . '/' . $file );
	}
	digi_f8_set_id( 'icons-source', $icon_set );
	$library = 'bdt-custom-glifos-' . $icon_set;
	digi_f8_document(
		'digi-f8-iconos',
		array(
			array( 'id' => 'f8ico01', 'elType' => 'container', 'settings' => new stdClass(), 'elements' => array(
				array( 'id' => 'f8ico02', 'elType' => 'widget', 'widgetType' => 'icon', 'settings' => array( 'selected_icon' => array( 'value' => 'gl gl-estrella', 'library' => $library ) ), 'elements' => array() ),
				array( 'id' => 'f8ico03', 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array( array( '_id' => 'r1', 'text' => 'Uno', 'selected_icon' => array( 'value' => 'gl gl-nube', 'library' => $library ) ), array( '_id' => 'r2', 'text' => 'Dos', 'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) ) ) ), 'elements' => array() ),
			) ),
		)
	);

	// Ajustes del kit con la pestaña global del origen (sólo las claves `element_pack_*`, `ep_*` y el menú contextual).
	$kit = (int) get_option( 'elementor_active_kit' );
	if ( ! $kit && class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->kits_manager ) ) {
		$kit = (int) \Elementor\Plugin::$instance->kits_manager->create_default();
		update_option( 'elementor_active_kit', $kit );
	}
	if ( $kit ) {
		$settings = (array) get_post_meta( $kit, '_elementor_page_settings', true );
		$source   = json_decode( (string) file_get_contents( DIGI_F8_SOURCES . '/kit.json' ), true );
		foreach ( (array) $source['kit'] as $key => $value ) {
			if ( preg_match( '/^(element_pack_|ep_|menus$|menu_)/', $key ) ) {
				$settings[ $key ] = $value;
			}
		}
		update_post_meta( $kit, '_elementor_page_settings', wp_slash( $settings ) );
		digi_f8_set_id( 'kit', $kit );
	} else {
		digi_f8_warn( 'Sin kit activo: el paso del kit no tendrá nada que limpiar' );
	}

	// Código global del origen: CSS válido en la cabecera y JavaScript (que sólo puede ir a un borrador).
	$code = json_decode( (string) file_get_contents( DIGI_F8_SOURCES . '/code.json' ), true );
	update_option( 'ep_custom_css', (string) $code['valid'] );
	update_option( 'ep_custom_js', (string) $code['js'] );

	// Barra lateral con el widget de plantilla del origen.
	$tpl = digi_f8_document( 'digi-f8-plantilla-lateral', array( digi_f8_wrap( 'heading', array( 'title' => 'Promoción lateral' ), 'lateral' ) ), array( 'post_type' => 'elementor_library', 'template_type' => 'section', 'title' => 'Promoción lateral' ) );
	update_option( 'widget_elementortemplate_widget', array( 2 => array( 'title' => 'Promo <b>nueva</b>', 'template_id' => (string) $tpl ), '_multiwidget' => 1 ) );
	$sidebars                = (array) get_option( 'sidebars_widgets', array() );
	$sidebars['sidebar-1']   = array_merge( (array) ( $sidebars['sidebar-1'] ?? array() ), array( 'elementortemplate_widget-2' ) );
	update_option( 'sidebars_widgets', $sidebars );

	// Contraseña de aplicación del asistente del origen (el informe la nombra; su valor nunca sale en ningún HTML).
	if ( class_exists( 'WP_Application_Passwords' ) ) {
		$created = WP_Application_Passwords::create_new_application_password( 1, array( 'name' => 'Element Pack AI — Asistente' ) );
		if ( is_wp_error( $created ) ) {
			digi_f8_warn( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
		} else {
			update_option( 'digi_f8_secret', array( 'password' => (string) $created[0], 'uuid' => (string) $created[1]['uuid'] ), false );
		}
	}
	// Una clave de API del origen (sólo se informa; nunca se copia ni se muestra).
	update_option( 'element_pack_api_settings', array( 'google_map_key' => 'clave-ficticia-f8-no-mostrar' ) );
	// Pasos cortos: más sondeos de la portada durante «Migrar».
	update_option( 'digi_f8_step_seconds', 5 );
}

/** Operaciones propias de P2, P3 y P9 en la sonda. */
function digi_f8_p2_ops() {
	return array(
		// Copias del Theme Builder: estado, tipo, condiciones y prioridad.
		'tb'      => static function ( $arg ) {
			$out = array();
			foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) ) as $post ) {
				$type = (string) get_post_meta( $post->ID, '_elementor_template_type', true );
				if ( in_array( $type, array( 'kit', 'section', 'page', 'loop-item' ), true ) ) {
					continue;
				}
				$settings = (array) get_post_meta( $post->ID, '_elementor_page_settings', true );
				$out[]    = array(
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'status'     => $post->post_status,
					'filtered'   => get_post_status( $post->ID ),
					'type'       => $type,
					'conditions' => get_post_meta( $post->ID, '_elementor_conditions', true ),
					'priority'   => $settings['digitalisimo_tb_priority'] ?? null,
					'origin'     => get_post_meta( $post->ID, '_digitalisimo_import_origin', true ),
				);
			}
			return array( 'templates' => $out, 'state' => get_option( 'digitalisimo_migration_tb_state', null ), 'tb' => get_option( 'digi_f8_tb', array() ) );
		},
		// Plataforma: fuentes, iconos, código, kit, barra lateral y plugins instalados (que nunca se borran).
		'platform' => static function ( $arg ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$posts = static function ( $type ) {
				$out = array();
				foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => -1, 'suppress_filters' => true ) ) as $p ) {
					$row = array( 'id' => $p->ID, 'title' => $p->post_title, 'status' => $p->post_status );
					if ( 'elementor_snippet' === $type ) {
						$row['location'] = get_post_meta( $p->ID, '_elementor_location', true );
						$row['code']     = substr( (string) get_post_meta( $p->ID, '_elementor_code', true ), 0, 200 );
					}
					if ( 'elementor_icons' === $type ) {
						$row['config'] = get_post_meta( $p->ID, 'elementor_custom_icon_set_config', true );
						$row['path']   = get_post_meta( $p->ID, '_elementor_icon_set_path', true );
					}
					if ( 'elementor_font' === $type ) {
						$row['files'] = get_post_meta( $p->ID, 'elementor_font_files', true );
						$row['face']  = substr( (string) get_post_meta( $p->ID, 'elementor_font_face', true ), 0, 400 );
					}
					$out[] = $row;
				}
				return $out;
			};
			$kit     = (int) get_option( 'elementor_active_kit' );
			$uploads = wp_upload_dir();
			$icons   = array();
			foreach ( (array) glob( $uploads['basedir'] . '/elementor/custom-icons/*', GLOB_ONLYDIR ) as $dir ) {
				$icons[ basename( $dir ) ] = array_map( 'basename', (array) glob( $dir . '/*' ) );
			}
			return array(
				'fonts'     => $posts( 'elementor_font' ),
				'icons'     => $posts( 'elementor_icons' ),
				'icon_dirs' => $icons,
				'snippets'  => $posts( 'elementor_snippet' ),
				'kit'       => array_values( array_filter( array_keys( (array) get_post_meta( $kit, '_elementor_page_settings', true ) ), static function ( $k ) { return (bool) preg_match( '/^(element_pack_|ep_)/', $k ); } ) ),
				'kit_backup' => '' !== (string) get_post_meta( $kit, '_digitalisimo_ep_backup_page', true ),
				'sidebars'  => get_option( 'sidebars_widgets', array() ),
				'installed' => array_keys( get_plugins() ),
				'alias'     => get_option( 'digitalisimo_migration_icon_alias', null ),
			);
		},
		// El secreto creado en la preparación (sólo para comprobar que ningún HTML lo muestra).
		'secret'  => static function ( $arg ) {
			return (array) get_option( 'digi_f8_secret', array() );
		},
	);
}

if ( 'P2' === ( digi_f8_mode()['scenario'] ?? '' ) ) {
	function digi_f8_activate( array $mode ) {
		digi_f8_p2_activate( $mode );
	}
	function digi_f8_content( array $mode ) {
		if ( ! empty( $mode['woo'] ) && class_exists( 'WC_Install' ) && ! get_option( 'woocommerce_shop_page_id' ) ) {
			WC_Install::install();
			WC_Install::create_pages();
		}
		digi_f8_p2_content( $mode );
	}
	function digi_f8_ops() {
		return digi_f8_p2_ops();
	}
}
