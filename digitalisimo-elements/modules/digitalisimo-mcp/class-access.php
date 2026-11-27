<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * El MCP con acceso total por naturaleza (decisión del dueño del 2026-11-27): sin interruptores ni modos.
 *
 * - **Siempre activo.** La opción del núcleo `elementor_mcp_enabled` (apagada por defecto en el núcleo) vale siempre 1
 *   (`pre_option_…`): el servidor `POST /wp-json/elementor/mcp` y todas las herramientas (constructor, tienda, SEO,
 *   Hosting, Tools, IA Tools) existen en cada sitio, y el Asistente de IA Tools lo usa sin configurar nada.
 * - **La seguridad no depende de interruptores:** el transporte del adaptador exige un usuario autenticado (contraseña
 *   de aplicación, OAuth o la sesión del escritorio con su nonce; sin nada, 401), cada herramienta comprueba las
 *   capacidades de WordPress de ese usuario, cada
 *   escritura queda en la bitácora (`Write_Log`) y las guardas de negocio siguen en cada herramienta.
 * - **Migración** (`migrate()`, idempotente, una vez por sitio en su primera petición tras actualizar, también en cada
 *   sitio de una red): borra los ajustes viejos que limitaban el MCP («WooCommerce en el MCP» del sitio y de la red).
 *   Ningún código los lee ya: un sitio que estaba en «Sólo lectura» tiene acceso total desde la actualización, aunque
 *   la migración aún no haya corrido.
 * - **La única opción visible:** «Mostrar el prompt para vincular» en «Rendimiento» (por sitio). Enseña la dirección
 *   del servidor y la configuración lista para copiar en un cliente MCP; ocultarla no apaga nada. El conmutador de la
 *   página MCP del núcleo ya no apaga nada: su guardado responde «activo» sin escribir (`core_settings_save()`).
 */
final class Access {
	/** Opción del núcleo que encendía y apagaba el servidor MCP. */
	const CORE_OPTION = 'elementor_mcp_enabled';

	/** Marca de la migración (con carga automática: tras correr una vez no cuesta ninguna consulta). */
	const VERSION_OPTION = 'digitalisimo_elements_mcp_access';
	const VERSION        = 1;

	/** «Mostrar el prompt para vincular»: `hidden` lo oculta; sin la opción, se muestra. Sin carga automática. */
	const PROMPT_OPTION = 'digitalisimo_elements_mcp_prompt';

	const ANCHOR       = 'digitalisimo-mcp';
	const CAPABILITY   = 'manage_options';
	const ACTION_SAVE  = 'digitalisimo_elements_mcp_prompt_save';
	const NOTICE_PARAM = 'mcp_notice';
	const FRONT_PAGE   = 'digitalisimo-elements-front';

	/** Opciones de sitio que limitaban el MCP y se borran al actualizar. */
	const LEGACY_SITE_OPTIONS = array( 'digitalisimo_elements_woo_mcp' );

	/** Opciones de red que limitaban el MCP y se borran al actualizar. */
	const LEGACY_NETWORK_OPTIONS = array( 'digitalisimo_elements_woo_mcp_defaults' );

