<?php
/**
 * Kits (etapa K): pantalla «Kits», sus acciones, el informe y las opciones de WP-CLI (§12, §13.1 «actions», CV-21).
 *
 * Sin WordPress ni el constructor: stubs mínimos en memoria (opciones por sitio, entradas, metas, nonces y
 * capacidades). El importador (`Session`, `Runners\Site_Settings`) y la procedencia (`Parts\Origin`) se simulan con
 * su contrato: aquí se prueba la interfaz, no su lógica (que prueban `elements-kit-session.php` y
 * `elements-kit-settings.php`). Comprueba:
 * - nonce ausente, de otra acción, de otra sesión o de otro sitio de la red → 403, sin cambios;
 * - sesión de otro sitio de la red, revertida o sin terminar → rechazo, en todas las acciones;
 * - «Publicar páginas y plantillas»: sólo lo visible que creó la sesión, nunca entradas ajenas, el Theme Builder, los
 *   popups, los botones flotantes, los fragmentos, lo conservado ni lo pendiente de revisar sin cambios;
 *   `publish_post` por entrada; las plantillas de la biblioteca sí (la consulta no usa `any`);
 * - «Aplicar los ajustes del kit» dos veces; deshacer tras editar el kit → no pisa y lo explica;
 * - subida con «nuevo» en un sitio no vacío → rechazo con los motivos de D1, sin preparar nada; en uno vacío → token;
 * - revertir: sólo la última, con confirmación;
 * - el informe (secciones, textos, CR-18 y direcciones del proveedor) y la pantalla (escapado, sin acciones sobre una
 *   sesión revertida, casilla «nuevo» deshabilitada con sus motivos, sin enlaces fuera del sitio);
 * - WP-CLI: validación de opciones y el informe de `inspect` sin nombres del constructor original.
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $app;
	}
}

namespace Digitalisimo\Elements\Import\Kits {
	/** Contrato del importador (frente A) que usa la interfaz. */
	class Session {
		public static $calls  = array();
		public static $empty  = array( 'empty' => true, 'reasons' => array() );
		public static $fail   = '';
		public static $error  = '';
		public static $hashes = array();
		public static function prepare( $zip, $args ) {
			self::$calls[] = array( 'prepare', $zip, $args['mode'], $args['surface'] );
			if ( '' !== self::$fail ) {
				throw new \RuntimeException( self::$fail );
			}
			return array( 'token' => str_repeat( 'ab', 32 ), 'mode' => $args['mode'], 'error' => self::$error );
		}
		public static function remember_token( $pending ) {
			self::$calls[] = array( 'remember', $pending['token'] );
		}
		public static function delete_tmp( $token ) {
			self::$calls[] = array( 'delete', $token );
			return true;
		}
		public static function app_url( $token ) {
			return 'https://b.test/wp-admin/admin.php?page=elementor-app#/import-customization?referrer=digitalisimo&id=' . $token;
		}
		public static function site_is_empty() {
			return self::$empty;
		}
		public static function update( $session, $changes ) {
			$name   = 'digitalisimo_elements_kit_session_' . $session;
			$record = array_replace( (array) get_option( $name, array() ), $changes );
			update_option( $name, $record );
			return $record;
		}
		/** Como el del frente A: tipos visibles y plantillas que no son del Theme Builder. */
		public static function publishable( $id ) {
			$type = get_post_type( $id );
			if ( ! $type || in_array( $type, array( 'elementor_snippet', 'e-floating-buttons', 'attachment', 'nav_menu_item' ), true ) ) {
				return false;
			}
			if ( 'elementor_library' === $type ) {
				return ! in_array( get_post_meta( $id, '_elementor_template_type', true ), array( 'header', 'footer', 'popup', 'single-page' ), true );
			}
			return is_post_type_viewable( $type );
		}
		public static function local_hash( $id ) {
			return self::$hashes[ $id ] ?? '';
		}
	}

	/** Las subidas de la prueba no pasan por PHP: se declaran aquí. */
	function is_uploaded_file( $file ) {
		return ! empty( $GLOBALS['kt']['uploaded'][ $file ] );
	}
}

namespace Digitalisimo\Elements\Import\Kits\Runners {
	/** «Aplicar» y «Deshacer» del frente A, con su estado en `backup.applied_hash`. */
	class Site_Settings {
		public static $calls   = array();
		public static $changed = false;
		public static function apply( $session ) {
			self::$calls[] = 'apply';
			$record        = get_option( 'digitalisimo_elements_kit_session_' . $session );
			$record['backup']['applied_hash'] = 'h-aplicado';
			\Digitalisimo\Elements\Import\Kits\Session::update( $session, array( 'backup' => $record['backup'], 'applied' => 1700000000 ) );
			return true;
		}
		public static function unapply( $session ) {
			self::$calls[] = 'unapply';
			if ( self::$changed ) {
				return new \WP_Error( 'digitalisimo_kit_unapply', 'El kit cambió después de aplicar sus ajustes: no se deshace para no perder esos cambios.' );
			}
			$record = get_option( 'digitalisimo_elements_kit_session_' . $session );
			unset( $record['backup']['applied_hash'] );
			\Digitalisimo\Elements\Import\Kits\Session::update( $session, array( 'backup' => $record['backup'], 'applied' => 0 ) );
			return true;
		}
	}
}

namespace Digitalisimo\Elements\Import\Kits\Parts {
	/** Huella local de la parte `origin` (frente B): otra fórmula que la del importador. */
	class Origin {
		public static $hashes = array();
		public static function local_hash( $post_id, $object = 'post' ) {
			return self::$hashes[ $post_id ] ?? '';
		}
	}
}

namespace {
	define( 'ABSPATH', '/srv/sitio/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.999' );

	$vendor_host = 'element' . 'or.com';

	/** Dominios del proveedor (como `digitalisimo-vendor-free.php`, que la prueba no carga). */
	final class Digitalisimo_Vendor_Free {
		const HOSTS = array( 'element' . 'or.com' );
		public static function vendor_url( $url ) {
			$host = strtolower( (string) parse_url( (string) $url, PHP_URL_HOST ) );
			return '' !== $host && ( 'element' . 'or.com' === $host || substr( $host, -14 ) === '.element' . 'or.com' );
		}
	}

	class WP_Error {
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
	}

