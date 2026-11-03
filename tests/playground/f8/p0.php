<?php
/**
 * P0 · A/B de Prime Slider Lite 4.7.0 oficial (f8.sh, F8 frente J). P1 reutiliza sus funciones (`digi_f8_ps_*`).
 *
 * La copia oficial de Lite (ZIP de reference/, sha256 comprobada por f8.sh contra OFFICIAL-SOURCES.txt) se monta en
 * `plugins/bdthemes-prime-slider-lite/` y se activa con todos sus módulos (también los de WooCommerce). Cada widget de
 * Lite se guarda dos veces, con los casos `defaults` y `custom` de `tests/legacy/prime-slider-<id>.json` (fixtures
 * sintéticos de los frentes E y F): `defaults` dentro de sección y columna, `custom` dentro de un contenedor. Los
 * marcadores se expanden como en tests/migration-prime-slider.php:
 *
 * - `{{ps_assets}}` → la carpeta `assets/` de Lite en este sitio (imágenes de demostración del proveedor);
 * - `{{vendor_profile:x}}` → el perfil del proveedor en la red `x` (nombre deducido de vendors.json, nunca copiado);
 * - `{{demo_video}}` → el último vídeo del proveedor de vendors.json; `{{demo_video_file}}` → un MP4 de example.test;
 * - las imágenes `https://s1.test/wp-content/uploads/…` → adjuntos reales de este sitio (con su ID);
 * - el término 52 («Noticias» en las pruebas) → la categoría «Noticias» (o la de productos en los de WooCommerce).
 *
 * Contenido para las familias dinámicas: cuatro entradas con imagen destacada en «Noticias» y cuatro productos con
 * imagen en «Noticias». La subida de SVG sin filtrar queda encendida (`elementor_unfiltered_files_upload`): así la
 * copia de las imágenes de demostración SVG pasa por `wp_check_filetype_and_ext()` real.
 */

defined( 'ABSPATH' ) || exit;

const DIGI_F8_PS_LITE = 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php';

/** Widgets de una edición (`lite` o `pro`), según la clave `plugin` de los 57 mapas de Migración montada. */
function digi_f8_ps_inventory( $edition ) {
	$out = array();
	foreach ( (array) glob( WP_PLUGIN_DIR . '/digitalisimo-migration/legacy/prime-slider/maps/prime-slider-*.php' ) as $file ) {
		$map = include $file;
		if ( is_array( $map ) && $edition === ( $map['plugin'] ?? '' ) ) {
			$out[] = basename( $file, '.php' );
		}
	}
	sort( $out );
	return $out;
}

