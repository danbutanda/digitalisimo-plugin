<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Pantalla «Rendimiento» (`admin.php?page=digitalisimo-elements-front`), en cada sitio y en la red (D13).
 *
 * - **Sitio:** un formulario de ajustes sin pestañas con los seis interruptores, su estado en vigor y, si lo decide
 *   otro, quién, y la sección «Privacidad» (F7a: «Registrar visitas sin gestor de consentimiento»); en una red,
 *   «Heredar de la red» por interruptor. Debajo, «Iconos en línea (SVG)»: el análisis, sus hallazgos y tres
 *   formularios pequeños («Analizar», «Aplicar» y «Revertir»), cada uno con su acción y su nonce. Guardar transita el
 *   sitio en el momento; lo de «Privacidad» no transita: no vacía cachés.
 * - **Red:** los valores que heredan los sitios (`Settings::network_fields()` y `Settings::network_privacy_fields()`),
 *   sin «Heredar de la red». Guardar sólo escribe la opción de red: cada sitio aplica en sus propias peticiones.
 *
 * Seguridad (R14): acciones distintas para sitio y red, cada una con su nonce (acción y sitio, o acción y red);
 * capacidad por acción, nunca por un parámetro de contexto (`manage_options` en el sitio; `is_multisite()` y
 * `manage_network_options` en la red); sólo `POST`; `wp_safe_redirect()` a una URL construida en el servidor para la
 * misma superficie. Los extractos del análisis siempre con `esc_html()`, nunca dentro de `<style>` ni `<script>`.
 *
 * Diseño: `digitalisimo-ui` (el prefijo `digitalisimo` del slug hace que su cargador ponga `.digitalisimo-admin`),
 * sólo con sus componentes y sin hoja propia. Textos en español, sin el nombre del constructor original ni enlaces
 * fuera del sitio. Cada acción tiene un `process_*()` que valida y hace el trabajo sin redirigir (lo prueban las
 * pruebas) y un `handle_*()` que lo envuelve para `admin-post.php`.
 */
final class Settings_Page {
	const PAGE               = 'digitalisimo-elements-front';
	const TITLE              = 'Rendimiento';
	const CAPABILITY         = 'manage_options';
	const NETWORK_CAPABILITY = 'manage_network_options';

	const ACTION_SAVE         = 'digitalisimo_elements_front_save';
	const ACTION_SAVE_NETWORK = 'digitalisimo_elements_front_save_network';
	const ACTION_SCAN         = 'digitalisimo_elements_front_icons_scan';
	const ACTION_APPLY        = 'digitalisimo_elements_front_icons_apply';
	const ACTION_REVERT       = 'digitalisimo_elements_front_icons_revert';

	/** Acciones de la pantalla del sitio (la de red es aparte). */
	const SITE_ACTIONS = array( self::ACTION_SAVE, self::ACTION_SCAN, self::ACTION_APPLY, self::ACTION_REVERT );

	const NOTICE_PARAM = 'front_notice';

	/** Avisos tras una acción: código => [ tipo de aviso, texto ]. */
	const NOTICES = array(
		'saved'                 => array( 'success', 'Ajustes guardados y aplicados en este sitio.' ),
		'saved-pending'         => array( 'success', 'Ajustes guardados. Se aplicarán en este sitio en unos momentos.' ),
		'network-saved'         => array( 'success', 'Valores de red guardados. Cada sitio que los hereda los aplica en su siguiente visita.' ),
		'scan-done'             => array( 'success', 'Análisis terminado.' ),
		'scan-done-pending'     => array( 'info', 'El análisis sigue en segundo plano. Vuelve a esta pantalla en unos minutos para ver el resultado.' ),
		'scan-missing'          => array( 'error', 'El análisis de iconos no está disponible en esta versión de DIGITALÍSIMO Elements.' ),
		'icons-applied'         => array( 'success', 'Se aplicaron los iconos en línea (SVG) en este sitio. Revisa sus páginas: «Revertir» los deja como estaban.' ),
		'icons-applied-pending' => array( 'success', 'Los iconos en línea (SVG) se aplicarán en este sitio en unos momentos.' ),
		'icons-reverted'        => array( 'success', 'Los iconos vuelven a cargarse como fuentes en este sitio.' ),
		'icons-reverted-pending' => array( 'success', 'Los iconos volverán a cargarse como fuentes en unos momentos.' ),
		'busy'                  => array( 'warning', 'Otra tarea de rendimiento está en curso en este sitio. Inténtalo de nuevo en unos momentos.' ),
		'core-original'         => array( 'error', 'Con el constructor original activo, los iconos los decide su configuración.' ),
		'forbidden'             => array( 'error', 'No tienes permiso para cambiar estos ajustes.' ),
		'nonce'                 => array( 'error', 'El formulario caducó. Vuelve a la pantalla «Rendimiento» e inténtalo de nuevo.' ),
		'method'                => array( 'error', 'Esta acción sólo se envía desde el formulario de la pantalla «Rendimiento».' ),
	);