	/** Base de datos en memoria: sólo la consulta de entradas creadas por sesión de `Report`. */
	final class Kt_Wpdb {
		public $posts    = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public function prepare( $sql, ...$args ) {
			return array(
				'sql'  => $sql,
				'args' => 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args,
			);
		}
		public function get_results( $query, $output = null ) {
			if ( false === strpos( $query['sql'], 'SUM( p.post_status' ) ) {
				throw new RuntimeException( 'Consulta no prevista: ' . $query['sql'] );
			}
			$sessions = array_slice( $query['args'], 1 );
			$out      = array();
			foreach ( kt_posts() as $post ) {
				$session = $post['meta']['_elementor_import_session_id'] ?? '';
				if ( 'revision' !== $post['post_type'] && in_array( $session, $sessions, true ) ) {
					$out[ $session ] = $out[ $session ] ?? array( 'session' => $session, 'live' => 0, 'trash' => 0 );
					++$out[ $session ][ 'trash' === $post['post_status'] ? 'trash' : 'live' ];
				}
			}
			return array_values( $out );
		}
	}
	$GLOBALS['wpdb'] = new Kt_Wpdb();

	final class Kt_Module {
		public $reverts = 0;
		public function revert_last_imported_kit() {
			++$this->reverts;
			return array( 'revert_completed' => true );
		}
	}
	final class Kt_App {
		public $module;
		public function __construct() {
			$this->module = new Kt_Module();
		}
		public function get_component( $name ) {
			return 'import-export-customization' === $name ? $this->module : null;
		}
	}

	function kt_reset() {
		$GLOBALS['kt'] = array(
			'blog'      => 1,
			'user'      => 1,
			'caps'      => array( 'manage_options', 'edit_theme_options' ),
			'deny_post' => array(),
			'options'   => array( 1 => array(), 2 => array() ),
			'posts'     => array( 1 => array(), 2 => array() ),
			'uploaded'  => array(),
			'actions'   => array(),
			'transient' => array(),
		);
		$_POST   = array();
		$_GET    = array();
		$_FILES  = array();
		$_SERVER = array( 'REQUEST_METHOD' => 'POST' );
		\Digitalisimo\Elements\Import\Kits\Session::$calls   = array();
		\Digitalisimo\Elements\Import\Kits\Session::$empty   = array( 'empty' => true, 'reasons' => array() );
		\Digitalisimo\Elements\Import\Kits\Session::$fail    = '';
		\Digitalisimo\Elements\Import\Kits\Session::$error   = '';
		\Digitalisimo\Elements\Import\Kits\Session::$hashes  = array();
		\Digitalisimo\Elements\Import\Kits\Parts\Origin::$hashes = array();
		\Digitalisimo\Elements\Import\Kits\Runners\Site_Settings::$calls   = array();
		\Digitalisimo\Elements\Import\Kits\Runners\Site_Settings::$changed = false;
		\Elementor\Plugin::$instance      = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->app = new Kt_App();
	}
	function &kt_posts() {
		return $GLOBALS['kt']['posts'][ $GLOBALS['kt']['blog'] ];
	}
	function kt_post( $id, $type, $status, $title = '', array $meta = array() ) {
		$posts        = &kt_posts();
		$posts[ $id ] = array(
			'ID'          => $id,
			'post_type'   => $type,
			'post_status' => $status,
			'post_title'  => $title,
			'meta'        => $meta,
		);
	}