/** Plugins y opciones de un sitio con Lite: WooCommerce (si se montó), Elements, Migración, Lite y el tema. */
function digi_f8_ps_activate( array $mode ) {
	$plugins = array();
	if ( ! empty( $mode['woo'] ) ) {
		$plugins[] = 'woocommerce/woocommerce.php';
	}
	$plugins = array_merge( $plugins, array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php', DIGI_F8_PS_LITE ) );
	foreach ( $plugins as $plugin ) {
		digi_f8_activate_plugin( $plugin );
	}
	if ( file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
		switch_theme( 'digitalisimo-theme' );
	} else {
		digi_f8_fail( 'Falta el tema digitalisimo-theme montado' );
	}
	update_option( 'blogname', 'Sitio F8 Prime Slider' );
	// Todos los módulos de Lite encendidos (los de WooCommerce son «terceros»), como desde su pantalla.
	$on = array();
	foreach ( (array) glob( WP_PLUGIN_DIR . '/bdthemes-prime-slider-lite/modules/*', GLOB_ONLYDIR ) as $dir ) {
		$on[ basename( $dir ) ] = 'on';
	}
	update_option( 'prime_slider_active_modules', $on );
	update_option( 'prime_slider_third_party_widget', $on );
	update_option( 'elementor_unfiltered_files_upload', 1 );
}

/** Imagen real en uploads (GD si existe; si no, un PNG mínimo) convertida en adjunto. Devuelve `{ id, url }`. */
function digi_f8_ps_image( $name, $color ) {
	static $made = array();
	if ( isset( $made[ $name ] ) ) {
		return $made[ $name ];
	}
	$uploads = wp_upload_dir();
	$file    = trailingslashit( $uploads['path'] ) . $name;
	$ext     = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( function_exists( 'imagecreatetruecolor' ) ) {
		$img = imagecreatetruecolor( 640, 400 );
		imagefill( $img, 0, 0, imagecolorallocate( $img, $color[0], $color[1], $color[2] ) );
		imagestring( $img, 5, 20, 20, $name, imagecolorallocate( $img, 255, 255, 255 ) );
		'png' === $ext ? imagepng( $img, $file ) : imagejpeg( $img, $file, 85 );
		imagedestroy( $img );
		$mime = 'png' === $ext ? 'image/png' : 'image/jpeg';
	} else {
		file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore
		$mime = 'image/png';
	}
	$id = wp_insert_attachment( array( 'post_title' => pathinfo( $name, PATHINFO_FILENAME ), 'post_mime_type' => $mime, 'post_status' => 'inherit', 'guid' => trailingslashit( $uploads['url'] ) . $name ), $file );
	if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', 'Imagen ' . pathinfo( $name, PATHINFO_FILENAME ) );
	$made[ $name ] = array( 'id' => (int) $id, 'url' => (string) wp_get_attachment_url( $id ) );
	return $made[ $name ];
}

/** Entradas y productos con imagen para las familias dinámicas (E, F, G). Devuelve los term_taxonomy_id de «Noticias». */
function digi_f8_ps_posts( array $mode ) {
	$terms = get_option( 'digi_f8_ps_terms', array() );
	if ( $terms ) {
		return $terms;
	}
	$cat   = wp_insert_term( 'Noticias', 'category' );
	$cat   = is_wp_error( $cat ) ? get_term_by( 'name', 'Noticias', 'category', ARRAY_A ) : $cat;
	$terms = array( 'post' => (int) $cat['term_taxonomy_id'], 'post_term' => (int) $cat['term_id'] );
	$colors = array( array( 36, 94, 255 ), array( 200, 60, 60 ), array( 40, 140, 80 ), array( 120, 70, 160 ) );
	foreach ( array( 1, 2, 3, 4 ) as $n ) {
		$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Noticia F8 ' . $n, 'post_name' => 'digi-f8-noticia-' . $n, 'post_excerpt' => 'Resumen de la noticia ' . $n . '.', 'post_content' => 'Contenido de la noticia ' . $n . ' de prueba.', 'post_category' => array( (int) $cat['term_id'] ), 'post_date' => '2026-10-0' . $n . ' 10:00:00' ) );
		$img  = digi_f8_ps_image( 'noticia-' . $n . '.jpg', $colors[ $n - 1 ] );
		set_post_thumbnail( $post, $img['id'] );
		digi_f8_set_id( 'digi-f8-noticia-' . $n, $post );
	}
	if ( ! empty( $mode['woo'] ) && class_exists( 'WC_Product_Simple' ) ) {
		$pcat = wp_insert_term( 'Noticias', 'product_cat' );
		$pcat = is_wp_error( $pcat ) ? get_term_by( 'name', 'Noticias', 'product_cat', ARRAY_A ) : $pcat;
		$terms['product'] = (int) $pcat['term_taxonomy_id'];
		foreach ( array( array( 'Camisa F8', '25', '' ), array( 'Gorra F8', '15', '12' ), array( 'Taza F8', '9', '' ), array( 'Libro F8', '30', '' ) ) as $i => $row ) {
			$product = new WC_Product_Simple();
			$product->set_name( $row[0] );
			$product->set_slug( sanitize_title( $row[0] ) );
			$product->set_status( 'publish' );
			$product->set_regular_price( $row[1] );
			if ( '' !== $row[2] ) {
				$product->set_sale_price( $row[2] );
			}
			$product->set_short_description( 'Descripción corta de ' . $row[0] . '.' );
			$product->set_category_ids( array( (int) $pcat['term_id'] ) );
			$product->set_image_id( digi_f8_ps_image( 'producto-' . ( $i + 1 ) . '.jpg', $colors[ $i ] )['id'] );
			$product->set_date_created( time() - $i * HOUR_IN_SECONDS );
			digi_f8_set_id( 'digi-f8-producto-' . ( $i + 1 ), $product->save() );
		}
	}
	update_option( 'digi_f8_ps_terms', $terms, false );
	return $terms;
}

/** Nombre del proveedor deducido de vendors.json (como tests/migration-prime-slider.php), sin copiarlo. */
function digi_f8_ps_vendor() {
	$v      = json_decode( (string) @file_get_contents( DIGI_F8_DIR . '/vendors.json' ), true ); // phpcs:ignore
	$vendor = '';
	foreach ( (array) ( $v['hosts'] ?? array() ) as $host ) {
		$label = explode( '.', $host )[0];
		foreach ( (array) ( $v['originalSlugs'] ?? array() ) as $slug ) {
			if ( 0 === strpos( $slug, $label . '-' ) ) {
				$vendor = $label;
			}
		}
	}
	$videos = (array) ( $v['vendorVideos'] ?? array() );
	return array( $vendor, (string) end( $videos ) );
}

/** Expande los marcadores de un fixture para este sitio (ver la cabecera). */
function digi_f8_ps_expand( $value, $widget, array $terms ) {
	static $vendor = null;
	if ( null === $vendor ) {
		$vendor = digi_f8_ps_vendor();
	}
	if ( is_array( $value ) ) {
		// Imagen de s1.test: adjunto real de este sitio con su ID.
		if ( isset( $value['url'] ) && is_string( $value['url'] ) && preg_match( '#^https://s1\.test/wp-content/uploads/\d{4}/\d{2}/([a-z0-9-]+\.(?:jpe?g|png))$#', $value['url'], $m ) ) {
			$img          = digi_f8_ps_image( $m[1], array( 60 + strlen( $m[1] ) * 7 % 180, 90, 140 ) );
			$value['url'] = $img['url'];
			if ( isset( $value['id'] ) ) {
				$value['id'] = $img['id'];
			}
			return $value;
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && preg_match( '/term_ids$/', $key ) && is_array( $item ) ) {
				$tt   = false !== strpos( $widget, '-woo' ) && ! empty( $terms['product'] ) ? $terms['product'] : $terms['post'];
				$item = array_map( static function ( $t ) use ( $tt ) {
					return '52' === (string) $t ? (string) $tt : $t;
				}, $item );
			}
			$out[ $key ] = digi_f8_ps_expand( $item, $widget, $terms );
		}
		return $out;
	}
	if ( ! is_string( $value ) ) {
		return $value;
	}
	$assets = plugins_url( 'assets/', WP_PLUGIN_DIR . '/' . DIGI_F8_PS_LITE );
	$value  = str_replace( array( '{{ps_assets}}', '{{demo_video}}', '{{demo_video_file}}' ), array( $assets, 'https://youtu.be/' . $vendor[1], '//media.example.test/demo/sample.mp4' ), $value );
	return preg_replace_callback( '/\{\{vendor_profile:([a-z]+)\}\}/', static function ( $m ) use ( $vendor ) {
		return 'http://www.' . $m[1] . '.com/' . $vendor[0] . '/';
	}, $value );
}

