<?php
/**
 * Plugin Name: DIGITALÍSIMO Elements · Instalador
 * Description: Instala DIGITALÍSIMO Elements sin pasar por el límite de subida del hosting: lo descarga en el servidor desde la última versión publicada, lo instala, lo activa y después se borra solo.
 * Plugin URI: https://github.com/danbutanda/digitalisimo-plugin
 * Version: 1.0.0
 * Update URI: false
 * Author: DIGITALÍSIMO
 * Author URI: https://digitalisimo.mx/
 * Requires PHP: 7.4
 * Requires at least: 6.8
 * License: GPLv2 or later
 * Text Domain: digitalisimo-elements-installer
 */

/*
 * El ZIP de DIGITALÍSIMO Elements pesa más de 30 MB (lleva el núcleo del constructor sin cambios), más de lo que muchos
 * hostings dejan subir por Plugins → Añadir nuevo → Subir plugin (`upload_max_filesize`/`post_max_size`; WordPress
 * responde «The link you followed has expired»). Este plugin pesa pocos KB y hace la descarga en el servidor, donde no
 * hay límite de subida:
 *
 *  1. Al activarlo lleva a su pantalla (Plugins → Instalar DIGITALÍSIMO Elements) y empieza solo, con nonce; si la
 *     redirección no aplica (activación en bloque, WP-CLI), un aviso con botón hace lo mismo.
 *  2. Lee el índice de la última Release y sólo acepta el ZIP de Elements de las Releases oficiales (`accepts()`).
 *  3. Lo instala con Plugin_Upgrader (la salida es la del actualizador de WordPress), comprueba que quedó
 *     `digitalisimo-elements/pro-elements.php` y lo activa: en toda la red si el instalador está activo en toda la red;
 *     si no, sólo en este sitio.
 *  4. Si todo salió bien se desactiva y se borra (`delete_plugins()`); si algo falla, explica la causa, ofrece la
 *     alternativa manual y «Reintentar», y se queda.
 *
 * No es uno de los siete plugins DIGITALÍSIMO: no tiene actualizador ni entrada en el índice de la Release, y su
 * pantalla es una pantalla efímera del núcleo de WordPress (clases `wrap`, `notice` y `button`), sin la hoja compartida
 * `digitalisimo-ui/` ni JavaScript propio. Ver «Instalar Elements en un sitio nuevo» en el README del repositorio.
 */

defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Installer {
	const VERSION = '1.0.0';
	/** Página bajo Plugins. No empieza por «digitalisimo»: el diseño compartido de los siete plugins no la toma. */
	const PAGE = 'instalar-digitalisimo-elements';
	const NONCE = 'digitalisimo-elements-installer';
	const REDIRECT = 'digitalisimo_elements_installer_redirect';
	const LOCK = 'digitalisimo_elements_installer';
	const ELEMENTS = 'digitalisimo-elements/pro-elements.php';
	const ELEMENTS_URI = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-elements';
	const SLUG = 'digitalisimo-elements';
	/** Repositorio oficial: el índice y el ZIP sólo se aceptan de sus Releases. */
	const RELEASES = 'https://github.com/danbutanda/digitalisimo-plugin/';
	const INDEX = 'releases/latest/download/digitalisimo-plugins.json';
	/**
	 * Origen sólo de pruebas (tests/playground/elements-installer.sh): un filtro que devuelve la base de un servidor
	 * local con el índice y el ZIP de `dist/`, para no depender de GitHub. Sólo vale una base de bucle local
	 * (`http://127.0.0.1:<puerto>/`, `http://localhost:<puerto>/` o `http://[::1]:<puerto>/`), así que ni con este filtro
	 * puede apuntar a otro servidor de internet; sin código que lo añada (un mu-plugin de la prueba) se usa GitHub.
	 */
	const TEST_SOURCE_FILTER = 'digitalisimo_elements_installer_test_source';

	/** @var string archivo principal del instalador. */
	private static $file = '';
	/** @var string URL de «Reintentar» para el mensaje de error fatal. */
	private static $retry = '';
	/**
	 * @var array|null instalación en curso en esta petición: desde cuándo (para reconocer su ZIP temporal) y si ya se
	 * liberó el cerrojo. La lee la función de cierre (shutdown()).
	 */
	private static $running = null;

	public static function boot( $file ) {
		self::$file = $file;
		register_activation_hook( $file, array( __CLASS__, 'activated' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'plugin_action_links_' . self::basename(), array( __CLASS__, 'action_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . self::basename(), array( __CLASS__, 'action_link' ) );
	}

	/** El instalador tal como está instalado (carpeta real incluida). */
	public static function basename() {
		return plugin_basename( self::$file );
	}

	private static function admin_includes() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
	}

	/**
	 * Dónde actúa: «network» si el instalador está activo en toda la red (Elements se activa en toda la red); «site» en
	 * un WordPress individual o si se activó en un sitio de la red (Elements se activa sólo en ese sitio).
	 */
	public static function scope() {
		if ( ! is_multisite() ) {
			return 'site';
		}
		self::admin_includes();
		return is_plugin_active_for_network( self::basename() ) ? 'network' : 'site';
	}

	/** ¿La pantalla actual es la de su ámbito? La red en la administración de red; un sitio en la de ese sitio. */
	private static function in_scope( $scope ) {
		return 'network' === $scope ? is_network_admin() : ! is_network_admin();
	}

	/** Instalar y activar plugins; en la red, además, administrar los de la red (instalar es del superadministrador). */
	public static function allowed( $scope ) {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}
		return 'network' !== $scope || current_user_can( 'manage_network_plugins' );
	}

	public static function page_url( $scope ) {
		$path = 'plugins.php?page=' . self::PAGE;
		return 'network' === $scope ? network_admin_url( $path ) : admin_url( $path );
	}

	/** Enlace que empieza la instalación (con nonce); sin escapar, para redirecciones. */
	public static function start_url( $scope ) {
		return add_query_arg( array( 'instalar' => '1', '_wpnonce' => wp_create_nonce( self::NONCE ) ), self::page_url( $scope ) );
	}

	// ── Activación del instalador ──────────────────────────────────────────────────────────────────────────────────

	/** Al activarlo se anota a quién y dónde llevar a su pantalla (una vez, en la siguiente petición de administración). */
	public static function activated( $network_wide = false ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		set_site_transient( self::REDIRECT, array( 'user' => get_current_user_id(), 'scope' => $network_wide ? 'network' : 'site', 'blog' => get_current_blog_id() ), 2 * MINUTE_IN_SECONDS );
	}

	/**
	 * A dónde llevar tras la activación, o '' si no aplica: otra persona, otra pantalla (red o sitio), activación en
	 * bloque, AJAX o sin permisos. La anotación se consume al leerla en su pantalla.
	 */
	public static function redirect_target() {
		$pending = get_site_transient( self::REDIRECT );
		if ( ! is_array( $pending ) || (int) ( $pending['user'] ?? 0 ) !== get_current_user_id() ) {
			return '';
		}
		$scope = 'network' === ( $pending['scope'] ?? '' ) ? 'network' : 'site';
		if ( ! self::in_scope( $scope ) || ( 'site' === $scope && (int) ( $pending['blog'] ?? 0 ) !== get_current_blog_id() ) ) {
			return '';
		}
		delete_site_transient( self::REDIRECT );
		if ( isset( $_GET['activate-multi'] ) || wp_doing_ajax() || ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) || self::scope() !== $scope || ! self::allowed( $scope ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si redirigir.
			return '';
		}
		return self::start_url( $scope );
	}

	public static function maybe_redirect() {
		$target = self::redirect_target();
		if ( '' !== $target ) {
			wp_safe_redirect( $target );
			exit;
		}
	}

	public static function menu() {
		$scope = self::scope();
		if ( self::in_scope( $scope ) ) {
			add_submenu_page( 'plugins.php', 'DIGITALÍSIMO Elements · Instalador', 'Instalar DIGITALÍSIMO Elements', 'network' === $scope ? 'manage_network_plugins' : 'activate_plugins', self::PAGE, array( __CLASS__, 'render' ) );
		}
	}

	public static function action_link( $links ) {
		$scope = self::scope();
		if ( self::in_scope( $scope ) && self::allowed( $scope ) ) {
			array_unshift( $links, '<a href="' . esc_url( self::page_url( $scope ) ) . '">Instalar DIGITALÍSIMO Elements</a>' );
		}
		return $links;
	}

	/** Aviso con botón en el resto de la administración mientras el instalador siga activo. */
	public static function notice() {
		$scope = self::scope();
		if ( ! self::in_scope( $scope ) || ! self::allowed( $scope ) || self::PAGE === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		self::admin_includes();
		$installed = '' !== self::find_elements( get_plugins() );
		$text = $installed ? 'DIGITALÍSIMO Elements ya está instalado: el instalador ya no hace falta.' : 'Falta un paso: descargar e instalar DIGITALÍSIMO Elements en el servidor.';
		$button = $installed ? 'Activar DIGITALÍSIMO Elements y retirar el instalador' : 'Instalar DIGITALÍSIMO Elements';
		echo '<div class="notice notice-info"><p><strong>DIGITALÍSIMO Elements · Instalador.</strong> ' . esc_html( $text ) . '</p><p><a class="button button-primary" href="' . esc_url( self::start_url( $scope ) ) . '">' . esc_html( $button ) . '</a></p></div>';
	}

	// ── Origen del paquete ─────────────────────────────────────────────────────────────────────────────────────────

	/** Base de pruebas válida (bucle local con puerto) o ''. Ver TEST_SOURCE_FILTER. */
	public static function test_base() {
		$base = apply_filters( self::TEST_SOURCE_FILTER, '' );
		return is_string( $base ) && preg_match( '#^http://(?:127\.0\.0\.1|localhost|\[::1\]):[0-9]{2,5}/\z#', $base ) ? $base : '';
	}

	/** Base de las Releases: la oficial, salvo un origen de pruebas de bucle local. */
	public static function base() {
		$test = self::test_base();
		return '' !== $test ? $test : self::RELEASES;
	}

	public static function index_url() {
		return self::base() . self::INDEX;
	}

	/**
	 * Lista blanca: sólo `<base>releases/download/vAAAA.MM.DD.N/digitalisimo-elements-<versión>.zip`, con la versión de
	 * la entrada del índice y nada más (ni otra cuenta, repositorio, host, esquema, nombre de ZIP, `..`, consulta ni
	 * fragmento). Las redirecciones a los dominios de descarga de GitHub las sigue download_url().
	 */
	public static function accepts( $url, $version, $base = null ) {
		$base = null === $base ? self::base() : $base;
		if ( ! is_string( $url ) || ! is_string( $version ) || ! preg_match( '/^[0-9]+(?:\.[0-9]+)+\z/', $version ) ) {
			return false;
		}
		$pattern = '#^' . preg_quote( $base, '#' ) . 'releases/download/v[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]+/' . preg_quote( self::SLUG . '-' . $version, '#' ) . '\.zip\z#';
		return 1 === preg_match( $pattern, $url );
	}

	/** Paquete de Elements en un índice ya leído: array( url, version ) o WP_Error. */
	public static function package_from_index( $manifest, $base = null ) {
		if ( ! is_array( $manifest ) || ! isset( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) {
			return new WP_Error( 'indice_ilegible', 'El índice de la última versión no se pudo leer: no es el archivo esperado.' );
		}
		$entry = $manifest['plugins'][ self::SLUG ] ?? null;
		if ( ! is_array( $entry ) ) {
			return new WP_Error( 'indice_sin_elements', 'El índice de la última versión no trae DIGITALÍSIMO Elements.' );
		}
		$version = (string) ( $entry['version'] ?? '' );
		$url = (string) ( $entry['url'] ?? '' );
		if ( ! self::accepts( $url, $version, $base ) ) {
			return new WP_Error( 'paquete_rechazado', 'El índice trae un enlace de descarga que no es un ZIP de DIGITALÍSIMO Elements de las versiones publicadas oficiales; por seguridad no se descarga.' );
		}
		return array( 'url' => $url, 'version' => $version );
	}

	/** Lee el índice de la última Release y devuelve el paquete de Elements o un WP_Error con la causa. */
	public static function package() {
		$base = self::base();
		$response = wp_safe_remote_get( $base . self::INDEX, array( 'timeout' => 20, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Elements-Installer/' . self::VERSION ) ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sin_salida', 'El servidor no pudo conectarse con GitHub para leer la última versión publicada (' . $response->get_error_message() . '). Puede que el hosting no permita conexiones salientes.' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'indice_http', sprintf( 'GitHub respondió con el código HTTP %d al pedir el índice de la última versión publicada.', $code ) );
		}
		return self::package_from_index( json_decode( (string) wp_remote_retrieve_body( $response ), true ), $base );
	}

	// ── Instalación ────────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * DIGITALÍSIMO Elements instalado: su archivo habitual o, en otra carpeta, el `pro-elements.php` con su «Update URI».
	 *
	 * @param array $plugins resultado de get_plugins().
	 * @return string archivo del plugin o ''.
	 */
	public static function find_elements( $plugins ) {
		$plugins = is_array( $plugins ) ? $plugins : array();
		if ( isset( $plugins[ self::ELEMENTS ] ) ) {
			return self::ELEMENTS;
		}
		foreach ( $plugins as $file => $data ) {
			if ( is_array( $data ) && self::ELEMENTS_URI === (string) ( $data['UpdateURI'] ?? '' ) && 'pro-elements.php' === basename( (string) $file ) ) {
				return (string) $file;
			}
		}
		return '';
	}

	private static function is_active( $file, $network ) {
		return $network ? is_plugin_active_for_network( $file ) : is_plugin_active( $file );
	}

	/** Activa Elements en el ámbito (red o sitio): 'activado', 'ya' o WP_Error. */
	public static function activate( $file, $network ) {
		if ( self::is_active( $file, $network ) ) {
			return 'ya';
		}
		$result = activate_plugin( $file, '', $network );
		// «unexpected_output»: WordPress lo activa igual y sólo avisa de que imprimió algo al activarse.
		if ( is_wp_error( $result ) && 'unexpected_output' !== $result->get_error_code() ) {
			return $result;
		}
		return self::is_active( $file, $network ) ? 'activado' : new WP_Error( 'sin_activar', 'WordPress no dejó DIGITALÍSIMO Elements activo.' );
	}

	/** Pide una vez los datos de acceso a archivos (vacío con acceso directo) sin imprimir nada: ¿se puede borrar sin preguntar? */
	private static function filesystem_ready() {
		ob_start();
		$credentials = request_filesystem_credentials( '', '', false, false, null );
		ob_end_clean();
		return false !== $credentials && WP_Filesystem( $credentials );
	}

	/** Se desactiva y se borra con delete_plugins(); nunca toca la carpeta de Elements. true o WP_Error. */
	public static function remove_self( $network ) {
		$self = self::basename();
		if ( '' === $self || '.' === dirname( $self ) || dirname( $self ) === dirname( self::ELEMENTS ) ) {
			return new WP_Error( 'carpeta_propia', 'El instalador no está en una carpeta propia: bórralo desde Plugins.' );
		}
		deactivate_plugins( $self, true, $network );
		if ( $network && is_plugin_active( $self ) ) {
			deactivate_plugins( $self, true, false );
		}
		if ( ! self::filesystem_ready() ) {
			return new WP_Error( 'sin_acceso', 'WordPress pide los datos de acceso a los archivos para borrarlo.' );
		}
		$deleted = delete_plugins( array( $self ) );
		if ( true === $deleted ) {
			return true;
		}
		return is_wp_error( $deleted ) ? $deleted : new WP_Error( 'sin_borrar', 'WordPress no pudo borrar la carpeta del instalador.' );
	}

	private static function failure( $code, $cause ) {
		return array( 'status' => 'error', 'code' => $code, 'cause' => $cause );
	}

	/** Explicación de una falla del actualizador de WordPress, con la causa más probable. */
	public static function explain( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return 'La instalación no terminó y WordPress no informó la causa.';
		}
		$code = (string) $error->get_error_code();
		$message = trim( wp_strip_all_tags( (string) $error->get_error_message() ) );
		if ( in_array( $code, array( 'download_failed', 'http_request_failed', 'http_404', 'http_no_url' ), true ) ) {
			return 'No se pudo descargar el paquete desde GitHub: ' . $message;
		}
		if ( 'folder_exists' === $code ) {
			return 'Ya existe la carpeta wp-content/plugins/digitalisimo-elements, pero WordPress no reconoce dentro un plugin completo (quizá una copia a medias). Bórrala con el administrador de archivos del hosting y pulsa «Reintentar».';
		}
		if ( 0 === strpos( $code, 'disk_full' ) ) {
			return 'No hay espacio libre suficiente en el servidor para descomprimir el paquete: ' . $message;
		}
		if ( 0 === strpos( $code, 'incompatible_' ) && false !== strpos( $code, '_required_version' ) ) {
			return 'Este servidor no cumple los requisitos de esta versión de DIGITALÍSIMO Elements: ' . $message;
		}
		if ( 0 === strpos( $code, 'incompatible_archive' ) || 0 === strpos( $code, 'empty_archive' ) || 0 === strpos( $code, 'stat_failed' ) ) {
			return 'El paquete descargado no se pudo abrir (descarga incompleta o dañada): ' . $message;
		}
		if ( 0 === strpos( $code, 'fs_' ) || 0 === strpos( $code, 'mkdir_failed' ) || 0 === strpos( $code, 'copy_failed' ) || in_array( $code, array( 'files_not_writable', 'remove_old_failed' ), true ) ) {
			return 'WordPress no puede escribir en wp-content/plugins (permisos de escritura): ' . $message;
		}
		return 'La instalación falló: ' . $message . ( '' !== $code ? ' (' . $code . ')' : '' );
	}

	/**
	 * Skin del actualizador de WordPress con su salida estándar dentro de la pantalla del instalador (sin la cabecera
	 * ni el pie propios del skin) que anota los errores y si pidió datos de acceso a los archivos.
	 */
	private static function skin( $scope ) {
		$skin = new class( array( 'url' => add_query_arg( 'instalar', '1', self::page_url( $scope ) ), 'nonce' => self::NONCE, 'title' => '' ) ) extends WP_Upgrader_Skin {
			/** @var array errores informados por el actualizador. */
			public $digitalisimo_errors = array();
			/** @var bool WordPress pidió los datos de acceso a los archivos (FTP/SSH). */
			public $digitalisimo_credentials = false;
			public function error( $errors ) {
				$this->digitalisimo_errors[] = $errors;
				parent::error( $errors );
			}
			public function request_filesystem_credentials( $error = false, $context = '', $allow_relaxed_file_ownership = false ) {
				$credentials = parent::request_filesystem_credentials( $error, $context, $allow_relaxed_file_ownership );
				if ( false === $credentials ) {
					$this->digitalisimo_credentials = true;
				}
				return $credentials;
			}
		};
		$skin->done_header = true;
		$skin->done_footer = true;
		return $skin;
	}

	/** Instala el paquete: true, 'credenciales' o el resultado de falla. */
	private static function install( $package, $scope ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- algunos hostings lo desactivan.
		}
		wp_raise_memory_limit( 'admin' );
		$skin = self::skin( $scope );
		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->install( $package['url'] );
		if ( true === $result ) {
			return true;
		}
		if ( $skin->digitalisimo_credentials ) {
			return array( 'status' => 'credenciales' );
		}
		$error = is_wp_error( $result ) ? $result : null;
		foreach ( array_reverse( $skin->digitalisimo_errors ) as $reported ) {
			if ( null === $error && is_wp_error( $reported ) ) {
				$error = $reported;
			}
		}
		if ( null === $error && is_wp_error( $skin->result ) ) {
			$error = $skin->result;
		}
		if ( null === $error && $skin->digitalisimo_errors && is_string( end( $skin->digitalisimo_errors ) ) ) {
			$error = new WP_Error( 'upgrader', end( $skin->digitalisimo_errors ) );
		}
		return self::failure( 'instalar', self::explain( $error ) );
	}

	/**
	 * Todo el proceso en su ámbito, ya comprobados permisos y nonce: si Elements ya está, sólo lo activa; si no, lo
	 * descarga e instala y lo activa. Sólo si todo salió bien se retira el instalador.
	 *
	 * @return array status 'instalado' | 'ya' | 'credenciales' | 'error', con 'cause', 'elements', 'version' y 'removed'.
	 */
	public static function process( $scope ) {
		self::admin_includes();
		$network = 'network' === $scope;
		$found = self::find_elements( get_plugins() );
		if ( '' !== $found ) {
			$activated = self::activate( $found, $network );
			if ( is_wp_error( $activated ) ) {
				return self::failure( 'activar', 'DIGITALÍSIMO Elements ya estaba instalado, pero no se pudo activar: ' . wp_strip_all_tags( $activated->get_error_message() ) );
			}
			$data = get_plugins()[ $found ] ?? array();
			return array( 'status' => 'ya', 'elements' => $found, 'version' => (string) ( $data['Version'] ?? '' ), 'activated' => $activated, 'removed' => self::remove_self( $network ) );
		}
		$package = self::package();
		if ( is_wp_error( $package ) ) {
			return self::failure( $package->get_error_code(), $package->get_error_message() );
		}
		$installed = self::install( $package, $scope );
		if ( true !== $installed ) {
			return $installed;
		}
		if ( self::ELEMENTS !== self::find_elements( get_plugins() ) || ! is_file( WP_PLUGIN_DIR . '/' . self::ELEMENTS ) ) {
			return self::failure( 'sin_elements', 'El paquete se instaló, pero no dejó ' . self::ELEMENTS . ': no se activa nada.' );
		}
		$activated = self::activate( self::ELEMENTS, $network );
		if ( is_wp_error( $activated ) ) {
			return self::failure( 'activar', 'DIGITALÍSIMO Elements se instaló, pero no se pudo activar: ' . wp_strip_all_tags( $activated->get_error_message() ) );
		}
		return array( 'status' => 'instalado', 'elements' => self::ELEMENTS, 'version' => $package['version'], 'activated' => $activated, 'removed' => self::remove_self( $network ) );
	}

	/** Permisos, nonce y un solo proceso a la vez; después process(). */
	public static function handle( $scope ) {
		if ( ! self::allowed( $scope ) ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return self::failure( 'permisos', 'La instalación de plugins está desactivada en este sitio (DISALLOW_FILE_MODS en wp-config.php).' );
			}
			return self::failure( 'permisos', is_multisite() ? 'Sólo el superadministrador de la red puede instalar y activar plugins.' : 'Tu usuario no puede instalar y activar plugins en este sitio.' );
		}
		if ( ! wp_verify_nonce( (string) wp_unslash( $_REQUEST['_wpnonce'] ?? '' ), self::NONCE ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se verifica.
			return self::failure( 'nonce', 'El enlace caducó o no es válido. Pulsa «Reintentar».' );
		}
		self::admin_includes();
		if ( ! WP_Upgrader::create_lock( self::LOCK, 10 * MINUTE_IN_SECONDS ) ) {
			return self::failure( 'en_curso', 'Ya hay una instalación en curso (puede ser la de una petición anterior que sigue en el servidor aunque se cerrara la página). Al terminar, DIGITALÍSIMO Elements aparecerá activo en Plugins; si en unos minutos no aparece, pulsa «Reintentar».' );
		}
		// Desde aquí la petición sigue aunque el navegador corte (recarga, doble clic, el proxy del hosting que corta tras
		// un rato sin recibir nada): si PHP la matara a mitad, el `finally` no correría y el cerrojo quedaría 10 minutos.
		// La función de cierre libera el cerrojo y borra el ZIP temporal si la petición termina sin pasar por él.
		ignore_user_abort( true );
		self::$running = array( 'since' => time(), 'released' => false );
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
		self::$retry = self::start_url( $scope );
		add_filter( 'wp_php_error_message', array( __CLASS__, 'fatal_message' ), 10, 2 );
		try {
			return self::process( $scope );
		} finally {
			remove_filter( 'wp_php_error_message', array( __CLASS__, 'fatal_message' ), 10 );
			self::release();
		}
	}

	/** Libera el cerrojo una sola vez y, si la instalación no terminó por su camino, borra el ZIP temporal. */
	private static function release( $interrupted = false ) {
		if ( null === self::$running || self::$running['released'] ) {
			return;
		}
		self::$running['released'] = true;
		if ( $interrupted ) {
			self::clean_downloads( (int) self::$running['since'] );
		}
		WP_Upgrader::release_lock( self::LOCK );
	}

	/**
	 * Borra los ZIP temporales de una descarga cortada: download_url() los deja en get_temp_dir() como
	 * `digitalisimo-elements-<versión>-XXXXXX.tmp`. Sólo los creados desde que empezó esta instalación.
	 */
	public static function clean_downloads( $since ) {
		foreach ( (array) glob( get_temp_dir() . 'digitalisimo-elements-*.tmp' ) as $file ) {
			if ( is_string( $file ) && is_file( $file ) && (int) filemtime( $file ) >= $since - 1 ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * Función de cierre (registrada al empezar): si la petición terminó sin pasar por el `finally` (el navegador cortó
	 * y PHP la mató, o un error fatal), libera el cerrojo y borra el ZIP temporal.
	 */
	public static function shutdown() {
		self::release( true );
	}

	/** Un error fatal (memoria, tiempo) durante la instalación: la causa, la alternativa manual y «Reintentar». */
	public static function fatal_message( $message, $error = array() ) {
		self::release( true );
		$detail = is_array( $error ) && ! empty( $error['message'] ) ? ' (' . esc_html( strtok( (string) $error['message'], "\n" ) ) . ')' : '';
		return $message . '<p><strong>La instalación de DIGITALÍSIMO Elements se interrumpió por un error de PHP' . $detail . '</strong>; suele ser falta de memoria o de tiempo en el hosting. El instalador sigue en Plugins.</p>' . self::manual_html() . ( '' !== self::$retry ? '<p><a class="button" href="' . esc_url( self::$retry ) . '">Reintentar</a></p>' : '' );
	}

	// ── Pantalla ───────────────────────────────────────────────────────────────────────────────────────────────────

	private static function manual_html() {
		return '<p><strong>Alternativa manual:</strong> descarga <code>digitalisimo-elements-&lt;versión&gt;.zip</code> de la <a href="' . esc_url( self::RELEASES . 'releases/latest' ) . '">última versión publicada</a>, súbelo con el administrador de archivos del hosting a <code>wp-content/plugins/</code>, extráelo ahí (queda <code>wp-content/plugins/digitalisimo-elements/</code>) y actívalo en Plugins. Después puedes borrar este instalador.</p>';
	}

	private static function where( $scope ) {
		return 'network' === $scope ? 'en toda la red' : ( is_multisite() ? 'en este sitio' : '' );
	}

	public static function render() {
		$scope = self::scope();
		echo '<div class="wrap"><h1>DIGITALÍSIMO Elements · Instalador</h1>';
		if ( '' !== self::test_base() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( 'Origen de pruebas activo: ' . self::test_base() ) . '</p></div>';
		}
		if ( ! empty( $_REQUEST['instalar'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- handle() verifica el nonce.
			self::print_result( self::handle( $scope ), $scope );
		} else {
			self::print_intro( $scope );
		}
		echo '</div>';
	}

	private static function button( $scope, $label ) {
		return '<form method="post" action="' . esc_url( add_query_arg( 'instalar', '1', self::page_url( $scope ) ) ) . '">' . wp_nonce_field( self::NONCE, '_wpnonce', true, false ) . '<p><button type="submit" class="button button-primary">' . esc_html( $label ) . '</button></p></form>';
	}

	private static function print_intro( $scope ) {
		self::admin_includes();
		$found = self::find_elements( get_plugins() );
		if ( '' !== $found ) {
			echo '<p>' . esc_html( 'DIGITALÍSIMO Elements ya está instalado (' . $found . '): no se vuelve a instalar. El botón lo activa ' . ( self::where( $scope ) ?: 'en este sitio' ) . ' si hace falta y retira el instalador.' ) . '</p>';
			echo self::button( $scope, 'Activar DIGITALÍSIMO Elements y retirar el instalador' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en button().
			return;
		}
		echo '<p>' . esc_html( trim( 'Este instalador descarga DIGITALÍSIMO Elements de la última versión publicada directamente en el servidor (sin pasar por el límite de subida del hosting), lo instala, lo activa ' . self::where( $scope ) ) . ' y después se borra solo.' ) . '</p>';
		echo self::button( $scope, 'Instalar DIGITALÍSIMO Elements' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en button().
		echo self::manual_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML fijo y escapado.
	}

	private static function elements_link( $scope ) {
		if ( 'network' === $scope ) {
			return '<a class="button button-primary" href="' . esc_url( network_admin_url( 'plugins.php' ) ) . '">Ver los plugins de la red</a> <a class="button" href="' . esc_url( get_admin_url( get_main_site_id(), 'admin.php?page=elementor' ) ) . '">Ir a DIGITALÍSIMO Elements en el sitio principal</a>';
		}
		return '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=elementor' ) ) . '">Ir a DIGITALÍSIMO Elements</a> <a class="button" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Ver los plugins</a>';
	}

	private static function removed_html( $removed ) {
		if ( true === $removed ) {
			return '<p>El instalador se desactivó y se borró: ya no hace falta.</p>';
		}
		$why = is_wp_error( $removed ) ? ' ' . wp_strip_all_tags( $removed->get_error_message() ) : '';
		return '<p>' . esc_html( 'El instalador no se pudo borrar solo.' . $why . ' Puedes borrarlo desde Plugins: ya no hace falta.' ) . '</p>';
	}

	public static function print_result( $result, $scope ) {
		$status = (string) ( $result['status'] ?? 'error' );
		$where = self::where( $scope );
		if ( 'instalado' === $status || 'ya' === $status ) {
			$version = '' !== (string) ( $result['version'] ?? '' ) ? ' ' . $result['version'] : '';
			$head = 'instalado' === $status
				? 'Listo: DIGITALÍSIMO Elements' . $version . ' está instalado y activo' . ( '' !== $where ? ' ' . $where : '' ) . '.'
				: 'Listo: DIGITALÍSIMO Elements' . $version . ' ya estaba instalado; no se reinstaló' . ( 'activado' === ( $result['activated'] ?? '' ) ? ' y ahora está activo' : ' y ya estaba activo' ) . ( '' !== $where ? ' ' . $where : '' ) . '.';
			echo '<div class="notice notice-success inline"><p><strong>' . esc_html( $head ) . '</strong></p>';
			if ( 'ya' === $status ) {
				echo '<p>Para actualizarlo usa las actualizaciones de WordPress (Escritorio → Actualizaciones, o «Actualizar ahora» en Plugins): DIGITALÍSIMO Elements trae su propio actualizador.</p>';
			}
			echo self::removed_html( $result['removed'] ?? null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en removed_html().
			echo '<p>' . self::elements_link( $scope ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en elements_link().
			return;
		}
		if ( 'credenciales' === $status ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( 'WordPress necesita los datos de acceso a los archivos del servidor (FTP o SSH) para instalar: escríbelos arriba y continúa. Si no los tienes, usa la alternativa manual.' ) . '</p>' . self::manual_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		echo '<div class="notice notice-error inline"><p><strong>No se pudo instalar DIGITALÍSIMO Elements.</strong></p>';
		echo '<p>' . esc_html( 'Causa: ' . (string) ( $result['cause'] ?? 'desconocida' ) ) . '</p>';
		echo self::manual_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p>El instalador sigue en Plugins para volver a intentarlo.</p></div>';
		echo self::button( $scope, 'Reintentar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en button().
	}
}

Digitalisimo_Elements_Installer::boot( __FILE__ );