	// Stubs de WordPress.
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['kt']['actions'][] = array( $hook, $callback, $priority );
	}
	function apply_filters( $hook, $value ) {
		return $value;
	}
	function _doing_it_wrong( $function, $message, $version ) {
		throw new RuntimeException( 'doing_it_wrong: ' . $message );
	}
	function is_admin() {
		return true;
	}
	function is_network_admin() {
		return false;
	}
	function get_current_blog_id() {
		return $GLOBALS['kt']['blog'];
	}
	function get_current_user_id() {
		return $GLOBALS['kt']['user'];
	}
	function current_user_can( $cap, ...$args ) {
		if ( 'publish_post' === $cap ) {
			return ! in_array( (int) $args[0], $GLOBALS['kt']['deny_post'], true );
		}
		return 'edit_post' === $cap || in_array( $cap, $GLOBALS['kt']['caps'], true );
	}
	function get_option( $name, $default = false ) {
		$options = $GLOBALS['kt']['options'][ $GLOBALS['kt']['blog'] ];
		return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['kt']['options'][ $GLOBALS['kt']['blog'] ][ $name ] = $value;
		return true;
	}
	function set_transient( $key, $value, $ttl ) {
		$GLOBALS['kt']['transient'][ $key ] = $value;
		return true;
	}
	function get_transient( $key ) {
		return $GLOBALS['kt']['transient'][ $key ] ?? false;
	}
	function delete_transient( $key ) {
		unset( $GLOBALS['kt']['transient'][ $key ] );
	}
	function wp_create_nonce( $action ) {
		return 'n' . substr( md5( $action . '|' . $GLOBALS['kt']['user'] ), 0, 10 );
	}
	function wp_verify_nonce( $nonce, $action ) {
		return hash_equals( wp_create_nonce( $action ), (string) $nonce ) ? 1 : false;
	}
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$field = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '">';
		if ( $echo ) {
			echo $field; // phpcs:ignore
		}
		return $field;
	}
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return esc_html( $text );
	}
	function esc_url( $url ) {
		return esc_html( $url );
	}
	function admin_url( $path = '' ) {
		return 'https://b.test/wp-admin/' . ltrim( $path, '/' );
	}
	function add_query_arg( $args, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
	}
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
	function size_format( $bytes ) {
		return $bytes . ' B';
	}
	function wp_max_upload_size() {
		return 2097152;
	}
	function date_i18n( $format, $timestamp ) {
		return gmdate( 'Y-m-d H:i', (int) $timestamp );
	}
	function wp_date( $format, $timestamp ) {
		return gmdate( 'Y-m-d H:i', (int) $timestamp );
	}
	function wp_die( $message = '' ) {
		throw new RuntimeException( 'wp_die: ' . $message );
	}
	function get_post_types( $args = array() ) {
		$all = array( 'post', 'page', 'e-landing-page', 'attachment', 'elementor_library', 'revision', 'nav_menu_item', 'elementor_snippet', 'elementor_font', 'e-floating-buttons' );
		return array_combine( $all, $all );
	}
	function is_post_type_viewable( $type ) {
		return in_array( $type, array( 'post', 'page', 'e-landing-page', 'elementor_library', 'e-floating-buttons' ), true );
	}
	function get_posts( $args ) {
		if ( ! is_array( $args['post_type'] ) ) {
			throw new RuntimeException( 'La consulta debe nombrar los tipos: «any» deja fuera la biblioteca de plantillas.' );
		}
		$out = array();
		foreach ( kt_posts() as $post ) {
			if ( ! in_array( $post['post_type'], $args['post_type'], true ) || $post['post_status'] !== $args['post_status'] ) {
				continue;
			}
			foreach ( $args['meta_query'] as $clause ) {
				if ( ( $post['meta'][ $clause['key'] ] ?? null ) !== $clause['value'] ) {
					continue 2;
				}
			}
			$out[] = $post['ID'];
		}
		sort( $out );
		return $out;
	}
	function get_post_type( $id ) {
		return kt_posts()[ $id ]['post_type'] ?? false;
	}
	function get_post( $id ) {
		$post = kt_posts()[ $id ] ?? null;
		return $post ? (object) $post : null;
	}
	function get_post_meta( $id, $key, $single = false ) {
		return kt_posts()[ $id ]['meta'][ $key ] ?? '';
	}
	function wp_update_post( $data, $wp_error = false ) {
		$posts                               = &kt_posts();
		$posts[ $data['ID'] ]['post_status'] = $data['post_status'];
		return $data['ID'];
	}
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
	function get_edit_post_link( $id, $context = 'display' ) {
		return admin_url( 'post.php?post=' . $id . '&action=edit' );
	}

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	};

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Cli;
	use Digitalisimo\Elements\Import\Kits\Report;
	use Digitalisimo\Elements\Import\Kits\Report_Page;
	use Digitalisimo\Elements\Import\Kits\Session;
	use Digitalisimo\Elements\Import\Kits\Parts\Origin;
	use Digitalisimo\Elements\Import\Kits\Runners\Site_Settings;

	const S1 = '6612f0a1b2c3d4.10000001';
	const S2 = '6612f0a1b2c3d4.10000002';
	const S3 = '6612f0a1b2c3d4.10000003';
	const SO = '6612f0a1b2c3d4.20000009';

	/** Sitio con tres sesiones: S1 revertida, S2 terminada (la anterior) y S3 terminada (la última), en «agregar». */
	$scenario = function () {
		kt_reset();
		update_option(
			'elementor_import_sessions',
			array(
				S2 => array( 'session_id' => S2, 'kit_title' => 'Kit anterior', 'start_timestamp' => 1000, 'end_timestamp' => 1001, 'runners' => array() ),
				S3 => array( 'session_id' => S3, 'kit_title' => 'Kit <script>alert(1)</script>', 'start_timestamp' => 2000, 'end_timestamp' => 2001, 'runners' => array() ),
			)
		);
		update_option( 'elementor_revert_sessions', array( array( 'session_id' => S1, 'kit_title' => 'Kit revertido', 'import_timestamp' => 500, 'revert_timestamp' => 600 ) ) );
		$base = array(
			'v'           => 1,
			'status'      => 'imported',
			'mode'        => Module::MODE_ADD,
			'requested'   => Module::MODE_ADD,
			'format'      => '3.0',
			'user'        => 1,
			'time'        => 2000,
			'origin'      => array( 'format' => '3.0', 'kit' => str_repeat( 'a', 40 ), 'home' => 'https://origen.test', 'site' => 'https://origen.test', 'uploads' => 'https://origen.test/wp-content/uploads' ),
			'max_post_id' => 100,
			'kit_id'      => 7,
			'backup'      => array( 'kit_settings' => array( 'custom_css' => '.x{}' ) ),
			'report'      => array(),
		);
		update_option( 'digitalisimo_elements_kit_session_' . S3, $base );
		update_option( 'digitalisimo_elements_kit_session_' . S2, $base );
		update_option( 'digitalisimo_elements_kit_session_' . S1, array_replace( $base, array( 'status' => 'reverted' ) ) );
	};
	$post = function ( $action, $session, array $extra = array(), $nonce_session = null, $nonce_blog = null ) {
		$blog                  = $GLOBALS['kt']['blog'];
		$GLOBALS['kt']['blog'] = null === $nonce_blog ? $blog : $nonce_blog;
		$nonce                 = wp_create_nonce( Report_Page::nonce_action( $action, null === $nonce_session ? $session : $nonce_session ) );
		$GLOBALS['kt']['blog'] = $blog;
		$_POST                 = array_merge( array( 'action' => $action, 'session' => $session, '_wpnonce' => $nonce ), $extra );
	};

	// 1. Informe: secciones, textos y forma.
	kt_reset();
	$cases = array(
		'files.rejected.path'  => 'media',
		'note:form'            => 'configure',
		'map-conflict'         => 'unresolved',
		'review'               => 'review',
		'unconverted'          => 'preserved',
		'origin-url'           => 'urls',
		'media.embedded'       => 'media',
		'settings.not_applied' => 'settings',
		'trash'                => 'trash',
		'plugins'              => 'requested',
		'mediaxyz'             => 'other',
	);
	foreach ( $cases as $code => $section ) {
		$check( Report::section_for( $code ) === $section, "El código {$code} va a la sección {$section} (" . Report::section_for( $code ) . ').' );
	}
	$check( 'media' === Report::section_for( 'otro-codigo', 'files' ) && 'other' === Report::section_for( 'otro-codigo', 'theme-builder' ), 'Un código sin prefijo conocido va a la sección de su parte, o a «Otros avisos».' );
	$check( 'Archivo no incluido' === Report::code_label( 'files.rejected.size' ) && 'Configurar después' === Report::code_label( 'note:otro' ) && '' === Report::code_label( 'zzz' ), 'Los textos se buscan por código y por prefijo.' );
	$normal = Report::normalize( array( 'entries' => array( array( 'code' => 'Mal Código', 'level' => 'info' ), array( 'code' => 'review', 'level' => 'raro', 'data' => 'x' ), 'x' ), 'counts' => array( 'media.embedded' => '3', 'BAD' => 1 ), 'dropped' => -4 ) );
	$check( 1 === count( $normal['entries'] ) && 'warning' === $normal['entries'][0]['level'] && array() === $normal['entries'][0]['data'] && array( 'media.embedded' => 3 ) === $normal['counts'] && 0 === $normal['dropped'], 'El informe se normaliza: códigos inválidos fuera, nivel desconocido = aviso, datos siempre array.' );
	$check( array() === Report::normalize( 'basura' )['entries'], 'Un informe que no es array queda vacío.' );
	$check( '' === Report::option_name( '../x' ) && 'digitalisimo_elements_kit_session_' . S1 === Report::option_name( S1 ), 'Sólo un ID de sesión válido da nombre de opción.' );
	foreach ( array( '3.0' => 'Kit 3.0', '2.x' => 'Kit en formato 1.x o 2.x', 'envato' => 'Template Kit', '' => 'Kit' ) as $format => $label ) {
		$check( Report::format_label( $format ) === $label, "Formato {$format}: {$label}." );
	}
	$check( false !== strpos( Report::mode_reason( array( 'mode' => 'add', 'format' => 'envato' ) ), 'Template Kit' ) && false !== strpos( Report::mode_reason( array( 'mode' => 'fresh' ) ), 'vacío' ), 'El resumen explica el modo y su porqué.' );

	// CR-18: la familia del constructor nunca se nombra; tampoco las direcciones del proveedor.
	$check( Report::is_builder_plugin( array( 'name' => 'Elementor Pro', 'plugin' => 'elementor-pro/elementor-pro' ) ) && Report::is_builder_plugin( 'PRO Elements' ) && Report::is_builder_plugin( array( 'name' => 'Algo', 'plugin' => 'x/x', 'pluginUri' => 'https://www.' . $vendor_host . '/pro/' ) ) && ! Report::is_builder_plugin( array( 'name' => 'Acme Forms', 'plugin' => 'acme-forms/acme' ) ), 'La familia del constructor se reconoce por nombre, carpeta o dominio.' );
	$check( Report::FAMILY_LABEL === Report::plugin_label( array( 'name' => 'Elementor', 'plugin' => 'elementor/elementor' ) ) && 'Acme Forms' === Report::plugin_label( array( 'name' => '<b>Acme Forms</b>' ) ), 'Un plugin de la familia se muestra como «Funciones incluidas en DIGITALÍSIMO Elements».' );
	$requested = Report::requested( array( 'requests' => array( 'theme' => true, 'experiments' => array( 'container' => 'active', 'Mal Nombre' => '' ), 'plugins' => array( 'Acme Forms' ), 'builder_plugins' => 3 ) ) );
	$check( true === $requested['theme'] && array( 'container', 'malnombre' ) === $requested['experiments'] && array( 'Acme Forms', Report::FAMILY_LABEL ) === $requested['plugins'], 'Pedidos del kit: tema sin nombre, experimentos y plugins sin la familia: ' . json_encode( $requested, JSON_UNESCAPED_UNICODE ) );
	$lines = Report::analysis_lines(
		array(
			'stage'      => array( 'embedded' => 2, 'bytes' => 10, 'svg_sanitized' => 1, 'rejected' => array( 'sha256' => 1 ) ),
			'downloaded' => 5,
			'analysis'   => array( 'hosts' => array( 'cdn.' . $vendor_host => 2, 'demo.test' => 1 ), 'blocked_types' => array( 'svg' => 1 ), 'missing_post_types' => array( 'recipe' => 3 ), 'needs' => array( 'acf' => array( 'field_1' ) ) ),
		)
	);
	$media = implode( ' | ', $lines['media'] );
	$check( false !== strpos( $media, 'comprobados: 2 (10 B)' ) && false !== strpos( $media, 'SVG saneados: 1' ) && false !== strpos( $media, 'contenido distinto del declarado (1)' ) && false !== strpos( $media, 'Descargado durante la importación: 5 B' ) && false !== strpos( $media, 'svg (1)' ), 'Medios: preparados, saneados, descartados, descargados y tipos bloqueados: ' . $media );
	$check( false !== strpos( implode( ' ', $lines['urls'] ), 'demo.test (1)' ) && false === stripos( json_encode( $lines ), $vendor_host ) && false !== strpos( implode( ' ', $lines['urls'] ), 'proveedor del constructor original: 2' ), 'Dominios externos sin enlace; los del proveedor sólo se cuentan.' );
	$check( false !== strpos( implode( ' ', $lines['preserved'] ), 'recipe (3)' ) && false !== strpos( implode( ' ', $lines['configure'] ), 'ACF' ), 'Contenido de tipos que el sitio no tiene y campos por configurar.' );
	$scrubbed = Report::scrub( 'Ver https://go.' . $vendor_host . '/kit?x=1 y ' . $vendor_host . '/a, no https://otro.test/' . $vendor_host . 'x' );
	$check( false === stripos( $scrubbed, $vendor_host . '/' ) && false === stripos( $scrubbed, 'go.' . $vendor_host ) && false !== strpos( $scrubbed, '[dirección del proveedor]' ) && false !== strpos( $scrubbed, 'https://otro.test/' ), 'Las direcciones del proveedor se quitan del texto del informe: ' . $scrubbed );

	// 2. D1: la regla es la del importador; sin él, nunca «nuevo».
	kt_reset();
	$check( array() === Report::fresh_blockers(), 'Un sitio vacío no tiene motivos en contra.' );
	Session::$empty = array( 'empty' => false, 'reasons' => array( 'El sitio ya tiene contenido.', 'El sitio ya tiene menús.' ) );
	$check( array( 'El sitio ya tiene contenido.', 'El sitio ya tiene menús.' ) === Report::fresh_blockers(), 'Los motivos son los de Session::site_is_empty().' );
	Session::$empty = array( 'empty' => false );
	$check( array( 'El sitio no está vacío.' ) === Report::fresh_blockers(), 'Sin motivos pero no vacío: no se ofrece «nuevo».' );

	// 3. Subida: «nuevo» sólo en un sitio vacío (D1); sin la casilla, «sólo agregar».
	$upload = function ( array $extra = array(), $file = true ) {
		$_POST  = array_merge( array( '_wpnonce' => wp_create_nonce( Report_Page::nonce_action( Module::ACTION_UPLOAD, '' ) ) ), $extra );
		$_FILES = array();
		if ( $file ) {
			$_FILES[ Report_Page::FILE_FIELD ]          = array( 'name' => 'kit.zip', 'tmp_name' => '/tmp/php-subida', 'error' => UPLOAD_ERR_OK, 'size' => 1024 );
			$GLOBALS['kt']['uploaded']['/tmp/php-subida'] = true;
		}
		return Report_Page::process_upload();
	};
	kt_reset();
	Session::$empty = array( 'empty' => false, 'reasons' => array( 'El sitio ya tiene contenido.' ) );
	$result         = $upload( array( 'fresh' => '1' ) );
	$check( 'not-empty' === $result['notice'] && array( 'El sitio ya tiene contenido.' ) === $result['flash'] && array() === Session::$calls, '«nuevo» en un sitio con contenido se rechaza con sus motivos y no prepara nada.' );
	$result = $upload();
	$token  = str_repeat( 'ab', 32 );
	$check( array( array( 'prepare', '/tmp/php-subida', Module::MODE_ADD, 'screen' ), array( 'remember', $token ) ) === Session::$calls && Session::app_url( $token ) === $result['redirect'], 'Sin la casilla, el kit se prepara en «sólo agregar», el token se guarda y la subida lleva a la App.' );
	kt_reset();
	$result = $upload( array( 'fresh' => '1' ) );
	$check( isset( $result['redirect'] ) && Module::MODE_FRESH === Session::$calls[0][2], 'En un sitio vacío, «nuevo» llega al importador.' );
	kt_reset();
	Session::$error = 'Un Template Kit de Envato se importa siempre en «sólo agregar».';
	$result         = $upload( array( 'fresh' => '1' ) );
	$check( 'not-empty' === $result['notice'] && array( 'delete', $token ) === Session::$calls[1] && 2 === count( Session::$calls ) && false !== strpos( $result['flash'][0], 'Template Kit' ), 'Si el importador no admite «nuevo» (Template Kit), borra lo preparado, no guarda el token y lo explica.' );
	kt_reset();
	Session::$fail = 'El kit trae un archivo no admitido: /srv/sitio/wp-content/uploads/x.php';
	$result        = $upload();
	$check( 'upload-failed' === $result['notice'] && false === strpos( $result['flash'][0], '/srv/sitio/' ) && false !== strpos( $result['flash'][0], 'no admitido' ), 'Un kit rechazado muestra el motivo del guardián sin rutas del servidor: ' . $result['flash'][0] );
	kt_reset();
	$check( 'upload-missing' === $upload( array(), false )['notice'], 'Sin archivo, se pide.' );
	$_FILES[ Report_Page::FILE_FIELD ] = array( 'name' => 'kit.zip', 'tmp_name' => '/etc/passwd', 'error' => UPLOAD_ERR_OK, 'size' => 10 );
	$_POST                             = array( '_wpnonce' => wp_create_nonce( Report_Page::nonce_action( Module::ACTION_UPLOAD, '' ) ) );
	$check( 'upload-error' === Report_Page::process_upload()['notice'], 'Un archivo que no llegó por la subida de PHP se rechaza.' );
	$_FILES[ Report_Page::FILE_FIELD ]            = array( 'name' => 'kit.php', 'tmp_name' => '/tmp/php-subida', 'error' => UPLOAD_ERR_OK, 'size' => 10 );
	$GLOBALS['kt']['uploaded']['/tmp/php-subida'] = true;
	$check( 'upload-type' === Report_Page::process_upload()['notice'] && array() === Session::$calls, 'Sólo se aceptan ZIP.' );
	$_POST = array( '_wpnonce' => 'nfalso' );
	$check( 403 === Report_Page::process_upload()['status'], 'La subida sin un nonce válido devuelve 403.' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_POST                     = array( '_wpnonce' => wp_create_nonce( Report_Page::nonce_action( Module::ACTION_UPLOAD, '' ) ) );
	$check( 'method' === Report_Page::process_upload()['notice'], 'Las acciones sólo se aceptan por POST.' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$GLOBALS['kt']['caps']     = array();
	$check( 403 === Report_Page::process_upload()['status'] && 'forbidden' === Report_Page::process_upload()['notice'], 'Sin manage_options, 403.' );

	// 4. Nonces y sesiones: ausente, ajeno, de otra sesión, de otro sitio; sesión de otro sitio, revertida o a medias.
	$scenario();
	$_POST = array( 'session' => S3 );
	$check( 403 === Report_Page::process_apply_settings()['status'], 'Sin nonce, 403.' );
	$post( Module::ACTION_APPLY_SETTINGS, S3, array(), S2 );
	$check( 403 === Report_Page::process_apply_settings()['status'], 'Un nonce de otra sesión no vale.' );
	$post( Module::ACTION_PUBLISH, S3 );
	$check( 403 === Report_Page::process_apply_settings()['status'], 'Un nonce de otra acción no vale.' );
	$post( Module::ACTION_APPLY_SETTINGS, S3, array(), null, 2 );
	$check( 403 === Report_Page::process_apply_settings()['status'], 'Un nonce de otro sitio de la red no vale.' );
	$check( array() === Site_Settings::$calls, 'Ningún rechazo llegó al importador.' );
	$GLOBALS['kt']['options'][2]['elementor_import_sessions']                  = array( SO => array( 'session_id' => SO, 'start_timestamp' => 3000, 'end_timestamp' => 3001, 'runners' => array() ) );
	$GLOBALS['kt']['options'][2][ 'digitalisimo_elements_kit_session_' . SO ] = $GLOBALS['kt']['options'][1][ 'digitalisimo_elements_kit_session_' . S3 ];
	foreach ( array( Module::ACTION_APPLY_SETTINGS => 'process_apply_settings', Module::ACTION_UNAPPLY_SETTINGS => 'process_unapply_settings', Module::ACTION_PUBLISH => 'process_publish', Report_Page::ACTION_REVERT => 'process_revert' ) as $action => $method ) {
		$post( $action, SO, array( 'confirm' => '1' ) );
		$check( 'session' === Report_Page::$method()['notice'], "Una sesión de otro sitio de la red no existe en este ({$action})." );
		$post( $action, S1, array( 'confirm' => '1' ) );
		$check( 'reverted' === Report_Page::$method()['notice'], "Ninguna acción sobre una sesión revertida ({$action})." );
	}
	$check( array() === Site_Settings::$calls && 0 === \Elementor\Plugin::$instance->app->module->reverts, 'Ni la sesión ajena ni la revertida llegaron al importador.' );
	$post( Module::ACTION_APPLY_SETTINGS, '../../x' );
	$check( 403 === ( Report_Page::process_apply_settings()['status'] ?? 0 ), 'Un ID de sesión inválido no tiene nonce válido.' );
	$sessions       = get_option( 'elementor_import_sessions' );
	$sessions[ S2 ] = array( 'session_id' => S2, 'kit_title' => 'A medias', 'start_timestamp' => 1000 );
	update_option( 'elementor_import_sessions', $sessions );
	$post( Module::ACTION_PUBLISH, S2 );
	$check( 'in-progress' === Report_Page::process_publish()['notice'], 'Ninguna acción sobre una sesión a medias.' );
	$GLOBALS['kt']['caps'] = array( 'manage_options' );
	$post( Module::ACTION_APPLY_SETTINGS, S3 );
	$check( 403 === Report_Page::process_apply_settings()['status'] && array() === Site_Settings::$calls, 'Aplicar los ajustes exige además edit_theme_options.' );

	// 5. «Aplicar los ajustes del kit» (D11) dos veces, y deshacer.
	$scenario();
	$check( Report::settings_pending( Report::detail( S3 ) ) && ! Report::settings_applied( Report::detail( S3 ) ), 'En «agregar», los ajustes del kit quedan pendientes.' );
	$post( Module::ACTION_APPLY_SETTINGS, S3 );
	$check( 'settings-ok' === Report_Page::process_apply_settings()['notice'] && array( 'apply' ) === Site_Settings::$calls, 'Aplicar llega una vez al importador.' );
	$check( Report::settings_applied( Report::detail( S3 ) ) && ! Report::settings_pending( Report::detail( S3 ) ), 'Tras aplicar, los ajustes constan como aplicados (backup.applied_hash).' );
	$post( Module::ACTION_APPLY_SETTINGS, S3 );
	$check( 'settings-applied' === Report_Page::process_apply_settings()['notice'] && array( 'apply' ) === Site_Settings::$calls, 'Aplicar dos veces no vuelve a aplicar.' );
	Site_Settings::$changed = true;
	$post( Module::ACTION_UNAPPLY_SETTINGS, S3 );
	$result = Report_Page::process_unapply_settings();
	$check( 'settings-changed' === $result['notice'] && false !== strpos( $result['flash'][0], 'no se deshace' ) && Report::settings_applied( Report::detail( S3 ) ), 'Deshacer tras editar el kit no pisa lo editado y lo explica.' );
	Site_Settings::$changed = false;
	$post( Module::ACTION_UNAPPLY_SETTINGS, S3 );
	$check( 'unapply-ok' === Report_Page::process_unapply_settings()['notice'] && Report::settings_pending( Report::detail( S3 ) ), 'Deshacer sin cambios deja los ajustes otra vez pendientes.' );
	$post( Module::ACTION_UNAPPLY_SETTINGS, S3 );
	$check( 'settings-not-applied' === Report_Page::process_unapply_settings()['notice'] && array( 'apply', 'unapply', 'unapply' ) === Site_Settings::$calls, 'Deshacer sin aplicar no llega al importador.' );
	Session::update( S2, array( 'mode' => Module::MODE_FRESH ) );
	$post( Module::ACTION_APPLY_SETTINGS, S2 );
	$check( 'settings-none' === Report_Page::process_apply_settings()['notice'], 'En «nuevo» no quedan ajustes por aplicar.' );

	// 6. «Publicar páginas y plantillas» (D8, A14).
	$scenario();
	$meta = function ( $session, array $extra = array() ) {
		return array_merge( array( '_elementor_import_session_id' => $session ), $extra );
	};
	kt_post( 50, 'page', 'draft', 'Ya existía', $meta( S3 ) );
	kt_post( 101, 'page', 'draft', 'Página', $meta( S3 ) );
	kt_post( 102, 'post', 'draft', 'Entrada', $meta( S3 ) );
	kt_post( 103, 'page', 'draft', 'De otra sesión', $meta( S2 ) );
	kt_post( 104, 'page', 'draft', 'Sin sesión' );
	kt_post( 105, 'elementor_library', 'draft', 'Cabecera', $meta( S3, array( '_elementor_template_type' => 'header' ) ) );
	kt_post( 106, 'elementor_library', 'draft', 'Popup', $meta( S3, array( '_elementor_template_type' => 'popup' ) ) );
	kt_post( 107, 'e-floating-buttons', 'draft', 'Botón', $meta( S3 ) );
	kt_post( 108, 'elementor_snippet', 'draft', 'Código', $meta( S3 ) );
	kt_post( 109, 'page', 'draft', 'Revisar sin cambios', $meta( S3, array( Module::META_ORIGIN => array( 'local' => 'h1' ) ) ) );
	kt_post( 110, 'page', 'draft', 'Revisar editada', $meta( S3, array( Module::META_ORIGIN => array( 'local' => 'h1' ) ) ) );
	kt_post( 111, 'elementor_library', 'draft', 'Sección', $meta( S3, array( '_elementor_template_type' => 'section' ) ) );
	kt_post( 112, 'elementor_library', 'draft', 'Tipo ajeno', $meta( S3, array( '_elementor_template_type' => 'section', Module::META_ORIGINAL_TYPE => 'post_type:recipe' ) ) );
	kt_post( 113, 'page', 'publish', 'Ya publicada', $meta( S3 ) );
	kt_post( 114, 'page', 'draft', 'Sin permiso', $meta( S3 ) );
	kt_post( 115, 'page', 'draft', 'Revisar sin huella', $meta( S3 ) );
	kt_post( 116, 'elementor_library', 'draft', 'Single', $meta( S3, array( '_elementor_template_type' => 'single' ) ) );
	kt_post( 117, 'page', 'draft', 'Revisar, huella guardada con la única fórmula', $meta( S3, array( Module::META_ORIGIN => array( 'local' => 'b1' ) ) ) );
	$review = array( array( 'part' => 'theme-builder', 'reason' => 'gate', 'data' => array() ) );
	Session::update( S3, array( 'review' => array( 109 => $review, 110 => $review, 115 => $review, 117 => $review ) ) );
	// Una sola huella local (`Session::local_hash()`, que es la de `Parts\Origin`): la de otra fórmula ya no cuenta.
	Session::$hashes            = array( 109 => 'h1', 110 => 'h2', 117 => 'b1' );
	Origin::$hashes             = array( 110 => 'h1' );
	$GLOBALS['kt']['deny_post'] = array( 114 );
	$publishable                = Report_Page::publishable( S3, Report::detail( S3 ) );
	$check( array( 101, 102, 110, 111, 114 ) === $publishable, 'Sólo lo visible y nuevo de la sesión (también plantillas), sin Theme Builder, popups, botones, fragmentos, tipos conservados ni revisiones sin cambios: ' . implode( ',', $publishable ) );
	$post( Module::ACTION_PUBLISH, S3 );
	$result = Report_Page::process_publish();
	$status = function ( $id ) {
		return kt_posts()[ $id ]['post_status'];
	};
	$check( 'publish-partial' === $result['notice'] && 4 === $result['count'], 'Una entrada sin publish_post deja la publicación parcial.' );
	foreach ( array( 101, 102, 110, 111 ) as $id ) {
		$check( 'publish' === $status( $id ), "Se publicó {$id}." );
	}
	foreach ( array( 50, 103, 104, 105, 106, 107, 108, 109, 112, 114, 115, 116, 117 ) as $id ) {
		$check( 'draft' === $status( $id ), "No se publicó {$id}." );
	}
	$check( array( 101, 102, 110, 111 ) === Report::detail( S3 )['published'][0]['ids'], 'El detalle anota lo publicado.' );
	$post( Module::ACTION_PUBLISH, S3 );
	$check( 'publish-none' === Report_Page::process_publish()['notice'], 'Publicar otra vez no encuentra nada más que publicar.' );
	$check( null === Report_Page::local_changed( 115 ) && false === Report_Page::local_changed( 117 ) && true === Report_Page::local_changed( 110 ), 'Sin huella guardada, no se sabe (no se publica); basta con que una fórmula coincida para «sin cambios».' );

	// 7. Revertir: sólo la última, con confirmación.
	$scenario();
	$module = \Elementor\Plugin::$instance->app->module;
	$post( Report_Page::ACTION_REVERT, S2, array( 'confirm' => '1' ) );
	$check( 'revert-not-last' === Report_Page::process_revert()['notice'] && 0 === $module->reverts, 'Sólo se revierte la última importación.' );
	$post( Report_Page::ACTION_REVERT, S3 );
	$check( 'revert-confirm' === Report_Page::process_revert()['notice'] && 0 === $module->reverts, 'Revertir pide confirmación.' );
	$post( Report_Page::ACTION_REVERT, S3, array( 'confirm' => '1' ) );
	$check( 'revert-ok' === Report_Page::process_revert()['notice'] && 1 === $module->reverts && 'reverted' === Report::detail( S3 )['status'], 'Revertir usa la reversión del importador y lo anota en el detalle.' );
	$check( Report::STATUS_REVERTED === Report::session( S3 )['status'] && false === Report::session( S3 )['actions'], 'Una sesión revertida ya no ofrece acciones.' );

	// 8. Lista de sesiones y última sesión.
	$scenario();
	$rows = Report::sessions();
	$check( array( S3, S2, S1 ) === array_column( $rows, 'id' ), 'La lista va de la más nueva a la más vieja, con las revertidas.' );
	$check( S3 === Report::last_session() && $rows[0]['revert'] && ! $rows[1]['revert'] && ! $rows[2]['revert'] && ! $rows[2]['actions'] && Report::STATUS_REVERTED === $rows[2]['status'], '«Revertir» sólo en la última; ninguna acción en la revertida.' );
	$sessions                            = get_option( 'elementor_import_sessions' );
	$sessions['6612f0a1b2c3d4.30000000'] = array( 'session_id' => '6612f0a1b2c3d4.30000000', 'start_timestamp' => 5000 );
	update_option( 'elementor_import_sessions', $sessions );
	$check( '' === Report::last_session(), 'Si la última sesión está a medias, no se ofrece revertir ninguna (el núcleo revertiría esa).' );

	// 9. Pantalla: escapado, acciones y casilla «nuevo».
	$scenario();
	Session::$empty = array( 'empty' => false, 'reasons' => array( 'El sitio ya tiene contenido.' ) );
	$detail         = array(
		'report'   => array(
			'entries' => array(
				array( 'part' => 'media', 'code' => 'media.failed', 'level' => 'error', 'data' => array( 'url' => 'https://cdn.' . $vendor_host . '/x.png' ) ),
				array( 'part' => 'origin', 'code' => 'plugins.requested', 'level' => 'info', 'data' => array( 'plugin' => 'elementor-pro/elementor-pro', 'name' => 'Elementor Pro' ) ),
				array( 'part' => 'theme-builder', 'code' => 'note:form', 'level' => 'warning', 'data' => array( 'post' => 101, 'campo' => '<img src=x onerror=alert(2)>' ) ),
			),
			'counts'  => array( 'media.embedded' => 4 ),
			'dropped' => 2,
		),
		'requests' => array( 'theme' => true, 'experiments' => array( 'container' => 'active' ), 'plugins' => array( 'Acme Forms' ), 'builder_plugins' => 2 ),
		'analysis' => array(
			'unknown_widgets' => array( 'acme-hero' => 2 ),
			'hosts'           => array( 'demo-autor.test' => 3, 'cdn.' . $vendor_host => 5 ),
			'forms'           => 1,
		),
	);
	Session::update( S3, $detail );
	kt_post( 101, 'page', 'draft', 'Página <b>importada</b>', array( '_elementor_import_session_id' => S3 ) );
	$_GET = array( 'page' => Module::PAGE, 'session' => S3 );
	ob_start();
	Report_Page::render();
	$html = ob_get_clean();
	$check( false === strpos( $html, '<script>alert(1)</script>' ) && false !== strpos( $html, 'Kit &lt;script&gt;alert(1)&lt;/script&gt;' ) && false === strpos( $html, '<img src=x' ) && false === strpos( $html, '<b>importada</b>' ), 'La pantalla escapa los textos del kit.' );
	$check( false === stripos( $html, $vendor_host ) && false === strpos( $html, 'Elementor Pro' ) && false !== strpos( $html, 'Funciones incluidas en DIGITALÍSIMO Elements' ) && false !== strpos( $html, 'Acme Forms' ) && false !== strpos( $html, 'demo-autor.test' ), 'Sin dominios ni plugins del constructor original (CR-18); los demás plugins y dominios, sin enlace.' );
	$check( false !== strpos( $html, 'name="fresh" value="1" disabled' ) && false !== strpos( $html, 'El sitio ya tiene contenido.' ) && false !== strpos( $html, 'name="kit"' ), 'La casilla «nuevo» se deshabilita en un sitio con contenido y explica por qué.' );
	foreach ( array( Module::ACTION_APPLY_SETTINGS, Module::ACTION_PUBLISH, Report_Page::ACTION_REVERT ) as $action ) {
		$check( false !== strpos( $html, 'value="' . $action . '"' ), "La última sesión terminada ofrece {$action}." );
	}
	foreach ( array( 'Conservado sin mostrar', 'Lo que el kit pedía y no se aplicó', 'Medios', 'URL del origen que quedan', 'Configurar después', 'Ajustes del kit no aplicados', 'Borradores' ) as $title ) {
		$check( false !== strpos( $html, '<h3>' . $title . '</h3>' ), "La sección «{$title}» aparece." );
	}
	$check( strpos( $html, '<h3>Conservado sin mostrar</h3>' ) < strpos( $html, '<h3>Lo que el kit pedía y no se aplicó</h3>' ) && strpos( $html, '<h3>Lo que el kit pedía y no se aplicó</h3>' ) < strpos( $html, '<h3>Medios</h3>' ) && strpos( $html, '<h3>Medios</h3>' ) < strpos( $html, '<h3>Configurar después</h3>' ) && strpos( $html, '<h3>Configurar después</h3>' ) < strpos( $html, '<h3>Borradores</h3>' ), 'Las secciones siguen el orden de §12.2.' );
	$check( false !== strpos( $html, '2 avisos más no caben' ) && false !== strpos( $html, '1 errores' ), 'El resumen cuenta los errores y lo que no cupo.' );
	$check( 0 === preg_match_all( '#<a [^>]*href="(?!https://b\.test/)#', $html ), 'Ningún enlace sale del sitio.' );
	$_GET = array( 'page' => Module::PAGE, 'session' => S1 );
	ob_start();
	Report_Page::render();
	$detail_html = (string) ob_get_clean();
	$detail_html = substr( $detail_html, (int) strpos( $detail_html, 'Informe:' ) );
	foreach ( array( Module::ACTION_APPLY_SETTINGS, Module::ACTION_UNAPPLY_SETTINGS, Module::ACTION_PUBLISH, Report_Page::ACTION_REVERT ) as $action ) {
		$check( false === strpos( $detail_html, 'value="' . $action . '"' ), "Una sesión revertida no ofrece {$action}." );
	}
	$check( false !== strpos( $detail_html, 'Papelera tras revertir' ), 'Una sesión revertida muestra la papelera.' );

	// 10. Salida JSON (WP-CLI) sin nombres del constructor original.
	$json = json_encode( Report::public_report( S3 ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$check( false === strpos( $json, 'Elementor Pro' ) && false === stripos( $json, $vendor_host ) && false !== strpos( $json, 'Funciones incluidas en DIGITALÍSIMO Elements' ), 'El informe JSON tampoco nombra la familia del constructor ni sus dominios.' );
	$rows = Report::table_rows( Report::public_report( S3 ) );
	$check( count( $rows ) > 5 && array( 'seccion', 'nivel', 'aviso' ) === array_keys( $rows[0] ), 'La tabla de WP-CLI tiene sección, nivel y aviso.' );

	// 11. WP-CLI: opciones e informe de `inspect`.
	$tmp = sys_get_temp_dir() . '/kit-actions-' . getmypid();
	@mkdir( $tmp, 0777, true );
	file_put_contents( $tmp . '/kit.zip', 'PK' );
	$options = Cli::import_options( array( $tmp . '/kit.zip' ), array( 'mode' => 'fresh', 'include' => ' Templates, ,content,templates', 'format' => 'json' ) );
	$check( array( 'file' => $tmp . '/kit.zip', 'format' => 'json', 'args' => array( 'mode' => 'fresh', 'include' => array( 'templates', 'content' ) ) ) === $options, 'import: opciones limpias para Session::import_file().' );
	foreach ( array( array( array( $tmp . '/nada.zip' ), array() ), array( array( $tmp . '/kit.zip' ), array( 'mode' => 'otro' ) ), array( array( $tmp . '/kit.zip' ), array( 'include' => 'themes' ) ), array( array( $tmp . '/kit.zip' ), array( 'format' => 'xml' ) ) ) as $bad ) {
		$check( is_string( Cli::import_options( $bad[0], $bad[1] ) ), 'import rechaza ' . json_encode( $bad ) );
	}
	$export = Cli::export_options( array( $tmp . '/salida.zip' ), array( 'post-types' => 'Recipe,product', 'title' => '<b>Mi kit</b>', 'media' => 'link' ) );
	$check( array( 'include' => array(), 'title' => 'Mi kit', 'media' => 'link', 'post_types' => array( 'recipe', 'product' ) ) === $export['args'] && $tmp . '/salida.zip' === $export['target'], 'export: opciones limpias para Session::export_file().' );
	$check( is_string( Cli::export_options( array( $tmp . '/salida.txt' ), array() ) ) && is_string( Cli::export_options( array( '/no/existe/k.zip' ), array() ) ) && is_string( Cli::export_options( array( $tmp . '/k.zip' ), array( 'media' => 'cloud' ) ) ), 'export rechaza un nombre sin .zip, una carpeta que no existe y otro modo de medios.' );
	@unlink( $tmp . '/kit.zip' );
	@rmdir( $tmp );
	$inspect      = Cli::inspection_report(
		array(
			'format'   => 'envato',
			'origin'   => array( 'kit' => str_repeat( 'c', 40 ), 'home' => 'https://www.' . $vendor_host . '/demo', 'site' => 'https://autor.test' ),
			'requests' => array( 'theme' => true, 'plugins' => array( 'Acme' ), 'builder_plugins' => 1 ),
			'analysis' => array( 'unknown_widgets' => array( 'acme-hero' => 1 ) ),
			'media'    => 3,
			'mode'     => array( 'mode' => 'add', 'error' => 'Un Template Kit de Envato se importa siempre en «sólo agregar».' ),
		)
	);
	$inspect_json = json_encode( $inspect, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$check( 'Template Kit' === $inspect['format_label'] && false === $inspect['fresh'] && 3 === $inspect['media'] && false === stripos( $inspect_json, $vendor_host ) && false !== strpos( $inspect_json, Report::FAMILY_LABEL ) && count( $inspect['rows'] ) >= 3, 'inspect: formato, modo posible, pedidos y análisis, sin dominios ni plugins del constructor original.' );

	// 12. Ganchos: sólo en la administración y sin red.
	kt_reset();
	Report_Page::init();
	$hooks = array_column( $GLOBALS['kt']['actions'], 0 );
	foreach ( array( 'admin_menu', 'admin_notices', 'admin_post_' . Module::ACTION_UPLOAD, 'admin_post_' . Module::ACTION_APPLY_SETTINGS, 'admin_post_' . Module::ACTION_UNAPPLY_SETTINGS, 'admin_post_' . Module::ACTION_PUBLISH, 'admin_post_' . Report_Page::ACTION_REVERT, 'elementor/admin/after_create_settings/elementor-tools', 'elementor/app/init' ) as $hook ) {
		$check( in_array( $hook, $hooks, true ), "Report_Page::init() engancha {$hook}." );
	}
	$check( ! in_array( 'network_admin_menu', $hooks, true ) && ! in_array( 'admin_post_nopriv_' . Module::ACTION_UPLOAD, $hooks, true ), 'Ni en la red ni para visitantes sin sesión.' );

	echo "Kits · acciones: nonces, sesiones, publicar, aplicar y deshacer, subida con D1, revertir, informe, pantalla y WP-CLI en verde.\n";
}