	/** Origen del valor deseado. */
	const ORIGINS = array(
		'site'    => 'este sitio',
		'network' => 'la red',
		'default' => 'valor predeterminado del plugin',
	);

	const NOTE_BUILDER  = 'Lo elegiste en los ajustes del constructor.';
	const NOTE_ORIGINAL = 'Con el constructor original activo lo decide su configuración.';
	const NOTE_PENDING  = 'Pendiente: se aplicará en la siguiente visita.';

	/** @var bool */
	private static $initialized = false;

	/** Ganchos de la pantalla. Fuera de la administración no engancha nada. Lo llama `Module::init()`. */
	public static function init(): void {
		if ( self::$initialized || ! is_admin() ) {
			return;
		}
		self::$initialized = true;
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 99 );
		}
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_NETWORK, array( __CLASS__, 'handle_save_network' ) );
		add_action( 'admin_post_' . self::ACTION_SCAN, array( __CLASS__, 'handle_scan' ) );
		add_action( 'admin_post_' . self::ACTION_APPLY, array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_' . self::ACTION_REVERT, array( __CLASS__, 'handle_revert' ) );
	}

	/** URL de la pantalla en la misma superficie, con los parámetros que se pidan. */
	public static function url( bool $network = false, array $args = array() ): string {
		$base = $network ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), $base );
	}

	/** Acción del nonce: acción y sitio, o acción y red. Un nonce de otra acción o de otro sitio de la red no vale. */
	public static function nonce_action( string $action ): string {
		if ( self::ACTION_SAVE_NETWORK === $action ) {
			return $action . '|network';
		}
		return $action . '|' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Menús.

	/** En el sitio: bajo DIGITALÍSIMO, el menú del constructor (sólo con el núcleo incluido), «Acerca de» o Herramientas. */
	public static function menu(): void {
		$parent = self::parent_menu( false );
		$title  = 'digitalisimo' === $parent ? 'Rendimiento de Elements' : self::TITLE;
		add_submenu_page( $parent, 'Rendimiento · DIGITALÍSIMO Elements', $title, self::CAPABILITY, self::PAGE, array( __CLASS__, 'render' ) );
	}

	/** En la red: bajo DIGITALÍSIMO de la red o bajo «Acerca de» de DIGITALÍSIMO Elements. */
	public static function network_menu(): void {
		$parent = self::parent_menu( true );
		$title  = 'digitalisimo-network' === $parent ? 'Rendimiento de Elements' : self::TITLE;
		add_submenu_page( $parent, 'Rendimiento · DIGITALÍSIMO Elements', $title, self::NETWORK_CAPABILITY, self::PAGE, array( __CLASS__, 'render_network' ) );
	}

	private static function parent_menu( bool $network ): string {
		global $menu;
		$slugs = array();
		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && is_string( $item[2] ) ) {
				$slugs[ $item[2] ] = true;
			}
		}
		$about = class_exists( '\Digitalisimo_Elements_Brand' ) ? \Digitalisimo_Elements_Brand::PAGE : 'digitalisimo-elements-about';
		if ( $network ) {
			$order = array( 'digitalisimo-network', $about );
		} else {
			$order = array_merge( array( 'digitalisimo' ), Transition::bundled() ? array( 'elementor-home', 'elementor' ) : array(), array( $about ) );
		}
		foreach ( $order as $slug ) {
			if ( isset( $slugs[ $slug ] ) ) {
				return $slug;
			}
		}
		return $network ? 'settings.php' : 'tools.php';
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Pantallas.

	/** Pantalla del sitio. */
	public static function render(): void {
		if ( is_network_admin() || ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html( self::NOTICES['forbidden'][1] ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap digitalisimo-elements-front">';
		echo '<h1>' . esc_html( self::TITLE ) . '</h1>';
		echo '<p>' . esc_html( 'Ajustes que hacen más ligeras las páginas de DIGITALÍSIMO Elements. Cada cambio se aplica en este sitio vaciando sus archivos generados y las cachés de página que escuchan ese vaciado. Ante cualquier duda, el constructor carga todo como siempre.' ) . '</p>';
		echo '<p class="description">' . esc_html( 'Guardar los ajustes del constructor escribe todos sus campos: desde ese momento, cómo se muestran las fuentes, la caché de elementos y la carga de imágenes quedan como elección del sitio, y los valores por defecto de esta pantalla dejan de aplicarse en ellos. Cada interruptor lo indica.' ) . '</p>';
		self::render_notices();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">';
		wp_nonce_field( self::nonce_action( self::ACTION_SAVE ) );
		echo '<table class="form-table" role="presentation"><tbody>';
		self::site_fields();
		echo '</tbody></table>';
		self::privacy_heading();
		echo '<table class="form-table" role="presentation"><tbody>';
		self::privacy_fields();
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html( 'Guardar ajustes' ) . '</button></p>';
		echo '</form>';
		self::render_icons();
		if ( is_multisite() ) {
			self::inherit_script();
		}
		echo '</div>';
	}

	/** Pantalla de la red. */
	public static function render_network(): void {
		if ( ! is_multisite() || ! is_network_admin() || ! current_user_can( self::NETWORK_CAPABILITY ) ) {
			wp_die( esc_html( self::NOTICES['forbidden'][1] ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap digitalisimo-elements-front">';
		echo '<h1>' . esc_html( self::TITLE ) . '</h1>';
		echo '<p>' . esc_html( 'Valores que heredan todos los sitios de la red. Cada sitio puede personalizarlos en su propia pantalla «Rendimiento» y aplica los cambios en su siguiente visita, vaciando sus archivos generados.' ) . '</p>';
		self::render_notices();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE_NETWORK ) . '">';
		wp_nonce_field( self::nonce_action( self::ACTION_SAVE_NETWORK ) );
		echo '<table class="form-table" role="presentation"><tbody>';
		Settings::network_fields();
		echo '</tbody></table>';
		self::privacy_heading();
		echo '<table class="form-table" role="presentation"><tbody>';
		Settings::network_privacy_fields();
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html( 'Guardar valores de red' ) . '</button></p>';
		echo '</form>';
		echo '<p class="description">' . esc_html( 'Los iconos en línea nunca se aplican sin análisis. El análisis no ve el PHP ni las hojas de los temas y plugins: cada sitio muestra sus hallazgos en su pantalla y su administrador puede aplicarlos o revertirlos allí.' ) . '</p>';
		echo '</div>';
	}

	/** Filas de los seis interruptores del sitio; en una red, con «Heredar de la red». */
	public static function site_fields(): void {
		$inherit_ui = is_multisite() && ! is_network_admin();
		$network    = Settings::network_values();
		foreach ( Settings::KEYS as $key ) {
			$id      = 'digitalisimo-front-' . str_replace( '_', '-', $key );
			$name    = Settings::OPTION . '[' . $key . ']';
			$inherit = $inherit_ui && Settings::inherits_network( $key );
			$value   = Settings::get( $key );
			$origin  = Settings::origin( $key );
			echo '<tr><th scope="row">';
			if ( 'svg_icons' === $key ) {
				echo esc_html( Settings::label( $key ) );
			} else {
				echo '<label for="' . esc_attr( $id ) . '">' . esc_html( Settings::label( $key ) ) . '</label>';
			}
			echo '</th><td>';
			if ( 'svg_icons' === $key ) {
				// Se cambia con «Aplicar» y «Revertir»; el formulario conserva el valor actual.
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . ( $value ? '1' : '0' ) . '">';
				echo '<p>' . esc_html( 'Se aplican o se revierten con los botones de ' ) . '<a href="#digitalisimo-front-icons">' . esc_html( 'Iconos en línea (SVG)' ) . '</a>' . esc_html( ', más abajo.' ) . '</p>';
			} else {
				$class = $inherit ? 'digitalisimo-inherited-value' : ( 'default' === $origin ? 'digitalisimo-default-value' : '' );
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ( $value ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Activar' ) . '</label>';
			}
			echo '<p class="description">' . esc_html( Settings::help( $key ) ) . '</p>';
			$state = self::state( $key );
			echo '<p class="description">' . esc_html( 'En vigor en este sitio:' ) . ' ' . self::badge( $state['active'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en badge().
			if ( '' !== $state['note'] ) {
				echo ' · ' . esc_html( $state['note'] );
			}
			echo '</p>';
			if ( 'default' === $origin && ! $inherit ) {
				echo '<p class="description digitalisimo-default-note">' . esc_html( 'Valor predeterminado del plugin.' ) . '</p>';
			}
			if ( $inherit_ui ) {
				$field = Settings::OPTION . '[inherit][' . $key . ']';
				echo '<p class="digitalisimo-inheritance"><input type="hidden" name="' . esc_attr( $field ) . '" value="0">';
				echo '<label><input type="checkbox" class="digitalisimo-front-inherit" data-field="' . esc_attr( $id ) . '" name="' . esc_attr( $field ) . '" value="1"' . ( $inherit ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Heredar de la red' ) . '</label><br>';
				echo '<span class="description">' . esc_html( 'Valor de la red:' ) . ' ' . self::badge( $network[ $key ] ) . ' · ' . esc_html( 'Origen: ' . self::ORIGINS[ $origin ] ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en badge().
			}
			echo '</td></tr>';
		}
	}

	/** Encabezado de la sección «Privacidad», igual en el sitio y en la red. */
	private static function privacy_heading(): void {
		echo '<h2 id="digitalisimo-front-privacy">' . esc_html( 'Privacidad' ) . '</h2>';
		echo '<p>' . esc_html( 'Cookies propias que algunas condiciones de visualización necesitan. Ninguna sale del sitio y ninguna se escribe en las páginas que no las usan.' ) . '</p>';
	}

	/**
	 * Filas de la sección «Privacidad» del sitio: casilla, ayuda y estado en vigor; en el sitio de una red, «Heredar de
	 * la red» (nunca en la red ni en un sitio único). No dependen de la transición: rigen en cuanto se guardan.
	 */
	public static function privacy_fields(): void {
		$inherit_ui = is_multisite() && ! is_network_admin();
		$network    = Settings::network_values();
		foreach ( Settings::PRIVACY_KEYS as $key ) {
			$id      = 'digitalisimo-front-' . str_replace( '_', '-', $key );
			$name    = Settings::OPTION . '[' . $key . ']';
			$inherit = $inherit_ui && Settings::inherits_network( $key );
			$value   = Settings::get( $key );
			$origin  = Settings::origin( $key );
			$class   = $inherit ? 'digitalisimo-inherited-value' : ( 'default' === $origin ? 'digitalisimo-default-value' : '' );
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( Settings::label( $key ) ) . '</label></th><td>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
			echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ( $value ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Activar' ) . '</label>';
			echo '<p class="description">' . esc_html( Settings::help( $key ) ) . '</p>';
			echo '<p class="description">' . esc_html( 'En vigor en este sitio:' ) . ' ' . self::badge( $value ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en badge().
			if ( 'default' === $origin && ! $inherit ) {
				echo '<p class="description digitalisimo-default-note">' . esc_html( 'Valor predeterminado del plugin.' ) . '</p>';
			}
			if ( $inherit_ui ) {
				$field = Settings::OPTION . '[inherit][' . $key . ']';
				echo '<p class="digitalisimo-inheritance"><input type="hidden" name="' . esc_attr( $field ) . '" value="0">';
				echo '<label><input type="checkbox" class="digitalisimo-front-inherit" data-field="' . esc_attr( $id ) . '" name="' . esc_attr( $field ) . '" value="1"' . ( $inherit ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Heredar de la red' ) . '</label><br>';
				echo '<span class="description">' . esc_html( 'Valor de la red:' ) . ' ' . self::badge( $network[ $key ] ) . ' · ' . esc_html( 'Origen: ' . self::ORIGINS[ $origin ] ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en badge().
			}
			echo '</td></tr>';
		}
	}

	/** «Iconos en línea (SVG)» en el sitio: estado, análisis, hallazgos y los tres formularios. */
	private static function render_icons(): void {
		echo '<h2 id="digitalisimo-front-icons">' . esc_html( 'Iconos en línea (SVG)' ) . '</h2>';
		echo '<p>' . esc_html( 'Con los iconos en línea, cada icono se pinta como SVG y el sitio deja de cargar las fuentes de iconos y sus hojas de estilo. Un estilo, un script o un PHP que espere las etiquetas <i> de esas fuentes puede dejar de verse bien: por eso nunca se aplican solos en un sitio que ya existe.' ) . '</p>';
		echo '<p>' . esc_html( 'El análisis busca usos de los iconos de fuente en el CSS adicional, el CSS del kit, de cada página y de cada elemento, los fragmentos de código, el contenido, los menús, los widgets clásicos y los iconos antiguos sin actualizar. No ve el PHP ni las hojas de los temas y plugins: revisa el sitio después de aplicarlos.' ) . '</p>';
		$state = self::state( 'svg_icons' );
		echo '<p>' . esc_html( 'En vigor en este sitio:' ) . ' ' . self::badge( $state['active'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en badge().
		if ( '' !== $state['note'] ) {
			echo ' · ' . esc_html( $state['note'] );
		}
		echo '</p>';
		if ( ! Transition::bundled() ) {
			return;
		}
		$scanner = self::scanner();
		if ( $scanner ) {
			self::render_scan();
		}
		$own_on = ! Settings::inherits_network( 'svg_icons' ) && Settings::get( 'svg_icons' );
		$on     = ! empty( Transition::applied()['svg_icons'] );
		echo '<div class="digitalisimo-front-icon-actions">';
		if ( $scanner ) {
			self::action_form( self::ACTION_SCAN, 'Analizar', false );
		}
		if ( ! $own_on ) {
			self::action_form( self::ACTION_APPLY, 'Aplicar', true );
		}
		if ( $own_on || $on ) {
			self::action_form( self::ACTION_REVERT, 'Revertir', false );
		}
		echo '</div>';
	}

	/** Veredicto y hallazgos del análisis (frente C). Cada extracto con `esc_html()`. */
	private static function render_scan(): void {
		$scan       = __NAMESPACE__ . '\\Icon_Scan';
		$verdict    = (string) call_user_func( array( $scan, 'verdict' ) );
		$state      = method_exists( $scan, 'state' ) ? call_user_func( array( $scan, 'state' ) ) : array();
		$applicable = ! method_exists( $scan, 'applicable' ) || call_user_func( array( $scan, 'applicable' ) );
		$texts      = array(
			'clean'    => 'El último análisis no encontró usos de los iconos de fuente.',
			'findings' => 'El último análisis encontró estos usos de los iconos de fuente:',
			'pending'  => 'El análisis sigue en curso en segundo plano: vuelve a esta pantalla en unos minutos.',
		);
		if ( isset( $texts[ $verdict ] ) ) {
			$text = $texts[ $verdict ];
		} else {
			$text = $applicable ? 'Este sitio todavía no se analizó.' : 'No hace falta analizar: los iconos en línea ya están activos o los decide la configuración del constructor.';
		}
		echo '<p>' . esc_html( $text ) . '</p>';
		$findings = is_array( $state ) && isset( $state['findings'] ) && is_array( $state['findings'] ) ? array_slice( $state['findings'], 0, 50 ) : array();
		if ( ! $findings ) {
			return;
		}
		$total = is_array( $state ) && isset( $state['total'] ) ? (int) $state['total'] : 0;
		if ( $total > count( $findings ) ) {
			echo '<p class="description">' . esc_html( sprintf( 'Se muestran %1$d de %2$d hallazgos.', count( $findings ), $total ) ) . '</p>';
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Fuente', 'Elemento', 'Extracto' ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $findings as $finding ) {
			$finding = is_array( $finding ) ? $finding : array( 'excerpt' => is_scalar( $finding ) ? (string) $finding : '' );
			$source  = self::pick( $finding, array( 'source', 'kind', 'fuente' ) );
			$source  = method_exists( $scan, 'label' ) ? (string) call_user_func( array( $scan, 'label' ), $source ) : $source;
			$id      = self::pick( $finding, array( 'id', 'post', 'object' ) );
			$excerpt = self::pick( $finding, array( 'excerpt', 'extracto', 'text' ) );
			$excerpt = function_exists( 'mb_substr' ) ? mb_substr( $excerpt, 0, 120, 'UTF-8' ) : substr( $excerpt, 0, 120 );
			echo '<tr><td>' . esc_html( $source ) . '</td><td>' . esc_html( $id ) . '</td><td><code>' . esc_html( $excerpt ) . '</code></td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Formulario pequeño de una acción de iconos, con su nombre y su nonce. */
	private static function action_form( string $action, string $label, bool $primary ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-front-' . esc_attr( strtolower( $label ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( self::nonce_action( $action ) );
		echo '<p><button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></p>';
		echo '</form>';
	}

	/** «Heredar de la red» deshabilita el interruptor que hereda (sin JavaScript, el valor heredado se ignora igual). */
	private static function inherit_script(): void {
		$script = 'document.querySelectorAll(".digitalisimo-front-inherit").forEach(function(b){var f=document.getElementById(b.getAttribute("data-field"));if(!f){return;}var s=function(){f.disabled=b.checked;};b.addEventListener("change",s);s();});';
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $script );
		} else {
			echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texto fijo.
		}
	}

	private static function render_notices(): void {
		$code = isset( $_GET[ self::NOTICE_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el texto del aviso.
		if ( ! isset( self::NOTICES[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = self::NOTICES[ $code ];
		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}

	private static function badge( bool $active ): string {
		return '<span class="digitalisimo-effective-state ' . ( $active ? 'is-active' : 'is-inactive' ) . '">' . esc_html( $active ? 'Activo' : 'Inactivo' ) . '</span>';
	}

	/**
	 * Estado en vigor de un interruptor y, si lo decide otro, quién. Sólo con funciones de WordPress: filas de
	 * opciones, `pre_option_` y el gestor de experimentos del núcleo, sin acoplar plugins.
	 *
	 * @return array{active:bool,note:string}
	 */
	public static function state( string $key ): array {
		$applied = Transition::applied();
		$active  = ! empty( $applied[ $key ] );
		$note    = '';
		if ( in_array( $key, Settings::CORE_KEYS, true ) && ! Transition::bundled() ) {
			return array(
				'active' => self::core_active( $key, $active ),
				'note'   => self::NOTE_ORIGINAL,
			);
		}
		if ( 'font_swap' === $key ) {
			$forced = apply_filters( 'pre_option_' . Transition::CORE_OPTIONS['font_swap'], false, Transition::CORE_OPTIONS['font_swap'], 'auto' );
			if ( false !== $forced ) {
				return array(
					'active' => 'swap' === $forced,
					'note'   => defined( 'DIGITALISIMO_INTEGRATIONS_VERSION' ) ? 'Lo fuerza DIGITALÍSIMO SEO.' : 'Lo fuerza otro plugin.',
				);
			}
		}
		if ( isset( Transition::CORE_OPTIONS[ $key ] ) && Transition::row_exists( Transition::CORE_OPTIONS[ $key ] ) ) {
			return array(
				'active' => self::core_active( $key, $active ),
				'note'   => self::NOTE_BUILDER,
			);
		}
		if ( 'svg_icons' === $key ) {
			if ( ! Transition::experiment_unsaved() ) {
				return array(
					'active' => self::core_active( $key, $active ),
					'note'   => self::NOTE_BUILDER,
				);
			}
			$real = self::svg_active();
			if ( true === $real && ! $active ) {
				return array(
					'active' => true,
					'note'   => 'Este sitio ya los usa por la configuración del constructor.',
				);
			}
			if ( Settings::inherits_network( 'svg_icons' ) && Settings::get( 'svg_icons' ) && ! $active ) {
				$icons = isset( $applied['icons'] ) ? $applied['icons'] : '';
				if ( 'findings' === $icons ) {
					$note = 'El análisis encontró usos de los iconos de fuente: la red no los aplica en este sitio.';
				} elseif ( ! empty( $applied['scanning'] ) ) {
					$note = 'La red los pide: el análisis de este sitio está en curso.';
				} else {
					$note = 'La red los pide: se aplicarán si el análisis de este sitio sale limpio.';
				}
			}
		}
		if ( '' === $note && Transition::pending() ) {
			$note = self::NOTE_PENDING;
		}
		return array(
			'active' => $active,
			'note'   => $note,
		);
	}

	/** Estado real de un ajuste del núcleo que decide el sitio o el constructor original. */
	private static function core_active( string $key, bool $fallback ): bool {
		switch ( $key ) {
			case 'font_swap':
				return 'swap' === get_option( Transition::CORE_OPTIONS['font_swap'], 'auto' );
			case 'element_cache_off':
				return 'disable' === get_option( Transition::CORE_OPTIONS['element_cache_off'], '' );
			case 'image_loading_off':
				return ! get_option( Transition::CORE_OPTIONS['image_loading_off'], '1' );
			case 'svg_icons':
				$real = self::svg_active();
				return null === $real ? $fallback : $real;
		}
		return $fallback;
	}

	/** Estado real del experimento de iconos en línea, o `null` si el gestor no está. */
	private static function svg_active(): ?bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return null;
		}
		$experiments = isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
		if ( ! is_object( $experiments ) || ! method_exists( $experiments, 'is_feature_active' ) ) {
			return null;
		}
		return (bool) $experiments->is_feature_active( 'e_font_icon_svg' );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Acciones.

	public static function handle_save(): void {
		self::finish( self::process_save() );
	}

	public static function handle_save_network(): void {
		self::finish( self::process_save_network() );
	}

	public static function handle_scan(): void {
		self::finish( self::process_scan() );
	}

	public static function handle_apply(): void {
		self::finish( self::process_apply() );
	}

	public static function handle_revert(): void {
		self::finish( self::process_revert() );
	}

	/** Guardar en el sitio: sanea, guarda y transita en el momento. */
	public static function process_save(): array {
		$check = self::check_request( self::ACTION_SAVE, false );
		if ( $check ) {
			return $check;
		}
		$input = isset( $_POST[ Settings::OPTION ] ) && is_array( $_POST[ Settings::OPTION ] ) ? wp_unslash( $_POST[ Settings::OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- saneado en Settings::sanitize(); nonce en check_request().
		update_option( Settings::OPTION, Settings::sanitize( is_array( $input ) ? $input : array() ), true );
		return self::after_change( 'saved' );
	}

	/** Guardar en la red: sólo escribe la opción de red; cada sitio aplica en sus peticiones. */
	public static function process_save_network(): array {
		$check = self::check_request( self::ACTION_SAVE_NETWORK, true );
		if ( $check ) {
			return $check;
		}
		$input = isset( $_POST[ Settings::NETWORK_OPTION ] ) && is_array( $_POST[ Settings::NETWORK_OPTION ] ) ? wp_unslash( $_POST[ Settings::NETWORK_OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- saneado en Settings::save_network(); nonce en check_request().
		Settings::save_network( is_array( $input ) ? $input : array() );
		return array(
			'notice'  => 'network-saved',
			'network' => true,
		);
	}

	/** «Analizar»: reinicia el análisis (si el frente C lo ofrece), ejecuta un lote y copia el veredicto al aplicado. */
	public static function process_scan(): array {
		$check = self::check_request( self::ACTION_SCAN, false );
		if ( $check ) {
			return $check;
		}
		if ( ! Transition::bundled() ) {
			return array( 'notice' => 'core-original' );
		}
		if ( ! self::scanner() ) {
			return array( 'notice' => 'scan-missing' );
		}
		// Un análisis nuevo aunque haya otro en curso (`start()` del frente C); sin él, reinicio y primer lote.
		$scan = __NAMESPACE__ . '\\Icon_Scan';
		if ( method_exists( $scan, 'start' ) ) {
			call_user_func( array( $scan, 'start' ) );
		} else {
			if ( method_exists( $scan, 'reset' ) ) {
				call_user_func( array( $scan, 'reset' ) );
			}
			call_user_func( array( $scan, 'run_batch' ), Transition::BATCH );
		}
		Transition::request_scan();
		Transition::run_now();
		if ( Transition::busy() ) {
			return array( 'notice' => 'busy' );
		}
		$applied = Transition::applied();
		return array( 'notice' => ! empty( $applied['scanning'] ) ? 'scan-done-pending' : 'scan-done' );
	}

	/** «Aplicar»: los iconos en línea pasan a ser elección del sitio, aunque haya hallazgos (los acaba de ver). */
	public static function process_apply(): array {
		return self::set_icons( self::ACTION_APPLY, true, 'icons-applied' );
	}

	/** «Revertir»: los iconos en línea se apagan como elección del sitio (la red ya no los vuelve a poner). */
	public static function process_revert(): array {
		return self::set_icons( self::ACTION_REVERT, false, 'icons-reverted' );
	}

	private static function set_icons( string $action, bool $on, string $notice ): array {
		$check = self::check_request( $action, false );
		if ( $check ) {
			return $check;
		}
		if ( ! Transition::bundled() ) {
			return array( 'notice' => 'core-original' );
		}
		// Los valores deseados de hoy, para que ninguna otra clave cambie por guardarse (también las de privacidad).
		$input = array();
		foreach ( Settings::keys() as $key ) {
			$input[ $key ] = Settings::get( $key );
		}
		$input['svg_icons'] = $on;
		$input['own']       = array_merge( Settings::stored()['own'], array( 'svg_icons' ) );
		update_option( Settings::OPTION, Settings::sanitize( $input ), true );
		return self::after_change( $notice );
	}

	/** Tras cambiar lo deseado en el sitio: transita ahora; si no pudo, queda el evento. */
	private static function after_change( string $notice ): array {
		Transition::run_now();
		if ( Transition::busy() || Transition::pending() ) {
			Transition::schedule();
			return array( 'notice' => $notice . '-pending' );
		}
		return array( 'notice' => $notice );
	}

	/**
	 * Capacidad (por acción, nunca por un parámetro de contexto), método y nonce. `[]` si vale, o el rechazo.
	 */
	private static function check_request( string $action, bool $network ): array {
		$allowed = $network ? is_multisite() && current_user_can( self::NETWORK_CAPABILITY ) : current_user_can( self::CAPABILITY );
		if ( ! $allowed ) {
			return array(
				'status'  => 403,
				'notice'  => 'forbidden',
				'network' => $network,
			);
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara.
			return array(
				'notice'  => 'method',
				'network' => $network,
			);
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $action ) ) ) {
			return array(
				'status'  => 403,
				'notice'  => 'nonce',
				'network' => $network,
			);
		}
		return array();
	}

	/** Redirige a la pantalla de la misma superficie con el aviso; un 403 termina con `wp_die()`. */
	private static function finish( array $result ): void {
		$notice = isset( $result['notice'] ) && isset( self::NOTICES[ $result['notice'] ] ) ? (string) $result['notice'] : '';
		if ( isset( $result['status'] ) && 403 === (int) $result['status'] ) {
			wp_die( esc_html( '' !== $notice ? self::NOTICES[ $notice ][1] : self::NOTICES['forbidden'][1] ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		wp_safe_redirect( self::url( ! empty( $result['network'] ), '' !== $notice ? array( self::NOTICE_PARAM => $notice ) : array() ) );
		exit;
	}

	/** ¿Está el análisis de iconos (frente C) con su contrato? */
	private static function scanner(): bool {
		$scan = __NAMESPACE__ . '\\Icon_Scan';
		return class_exists( $scan ) && method_exists( $scan, 'run_batch' ) && method_exists( $scan, 'verdict' );
	}

	/** Primer valor escalar de un hallazgo entre varias claves posibles. */
	private static function pick( array $finding, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $finding[ $key ] ) && is_scalar( $finding[ $key ] ) ) {
				return (string) $finding[ $key ];
			}
		}
		return '';
	}
}
