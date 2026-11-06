<?php
/**
 * Primer paso del blueprint de pro-ab.sh: registro de errores fatales (mu-plugin), ajustes del sitio y activación de
 * DIGITALÍSIMO Elements con el núcleo incluido. Las páginas se crean en el paso siguiente (`setup.php`), en otra
 * petición, con el constructor ya cargado.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
// Sin actualizaciones automáticas: con una Release más nueva en GitHub, WordPress actualizaría DIGITALÍSIMO Elements
// (su actualizador las activa) y borraría la carpeta montada, que es la del repositorio o su copia de la corrida.
wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-no-autoupdate.php', "<?php\n// Arnés de pruebas: sin actualizaciones automáticas (borrarían el plugin montado).\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\nadd_filter( 'auto_update_theme', '__return_false', PHP_INT_MAX );\n" );
add_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );

const DIGI_AB_PROBE = '/wordpress/wp-content/probe';

if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
file_put_contents( WPMU_PLUGIN_DIR . '/digi-ab-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );' );

// WP-Cron no se dispara desde las visitas (como la sonda de front-budget): con un solo proceso de PHP, su petición en
// lazo hacía que Playground sirviera alguna hoja `post-N.css` como redirección a una página de WordPress.
file_put_contents( WPMU_PLUGIN_DIR . '/digi-ab-no-cron.php', "<?php\nif ( ! defined( 'DISABLE_WP_CRON' ) ) {\n\tdefine( 'DISABLE_WP_CRON', true );\n}\n" );

// Tienda (C7): pro-ab.sh deja woo.json y monta WooCommerce fijado si algún caso de la corrida lo pide (`woo`, un
// contexto de tienda o un widget de tienda de PRO). Se activa antes que el constructor, como en front-budget.sh, y la
// tienda queda abierta (sin «Próximamente») y sin asistente.
if ( is_file( DIGI_AB_PROBE . '/woo.json' ) ) {
	if ( ! file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		file_put_contents( DIGI_AB_PROBE . '/setup-error.txt', "WooCommerce no está montado\n", FILE_APPEND );
	} else {
		$digi_ab_woo = activate_plugin( 'woocommerce/woocommerce.php' );
		if ( is_wp_error( $digi_ab_woo ) ) {
			file_put_contents( DIGI_AB_PROBE . '/setup-error.txt', 'No se pudo activar WooCommerce: ' . $digi_ab_woo->get_error_message() . "\n", FILE_APPEND );
		}
		update_option( 'woocommerce_coming_soon', 'no' );
		update_option( 'woocommerce_store_pages_only', 'no' );
		update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
		update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
		update_option( 'woocommerce_allow_tracking', 'no' );
	}
}

$result = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
if ( is_wp_error( $result ) ) {
	file_put_contents( DIGI_AB_PROBE . '/setup-error.txt', 'No se pudo activar DIGITALÍSIMO Elements: ' . $result->get_error_message() . "\n", FILE_APPEND );
}
update_option( 'elementor_onboarded', true );
// Sin caché de elementos: cada visita pinta con los ajustes guardados.
update_option( 'elementor_element_cache_ttl', 'disable' );
update_option( 'blogname', 'Sitio de la comparación' );
update_option( 'blogdescription', 'A/B de widgets' );
update_option( 'permalink_structure', '/%postname%/' );

// Migas de Yoast de prueba (casos con `"yoast": true`, C4 frente H): el Breadcrumbs de PRO sólo existe con Yoast
// (`yoast_breadcrumb()` al registrar, `WPSEO_Breadcrumbs::breadcrumb()` al pintar). Yoast no se instala en el Playground:
// este mu-plugin mínimo imprime una ruta fija con el mismo formato que Yoast (inicio, categoría y página actual), igual
// para A y B. Sólo se escribe si algún caso de la corrida lo pide; la corrida sin esos casos no cambia.
$digi_ab_cases = json_decode( (string) @file_get_contents( DIGI_AB_PROBE . '/cases.json' ), true );
$digi_ab_only  = array_filter( array_map( 'trim', explode( ',', (string) @file_get_contents( DIGI_AB_PROBE . '/only.txt' ) ) ) );
$digi_ab_yoast = false;
foreach ( (array) ( $digi_ab_cases['cases'] ?? array() ) as $digi_ab_slug => $digi_ab_case ) {
	$digi_ab_chosen = $digi_ab_only ? in_array( (string) $digi_ab_slug, $digi_ab_only, true ) : empty( $digi_ab_case['pending'] );
	$digi_ab_yoast  = $digi_ab_yoast || ( $digi_ab_chosen && ! empty( $digi_ab_case['yoast'] ) );
}
if ( $digi_ab_yoast ) {
	file_put_contents(
		WPMU_PLUGIN_DIR . '/digi-ab-yoast.php',
		<<<'PHP'
<?php
// pro-ab.sh (activate.php, casos con `"yoast": true`): migas con el formato de Yoast SEO, sin Yoast.
if ( ! class_exists( 'WPSEO_Breadcrumbs' ) ) {
	class WPSEO_Breadcrumbs {
		public static function breadcrumb( $before = '', $after = '', $display = true ) {
			$links = array( array( home_url( '/' ), 'Inicio' ) );
			$last  = wp_get_document_title();
			if ( is_singular() ) {
				$post  = get_queried_object();
				$terms = $post ? get_the_category( $post->ID ) : array();
				if ( $terms ) {
					$links[] = array( get_category_link( $terms[0] ), $terms[0]->name );
				}
				$last = $post ? get_the_title( $post ) : $last;
			} elseif ( is_category() || is_tag() ) {
				$last = single_term_title( '', false );
			}
			$parts = array();
			foreach ( $links as $link ) {
				$parts[] = '<span><a href="' . esc_url( $link[0] ) . '">' . esc_html( $link[1] ) . '</a></span>';
			}
			$parts[] = '<span class="breadcrumb_last" aria-current="page">' . esc_html( $last ) . '</span>';
			$out     = $before . '<span>' . implode( ' » ', $parts ) . '</span>' . $after;
			if ( $display ) {
				echo $out; // phpcs:ignore
				return;
			}
			return $out;
		}
	}
}
if ( ! function_exists( 'yoast_breadcrumb' ) ) {
	function yoast_breadcrumb( $before = '', $after = '', $display = true ) {
		return WPSEO_Breadcrumbs::breadcrumb( $before, $after, $display );
	}
}
PHP
	);
}
