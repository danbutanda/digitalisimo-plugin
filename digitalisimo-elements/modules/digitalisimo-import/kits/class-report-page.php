<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Pantalla «Kits» (`admin.php?page=digitalisimo-elements-kits`), sólo en la administración de cada sitio (§12).
 *
 * - **Importar:** un ZIP y la casilla «Este sitio es nuevo…», que sólo se habilita si el sitio está vacío (D1). La
 *   subida va a `admin-post.php` (`digitalisimo_elements_kit_upload`), que prepara el kit con un token y lleva a la
 *   App (`#/import-customization?referrer=digitalisimo&id=<token>`). Sin la casilla, «sólo agregar».
 * - **Lista e informe:** las sesiones nativas unidas a su detalle (`Report`), con las doce secciones de §12.2.
 * - **Acciones** (`admin-post.php`, nonce por acción, sitio y sesión; vuelven a esta pantalla): «Aplicar los ajustes
 *   del kit» y deshacerlo (D11), «Publicar páginas y plantillas» (D8) y «Revertir» (sólo la última sesión). Ninguna
 *   acción se ofrece ni se acepta sobre una sesión revertida o a medias.
 * - **Avisos:** el aviso único tras importar (meta de usuario), la sección de Herramientas y `assets/digitalisimo/kits.js`
 *   en la App (enlace al informe en «Importación completa»).
 *
 * Diseño: `digitalisimo-ui` (la clase `digitalisimo-admin` la pone su cargador en `?page=digitalisimo*`), sólo con sus
 * componentes. Textos en español, sin enlaces externos ni nombres del constructor original.
 *
 * Cada acción tiene un `process_*()` que valida y hace el trabajo sin redirigir (lo prueban las pruebas unitarias) y
 * un `handle_*()` que lo envuelve para `admin-post.php`.
 */
final class Report_Page {
	/** Revertir desde la pantalla (además de las cuatro acciones de §5.1). */
	const ACTION_REVERT = Module::ACTION_REVERT;

	const FILE_FIELD   = 'kit';
	const NOTICE_PARAM = 'kits_notice';
	const COUNT_PARAM  = 'kits_count';
	const FLASH_PREFIX = 'digitalisimo_elements_kit_flash_';
	const SCRIPT       = 'digitalisimo-elements-kits';
	const TOOLS_PAGE   = 'elementor-tools';
	const TOOLS_TAB    = 'import-export-kit';
	const MAX_PUBLISH  = 500;

	/** Avisos tras una acción: código => [ tipo de aviso, texto ]. */
	const NOTICES = array(
		'forbidden'            => array( 'error', 'No tienes permiso para gestionar kits en este sitio.' ),
		'nonce'                => array( 'error', 'El formulario caducó. Vuelve a la pantalla «Kits» e inténtalo de nuevo.' ),
		'method'               => array( 'error', 'Esta acción sólo se envía desde el formulario de la pantalla «Kits».' ),
		'session'              => array( 'error', 'Esa importación no es de este sitio.' ),
		'reverted'             => array( 'error', 'Esa importación se revirtió: ya no admite acciones.' ),
		'in-progress'          => array( 'error', 'Esa importación no terminó: no admite acciones.' ),
		'no-detail'            => array( 'error', 'Esa importación no tiene informe de DIGITALÍSIMO Elements.' ),
		'importer'             => array( 'error', 'El importador de kits no está disponible en este sitio.' ),
		'upload-missing'       => array( 'error', 'Elige el archivo ZIP del kit.' ),
		'upload-error'         => array( 'error', 'El archivo no se pudo subir. Prueba otra vez o importa el kit con WP-CLI.' ),
		'upload-type'          => array( 'error', 'El kit debe ser un archivo ZIP.' ),
		'upload-failed'        => array( 'error', 'El kit no se pudo preparar.' ),
		'not-empty'            => array( 'error', 'Este sitio no está vacío: el kit sólo se puede importar en «sólo agregar».' ),
		'settings-none'        => array( 'error', 'Esta importación no dejó ajustes del kit por aplicar.' ),
		'settings-applied'     => array( 'error', 'Los ajustes del kit ya se aplicaron.' ),
		'settings-ok'          => array( 'success', 'Se aplicaron los ajustes del kit. Puedes deshacerlo mientras no edites los ajustes del sitio.' ),
		'settings-failed'      => array( 'error', 'Los ajustes del kit no se pudieron guardar.' ),
		'settings-not-applied' => array( 'error', 'Los ajustes del kit no se aplicaron desde esta pantalla: no hay nada que deshacer.' ),
		'settings-changed'     => array( 'error', 'Los ajustes del sitio cambiaron después de aplicar el kit: no se deshace para no perder esos cambios.' ),
		'unapply-ok'           => array( 'success', 'Se restauraron los ajustes del sitio que había antes de aplicar el kit.' ),
		'kit-missing'          => array( 'error', 'Este sitio no tiene un kit activo de DIGITALÍSIMO Elements.' ),
		'publish-none'         => array( 'warning', 'No queda nada de esta importación que se pueda publicar desde aquí.' ),
		'publish-ok'           => array( 'success', 'Se publicaron las páginas y plantillas de la importación.' ),
		'publish-partial'      => array( 'warning', 'Se publicó parte de la importación: algunos documentos no se pudieron publicar.' ),
		'revert-not-last'      => array( 'error', 'Sólo se puede revertir la última importación.' ),
		'revert-confirm'       => array( 'error', 'Marca la casilla para confirmar que quieres revertir la importación.' ),
		'revert-ok'            => array( 'success', 'Se revirtió la importación.' ),
		'revert-failed'        => array( 'error', 'La importación no se pudo revertir.' ),
	);

	/** @var bool */
	private static $initialized = false;

