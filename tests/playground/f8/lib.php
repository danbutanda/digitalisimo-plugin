<?php
/**
 * Funciones comunes de f8.sh (F8, frente J): las cargan la preparación (setup.php y cada `pN.php`) y la sonda
 * (probe.php). Sólo Playground; nunca forma parte de un plugin.
 *
 * Cada escenario prepara su sitio en `f8/pN.php`, que define `digi_f8_activate( array $mode )` (plugins y opciones, en
 * la primera petición) y `digi_f8_content( array $mode )` (documentos, en la segunda, ya con los plugins cargados), y
 * puede definir `digi_f8_ops(): array` (operaciones propias de la sonda: `op => callable( callable $arg ): array`).
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DIGI_F8_DIR' ) ) {
	define( 'DIGI_F8_DIR', WP_CONTENT_DIR . '/probe' );
}
if ( ! defined( 'DIGI_F8_FIXTURES' ) ) {
	define( 'DIGI_F8_FIXTURES', WP_CONTENT_DIR . '/digi-legacy-fixtures' );
}
if ( ! defined( 'DIGI_F8_SOURCES' ) ) {
	define( 'DIGI_F8_SOURCES', WP_CONTENT_DIR . '/digi-sources' );
}

/** Modo de la corrida (`mode.json`): escenario, token, red, WooCommerce, variantes. */
function digi_f8_mode() {
	static $mode = null;
	if ( null === $mode ) {
		$mode = json_decode( (string) @file_get_contents( DIGI_F8_DIR . '/mode.json' ), true ); // phpcs:ignore
		$mode = is_array( $mode ) ? $mode : array();
	}
	return $mode;
}

/** Anota un fallo de la preparación (el arnés se detiene si existe `setup-error.txt`). */
function digi_f8_fail( $message ) {
	file_put_contents( DIGI_F8_DIR . '/setup-error.txt', $message . "\n", FILE_APPEND ); // phpcs:ignore
}

/** Aviso de la preparación que no la detiene. */
function digi_f8_warn( $message ) {
	file_put_contents( DIGI_F8_DIR . '/setup-warnings.txt', $message . "\n", FILE_APPEND ); // phpcs:ignore
}

/** IDs de lo creado por la preparación de este sitio (`clave => ID`). */
function digi_f8_ids() {
	$ids = get_option( 'digi_f8_ids', array() );
	return is_array( $ids ) ? $ids : array();
}

function digi_f8_set_id( $key, $id ) {
	$ids         = digi_f8_ids();
	$ids[ $key ] = (int) $id;
	update_option( 'digi_f8_ids', $ids, false );
}