	const NOTICES = array(
		'saved'     => array( 'success', 'Guardado. El MCP sigue activo con lectura y escritura.' ),
		'forbidden' => array( 'error', 'No tienes permiso para cambiar este ajuste.' ),
		'nonce'     => array( 'error', 'El formulario caducó. Vuelve a intentarlo.' ),
		'method'    => array( 'error', 'Este ajuste sólo se guarda desde su formulario.' ),
	);

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'pre_option_' . self::CORE_OPTION, array( __CLASS__, 'core_enabled' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'core_settings_save' ), 10, 3 );
		add_action( 'init', array( __CLASS__, 'migrate' ), 1 );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/** `pre_option_elementor_mcp_enabled`: el servidor MCP siempre activo. */
	public static function core_enabled(): int {
		return 1;
	}

	/**
	 * `rest_pre_dispatch`: el guardado del conmutador de la página MCP del núcleo (`POST …/mcp-settings`) ya no cambia
	 * nada; a quien puede administrar el sitio le responde, sin escribir, que el MCP sigue activo (antes apagar dejaba la
	 * opción en falso o respondía 500). A los demás, la ruta les responde como siempre (403).
	 */
	public static function core_settings_save( $result, $server = null, $request = null ) {
		if ( null !== $result || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || 'POST' !== $request->get_method() ) {
			return $result;
		}
		if ( ! preg_match( '#^/elementor[a-z0-9._/-]*/mcp-settings$#', (string) $request->get_route() ) || ! current_user_can( 'manage_options' ) ) {
			return $result;
		}
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array( 'enabled' => true ),
			),
			200
		);
	}

	/**
	 * Paso de versión: borra los ajustes viejos del MCP en este sitio y en la red. Idempotente; con la marca al día no
	 * hace nada (la marca tiene carga automática). Devuelve true si migró en esta llamada.
	 */
	public static function migrate(): bool {
		if ( ! function_exists( 'get_option' ) || (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return false;
		}
		foreach ( self::LEGACY_SITE_OPTIONS as $option ) {
			delete_option( $option );
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'delete_site_option' ) ) {
			foreach ( self::LEGACY_NETWORK_OPTIONS as $option ) {
				delete_site_option( $option );
			}
		}
		update_option( self::VERSION_OPTION, self::VERSION, true );
		return true;
	}

	/** ¿Se muestra el prompt para vincular en este sitio? (por defecto, sí). */
	public static function show_prompt(): bool {
		return 'hidden' !== ( function_exists( 'get_option' ) ? get_option( self::PROMPT_OPTION, '' ) : '' );
	}

	/** Dirección del servidor MCP de este sitio. */
	public static function endpoint(): string {
		return rest_url( 'elementor/mcp' );
	}

	/** Nombre del servidor en la configuración del cliente: el dominio del sitio, como `digitalisimo-lat`. */
	public static function server_name(): string {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$name = sanitize_title( str_replace( '.', '-', $host . ( '' !== $path ? '-' . $path : '' ) ) );
		return '' !== $name ? $name : 'mi-sitio';
	}

	/**
	 * Configuración del cliente MCP, con la credencial como marcador (`$credential`: la base64 de «usuario:contraseña de
	 * aplicación»). `code`: Claude Code (`.mcp.json` o `~/.claude.json`); `desktop`: Claude Desktop
	 * (`claude_desktop_config.json`, por `mcp-remote`).
	 */
	public static function client_config( string $client, string $credential ): string {
		$name = self::server_name();
		$url  = self::endpoint();
		if ( 'desktop' === $client ) {
			// La cabecera por variable de entorno y sin espacios en el argumento (en Windows, Claude Desktop parte los
			// argumentos con espacios; es la forma que documenta mcp-remote).
			$server = array(
				'command' => 'npx',
				'args'    => array( '-y', 'mcp-remote', $url, '--header', 'Authorization:${AUTH_HEADER}' ),
				'env'     => array( 'AUTH_HEADER' => 'Basic ' . $credential ),
			);
		} else {
			$server = array(
				'type'    => 'http',
				'url'     => $url,
				'headers' => array( 'Authorization' => 'Basic ' . $credential ),
			);
		}
		return (string) wp_json_encode( array( 'mcpServers' => array( $name => $server ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/** El prompt para pedirle a un agente que se vincule (con la configuración de Claude Code dentro). */
	public static function agent_prompt( string $credential ): string {
		return 'Agrega a tu configuración de MCP el servidor de mi sitio ' . home_url( '/' ) . ' (DIGITALÍSIMO). Dirección: ' . self::endpoint() . ". Usa esta configuración tal cual dentro de «mcpServers» de mi configuración de usuario (en Claude Code, ~/.claude.json; nunca en un archivo que se suba a un repositorio, porque lleva la contraseña) y, al terminar, lista sus herramientas para comprobar que responde:\n\n" . self::client_config( 'code', $credential );
	}

	/** URL de la sección en «Rendimiento». */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::FRONT_PAGE ), $args ), admin_url( 'admin.php' ) ) . '#' . self::ANCHOR;
	}

	public static function nonce_action(): string {
		return self::ACTION_SAVE . '|' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
	}

	/** La sección «MCP de este sitio» de «Rendimiento». En la red, sólo una nota: no hay nada que heredar. */
	public static function render( bool $network ): void {
		echo '<h2 id="' . esc_attr( self::ANCHOR ) . '">' . esc_html( 'MCP de este sitio' ) . '</h2>';
		if ( $network ) {
			echo '<p class="description">' . esc_html( 'El MCP viene activo en cada sitio de la red con lectura y escritura en todas sus herramientas; no hay ajustes de red. El prompt para vincular un cliente está en la pantalla «Rendimiento» de cada sitio.' ) . '</p>';
			return;
		}
		self::render_notice();
		echo '<p>' . esc_html( 'El MCP está activo con lectura y escritura en todas las herramientas (constructor, tienda, SEO, Hosting, Tools e IA Tools). Cada asistente entra con un usuario de WordPress autenticado (contraseña de aplicación u OAuth), actúa con los permisos de ese usuario, y cada cambio queda en la bitácora (herramienta «digitalisimo-mcp-write-log»).' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-mcp-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">';
		wp_nonce_field( self::nonce_action() );
		echo '<input type="hidden" name="show_prompt" value="0">';
		echo '<p><label><input type="checkbox" name="show_prompt" value="1"' . ( self::show_prompt() ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Mostrar el prompt para vincular' ) . '</label> ';
		echo '<button type="submit" class="button">' . esc_html( 'Guardar' ) . '</button></p>';
		echo '<p class="description">' . esc_html( 'Ocultarlo no apaga nada: el MCP sigue activo.' ) . '</p>';
		echo '</form>';
		if ( self::show_prompt() ) {
			self::render_prompt();
		}
	}

	/** El bloque listo para copiar: dirección, configuración del cliente y dónde pegarla. */
	private static function render_prompt(): void {
		$placeholder = 'USUARIO_Y_CONTRASEÑA_EN_BASE64';
		$profile     = admin_url( 'profile.php#application-passwords-section' );
		echo '<div class="digitalisimo-mcp-prompt" data-placeholder="' . esc_attr( $placeholder ) . '">';
		echo '<p><strong>' . esc_html( 'Dirección del servidor MCP:' ) . '</strong> <code>' . esc_html( self::endpoint() ) . '</code></p>';
		echo '<ol>';
		$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		if ( function_exists( 'wp_is_application_passwords_available_for_user' ) && $user && ! wp_is_application_passwords_available_for_user( $user ) ) {
			echo '<li>' . esc_html( 'Las contraseñas de aplicación no están disponibles para tu usuario en este sitio (WordPress las pide con HTTPS, y algún plugin de seguridad puede desactivarlas). Actívalas o usa un cliente con OAuth.' ) . '</li>';
		} else {
			echo '<li>' . esc_html( 'Crea una contraseña de aplicación en tu perfil: ' ) . '<a href="' . esc_url( $profile ) . '">' . esc_html( 'Usuarios → Perfil → Contraseñas de aplicación' ) . '</a>.</li>';
		}
		echo '<li>' . esc_html( 'Escribe aquí tu usuario y esa contraseña: el bloque se completa en tu navegador; no se envían ni se guardan.' ) . '<br>';
		echo '<label>' . esc_html( 'Usuario' ) . ' <input type="text" class="regular-text digitalisimo-mcp-user" autocomplete="off" value="' . esc_attr( function_exists( 'wp_get_current_user' ) ? (string) wp_get_current_user()->user_login : '' ) . '"></label> ';
		echo '<label>' . esc_html( 'Contraseña de aplicación' ) . ' <input type="password" class="regular-text digitalisimo-mcp-pass" autocomplete="new-password"></label></li>';
		echo '<li>' . esc_html( 'Copia el bloque de tu cliente y pégalo dentro de «mcpServers» de su archivo de configuración de usuario (si ya tiene «mcpServers», agrega sólo la entrada de este sitio). Lleva tu contraseña: no lo pegues en un archivo que se suba a un repositorio. Después reinicia el cliente.' ) . '</li>';
		echo '</ol>';
		$blocks = array(
			'code'    => array( 'Claude Code: archivo ~/.claude.json de tu usuario (en Windows, %USERPROFILE%\\.claude.json)', self::client_config( 'code', $placeholder ) ),
			'desktop' => array( 'Claude Desktop: Configuración → Desarrollador → Editar configuración (claude_desktop_config.json)', self::client_config( 'desktop', $placeholder ) ),
			'prompt'  => array( 'O pídeselo a tu agente con este prompt', self::agent_prompt( $placeholder ) ),
		);
		foreach ( $blocks as $id => $block ) {
			echo '<p><strong>' . esc_html( $block[0] ) . '</strong></p>';
			echo '<textarea readonly rows="' . ( 'prompt' === $id ? 14 : 10 ) . '" class="large-text code digitalisimo-mcp-block" data-template="' . esc_attr( $block[1] ) . '">' . esc_textarea( $block[1] ) . '</textarea>';
			echo '<p><button type="button" class="button digitalisimo-mcp-copy">' . esc_html( 'Copiar' ) . '</button></p>';
		}
		echo '</div>';
		// Completa la credencial en el navegador (base64 de «usuario:contraseña»), sin enviarla, y copia cada bloque.
		echo '<script>(function(){var w=document.querySelector(".digitalisimo-mcp-prompt");if(!w){return;}var ph=w.getAttribute("data-placeholder"),u=w.querySelector(".digitalisimo-mcp-user"),p=w.querySelector(".digitalisimo-mcp-pass");function b64(s){try{return btoa(unescape(encodeURIComponent(s)));}catch(e){return ph;}}function fill(){var c=(u.value&&p.value)?b64(u.value+":"+p.value):ph;w.querySelectorAll(".digitalisimo-mcp-block").forEach(function(t){t.value=t.getAttribute("data-template").split(ph).join(c);});}u.addEventListener("input",fill);p.addEventListener("input",fill);w.querySelectorAll(".digitalisimo-mcp-copy").forEach(function(btn){btn.addEventListener("click",function(){var t=btn.parentNode.previousElementSibling;t.select();if(navigator.clipboard){navigator.clipboard.writeText(t.value);}else{document.execCommand("copy");}btn.textContent="Copiado";});});})();</script>';
	}

	private static function render_notice(): void {
		$code = isset( $_GET[ self::NOTICE_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el texto del aviso.
		if ( ! isset( self::NOTICES[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = self::NOTICES[ $code ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $text ) . '</p></div>';
	}

	public static function handle_save(): void {
		$result = self::process_save();
		if ( isset( $result['status'] ) && 403 === (int) $result['status'] ) {
			wp_die( esc_html( self::NOTICES[ $result['notice'] ][1] ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		wp_safe_redirect( self::url( array( self::NOTICE_PARAM => $result['notice'] ) ) );
		exit;
	}

	/** Guarda «Mostrar el prompt para vincular»: capacidad, POST y nonce. Nada más cambia. */
	public static function process_save(): array {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return array( 'status' => 403, 'notice' => 'forbidden' );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara.
			return array( 'notice' => 'method' );
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action() ) ) {
			return array( 'status' => 403, 'notice' => 'nonce' );
		}
		$show = isset( $_POST['show_prompt'] ) && '1' === $_POST['show_prompt']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce arriba; sólo se compara.
		if ( $show ) {
			delete_option( self::PROMPT_OPTION );
		} else {
			update_option( self::PROMPT_OPTION, 'hidden', false );
		}
		return array( 'notice' => 'saved' );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
