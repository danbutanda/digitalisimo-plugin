<?php
/**
 * Sonda del ensayo de la tanda B de Elements (mu-plugin que copia setup.php). Sin red hacia fuera y sin correo.
 * `?digi_tb=<paso>&token=<token>`:
 * - `ping`; `users` (contraseña de aplicación de la administradora en /digi-probe/secrets.json);
 * - `seed`: un producto publicado, la cabecera global (un Encabezado con «Título de la página» en H1), el pie global
 *   (un contenedor con ocho H2 y mucho texto que incrusta otra plantilla), una página «Inicio» y una página vacía del
 *   constructor para el MCP; condiciones «todo el sitio» con el gestor del Theme Builder;
 * - `optin&tag=p`: guarda el Encabezado de la cabecera con «Etiqueta en las fichas de producto» (Document::save);
 * - `state`: lo que guardó `Global_Weight`, si su evento está programado y los IDs y enlaces del ensayo;
 * - `cron`: corre el evento de `Global_Weight` como lo haría WP-Cron (el blueprint apaga WP-Cron);
 * - `publish&id=<id>`: publica la página del MCP.
 * Los avisos de PHP de los módulos propios van a /digi-probe/php-errors.log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_TB_DIR = '/digi-probe';

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_TB_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		if ( false !== strpos( (string) $file, 'modules/digitalisimo-' ) || false !== strpos( (string) $file, 'class-content-analysis' ) ) {
			file_put_contents( DIGI_TB_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file:$line\n", FILE_APPEND );
		}
		return false;
	}
);
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $host !== $home && '127.0.0.1' !== $host && 'localhost' !== $host ? new WP_Error( 'digi_tb_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

function digi_tb_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}

function digi_tb_heading( string $id, array $settings ): array {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => $settings, 'elements' => array() );
}

function digi_tb_container( string $id, array $children ): array {
	return array( 'id' => $id, 'elType' => 'container', 'settings' => array(), 'elements' => $children, 'isInner' => false );
}

/** El árbol de la cabecera: el Encabezado con el título de la página en H1 y, si se pide, la etiqueta de las fichas. */
function digi_tb_header_tree( string $tag = '' ): array {
	$settings = array(
		'title'       => '',
		'__dynamic__' => array( 'title' => '[elementor-tag id="a09ec62" name="page-title" settings="%7B%7D"]' ),
		'header_size' => 'h1',
	);
	if ( '' !== $tag ) {
		$settings['digitalisimo_product_tag'] = $tag;
	}
	return array( digi_tb_container( 'c0ffee1', array( digi_tb_heading( 'h0ffee1', $settings ), digi_tb_heading( 'h0ffee2', array( 'title' => 'Inicio · Contacto', 'header_size' => 'div' ) ) ) ) );
}

function digi_tb_template( string $title, string $type, array $elements ): int {
	$document = \Elementor\Plugin::$instance->documents->create( $type, array( 'post_title' => $title, 'post_status' => 'publish' ) );
	if ( is_wp_error( $document ) || ! $document ) {
		throw new RuntimeException( 'No se creó la plantilla ' . $title );
	}
	$document->save( array( 'elements' => $elements, 'settings' => array( 'post_status' => 'publish' ) ) );
	return (int) $document->get_main_id();
}

function digi_tb_ids(): array {
	$ids = get_option( 'digi_tb_ids', array() );
	return is_array( $ids ) ? $ids : array();
}

