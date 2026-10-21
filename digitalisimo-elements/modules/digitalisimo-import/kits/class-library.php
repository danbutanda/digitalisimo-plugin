<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Un ZIP de kit subido en «Plantillas → Importar» va al importador de kits (D12, §10.6).
 *
 * Hoy el núcleo trocea un kit subido ahí como si fuera un ZIP de plantillas, sin aviso. `intercept()` corre antes
 * (`wp_ajax_elementor_library_direct_actions`, prioridad 5) y, si la petición es la importación directa con un
 * nonce válido, la hace alguien con `manage_options` y el ZIP tiene `manifest.json` en la raíz o en el único nivel
 * admitido, prepara el kit como la pantalla «Kits» (`Session::prepare()`: guardián, normalizador y carpeta propia
 * `tmp/<token>/`, CC-16), guarda el token de un solo uso del usuario y lleva a la App
 * (`#/import-customization?referrer=digitalisimo&id=<token>`), que lo pide con
 * `elementor/import/kit/result/digitalisimo` (V-8, `Session::kit_result()`). Siempre en «sólo agregar» (D1).
 *
 * Si no se cumple todo, sigue el camino nativo, con el marcador para lo que este sitio no sabe pintar (`Preserve`).
 * Un ZIP que parece un kit pero el guardián rechaza no sigue al camino nativo: se explica el motivo y se para.
 *
 * Límite declarado: la ventana de la biblioteca dentro del editor no se intercepta.
 */
final class Library {
	const AJAX_ACTION    = 'elementor_library_direct_actions';
	const LIBRARY_ACTION = 'direct_import_template';
	const NONCE_ACTION   = 'elementor_ajax';
	const SURFACE        = 'library';
	const PRIORITY       = 5;

	/** Un token de la preparación: hexadecimal. */
	const TOKEN_PATTERN = '/^[0-9a-f]{32,64}$/';

	/** Entradas que se miran como mucho para decidir si el ZIP es un kit. */
	const MAX_ENTRIES = 10000;

	/** @var bool */
	private static $booted = false;

	/** @var callable|null `( string $zip ): array` con `token`; por defecto, `Session::prepare()` y su token. */
	private static $preparer = null;

	/** @var callable */
	private static $upload_check = 'is_uploaded_file';

