<?php
/**
 * Operaciones de sonda del grupo `resilience` de woo-flow.sh (W0.8, W0.9, W0.10, W3 y W13). `api.probe()` no pasa
 * parámetros: cada operación lee los suyos de `$_GET` (la petición ya trae el token de la sonda y corre como el
 * usuario 1). Sólo cierres: este archivo se incluye en cada operación de sonda que llega hasta él.
 *
 * - `resilience_doc&id=N`: árbol guardado del documento, la huella (md5 del JSON con las claves ordenadas) de los
 *   ajustes de cada widget, sus marcadores de K, las claves nuevas de los widgets de PRO y sus revisiones.
 * - `resilience_woo&on=0|1`: desactiva o activa WooCommerce como la pantalla Plugins (en toda la red si lo está).
 * - `resilience_swatches&type=label|select`: configuración de muestras del atributo `color` (W3 y W13); `select` la quita.
 * - `resilience_cache&on=0|1`: caché de elementos del núcleo (24 h) o desactivada; vacía la caché guardada.
 * - `resilience_export`: exporta este sitio como kit con K (`Session::export_file()`) a `probe/res-shared/kit.zip`.
 * - `resilience_version`: vacía OPcache y dice qué DIGITALÍSIMO Elements cargó la petición (W13 cambia la carpeta).
 * - `resilience_future&on=0|1`: mu-plugin que registra el tipo `digitalisimo-futuro-res` (el «widget del futuro»).
 * - `resilience_new_site` (red): crea un sitio sin visitarlo, para la primera petición anónima de W0.10.
 * - `resilience_fatal`: tamaño y final de `fatal.log`.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
$digi_res_get = static function ( string $key, string $default = '' ): string {
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
};
// phpcs:enable

/** JSON con las claves de cada objeto ordenadas (las listas, en su orden): la misma huella para los mismos datos. */
$digi_res_canon = static function ( $value ) use ( &$digi_res_canon ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
		ksort( $value, SORT_STRING );
	}
	foreach ( $value as $key => $item ) {
		$value[ $key ] = $digi_res_canon( $item );
	}
	return $value;
};
$digi_res_md5 = static function ( $value ) use ( $digi_res_canon ): string {
	return md5( (string) wp_json_encode( $digi_res_canon( is_array( $value ) ? $value : array() ) ) );
};

/** Árbol del documento y lo que se compara de él. */
$digi_res_tree = static function ( array $tree ) use ( $digi_res_md5 ): array {
	$out  = array( 'widgets' => array(), 'markers' => array(), 'pro' => array(), 'headings' => array() );
	$walk = static function ( array $nodes ) use ( &$walk, &$out, $digi_res_md5 ) {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$type     = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			if ( 'widget' === ( $node['elType'] ?? '' ) ) {
				$out['widgets'][] = array( 'id' => (string) ( $node['id'] ?? '' ), 'type' => $type, 'md5' => $digi_res_md5( $settings ), 'settings' => $settings );
				if ( 'digitalisimo-unconverted' === $type ) {
					$payload          = isset( $settings['digitalisimo_unconverted'] ) && is_array( $settings['digitalisimo_unconverted'] ) ? $settings['digitalisimo_unconverted'] : array();
					$original         = isset( $payload['element'] ) && is_array( $payload['element'] ) ? $payload['element'] : array();
					$out['markers'][] = array(
						'id'       => (string) ( $node['id'] ?? '' ),
						'type'     => (string) ( $payload['type'] ?? '' ),
						'reason'   => (string) ( $payload['reason'] ?? '' ),
						'source'   => (string) ( $payload['source'] ?? '' ),
						'original' => (string) ( $original['widgetType'] ?? '' ),
						'orig_id'  => (string) ( $original['id'] ?? '' ),
						'md5'      => $digi_res_md5( isset( $original['settings'] ) && is_array( $original['settings'] ) ? $original['settings'] : array() ),
					);
				}
				if ( 'woocommerce-products' === $type ) {
					$out['pro']['products'] = array( 'custom' => $settings['digitalisimo_wc_custom'] ?? null, 'parts' => $settings['digitalisimo_wc_parts'] ?? null );
				}
				if ( 'woocommerce-menu-cart' === $type ) {
					$out['pro']['menu_cart'] = array( 'bar' => $settings['digitalisimo_cart_bar'] ?? null, 'coupon' => $settings['digitalisimo_cart_coupon'] ?? null );
				}
				if ( 'heading' === $type ) {
					$out['headings'][ (string) ( $node['id'] ?? '' ) ] = (string) ( $settings['title'] ?? '' );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$walk( $node['elements'] );
			}
		}
	};
	$walk( $tree );
	return $out;
};