/**
 * Una página por widget y caso (`digi-f8-ps-<id sin prefijo>-<caso>`), con el JSON tal cual lo deja el editor del
 * origen. Anota el manifiesto `digi_f8_ps_docs` (clave → widget, caso, familia, edición).
 */
function digi_f8_ps_documents( array $widgets, array $cases, $edition, array $mode ) {
	$terms    = digi_f8_ps_posts( $mode );
	$manifest = (array) get_option( 'digi_f8_ps_docs', array() );
	foreach ( $widgets as $widget ) {
		$fixture = digi_f8_fixture( $widget );
		if ( ! $fixture ) {
			digi_f8_warn( 'Sin fixture para ' . $widget );
			continue;
		}
		foreach ( $cases as $case ) {
			if ( ! isset( $fixture[ $case ] ) || ! is_array( $fixture[ $case ] ) ) {
				continue;
			}
			$settings = digi_f8_ps_expand( $fixture[ $case ], $widget, $terms );
			$slug     = 'digi-f8-ps-' . substr( $widget, strlen( 'prime-slider-' ) ) . '-' . $case;
			// `defaults` en sección y columna (sitios antiguos); `custom` en un contenedor.
			$element = digi_f8_wrap( $widget, $settings, $slug, array(), 'defaults' === $case );
			digi_f8_document( $slug, array( $element ), array( 'title' => $slug ) );
			$map               = include WP_PLUGIN_DIR . '/digitalisimo-migration/legacy/prime-slider/maps/' . $widget . '.php';
			$map               = is_array( $map ) ? $map : array();
			$manifest[ $slug ] = array(
				'widget'   => $widget,
				'case'     => $case,
				'family'   => (string) ( $fixture['_family'] ?? '' ),
				'edition'  => $edition,
				'parent'   => 'defaults' === $case ? 'section' : 'container',
				'decision' => (string) ( $map['decision'] ?? '' ),
				'target'   => (string) ( $map['target'] ?? '' ),
				'fallback' => (string) ( $map['fallback'] ?? '' ),
				'losses'   => array_map( 'strval', (array) ( $map['losses'] ?? array() ) ),
				'reason'   => (string) ( $map['reason'] ?? '' ),
			);
		}
	}
	update_option( 'digi_f8_ps_docs', $manifest, false );
}