	/** Engancha la intercepción. Lo llama `Module::init()`; idempotente. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'intercept' ), self::PRIORITY );
	}

	/**
	 * `wp_ajax_elementor_library_direct_actions` (5): redirige a la App con el kit preparado, o no hace nada y deja
	 * seguir al núcleo.
	 */
	public static function intercept(): void {
		try {
			$url = self::handle( $_REQUEST, $_FILES ); // phpcs:ignore WordPress.Security.NonceVerification -- handle() comprueba el nonce.
		} catch ( \Throwable $error ) {
			self::fail( $error );
			return;
		}
		if ( null === $url ) {
			return;
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Decide y prepara. Devuelve la URL de la App, o null para dejar seguir al núcleo.
	 *
	 * @param array $request `$_REQUEST`.
	 * @param array $files   `$_FILES`.
	 *
	 * @throws \RuntimeException Si es un kit pero no se puede preparar (el mensaje es para el administrador).
	 */
	public static function handle( array $request, array $files ): ?string {
		$action = isset( $request['library_action'] ) && is_string( $request['library_action'] ) ? $request['library_action'] : '';
		if ( self::LIBRARY_ACTION !== $action ) {
			return null;
		}
		// El camino nativo también importa plantillas: el marcador debe existir aunque el sitio aún no tenga ninguno.
		Preserve::expect( true );
		if ( ! current_user_can( Module::CAPABILITY ) ) {
			return null;
		}
		$nonce = isset( $request['_nonce'] ) && is_string( $request['_nonce'] ) ? self::unslash( $request['_nonce'] ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return null;
		}
		$file = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;
		if ( null === $file || ! isset( $file['tmp_name'], $file['name'] ) || ! is_string( $file['tmp_name'] ) || ! is_string( $file['name'] ) || '' === $file['tmp_name'] ) {
			return null;
		}
		if ( isset( $file['error'] ) && 0 !== (int) $file['error'] ) {
			return null;
		}
		if ( 'zip' !== strtolower( (string) pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || ! call_user_func( self::$upload_check, $file['tmp_name'] ) ) {
			return null;
		}
		if ( ! self::looks_like_kit( $file['tmp_name'] ) ) {
			return null;
		}
		return self::app_url( self::prepare( $file['tmp_name'] ) );
	}

	/**
	 * ¿Es un kit y no un ZIP de plantillas? Sin extraer nada: `manifest.json` en la raíz o en una subcarpeta, o un
	 * único ZIP interior (el formato de un marketplace). El guardián decide después si es admisible.
	 */
	public static function looks_like_kit( string $zip ): bool {
		if ( ! class_exists( '\ZipArchive' ) || ! is_file( $zip ) ) {
			return false;
		}
		$archive = new \ZipArchive();
		if ( true !== $archive->open( $zip ) ) {
			return false;
		}
		$manifest = false;
		$inner    = 0;
		$total    = min( (int) $archive->numFiles, self::MAX_ENTRIES );
		for ( $i = 0; $i < $total; $i++ ) {
			$name = $archive->getNameIndex( $i );
			if ( ! is_string( $name ) ) {
				continue;
			}
			$name = trim( str_replace( '\\', '/', $name ), '/' );
			if ( '' === $name || 0 === strpos( $name, '__MACOSX/' ) ) {
				continue;
			}
			$base = basename( $name );
			if ( '' === $base || '.' === $base[0] || substr_count( $name, '/' ) > 1 ) {
				continue;
			}
			if ( 'manifest.json' === strtolower( $base ) ) {
				$manifest = true;
			} elseif ( 'zip' === strtolower( (string) pathinfo( $base, PATHINFO_EXTENSION ) ) ) {
				++$inner;
			}
		}
		$archive->close();
		return $manifest || 1 === $inner;
	}

	/** URL de la App que importa el kit preparado (la misma que usa la pantalla «Kits»). */
	public static function app_url( string $token ): string {
		$session = __NAMESPACE__ . '\\Session';
		if ( is_callable( array( $session, 'app_url' ) ) ) {
			return (string) call_user_func( array( $session, 'app_url' ), $token );
		}
		$base = admin_url( 'admin.php?page=elementor-app' );
		if ( class_exists( '\Elementor\Plugin', false ) && ! empty( \Elementor\Plugin::$instance->app ) && method_exists( \Elementor\Plugin::$instance->app, 'get_base_url' ) ) {
			$base = (string) \Elementor\Plugin::$instance->app->get_base_url();
		}
		return $base . '#/import-customization?referrer=' . rawurlencode( Module::SOURCE ) . '&id=' . rawurlencode( $token );
	}

	/**
	 * Fija quién prepara el kit y devuelve el registro con su `token` (ya guardado para la App).
	 *
	 * @internal Para pruebas; null vuelve a `Session::prepare()`.
	 *
	 * @param callable|null $preparer `( string $zip ): array`.
	 */
	public static function set_preparer( ?callable $preparer ): void {
		self::$preparer = $preparer;
	}

	/**
	 * Cambia la comprobación de que el archivo llegó por una subida HTTP.
	 *
	 * @internal Para pruebas.
	 */
	public static function set_upload_check( ?callable $check ): void {
		self::$upload_check = null === $check ? 'is_uploaded_file' : $check;
	}

	/**
	 * Olvida el estado de la petición.
	 *
	 * @internal Para pruebas.
	 */
	public static function reset(): void {
		self::$booted       = false;
		self::$preparer     = null;
		self::$upload_check = 'is_uploaded_file';
	}

	/**
	 * Prepara el kit como la pantalla «Kits», en «sólo agregar», y guarda su token de un solo uso.
	 *
	 * @throws \RuntimeException Si el importador no está o el kit no es admisible.
	 */
	private static function prepare( string $zip ): string {
		if ( null !== self::$preparer ) {
			$pending = call_user_func( self::$preparer, $zip );
		} else {
			$session = __NAMESPACE__ . '\\Session';
			if ( ! is_callable( array( $session, 'prepare' ) ) || ! is_callable( array( $session, 'remember_token' ) ) ) {
				throw new \RuntimeException( 'El importador de kits no está disponible en este sitio.' );
			}
			$pending = call_user_func(
				array( $session, 'prepare' ),
				$zip,
				array(
					'mode'    => Module::MODE_ADD,
					'surface' => self::SURFACE,
				)
			);
			if ( is_array( $pending ) ) {
				call_user_func( array( $session, 'remember_token' ), $pending );
			}
		}
		$token = is_array( $pending ) && isset( $pending['token'] ) && is_string( $pending['token'] ) ? $pending['token'] : '';
		if ( 1 !== preg_match( self::TOKEN_PATTERN, $token ) ) {
			throw new \RuntimeException( 'No se pudo preparar el kit.' );
		}
		return $token;
	}

	/** Explica por qué no se importó el kit y para: el camino nativo no debe trocear un kit rechazado. */
	private static function fail( \Throwable $error ): void {
		$message = $error instanceof \RuntimeException || $error instanceof \InvalidArgumentException ? $error->getMessage() : 'El ZIP parece un kit, pero no se pudo preparar.';
		$back    = admin_url( 'edit.php?post_type=elementor_library' );
		wp_die(
			'<p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( $back ) . '">' . esc_html( 'Volver a las plantillas' ) . '</a></p>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partes escapadas.
			esc_html( 'No se pudo importar el kit' ),
			array( 'response' => 400 )
		);
	}

	private static function unslash( string $value ): string {
		return function_exists( 'wp_unslash' ) ? (string) wp_unslash( $value ) : stripslashes( $value );
	}
}