$digi_res_data = static function ( int $id ): array {
	$raw  = get_post_meta( $id, '_elementor_data', true );
	$tree = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
	return is_array( $tree ) ? $tree : array();
};

return array(
	'resilience_doc'      => static function () use ( $digi_res_get, $digi_res_tree, $digi_res_data ) {
		$id   = (int) $digi_res_get( 'id' );
		$post = get_post( $id );
		if ( ! $post ) {
			return array( 'error' => 'sin documento' );
		}
		$out              = $digi_res_tree( $digi_res_data( $id ) );
		$out['id']        = $id;
		$out['status']    = $post->post_status;
		$out['revisions'] = array();
		foreach ( wp_get_post_revisions( $id, array( 'orderby' => 'ID', 'order' => 'DESC' ) ) as $revision ) {
			$data               = $digi_res_tree( $digi_res_data( (int) $revision->ID ) );
			$out['revisions'][] = array( 'id' => (int) $revision->ID, 'types' => array_values( array_unique( wp_list_pluck( $data['widgets'], 'type' ) ) ), 'headings' => $data['headings'] );
		}
		$out['unconverted'] = (int) get_option( 'digitalisimo_elements_unconverted' );
		return $out;
	},
	'resilience_woo'      => static function () use ( $digi_res_get ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin  = 'woocommerce/woocommerce.php';
		$network = is_multisite();
		$on      = '1' === $digi_res_get( 'on' );
		ob_start();
		$error = '';
		if ( $on ) {
			$result = activate_plugin( $plugin, '', $network );
			$error  = is_wp_error( $result ) ? $result->get_error_message() : '';
		} else {
			deactivate_plugins( $plugin, false, $network );
		}
		$output = (string) ob_get_clean();
		return array(
			'on'      => $on,
			'error'   => $error,
			'output'  => substr( wp_strip_all_tags( $output ), 0, 300 ),
			'active'  => is_plugin_active( $plugin ),
			'network' => $network,
		);
	},
	'resilience_swatches' => static function () use ( $digi_res_get ) {
		$class = '\\Digitalisimo\\Elements\\Woo\\Swatches\\Data';
		if ( ! class_exists( $class ) ) {
			return array( 'error' => 'sin Swatches\\Data' );
		}
		$type = 'label' === $digi_res_get( 'type' ) ? 'label' : 'select';
		$ok   = $class::put( 'color', array( 'type' => $type, 'shape' => 'pill', 'size' => 32 ) );
		if ( method_exists( $class, 'flush' ) ) {
			$class::flush();
		}
		return array( 'ok' => $ok, 'color' => $class::attribute( 'pa_color' ) );
	},
	'resilience_cache'    => static function () use ( $digi_res_get ) {
		if ( '1' === $digi_res_get( 'on' ) ) {
			delete_option( 'elementor_element_cache_ttl' );
		} else {
			update_option( 'elementor_element_cache_ttl', 'disable' );
		}
		delete_post_meta_by_key( '_elementor_element_cache' );
		return array( 'ttl' => get_option( 'elementor_element_cache_ttl', '(por defecto)' ) );
	},
	'resilience_export'   => static function () {
		$session = '\\Digitalisimo\\Elements\\Import\\Kits\\Session';
		if ( ! class_exists( $session ) ) {
			return array( 'error' => 'sin K' );
		}
		$dir = WP_CONTENT_DIR . '/probe/res-shared';
		wp_mkdir_p( $dir );
		$target = $dir . '/kit.zip';
		@unlink( $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		try {
			$result = $session::export_file( $target, array( 'include' => array( 'templates', 'content', 'settings', 'plugins' ), 'title' => 'Kit de resiliencia', 'media' => 'embed' ) );
		} catch ( \Throwable $error ) {
			return array( 'error' => $error->getMessage() );
		}
		$manifest = isset( $result['manifest'] ) && is_array( $result['manifest'] ) ? $result['manifest'] : array();
		return array(
			'file'         => $target,
			'size'         => is_file( $target ) ? filesize( $target ) : 0,
			'sha256'       => is_file( $target ) ? hash_file( 'sha256', $target ) : '',
			'manifest'     => array_keys( $manifest ),
			'plugins'      => isset( $manifest['plugins'] ) ? substr( (string) wp_json_encode( $manifest['plugins'] ), 0, 400 ) : null,
			'digitalisimo' => isset( $manifest['digitalisimo'] ) && is_array( $manifest['digitalisimo'] ) ? array_keys( $manifest['digitalisimo'] ) : null,
			'counts'       => isset( $result['report']['counts'] ) ? $result['report']['counts'] : array(),
		);
	},
	'resilience_version'  => static function () {
		$status = function_exists( 'opcache_get_status' ) ? opcache_get_status( false ) : null;
		$reset  = function_exists( 'opcache_reset' ) ? opcache_reset() : null;
		clearstatcache( true );
		$file = WP_PLUGIN_DIR . '/digitalisimo-elements/pro-elements.php';
		$head = is_file( $file ) ? (string) file_get_contents( $file, false, null, 0, 4096 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/m', $head, $m );
		return array(
			'loaded'  => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
			'on_disk' => isset( $m[1] ) ? $m[1] : null,
			'woo'     => class_exists( '\\Digitalisimo\\Elements\\Woo\\Module', false ),
			'woo_dir' => is_dir( WP_PLUGIN_DIR . '/digitalisimo-elements/modules/digitalisimo-woo' ),
			'opcache' => array(
				'reset'   => $reset,
				'enabled' => (string) ini_get( 'opcache.enable' ),
				'memory'  => is_array( $status ) && isset( $status['memory_usage'] ) ? array_map( 'intval', (array) $status['memory_usage'] ) : null,
				'scripts' => is_array( $status ) && isset( $status['opcache_statistics']['num_cached_scripts'] ) ? (int) $status['opcache_statistics']['num_cached_scripts'] : null,
				'full'    => is_array( $status ) ? ! empty( $status['cache_full'] ) : null,
				'restart' => is_array( $status ) ? ! empty( $status['restart_pending'] ) || ! empty( $status['restart_in_progress'] ) : null,
			),
			'memory'  => memory_get_peak_usage( true ),
		);
	},
	'resilience_future'   => static function () use ( $digi_res_get ) {
		$file = WPMU_PLUGIN_DIR . '/digi-wf-res-future.php';
		if ( '1' !== $digi_res_get( 'on' ) ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return array( 'on' => false, 'exists' => is_file( $file ) );
		}
		$code = <<<'PHP'
<?php
// woo-flow.sh W13.3: el tipo `digitalisimo-futuro-res` existe (como si el sitio tuviera la versión que lo trae).
add_action(
	'elementor/widgets/register',
	static function ( $manager ) {
		if ( ! class_exists( 'Digi_WF_Res_Future_Widget', false ) ) {
			// Declarada aquí, cuando el constructor ya cargó: un mu-plugin carga antes que \Elementor\Widget_Base.
			final class Digi_WF_Res_Future_Widget extends \Elementor\Widget_Base {
				public function get_name() { return 'digitalisimo-futuro-res'; }
				public function get_title() { return 'Widget del futuro'; }
				public function get_categories() { return array( 'digitalisimo' ); }
				protected function register_controls() {
					$this->start_controls_section( 's', array( 'label' => 'Contenido' ) );
					$this->add_control( 'texto', array( 'label' => 'Texto', 'type' => \Elementor\Controls_Manager::TEXT ) );
					$this->end_controls_section();
				}
				protected function render() { echo '<p class="digi-wf-futuro">' . esc_html( (string) $this->get_settings_for_display( 'texto' ) ) . '</p>'; }
			}
		}
		$manager->register( new Digi_WF_Res_Future_Widget() );
	}
);
PHP;
		file_put_contents( $file, $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array( 'on' => true, 'exists' => is_file( $file ) );
	},
	'resilience_new_site' => static function () {
		if ( ! is_multisite() ) {
			return array( 'error' => 'no es una red' );
		}
		$slug = 'res' . substr( md5( microtime( true ) . wp_rand() ), 0, 6 );
		$path = PATH_CURRENT_SITE . $slug . '/';
		$id   = wpmu_create_blog( DOMAIN_CURRENT_SITE, $path, 'Sitio nuevo de resiliencia', 1 );
		if ( is_wp_error( $id ) ) {
			return array( 'error' => $id->get_error_message() );
		}
		return array(
			'id'       => (int) $id,
			'path'     => $path,
			'wc_db'    => get_blog_option( (int) $id, 'woocommerce_db_version' ),
			'cart'     => (int) get_blog_option( (int) $id, 'woocommerce_cart_page_id' ),
			'visited'  => false,
		);
	},
	'resilience_site'     => static function () use ( $digi_res_get ) {
		$id = (int) $digi_res_get( 'blog' );
		if ( ! is_multisite() || ! $id ) {
			return array( 'error' => 'sin sitio' );
		}
		return array(
			'wc_db' => get_blog_option( $id, 'woocommerce_db_version' ),
			'cart'  => (int) get_blog_option( $id, 'woocommerce_cart_page_id' ),
			'shop'  => (int) get_blog_option( $id, 'woocommerce_shop_page_id' ),
		);
	},
	'resilience_fatal'    => static function () {
		$file = WP_CONTENT_DIR . '/probe/fatal.log';
		$text = is_file( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array( 'size' => strlen( $text ), 'tail' => substr( $text, -600 ) );
	},
);