	/**
	 * Ganchos de la pantalla. Fuera de la administración no engancha nada; en ella, sólo `add_action`.
	 * Lo llama `Module::init()`.
	 */
	public static function init(): void {
		if ( self::$initialized || ! is_admin() ) {
			return;
		}
		self::$initialized = true;
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_post_' . Module::ACTION_UPLOAD, array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_' . Module::ACTION_APPLY_SETTINGS, array( __CLASS__, 'handle_apply_settings' ) );
		add_action( 'admin_post_' . Module::ACTION_UNAPPLY_SETTINGS, array( __CLASS__, 'handle_unapply_settings' ) );
		add_action( 'admin_post_' . Module::ACTION_PUBLISH, array( __CLASS__, 'handle_publish' ) );
		add_action( 'admin_post_' . self::ACTION_REVERT, array( __CLASS__, 'handle_revert' ) );
		add_action( 'elementor/admin/after_create_settings/' . self::TOOLS_PAGE, array( __CLASS__, 'tools_section' ), 20 );
		add_action( 'elementor/app/init', array( __CLASS__, 'enqueue_app_script' ) );
	}

	/** URL de la pantalla en este sitio, con la sesión y los parámetros que se pidan. */
	public static function url( string $session = '', array $args = array() ): string {
		$query = array( 'page' => Module::PAGE );
		if ( Report::is_session_id( $session ) ) {
			$query['session'] = $session;
		}
		return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
	}

	/** URL de la App del constructor con una ruta (`#/…`). */
	public static function app_url( string $route ): string {
		$base = admin_url( 'admin.php?page=elementor-app' );
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->app ) && is_object( \Elementor\Plugin::$instance->app ) && method_exists( \Elementor\Plugin::$instance->app, 'get_base_url' ) ) {
			$base = (string) \Elementor\Plugin::$instance->app->get_base_url();
		}
		return $base . $route;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Menú, avisos y Herramientas.

	/** Sólo en el sitio (nunca en la red), junto a «Acerca de» de DIGITALÍSIMO Elements. */
	public static function menu(): void {
		$parent = self::parent_menu();
		$title  = 'digitalisimo' === $parent ? 'Kits de Elements' : 'Kits';
		add_submenu_page( $parent, 'Kits · DIGITALÍSIMO Elements', $title, Module::CAPABILITY, Module::PAGE, array( __CLASS__, 'render' ) );
	}

	private static function parent_menu(): string {
		global $menu;
		$slugs = array();
		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && is_string( $item[2] ) ) {
				$slugs[ $item[2] ] = true;
			}
		}
		$about = class_exists( '\Digitalisimo_Elements_Brand' ) ? \Digitalisimo_Elements_Brand::PAGE : 'digitalisimo-elements-about';
		foreach ( array( 'digitalisimo', 'elementor-home', 'elementor', $about ) as $slug ) {
			if ( isset( $slugs[ $slug ] ) ) {
				return $slug;
			}
		}
		return 'tools.php';
	}

	/** Aviso único tras importar (§12.3): se lee de la meta del usuario, ya cargada, y se borra al mostrarse. */
	public static function admin_notice(): void {
		if ( is_network_admin() || ! current_user_can( Module::CAPABILITY ) ) {
			return;
		}
		$user  = get_current_user_id();
		$value = get_user_meta( $user, Module::USER_META_NOTICE, true );
		if ( empty( $value ) ) {
			return;
		}
		delete_user_meta( $user, Module::USER_META_NOTICE );
		if ( self::on_page() ) {
			return;
		}
		$session = is_array( $value ) && isset( $value['session'] ) ? (string) $value['session'] : ( is_string( $value ) ? $value : '' );
		$row     = Report::is_session_id( $session ) ? Report::session( $session ) : null;
		if ( null === $row ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( sprintf( 'Terminó la importación del kit «%s» con DIGITALÍSIMO Elements.', $row['title'] ) ),
			esc_url( self::url( $session ) ),
			esc_html( 'Ver el informe' )
		);
	}

	/**
	 * Sección «Kits de DIGITALÍSIMO Elements» en la pestaña de kits de Herramientas (§12.3). Si esa pestaña no existe,
	 * no se añade (el núcleo la pondría en la primera pestaña).
	 *
	 * @param object $tools Página de Herramientas del núcleo.
	 */
	public static function tools_section( $tools ): void {
		if ( ! is_object( $tools ) || ! method_exists( $tools, 'add_section' ) || ! method_exists( $tools, 'get_tabs' ) || ! current_user_can( Module::CAPABILITY ) ) {
			return;
		}
		$tabs = $tools->get_tabs();
		if ( ! is_array( $tabs ) || ! isset( $tabs[ self::TOOLS_TAB ] ) ) {
			return;
		}
		$tools->add_section(
			self::TOOLS_TAB,
			'digitalisimo-kits',
			array(
				'label'    => 'Kits de DIGITALÍSIMO Elements',
				'callback' => array( __CLASS__, 'render_tools_section' ),
				'fields'   => array(),
			)
		);
	}

	public static function render_tools_section(): void {
		echo '<p>' . esc_html( 'Importar un kit nunca instala ni activa plugins o temas ni cambia experimentos. Por defecto entra en «sólo agregar»: no cambia nada de lo que ya tiene el sitio y lo que se ve queda en borrador.' ) . '</p>';
		echo '<p>' . esc_html( 'En la pantalla «Kits» están el informe de cada importación, la importación como sitio nuevo, «Aplicar los ajustes del kit» y «Publicar páginas y plantillas». Los kits grandes se importan con «wp digitalisimo-elements kit import».' ) . '</p>';
		if ( ! self::bundled() ) {
			echo '<p>' . esc_html( 'Con el plugin del constructor original activo, su importador heredado y su comando de WP-CLI siguen funcionando como él los hace: usa la pantalla «Kits» o «wp digitalisimo-elements kit».' ) . '</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( self::url() ) . '">' . esc_html( 'Abrir Kits' ) . '</a></p>';
	}

	/** `kits.js` en la App (sólo con el núcleo incluido): el enlace al informe en «Importación completa». */
	public static function enqueue_app_script(): void {
		if ( ! self::bundled() || ! current_user_can( Module::CAPABILITY ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/digitalisimo/kits.js', DIGITALISIMO_ELEMENTS_FILE ), array(), $version, true );
		wp_add_inline_script(
			self::SCRIPT,
			'window.digitalisimoKits=' . wp_json_encode(
				array(
					'report' => self::url(),
					'intro'  => 'Qué se importó, qué quedó en borrador y qué falta configurar:',
					'label'  => 'ver el informe en Kits',
				)
			) . ';',
			'before'
		);
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Pantalla.

	public static function render(): void {
		if ( is_network_admin() || ! current_user_can( Module::CAPABILITY ) ) {
			wp_die( esc_html( self::NOTICES['forbidden'][1] ), '', array( 'response' => 403 ) );
		}
		$sessions = Report::sessions();
		$current  = self::current_session( $sessions );
		echo '<div class="wrap digitalisimo-elements-kits">';
		echo '<h1>' . esc_html( 'Kits' ) . '</h1>';
		echo '<p>' . esc_html( 'Importa y exporta kits con DIGITALÍSIMO Elements. Importar nunca instala ni activa plugins o temas, ni cambia experimentos. Por defecto, un kit entra en «sólo agregar»: no cambia nada de lo que ya tiene el sitio y lo que se ve queda en borrador para que lo revises.' ) . ' ';
		echo '<a href="' . esc_url( self::app_url( '#/export-customization' ) ) . '">' . esc_html( 'Exportar este sitio como kit' ) . '</a></p>';
		self::render_notices();
		self::render_import_form();
		self::render_list( $sessions, $current );
		if ( '' !== $current ) {
			self::render_detail( $current );
		}
		echo '</div>';
	}

	/** Sesión que se muestra: la pedida si es de este sitio; si no, la más reciente. */
	private static function current_session( array $sessions ): string {
		$asked = isset( $_GET['session'] ) ? sanitize_text_field( wp_unslash( $_GET['session'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige qué informe mostrar.
		foreach ( $sessions as $row ) {
			if ( $row['id'] === $asked ) {
				return $asked;
			}
		}
		return $sessions ? $sessions[0]['id'] : '';
	}

	private static function render_notices(): void {
		$code  = isset( $_GET[ self::NOTICE_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el texto del aviso.
		$count = isset( $_GET[ self::COUNT_PARAM ] ) ? absint( $_GET[ self::COUNT_PARAM ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flash = self::take_flash();
		if ( ! isset( self::NOTICES[ $code ] ) && ! $flash ) {
			return;
		}
		list( $type, $text ) = isset( self::NOTICES[ $code ] ) ? self::NOTICES[ $code ] : array( 'warning', '' );
		if ( $count > 0 && in_array( $code, array( 'publish-ok', 'publish-partial' ), true ) ) {
			$text .= ' ' . sprintf( 'Documentos publicados: %d.', $count );
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . '">';
		if ( '' !== $text ) {
			echo '<p>' . esc_html( $text ) . '</p>';
		}
		if ( $flash ) {
			echo '<ul class="ul-disc">';
			foreach ( $flash as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	private static function render_import_form(): void {
		$blockers = Report::fresh_blockers();
		$max      = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : 0;
		echo '<h2>' . esc_html( 'Importar un kit' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="' . esc_attr( Module::ACTION_UPLOAD ) . '">';
		wp_nonce_field( self::nonce_action( Module::ACTION_UPLOAD, '' ) );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="digitalisimo-kit-file">' . esc_html( 'Archivo del kit' ) . '</label></th><td>';
		echo '<input type="file" id="digitalisimo-kit-file" name="' . esc_attr( self::FILE_FIELD ) . '" accept=".zip,application/zip" required aria-describedby="digitalisimo-kit-file-help">';
		echo '<p class="description" id="digitalisimo-kit-file-help">' . esc_html( 'Un ZIP exportado desde DIGITALÍSIMO Elements o desde otro sitio con el constructor (formatos 1.x, 2.x y 3.0), o un Template Kit, que siempre entra en «sólo agregar».' );
		if ( $max > 0 ) {
			echo ' ' . esc_html( sprintf( 'Tamaño máximo: %s. Los kits más grandes se importan con «wp digitalisimo-elements kit import».', size_format( $max ) ) );
		}
		echo '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html( 'Sitio nuevo' ) . '</th><td>';
		echo '<label><input type="checkbox" name="fresh" value="1"' . ( $blockers ? ' disabled' : '' ) . ' aria-describedby="digitalisimo-kit-fresh-help"> ' . esc_html( 'Este sitio es nuevo: aplicar también los ajustes del kit, su portada y publicar' ) . '</label>';
		echo '<div id="digitalisimo-kit-fresh-help">';
		if ( $blockers ) {
			echo '<p class="description">' . esc_html( 'No disponible: sólo un sitio vacío puede importar un kit como sitio nuevo.' ) . '</p><ul class="ul-disc">';
			foreach ( $blockers as $reason ) {
				echo '<li>' . esc_html( $reason ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="description">' . esc_html( 'El sitio está vacío. Sin la casilla, el kit entra en «sólo agregar». Nunca se cambian el tema, los plugins ni los experimentos.' ) . '</p>';
		}
		echo '</div></td></tr></tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html( 'Importar el kit' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_list( array $sessions, string $current ): void {
		echo '<h2>' . esc_html( 'Importaciones' ) . '</h2>';
		if ( ! $sessions ) {
			echo '<p>' . esc_html( 'Todavía no se importó ningún kit en este sitio.' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Fecha', 'Kit', 'Modo', 'Origen', 'Creado', 'Estado' ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html( 'Informe' ) . '</span></th></tr></thead><tbody>';
		foreach ( $sessions as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( self::date( $row['time'] ) ) . '</td>';
			echo '<td>' . esc_html( $row['title'] ) . '</td>';
			echo '<td>' . esc_html( Report::mode_label( $row['mode'] ) ) . '</td>';
			echo '<td>' . esc_html( $row['detail'] ? Report::format_label( $row['format'] ) : '—' ) . '</td>';
			echo '<td>' . esc_html( (string) ( Report::STATUS_REVERTED === $row['status'] ? $row['trashed'] : $row['created'] ) ) . '</td>';
			echo '<td>' . self::status_badge( $row['status'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en status_badge().
			if ( $row['id'] === $current ) {
				echo '<td><span aria-current="true">' . esc_html( 'Viendo el informe' ) . '</span></td>';
			} else {
				echo '<td><a href="' . esc_url( self::url( $row['id'] ) ) . '">' . esc_html( 'Ver el informe' ) . '</a></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function status_badge( string $status ): string {
		$labels = array(
			Report::STATUS_IMPORTED    => array( 'is-active', 'Importada' ),
			Report::STATUS_IN_PROGRESS => array( 'is-inactive', 'Sin terminar' ),
			Report::STATUS_REVERTED    => array( 'is-inactive', 'Revertida' ),
		);
		list( $class, $text ) = isset( $labels[ $status ] ) ? $labels[ $status ] : $labels[ Report::STATUS_IN_PROGRESS ];
		return '<span class="digitalisimo-effective-state ' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
	}

	/** El informe de una sesión, en el orden de §12.2. */
	private static function render_detail( string $session ): void {
		$row    = Report::session( $session );
		$detail = Report::detail( $session );
		if ( null === $row ) {
			return;
		}
		$groups = $detail ? Report::group( $detail['report'] ) : array();
		$levels = $detail ? Report::levels( $detail['report'] ) : Report::levels( array() );
		echo '<h2>' . esc_html( sprintf( 'Informe: %s', $row['title'] ) ) . '</h2>';

		// 1. Resumen.
		echo '<ul>';
		echo '<li><strong>' . esc_html( 'Modo' ) . '</strong><br>' . esc_html( Report::mode_label( $row['mode'] ) ) . '</li>';
		echo '<li><strong>' . esc_html( 'Origen' ) . '</strong><br>' . esc_html( $detail ? Report::format_label( $row['format'] ) : '—' ) . '</li>';
		echo '<li><strong>' . esc_html( 'Fecha' ) . '</strong><br>' . esc_html( self::date( $row['time'] ) ) . '</li>';
		echo '<li><strong>' . esc_html( Report::STATUS_REVERTED === $row['status'] ? 'En la papelera' : 'Creado' ) . '</strong><br>' . esc_html( (string) ( Report::STATUS_REVERTED === $row['status'] ? $row['trashed'] : $row['created'] ) ) . '</li>';
		echo '<li><strong>' . esc_html( 'Avisos' ) . '</strong><br>' . esc_html( sprintf( '%d errores · %d avisos', $levels['error'], $levels['warning'] ) ) . '</li>';
		echo '</ul>';
		echo '<p>' . esc_html( $detail ? Report::mode_reason( $detail ) : Report::mode_reason( array() ) ) . '</p>';
		if ( $levels['error'] > 0 ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( sprintf( 'La importación dejó %d errores: están en las secciones de abajo.', $levels['error'] ) ) . '</p></div>';
		}
		if ( $detail && $detail['report']['dropped'] > 0 ) {
			echo '<p>' . esc_html( sprintf( '%d avisos más no caben en el informe; sólo se cuentan.', $detail['report']['dropped'] ) ) . '</p>';
		}
		if ( ! $detail ) {
			self::render_revert( $session, $row );
			return;
		}
		$actions  = $row['actions'];
		$analysis = Report::analysis_lines( $detail );

		// 2. Revisar antes de publicar.
		$review = Report::review( $detail );
		if ( $review || isset( $groups['review'] ) ) {
			self::section_open( 'review' );
			echo '<p>' . esc_html( 'Quedaron en borrador porque una regla que decide quién los ve o dónde se aplican quedó sin destino. «Publicar páginas y plantillas» no los publica hasta que los edites.' ) . '</p>';
			echo '<ul class="ul-disc">';
			foreach ( $review as $post => $items ) {
				echo '<li>' . self::post_html( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en post_html().
				$reasons = array();
				foreach ( $items as $item ) {
					$reason    = isset( $item['reason'] ) ? (string) $item['reason'] : '';
					$label     = Report::code_label( $reason );
					$data      = Report::flatten( isset( $item['data'] ) && is_array( $item['data'] ) ? Report::public_value( $item['data'] ) : array() );
					$text      = '' !== $label ? $label : $reason;
					$reasons[] = $data ? $text . ' (' . implode( ' · ', self::pairs( $data ) ) . ')' : $text;
				}
				if ( $reasons ) {
					echo ': ' . esc_html( implode( '; ', array_filter( $reasons ) ) );
				}
				echo '</li>';
			}
			echo '</ul>';
			self::render_entries( $groups, 'review', array( 'review' ) );
			self::section_close();
		}

		// 3. Conservado sin mostrar.
		if ( isset( $groups['preserved'] ) || isset( $analysis['preserved'] ) ) {
			self::section_open( 'preserved' );
			echo '<p>' . esc_html( 'Se guardó tal cual, sin mostrarse en el sitio: vuelve solo cuando este sitio tenga lo que necesita, o se puede convertir más adelante.' ) . '</p>';
			self::render_lines( $analysis, 'preserved' );
			self::render_entries( $groups, 'preserved' );
			self::section_close();
		}

		// 4. Lo que el kit pedía y no se aplicó.
		$requested = Report::requested( $detail );
		if ( $requested['theme'] || $requested['experiments'] || $requested['plugins'] || isset( $groups['requested'] ) || isset( $analysis['requested'] ) ) {
			self::section_open( 'requested' );
			self::render_lines( array( 'requested' => Report::requested_lines( $requested ) ), 'requested' );
			self::render_lines( $analysis, 'requested' );
			self::render_entries( $groups, 'requested' );
			self::section_close();
		}

		// 5 a 7. Referencias sin destino, medios y URL del origen.
		foreach ( array( 'unresolved', 'media', 'urls' ) as $section ) {
			if ( isset( $groups[ $section ] ) || isset( $analysis[ $section ] ) ) {
				self::section_open( $section );
				self::render_lines( $analysis, $section );
				self::render_entries( $groups, $section );
				self::section_close();
			}
		}

		// 8. No exportado en el origen.
		$not_exported = Report::not_exported( $detail );
		if ( $not_exported || isset( $groups['not-exported'] ) ) {
			self::section_open( 'not-exported' );
			$lines = array();
			foreach ( $not_exported as $reason => $count ) {
				$lines[] = Report::not_exported_label( $reason ) . ': ' . $count . '.';
			}
			self::render_lines( array( 'not-exported' => $lines ), 'not-exported' );
			self::render_entries( $groups, 'not-exported' );
			self::section_close();
		}

		// 9. Configurar después.
		if ( isset( $groups['configure'] ) || isset( $analysis['configure'] ) ) {
			self::section_open( 'configure' );
			self::render_lines( $analysis, 'configure' );
			self::render_entries( $groups, 'configure' );
			self::section_close();
		}

		// 10. Ajustes del kit no aplicados.
		$pending = Report::settings_pending( $detail );
		$applied = Report::settings_applied( $detail );
		if ( $pending || $applied || isset( $groups['settings'] ) ) {
			self::section_open( 'settings' );
			if ( $pending ) {
				echo '<p>' . esc_html( 'En «sólo agregar» sólo se añadieron los colores y las tipografías que usa el contenido. El resto de los ajustes del kit (estilo del tema, CSS, anchos y puntos de corte) quedó en el respaldo. Aplicarlos cambia el aspecto de todo el sitio; se puede deshacer.' ) . '</p>';
				if ( $actions ) {
					self::action_form( Module::ACTION_APPLY_SETTINGS, $session, 'Aplicar los ajustes del kit', true );
				}
			} elseif ( $applied ) {
				$when = isset( $detail['applied'] ) && is_numeric( $detail['applied'] ) ? (int) $detail['applied'] : 0;
				echo '<p>' . esc_html( $when > 0 ? sprintf( 'Los ajustes del kit se aplicaron el %s. Deshacerlo restaura los de antes si nadie cambió los ajustes del sitio después.', self::date( $when, true ) ) : 'Los ajustes del kit se aplicaron. Deshacerlo restaura los de antes si nadie cambió los ajustes del sitio después.' ) . '</p>';
				if ( $actions ) {
					self::action_form( Module::ACTION_UNAPPLY_SETTINGS, $session, 'Deshacer los ajustes del kit', false );
				}
			}
			self::render_entries( $groups, 'settings' );
			self::section_close();
		}

		// 11. Borradores.
		$publishable = $actions ? self::publishable( $session, $detail ) : array();
		if ( $publishable || isset( $groups['drafts'] ) || ( $actions && Module::MODE_ADD === $detail['mode'] ) ) {
			self::section_open( 'drafts' );
			if ( $publishable ) {
				echo '<p>' . esc_html( sprintf( 'Quedan %d páginas y plantillas de esta importación en borrador. Las plantillas del Theme Builder, los popups y los botones flotantes se publican desde el Theme Builder.', count( $publishable ) ) ) . '</p>';
				self::action_form( Module::ACTION_PUBLISH, $session, 'Publicar páginas y plantillas', true );
			} else {
				echo '<p>' . esc_html( 'No queda nada de esta importación que se pueda publicar desde aquí. Las plantillas del Theme Builder, los popups y los botones flotantes se publican desde el Theme Builder.' ) . '</p>';
			}
			echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=elementor_library&tabs_group=theme' ) ) . '">' . esc_html( 'Abrir el Theme Builder' ) . '</a></p>';
			self::render_entries( $groups, 'drafts' );
			self::section_close();
		}

		// 12. Papelera tras revertir.
		if ( Report::STATUS_REVERTED === $row['status'] || isset( $groups['trash'] ) ) {
			self::section_open( 'trash' );
			if ( Report::STATUS_REVERTED === $row['status'] ) {
				echo '<p>' . esc_html( sprintf( 'Al revertir, %d documentos de esta importación fueron a la papelera, donde se pueden recuperar. Los archivos de medios que importó se borraron.', $row['trashed'] ) ) . '</p>';
			}
			self::render_entries( $groups, 'trash' );
			self::section_close();
		}

		if ( isset( $groups['other'] ) || isset( $analysis['other'] ) ) {
			self::section_open( 'other' );
			self::render_lines( $analysis, 'other' );
			self::render_entries( $groups, 'other' );
			self::section_close();
		}

		self::render_revert( $session, $row );
	}

	private static function render_revert( string $session, array $row ): void {
		if ( empty( $row['revert'] ) ) {
			return;
		}
		echo '<h2>' . esc_html( 'Revertir esta importación' ) . '</h2>';
		echo '<p>' . esc_html( 'Manda a la papelera los documentos que creó esta importación, borra los archivos de medios que importó y restaura los ajustes del kit que había antes. Lo que ya existía y lo que reutilizó no se toca. Sólo se puede revertir la última importación.' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_REVERT ) . '">';
		echo '<input type="hidden" name="session" value="' . esc_attr( $session ) . '">';
		wp_nonce_field( self::nonce_action( self::ACTION_REVERT, $session ) );
		echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html( 'Entiendo que se revierte toda la importación.' ) . '</label></p>';
		echo '<p><button type="submit" class="button">' . esc_html( 'Revertir' ) . '</button></p>';
		echo '</form>';
	}

	private static function section_open( string $section ): void {
		echo '<section class="digitalisimo-kit-section" id="' . esc_attr( 'kit-' . $section ) . '">';
		echo '<h3>' . esc_html( Report::SECTIONS[ $section ] ) . '</h3>';
	}

	private static function section_close(): void {
		echo '</section>';
	}

	/** Líneas del análisis de una sección (`Report::analysis_lines()`). */
	private static function render_lines( array $lines, string $section ): void {
		if ( empty( $lines[ $section ] ) ) {
			return;
		}
		echo '<ul class="ul-disc">';
		foreach ( $lines[ $section ] as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Avisos y contadores de una sección.
	 *
	 * @param array    $groups Salida de `Report::group()`.
	 * @param string   $section Sección.
	 * @param string[] $skip   Códigos que la sección ya mostró de otra forma.
	 */
	private static function render_entries( array $groups, string $section, array $skip = array() ): void {
		if ( ! isset( $groups[ $section ] ) ) {
			return;
		}
		$items = array();
		foreach ( $groups[ $section ]['counts'] as $code => $count ) {
			$label   = Report::code_label( $code );
			$items[] = esc_html( ( '' === $label ? $code : $label ) . ': ' . $count );
		}
		foreach ( $groups[ $section ]['entries'] as $entry ) {
			if ( in_array( $entry['code'], $skip, true ) ) {
				continue;
			}
			$items[] = self::entry_html( $entry );
		}
		if ( ! $items ) {
			return;
		}
		echo '<ul class="ul-disc"><li>' . implode( '</li><li>', $items ) . '</li></ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada elemento ya está escapado.
	}

	/** Un aviso: nivel, texto, código y datos, con los documentos del sitio enlazados a su edición. Escapado. */
	private static function entry_html( array $entry ): string {
		$label = Report::code_label( $entry['code'] );
		$html  = 'info' === $entry['level'] ? '' : '<strong>' . esc_html( Report::level_label( $entry['level'] ) ) . ':</strong> ';
		$html .= esc_html( '' === $label ? $entry['code'] : $label ) . ' <code>' . esc_html( $entry['code'] ) . '</code>';
		$data  = $entry['data'];
		$post  = isset( $data['post'] ) && is_numeric( $data['post'] ) ? (int) $data['post'] : 0;
		unset( $data['post'] );
		if ( $post > 0 ) {
			$html .= ' · ' . self::post_html( $post );
		}
		$pairs = self::pairs( Report::flatten( Report::public_value( $data ) ) );
		if ( $pairs ) {
			$html .= ' · ' . esc_html( implode( ' · ', $pairs ) );
		}
		return $html;
	}

	/** «clave: valor» de cada par. */
	private static function pairs( array $flat ): array {
		$out = array();
		foreach ( $flat as $key => $value ) {
			$out[] = $key . ': ' . $value;
		}
		return $out;
	}

	/** Título de un documento de este sitio, enlazado a su edición si el usuario puede editarlo. Escapado. */
	private static function post_html( int $post ): string {
		$object = get_post( $post );
		if ( ! $object ) {
			return esc_html( sprintf( 'documento %d (ya no existe)', $post ) );
		}
		$title = Report::brand( '' !== trim( (string) $object->post_title ) ? (string) $object->post_title : sprintf( 'Sin título (%d)', $post ) );
		$link  = current_user_can( 'edit_post', $post ) ? get_edit_post_link( $post, 'raw' ) : '';
		return $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
	}

	/** Formulario de una acción sobre una sesión. */
	private static function action_form( string $action, string $session, string $label, bool $primary ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="session" value="' . esc_attr( $session ) . '">';
		wp_nonce_field( self::nonce_action( $action, $session ) );
		echo '<p><button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></p>';
		echo '</form>';
	}

	private static function date( int $timestamp, bool $utc = false ): string {
		if ( $timestamp <= 0 ) {
			return '—';
		}
		$format = trim( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ) );
		// Las sesiones nativas guardan `current_time( 'timestamp' )` (hora local); lo que guarda K es UTC.
		return $utc && function_exists( 'wp_date' ) ? (string) wp_date( $format, $timestamp ) : (string) date_i18n( $format, $timestamp );
	}

	private static function on_page(): bool {
		return isset( $_GET['page'] ) && Module::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Acciones.

	/** Acción del nonce: acción, sitio y sesión. Un nonce de otro sitio de la red o de otra sesión no vale. */
	public static function nonce_action( string $action, string $session ): string {
		return $action . '|' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ) . '|' . $session;
	}

	public static function handle_upload(): void {
		self::finish( self::process_upload() );
	}

	public static function handle_apply_settings(): void {
		self::finish( self::process_apply_settings() );
	}

	public static function handle_unapply_settings(): void {
		self::finish( self::process_unapply_settings() );
	}

	public static function handle_publish(): void {
		self::finish( self::process_publish() );
	}

	public static function handle_revert(): void {
		self::finish( self::process_revert() );
	}

	/**
	 * Subida (§7.2 regla 4): comprueba D1 si se pide «nuevo», prepara el kit con el importador (guardián,
	 * normalizador, temporales y token de un solo uso que guarda el modo) y devuelve la URL de la App.
	 *
	 * @return array `{ redirect }` o `{ notice, flash?, status? }`.
	 */
	public static function process_upload(): array {
		$check = self::check_request( Module::ACTION_UPLOAD, '' );
		if ( $check ) {
			return $check;
		}
		$mode = empty( $_POST['fresh'] ) ? Module::MODE_ADD : Module::MODE_FRESH; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- comprobado en check_request().
		if ( Module::MODE_FRESH === $mode ) {
			$blockers = Report::fresh_blockers();
			if ( $blockers ) {
				return array(
					'notice' => 'not-empty',
					'flash'  => $blockers,
				);
			}
		}
		$file = isset( $_FILES[ self::FILE_FIELD ] ) && is_array( $_FILES[ self::FILE_FIELD ] ) ? $_FILES[ self::FILE_FIELD ] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- se valida abajo.
		if ( empty( $file['tmp_name'] ) || ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] ) ) {
			return array( 'notice' => 'upload-missing' );
		}
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array( 'notice' => 'upload-error' );
		}
		$name = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
		if ( 1 !== preg_match( '/\.zip$/i', $name ) || empty( $file['size'] ) ) {
			return array( 'notice' => 'upload-type' );
		}
		if ( ! self::has( 'Session', array( 'prepare', 'remember_token', 'delete_tmp', 'app_url' ) ) ) {
			return array( 'notice' => 'importer' );
		}
		try {
			$pending = Session::prepare(
				$file['tmp_name'],
				array(
					'mode'    => $mode,
					'surface' => 'screen',
				)
			);
		} catch ( \Throwable $error ) {
			return array(
				'notice' => 'upload-failed',
				'flash'  => array( self::error_text( $error ) ),
			);
		}
		$token = isset( $pending['token'] ) && is_string( $pending['token'] ) ? $pending['token'] : '';
		if ( '' === $token ) {
			return array( 'notice' => 'upload-failed' );
		}
		if ( Module::MODE_FRESH === $mode && ! empty( $pending['error'] ) ) {
			// Un Template Kit, o un sitio que dejó de estar vacío: no se importa en otro modo sin que se pida.
			Session::delete_tmp( $token );
			return array(
				'notice' => 'not-empty',
				'flash'  => array( (string) $pending['error'] ),
			);
		}
		Session::remember_token( $pending );
		return array( 'redirect' => (string) Session::app_url( $token ) );
	}

	/**
	 * «Aplicar los ajustes del kit» (D11): lo hace `Runners\Site_Settings::apply()` (respaldo, medios del kit con el
	 * contexto de K y `$kit->save()`). Una vez por sesión.
	 */
	public static function process_apply_settings(): array {
		$request = self::session_request( Module::ACTION_APPLY_SETTINGS, array( 'edit_theme_options' ) );
		if ( isset( $request['notice'] ) ) {
			return $request;
		}
		$session = $request['session'];
		$detail  = $request['detail'];
		if ( Report::settings_applied( $detail ) ) {
			return self::result( 'settings-applied', $session );
		}
		if ( ! Report::settings_pending( $detail ) ) {
			return self::result( 'settings-none', $session );
		}
		if ( ! self::has( 'Runners\\Site_Settings', array( 'apply' ) ) ) {
			return self::result( 'importer', $session );
		}
		return self::outcome( Runners\Site_Settings::apply( $session ), 'settings-ok', 'settings-failed', $session );
	}

	/** Deshace «Aplicar los ajustes del kit» si los ajustes del kit activo no cambiaron después («no pisar»). */
	public static function process_unapply_settings(): array {
		$request = self::session_request( Module::ACTION_UNAPPLY_SETTINGS, array( 'edit_theme_options' ) );
		if ( isset( $request['notice'] ) ) {
			return $request;
		}
		$session = $request['session'];
		if ( ! Report::settings_applied( $request['detail'] ) ) {
			return self::result( 'settings-not-applied', $session );
		}
		if ( ! self::has( 'Runners\\Site_Settings', array( 'unapply' ) ) ) {
			return self::result( 'importer', $session );
		}
		return self::outcome( Runners\Site_Settings::unapply( $session ), 'unapply-ok', 'settings-changed', $session );
	}

	/** Aviso de una operación del importador: `true` es el éxito; un `WP_Error`, el aviso de error con su mensaje. */
	private static function outcome( $done, string $ok, string $failed, string $session ): array {
		if ( true === $done || ( ! is_wp_error( $done ) && false !== $done && null !== $done ) ) {
			return self::result( $ok, $session );
		}
		$result = self::result( $failed, $session );
		if ( is_wp_error( $done ) && '' !== trim( (string) $done->get_error_message() ) ) {
			$result['flash'] = array( wp_strip_all_tags( (string) $done->get_error_message() ) );
		}
		return $result;
	}

	/**
	 * «Publicar páginas y plantillas» (D8): publica lo visible que la sesión creó y sigue en borrador, con
	 * `publish_post` por entrada. Nunca el Theme Builder, los popups, los botones flotantes ni lo pendiente de revisar.
	 */
	public static function process_publish(): array {
		$request = self::session_request( Module::ACTION_PUBLISH );
		if ( isset( $request['notice'] ) ) {
			return $request;
		}
		$session = $request['session'];
		$detail  = $request['detail'];
		$ids     = self::publishable( $session, $detail );
		if ( ! $ids ) {
			return self::result( 'publish-none', $session );
		}
		$done   = array();
		$failed = 0;
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'publish_post', $id ) ) {
				++$failed;
				continue;
			}
			$result = wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $result ) || ! $result ) {
				++$failed;
				continue;
			}
			$done[] = $id;
		}
		if ( $done ) {
			$published           = isset( $detail['published'] ) && is_array( $detail['published'] ) ? $detail['published'] : array();
			$published[]         = array(
				'time' => time(),
				'user' => get_current_user_id(),
				'ids'  => array_slice( $done, 0, 200 ),
			);
			self::update_detail( $session, array( 'published' => array_slice( $published, -10 ) ) );
		}
		$result = self::result( $failed ? ( $done ? 'publish-partial' : 'publish-none' ) : 'publish-ok', $session );
		if ( $done ) {
			$result['count'] = count( $done );
		}
		return $result;
	}

	/**
	 * Lo que «Publicar páginas y plantillas» publicaría: entradas creadas por la sesión (meta nativa de la sesión e
	 * ID > `max_post_id`) que siguen en borrador y que el importador da por publicables (`Session::publishable()`:
	 * tipos visibles y plantillas de la biblioteca, sin Theme Builder, popups, botones flotantes ni fragmentos), salvo
	 * lo conservado con su tipo original y lo pendiente de revisar cuya copia local no cambió (D8, A14). Las listas
	 * propias de tipos son una segunda barrera: las dos tienen que dejarlo pasar. Se consultan todos los tipos, no
	 * `any` (que deja fuera la biblioteca de plantillas, `exclude_from_search`).
	 *
	 * @param string $session ID de la sesión.
	 * @param array  $detail  Detalle de la sesión.
	 *
	 * @return int[]
	 */
	public static function publishable( string $session, array $detail ): array {
		if ( ! Report::is_session_id( $session ) || ! self::has( 'Session', array( 'publishable' ) ) ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'        => array_values( get_post_types() ),
				'post_status'      => 'draft',
				'posts_per_page'   => self::MAX_PUBLISH,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'no_found_rows'    => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sólo al publicar.
					array(
						'key'   => Report::NATIVE_SESSION_META,
						'value' => $session,
					),
				),
			)
		);
		$review = Report::review( $detail );
		$max    = isset( $detail['max_post_id'] ) ? (int) $detail['max_post_id'] : 0;
		$out    = array();
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$id   = (int) $id;
			$type = (string) get_post_type( $id );
			if ( $id <= $max || in_array( $type, Report::NEVER_PUBLISH_TYPES, true ) || '' !== (string) get_post_meta( $id, Module::META_ORIGINAL_TYPE, true ) ) {
				continue;
			}
			if ( 'elementor_library' === $type && in_array( (string) get_post_meta( $id, '_elementor_template_type', true ), Report::THEME_BUILDER_TYPES, true ) ) {
				continue;
			}
			if ( ! Session::publishable( $id ) ) {
				continue;
			}
			if ( isset( $review[ $id ] ) && true !== self::local_changed( $id ) ) {
				continue;
			}
			$out[] = $id;
		}
		return $out;
	}

	/**
	 * «Revertir»: `revert_last_imported_kit()` del importador 3.0 (sus runners y los de K), sólo de la última sesión
	 * terminada y con confirmación. Los documentos van a la papelera y los adjuntos de la sesión se borran (D16).
	 */
	public static function process_revert(): array {
		$request = self::session_request( self::ACTION_REVERT, array(), false );
		if ( isset( $request['notice'] ) ) {
			return $request;
		}
		$session = $request['session'];
		if ( Report::last_session() !== $session ) {
			return self::result( 'revert-not-last', $session );
		}
		if ( empty( $_POST['confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- comprobado en session_request().
			return self::result( 'revert-confirm', $session );
		}
		$importer = self::importer();
		if ( null === $importer ) {
			return self::result( 'importer', $session );
		}
		try {
			$done = $importer->revert_last_imported_kit();
		} catch ( \Throwable $error ) {
			$result          = self::result( 'revert-failed', $session );
			$result['flash'] = array( self::error_text( $error ) );
			return $result;
		}
		if ( ! is_array( $done ) || empty( $done['revert_completed'] ) ) {
			return self::result( 'revert-failed', $session );
		}
		$detail = Report::detail( $session );
		if ( $detail && empty( $detail['reverted'] ) ) {
			self::update_detail(
				$session,
				array(
					'reverted' => time(),
					'status'   => Report::STATUS_REVERTED,
				)
			);
		}
		return self::result( 'revert-ok', $session );
	}

	/**
	 * Capacidad, método y nonce de una petición. Devuelve [] si vale, o el aviso del rechazo.
	 *
	 * @param string   $action  Acción de admin-post.
	 * @param string   $session Sesión del nonce ('' en la subida).
	 * @param string[] $caps    Capacidades además de `manage_options`.
	 */
	private static function check_request( string $action, string $session, array $caps = array() ): array {
		foreach ( array_merge( array( Module::CAPABILITY ), $caps ) as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				return array(
					'status' => 403,
					'notice' => 'forbidden',
				);
			}
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara.
			return array( 'notice' => 'method' );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $action, $session ) ) ) {
			return array(
				'status' => 403,
				'notice' => 'nonce',
			);
		}
		return array();
	}

	/**
	 * Petición sobre una sesión: capacidad, método, nonce de esa sesión en este sitio, sesión de este sitio, terminada
	 * y sin revertir, y (salvo `$needs_detail = false`) con el detalle de K.
	 *
	 * @return array `{ session, row, detail }` o `{ notice, status?, session? }`.
	 */
	private static function session_request( string $action, array $caps = array(), bool $needs_detail = true ): array {
		$session = isset( $_POST['session'] ) && is_string( $_POST['session'] ) ? sanitize_text_field( wp_unslash( $_POST['session'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce incluye la sesión y se comprueba abajo.
		if ( ! Report::is_session_id( $session ) ) {
			$session = '';
		}
		$check = self::check_request( $action, $session, $caps );
		if ( $check ) {
			return $check;
		}
		if ( '' === $session ) {
			return array( 'notice' => 'session' );
		}
		$row = Report::session( $session );
		if ( null === $row ) {
			return array( 'notice' => 'session' );
		}
		if ( Report::STATUS_REVERTED === $row['status'] ) {
			return self::result( 'reverted', $session );
		}
		if ( Report::STATUS_IMPORTED !== $row['status'] ) {
			return self::result( 'in-progress', $session );
		}
		$detail = Report::detail( $session );
		if ( $needs_detail && ! $detail ) {
			return self::result( 'no-detail', $session );
		}
		return array(
			'session' => $session,
			'row'     => $row,
			'detail'  => $detail,
		);
	}

	private static function result( string $notice, string $session ): array {
		return array(
			'notice'  => $notice,
			'session' => $session,
		);
	}

	/** Redirige a la App o a la pantalla con el aviso; un 403 termina con `wp_die()`. */
	private static function finish( array $result ): void {
		$notice = isset( $result['notice'] ) ? (string) $result['notice'] : '';
		if ( isset( $result['status'] ) && 403 === (int) $result['status'] ) {
			wp_die( esc_html( isset( self::NOTICES[ $notice ] ) ? self::NOTICES[ $notice ][1] : self::NOTICES['forbidden'][1] ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		if ( ! empty( $result['flash'] ) && is_array( $result['flash'] ) ) {
			self::flash( $result['flash'] );
		}
		if ( isset( $result['redirect'] ) ) {
			$url = (string) $result['redirect'];
		} else {
			$args = array( self::NOTICE_PARAM => $notice );
			if ( ! empty( $result['count'] ) ) {
				$args[ self::COUNT_PARAM ] = (int) $result['count'];
			}
			$url = self::url( isset( $result['session'] ) ? (string) $result['session'] : '', $args );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/** Mensajes de una acción para la siguiente carga de la pantalla (5 minutos, por usuario). */
	private static function flash( array $lines ): void {
		$lines = array_slice( array_values( array_filter( array_map( 'strval', $lines ), 'strlen' ) ), 0, 20 );
		if ( $lines ) {
			set_transient( self::FLASH_PREFIX . get_current_user_id(), $lines, 5 * MINUTE_IN_SECONDS );
		}
	}

	private static function take_flash(): array {
		$key   = self::FLASH_PREFIX . get_current_user_id();
		$lines = get_transient( $key );
		if ( false === $lines ) {
			return array();
		}
		delete_transient( $key );
		return is_array( $lines ) ? array_map( 'strval', $lines ) : array();
	}

	/** Mensaje de una excepción del importador, sin rutas del servidor. */
	private static function error_text( \Throwable $error ): string {
		$text = trim( wp_strip_all_tags( $error->getMessage() ) );
		foreach ( array( ABSPATH, defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '' ) as $path ) {
			if ( '' !== $path ) {
				$text = str_replace( array( $path, rtrim( $path, '/' ) ), '…/', $text );
			}
		}
		return '' === $text ? 'Error desconocido.' : $text;
	}

	private static function bundled(): bool {
		return class_exists( '\Digitalisimo_Elements_Core' ) && \Digitalisimo_Elements_Core::bundled();
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Integración con el importador (frente A) y la procedencia (frente B).

	/** ¿Existe la clase del módulo (relativa a `Kits\`) con esos métodos? Sin ella, la acción no se ofrece. */
	private static function has( string $class, array $methods ): bool {
		$class = __NAMESPACE__ . '\\' . $class;
		if ( ! class_exists( $class ) ) {
			return false;
		}
		foreach ( $methods as $method ) {
			if ( ! method_exists( $class, $method ) ) {
				return false;
			}
		}
		return true;
	}

	/** Cambia claves de primer nivel del detalle de la sesión (con `Session::update()` si existe). */
	private static function update_detail( string $session, array $changes ): void {
		if ( self::has( 'Session', array( 'update' ) ) ) {
			Session::update( $session, $changes );
			return;
		}
		$raw = get_option( Report::option_name( $session ) );
		if ( is_array( $raw ) ) {
			Report::save_detail( $session, array_replace( $raw, $changes ) );
		}
	}

	/** El módulo del importador 3.0 del núcleo, o null. */
	private static function importer() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->app ) || ! is_object( \Elementor\Plugin::$instance->app ) ) {
			return null;
		}
		$module = \Elementor\Plugin::$instance->app->get_component( 'import-export-customization' );
		return is_object( $module ) && method_exists( $module, 'revert_last_imported_kit' ) ? $module : null;
	}

	/**
	 * ¿Cambió la copia local de un documento desde la importación? (D7, regla de cierre). `Finish::after_save()` guarda
	 * la huella `local` en `_digitalisimo_import_origin` con `Parts\Origin::local_hash()`, la única fórmula. Sin huella
	 * guardada o sin forma de calcularla, devuelve null y el documento no se publica (fallar cerrado).
	 */
	public static function local_changed( int $post_id ): ?bool {
		$origin = get_post_meta( $post_id, Module::META_ORIGIN, true );
		if ( ! is_array( $origin ) || empty( $origin['local'] ) || ! is_string( $origin['local'] ) || ! self::has( 'Session', array( 'local_hash' ) ) ) {
			return null;
		}
		$hash = Session::local_hash( $post_id );
		if ( ! is_string( $hash ) || '' === $hash ) {
			return null;
		}
		return ! hash_equals( $origin['local'], $hash );
	}
}
