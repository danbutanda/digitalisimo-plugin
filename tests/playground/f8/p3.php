<?php
/**
 * P3 · Red (f8.sh, F8 frente J): «Migrar toda la red» con la barrera de V.6. Red de subdirectorios servida como
 * http://playground.test: sitio principal y /dos/ con cabecera y pie del origen (f8/mu-ep-header.php), /tres/ sin nada
 * que preparar, /archivado/ archivado y, con SITES=n, n sitios más sin plantillas. Casos (`P3_CASE`, en mode.json):
 *
 * - `main`: el origen activado en toda la red (Pro y Addon) y Prime Slider también: la pantalla se cierra tras
 *   arrancar y todo sigue con el WP-Cron de cada sitio y el del sitio principal (liberación y retirada final).
 * - `deadline`: /dos/ no responde (nadie lo conduce): la barrera vence por el plazo relativo (`digi_f8_idle` s) y se
 *   libera con /dos/ listado como «sin preparar».
 * - `blocked`: la cabecera de /dos/ no tiene copia publicable (exclusión intraducible): la barrera no se libera y la
 *   red queda detenida, sin que ningún sitio pierda su cabecera.
 * - `addon-net`: el Addon en toda la red y Pro por sitio. `pro-net`: Pro en toda la red y el Addon sólo en /dos/.
 */

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/p2.php';

// Plazo relativo de la barrera más corto en la prueba (sin la opción, el de Migración: 900 s).
add_filter(
	'digitalisimo_migration_barrier_idle',
	static function ( $seconds ) {
		$own = (int) get_site_option( 'digi_f8_idle', 0 );
		return $own > 0 ? $own : $seconds;
	}
);

/** Caso de P3 (por defecto `main`). */
function digi_f8_p3_case() {
	$case = (string) ( digi_f8_mode()['p3_case'] ?? 'main' );
	return in_array( $case, array( 'main', 'deadline', 'blocked', 'addon-net', 'pro-net' ), true ) ? $case : 'main';
}

/** Sitios de la red: ruta => ID (la preparación los crea). */
function digi_f8_p3_sites() {
	$sites = get_site_option( 'digi_f8_sites', array() );
	return is_array( $sites ) ? $sites : array();
}

if ( 'P3' === ( digi_f8_mode()['scenario'] ?? '' ) ) {
	function digi_f8_activate( array $mode ) {
		$case  = digi_f8_p3_case();
		$sites = array( '/' => 1 );
		$paths = array( 'dos', 'tres', 'archivado' );
		for ( $i = 1; $i <= min( 40, (int) ( $mode['sites'] ?? 0 ) ); $i++ ) {
			$paths[] = sprintf( 's%02d', $i );
		}
		foreach ( $paths as $path ) {
			$id = get_blog_id_from_url( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . $path . '/' );
			if ( ! $id ) {
				$id = wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . $path . '/', 'Sitio ' . $path, 1 );
			}
			if ( is_wp_error( $id ) ) {
				digi_f8_fail( 'No se pudo crear /' . $path . '/: ' . $id->get_error_message() );
				return;
			}
			$sites[ '/' . $path . '/' ] = (int) $id;
		}
		update_site_option( 'digi_f8_sites', $sites );
		update_site_option( 'digi_f8_idle', 'deadline' === $case ? 20 : 0 );
		foreach ( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' ) as $plugin ) {
			digi_f8_activate_plugin( $plugin, true );
		}
		$net = array( 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php', 'bdthemes-prime-slider/bdthemes-prime-slider.php' );
		if ( 'addon-net' === $case ) {
			$net[] = 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php';
		} elseif ( 'pro-net' === $case ) {
			$net[] = 'bdthemes-element-pack/bdthemes-element-pack.php';
		} else {
			$net[] = 'bdthemes-element-pack/bdthemes-element-pack.php';
			$net[] = 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php';
		}
		foreach ( $net as $plugin ) {
			digi_f8_activate_plugin( $plugin, true );
		}
		$allowed                        = (array) get_site_option( 'allowedthemes', array() );
		$allowed['digitalisimo-theme'] = true;
		update_site_option( 'allowedthemes', $allowed ); // Sólo en la red de prueba: habilita el tema en toda la red.
		foreach ( $sites as $id ) {
			switch_to_blog( $id );
			switch_theme( 'digitalisimo-theme' );
			update_option( 'permalink_structure', '/%postname%/' );
			update_option( 'elementor_onboarded', true );
			update_option( 'elementor_google_font', '0' );
			update_option( 'elementor_element_cache_ttl', 'disable' );
			// Pro por sitio (con el Addon en la red) en todos; el Addon por sitio sólo en /dos/ (con Pro en la red).
			if ( 'addon-net' === $case ) {
				digi_f8_activate_plugin( 'bdthemes-element-pack/bdthemes-element-pack.php' );
			} elseif ( 'pro-net' === $case && 2 === (int) $id ) {
				digi_f8_activate_plugin( 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php' );
			}
			restore_current_blog();
		}
	}

	function digi_f8_content( array $mode ) {
		$case = digi_f8_p3_case();
		foreach ( digi_f8_p3_sites() as $path => $id ) {
			switch_to_blog( $id );
			if ( in_array( $path, array( '/', '/dos/' ), true ) ) {
				digi_f8_p2_content( $mode, false );
				if ( 'blocked' === $case && '/dos/' === $path ) {
					// Exclusión sin traducción exacta en la cabecera visible: su copia no es publicable (tb.blocked_retire).
					$tb = (array) get_option( 'digi_f8_tb', array() );
					update_post_meta( (int) $tb['header'], '_bdthemes_builder_template_conditions', wp_json_encode( array( array( 'polarity' => 'include', 'condition_a' => 'entire_site' ), array( 'polarity' => 'exclude', 'condition_a' => 'archive', 'condition_archive' => 'category_child' ) ) ) );
				}
			} else {
				digi_f8_document( 'digi-f8-inicio', array( digi_f8_wrap( 'heading', array( 'title' => 'Sitio sin plantillas' ), 'inicio' . $id ) ), array( 'title' => 'Inicio' ) );
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', digi_f8_ids()['digi-f8-inicio'] ?? 0 );
			}
			flush_rewrite_rules( false );
			restore_current_blog();
		}
		$sites = digi_f8_p3_sites();
		if ( isset( $sites['/archivado/'] ) ) {
			update_blog_status( $sites['/archivado/'], 'archived', 1 );
		}
	}

	function digi_f8_ops() {
		return digi_f8_p2_ops() + array(
			// WP-Cron de este sitio: Playground no sirve /<sitio>/wp-cron.php en una red de subdirectorios (404), así
			// que la sonda incluye wp-cron.php en la petición del sitio, como lo haría su propia visita con WP-Cron.
			'cron'  => static function ( $arg ) {
				delete_transient( 'doing_cron' );
				wp_set_current_user( 0 );
				require ABSPATH . 'wp-cron.php';
				return array( 'ran' => true );
			},
			'sites' => static function ( $arg ) {
				$out = array();
				foreach ( digi_f8_p3_sites() as $path => $id ) {
					$site  = get_site( $id );
					$out[] = array( 'path' => $path, 'id' => (int) $id, 'archived' => $site ? (int) $site->archived : null );
				}
				return array( 'sites' => $out, 'case' => digi_f8_p3_case(), 'network' => array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			},
		);
	}
}