/** Operaciones de la sonda de P0 y P1. */
function digi_f8_ps_ops() {
	return array(
		// Manifiesto de documentos y estado de Lite (activo e instalado).
		'psdocs' => static function ( $arg ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
			return array(
				'docs'      => (array) get_option( 'digi_f8_ps_docs', array() ),
				'lite'      => array( 'installed' => isset( $plugins[ DIGI_F8_PS_LITE ] ), 'version' => $plugins[ DIGI_F8_PS_LITE ]['Version'] ?? '', 'active' => is_plugin_active( DIGI_F8_PS_LITE ), 'loaded' => defined( 'BDTPS_CORE_VER' ) ),
				'terms'     => get_option( 'digi_f8_ps_terms', array() ),
				'registered' => class_exists( '\\Elementor\\Plugin' ) ? array_values( array_filter( array_keys( \Elementor\Plugin::$instance->widgets_manager->get_widget_types() ), static function ( $t ) { return 0 === strpos( $t, 'prime-slider-' ); } ) ) : array(),
			);
		},
		// Imágenes de demostración copiadas a Medios (meta de Demo_Media) y adjuntos SVG.
		'psmedia' => static function ( $arg ) {
			$out = array();
			foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'suppress_filters' => true ) ) as $a ) {
				$sha = (string) get_post_meta( $a->ID, '_digitalisimo_migration_demo', true );
				$any = get_post_meta( $a->ID );
				$own = '';
				foreach ( array_keys( (array) $any ) as $k ) {
					if ( 0 === strpos( $k, '_digitalisimo' ) ) {
						$own = $k;
					}
				}
				if ( '' === $own && '' === $sha && 'image/svg+xml' !== $a->post_mime_type ) {
					continue;
				}
				$file  = (string) get_attached_file( $a->ID );
				$out[] = array( 'id' => $a->ID, 'mime' => $a->post_mime_type, 'file' => basename( $file ), 'exists' => is_file( $file ), 'meta' => $own, 'url' => wp_get_attachment_url( $a->ID ), 'script' => is_file( $file ) && preg_match( '/<script|onload=|onerror=/i', (string) file_get_contents( $file ) ) ); // phpcs:ignore
			}
			return array( 'media' => $out, 'registry' => get_option( 'digitalisimo_migration_reg_demo', null ) );
		},
	);
}

if ( 'P0' === ( digi_f8_mode()['scenario'] ?? '' ) ) {
	function digi_f8_activate( array $mode ) {
		digi_f8_ps_activate( $mode );
	}
	function digi_f8_content( array $mode ) {
		if ( ! empty( $mode['woo'] ) && class_exists( 'WC_Install' ) && ! get_option( 'woocommerce_shop_page_id' ) ) {
			WC_Install::install();
			WC_Install::create_pages();
		}
		digi_f8_ps_documents( digi_f8_ps_inventory( 'lite' ), array( 'defaults', 'custom' ), 'lite', $mode );
	}
	function digi_f8_ops() {
		return digi_f8_ps_ops();
	}
}