add_action(
	'wp_loaded',
	static function () {
		if ( ! isset( $_GET['digi_tb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_TB_DIR . '/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! isset( $_GET['token'] ) || ! hash_equals( $token, (string) $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
			digi_tb_out( array( 'error' => 'token' ), 403 );
		}
		$step  = sanitize_key( wp_unslash( $_GET['digi_tb'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$admin = get_user_by( 'login', 'admin' );
		try {
			switch ( $step ) {
				case 'ping':
					digi_tb_out( array( 'ready' => true, 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'wc' => defined( 'WC_VERSION' ) ? WC_VERSION : '', 'elements' => defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : '' ) );
					break;
				case 'users':
					require_once ABSPATH . 'wp-admin/includes/user.php';
					$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-tb' ) );
					if ( is_wp_error( $created ) ) {
						digi_tb_out( array( 'error' => $created->get_error_message() ), 500 );
					}
					file_put_contents( DIGI_TB_DIR . '/secrets.json', wp_json_encode( array( 'password:admin' => $created[0] ) ) );
					digi_tb_out( array( 'users' => array( 'admin' => $admin->ID ) ) );
					break;
				case 'seed':
					wp_set_current_user( $admin->ID );
					$product = new WC_Product_Simple();
					$product->set_name( 'Web Hosting Starter' );
					$product->set_status( 'publish' );
					$product->set_regular_price( '97' );
					$product->set_description( 'Hosting con correos ilimitados.' );
					$product_id = (int) $product->save();
					$embedded   = digi_tb_template( 'Presentación', \Elementor\Plugin::$instance->documents->get_document_type( 'container', false ) ? 'container' : 'section', array( digi_tb_container( 'e0ffee1', array( digi_tb_heading( 'e0ffee2', array( 'title' => 'Hola, te saluda Daniel', 'header_size' => 'h2' ) ) ) ) ) );
					$footer_children = array();
					for ( $i = 1; $i <= 7; $i++ ) {
						$footer_children[] = digi_tb_heading( 'f' . $i . 'aaaaa', array( 'title' => 'Servicio ' . $i, 'header_size' => 'h2' ) );
						$footer_children[] = array( 'id' => 'f' . $i . 'bbbbb', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>' . str_repeat( 'Texto de la portada que se repite. ', 40 ) . '</p>' ), 'elements' => array() );
					}
					$footer_children[] = array( 'id' => 'f9ccccc', 'elType' => 'widget', 'widgetType' => 'digitalisimo-template', 'settings' => array( 'template_id' => (string) $embedded ), 'elements' => array() );
					$header = digi_tb_template( 'Cabecera del ensayo', 'header', digi_tb_header_tree() );
					$footer = digi_tb_template( 'Pie del ensayo', 'footer', array( digi_tb_container( 'f0ffee1', $footer_children ) ) );
					$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
					foreach ( array( $header, $footer ) as $template ) {
						$manager->save_conditions( $template, array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ) ) );
					}
					$manager->get_cache()->regenerate();
					$home = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio del ensayo', 'post_content' => '<p>Bienvenida.</p>' ) );
					$mcp  = \Elementor\Plugin::$instance->documents->create( 'wp-page', array( 'post_title' => 'Clientes', 'post_status' => 'draft' ) );
					$mcp->save( array( 'elements' => array(), 'settings' => array( 'post_status' => 'draft' ) ) );
					update_option(
						'digi_tb_ids',
						array(
							'product'  => $product_id,
							'header'   => $header,
							'footer'   => $footer,
							'embedded' => $embedded,
							'home'     => $home,
							'mcp'      => (int) $mcp->get_main_id(),
						),
						false
					);
					digi_tb_out( array( 'ids' => digi_tb_ids(), 'scheduled' => (bool) wp_next_scheduled( 'digitalisimo_elements_global_weight' ) ) );
					break;
				case 'optin':
					wp_set_current_user( $admin->ID );
					$ids      = digi_tb_ids();
					$tag      = isset( $_GET['tag'] ) ? sanitize_key( wp_unslash( $_GET['tag'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$document = \Elementor\Plugin::$instance->documents->get( (int) $ids['header'], false );
					$document->save( array( 'elements' => digi_tb_header_tree( $tag ), 'settings' => array( 'post_status' => 'publish' ) ) );
					digi_tb_out( array( 'saved' => $tag ) );
					break;
				case 'cron':
					$scheduled = (bool) wp_next_scheduled( 'digitalisimo_elements_global_weight' );
					wp_clear_scheduled_hook( 'digitalisimo_elements_global_weight' );
					do_action( 'digitalisimo_elements_global_weight' );
					digi_tb_out( array( 'was_scheduled' => $scheduled, 'data' => get_option( 'digitalisimo_elements_global_weight' ) ) );
					break;
				case 'state':
					$autoload = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", 'digitalisimo_elements_global_weight' ) );
					$ids      = digi_tb_ids();
					digi_tb_out(
						array(
							'ids'       => $ids,
							'urls'      => array(
								'product' => $ids ? get_permalink( (int) $ids['product'] ) : '',
								'home'    => $ids ? get_permalink( (int) $ids['home'] ) : '',
								'mcp'     => $ids ? get_permalink( (int) $ids['mcp'] ) : '',
							),
							'scheduled' => (bool) wp_next_scheduled( 'digitalisimo_elements_global_weight' ),
							'data'      => get_option( 'digitalisimo_elements_global_weight' ),
							'autoload'  => $autoload,
							'atomic'    => class_exists( '\Digitalisimo\Elements\Atomic_Marquee', false ),
						)
					);
					break;
				case 'publish':
					$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
					digi_tb_out( array( 'url' => get_permalink( $id ), 'data' => (string) get_post_meta( $id, '_elementor_data', true ) ) );
					break;
			}
		} catch ( Throwable $e ) {
			digi_tb_out( array( 'error' => $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() ), 500 );
		}
		digi_tb_out( array( 'error' => 'paso desconocido' ), 400 );
	}
);