/** Casos de un fixture de `tests/legacy/<archivo>.json`. */
function digi_f8_fixture( $file ) {
	$path = DIGI_F8_FIXTURES . '/' . basename( $file ) . '.json';
	$data = is_file( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore
	return is_array( $data ) ? $data : array();
}

/**
 * Un caso de fixture listo para guardar: `{ type, settings, page, section, expect }`, sin las claves de control
 * (`_widget`, `_page`, `_expect*`, `_known`, `_section`, `_options`…).
 */
function digi_f8_case( $file, $case ) {
	$cases    = digi_f8_fixture( $file );
	$settings = (array) ( $cases[ $case ] ?? array() );
	$out      = array(
		'type'     => (string) ( $settings['_widget'] ?? $file ),
		'page'     => (array) ( $settings['_page'] ?? array() ),
		'section'  => (array) ( $settings['_section'] ?? array() ),
		'options'  => (array) ( $settings['_options'] ?? array() ),
		'settings' => array(),
	);
	foreach ( $settings as $key => $value ) {
		if ( '_' === substr( (string) $key, 0, 1 ) && ! in_array( $key, array( '_id', '__globals__', '__dynamic__' ), true ) && preg_match( '/^_(widget|page|section|options|known|expect|acf)/', (string) $key ) ) {
			continue;
		}
		$out['settings'][ $key ] = $value;
	}
	return $out;
}

/** ID de elemento estable de 7 caracteres a partir de una semilla. */
function digi_f8_eid( $seed ) {
	return substr( md5( (string) $seed ), 0, 7 );
}

/** Un widget dentro de un contenedor (o sección y columna con `$legacy`), en el formato que guarda el constructor. */
function digi_f8_wrap( $type, array $settings, $seed, array $container = array(), $legacy = false ) {
	$widget = array( 'id' => digi_f8_eid( 'w' . $seed ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings ? $settings : new stdClass(), 'elements' => array() );
	if ( $legacy ) {
		return array( 'id' => digi_f8_eid( 's' . $seed ), 'elType' => 'section', 'settings' => $container ? $container : new stdClass(), 'elements' => array( array( 'id' => digi_f8_eid( 'c' . $seed ), 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array( $widget ) ) ) );
	}
	return array( 'id' => digi_f8_eid( 'k' . $seed ), 'elType' => 'container', 'settings' => $container ? $container : new stdClass(), 'elements' => array( $widget ) );
}

/**
 * Documento del constructor escrito tal cual en las metas, como lo deja un sitio con el origen (sin pasar por el
 * guardado del constructor: así se conserva lo heredado). Devuelve el ID.
 */
function digi_f8_document( $slug, array $elements, array $args = array() ) {
	$args += array( 'post_type' => 'page', 'status' => 'publish', 'title' => $slug, 'template_type' => 'wp-page', 'page_settings' => array(), 'meta' => array(), 'parent' => 0 );
	$existing = get_page_by_path( $slug, OBJECT, $args['post_type'] );
	$id       = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => $args['post_type'], 'post_status' => $args['status'], 'post_title' => $args['title'], 'post_name' => $slug, 'post_parent' => (int) $args['parent'] ) );
	if ( is_wp_error( $id ) || ! $id ) {
		digi_f8_fail( 'No se pudo crear ' . $slug . ': ' . ( is_wp_error( $id ) ? $id->get_error_message() : '0' ) );
		return 0;
	}
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	if ( '' !== (string) $args['template_type'] ) {
		update_post_meta( $id, '_elementor_template_type', $args['template_type'] );
	}
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	if ( $args['page_settings'] ) {
		update_post_meta( $id, '_elementor_page_settings', $args['page_settings'] );
	}
	foreach ( $args['meta'] as $key => $value ) {
		update_post_meta( $id, $key, is_string( $value ) ? wp_slash( $value ) : $value );
	}
	digi_f8_set_id( $slug, $id );
	return (int) $id;
}

/** Activa un plugin (en la red con `$network`) y anota el fallo. */
function digi_f8_activate_plugin( $plugin, $network = false ) {
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
		digi_f8_fail( 'Falta el plugin montado ' . $plugin );
		return false;
	}
	$result = activate_plugin( $plugin, '', $network, true );
	if ( is_wp_error( $result ) ) {
		digi_f8_fail( 'No se pudo activar ' . $plugin . ': ' . $result->get_error_message() );
		return false;
	}
	return true;
}

/** Opciones del sitio que el origen deja con sus módulos y extensiones encendidos (como legacy-setup.php). */
function digi_f8_ep_modules_on( array $extensions = array() ) {
	$modules = array();
	foreach ( (array) glob( WP_PLUGIN_DIR . '/bdthemes-element-pack*/modules/*', GLOB_ONLYDIR ) as $dir ) {
		$modules[ basename( $dir ) ] = 'on';
	}
	foreach ( $extensions as $ext ) {
		$modules[ $ext ] = 'on';
	}
	foreach ( array( 'element_pack_active_modules', 'element_pack_elementor_extend', 'element_pack_third_party_widget' ) as $option ) {
		update_option( $option, $modules );
	}
}
