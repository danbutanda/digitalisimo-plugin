<?php
namespace Digitalisimo\Tools\Php_Snippets;
defined( 'ABSPATH' ) || exit;

/**
 * Pestaña «Código PHP» de Digitalisimo → Herramientas, en el sitio y en la red (§7), y su manejador único (§5.3).
 *
 * - Vista: lista (con «De la red en este sitio» dentro de una red, o «En los sitios» en la red), editor, «Si algo deja de
 *   funcionar» y «Registro». Sin CSS propio: clases de WordPress y de digitalisimo-ui. Todo dato se escapa (§5.5).
 * - Manejador: `wp_ajax_` y `admin_post_digitalisimo_tools_php`. Access::authorize() hace las comprobaciones 1-6 y
 *   Store::sanitize_item() el saneamiento; dispatch() devuelve la respuesta sin terminar la petición (lo prueban las
 *   pruebas) y respond() la envía.
 * - «Guardar y activar» son tres pasos (prepare → el navegador abre los destinos → activate), con un solo botón.
 * - Modos: completo; reducido (constante o cookie de rescate: sólo desactivar o detener); bloqueado (DISALLOW_FILE_EDIT o
 *   DISALLOW_FILE_MODS: lo activo sigue y sólo se desactiva o detiene); sólo lectura (puede detener pero no editar).
 * - Avisos del escritorio (sólo leen, nunca concilian), fila del plugin y correo de activación.
 */
final class Admin {
	const ACTION = 'digitalisimo_tools_php';
	const SCRIPT = 'digitalisimo-tools-php';
	const JSON   = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	/** Acciones que necesitan un fragmento (save y prepare aceptan además 'new'). */
	const NEED_ID = array( 'save', 'prepare', 'activate', 'discard', 'deactivate', 'delete', 'purge_history', 'pause_site', 'resume_site', 'resume_all' );

	const EVENTS = array(
		'created' => 'Creado', 'saved' => 'Guardado', 'check_failed' => 'Revisión fallida', 'test_failed' => 'Prueba fallida',
		'activated' => 'Activado', 'deactivated' => 'Desactivado', 'failed' => 'Desactivado por error', 'paused_site' => 'Pausado en este sitio',
		'resumed' => 'Reanudado', 'safe_on' => 'Detenido', 'safe_off' => 'Reanudado', 'imported' => 'Importado', 'conflict' => 'Conflicto con el origen',
		'origin_changed' => 'Origen actualizado', 'deleted' => 'Eliminado', 'history_purged' => 'Versiones anteriores vaciadas',
		'bad_signature_seen' => 'Firma no válida', 'rescue_created' => 'Enlace de rescate creado', 'rescue_used' => 'Enlace de rescate usado',
		'rescue_revoked' => 'Enlaces de rescate revocados',
	);
	const HISTORY_EVENTS = array( 'saved' => 'Guardada', 'activated' => 'Activada', 'origin' => 'Nueva versión del origen', 'unsigned' => 'Cambiada fuera de Tools (sin firma)' );

	const REVIEW_TEXT    = 'Revisé este código y quiero ejecutarlo en este sitio.';
	const REVIEW_MISSING = 'Marca «Revisé este código» para activarlo.';
	const BLOCKED_TEXT   = 'La edición de código está bloqueada en este servidor (DISALLOW_FILE_EDIT o DISALLOW_FILE_MODS). Los fragmentos activos siguen ejecutándose; sólo puedes desactivarlos o detenerlos.';
	const REDUCED_TEXT   = 'Modo seguro de Código PHP: aquí no se ejecuta ningún fragmento. Sólo puedes desactivar o detener fragmentos; para editar, sal del modo seguro.';
	const CONSTANT_TEXT  = 'Modo seguro de Código PHP activo por wp-config.php: no se ejecuta ningún fragmento.';
	const KEYS_DB_TEXT   = 'Las claves de seguridad de este sitio se guardan en la base de datos: la firma de los fragmentos no protege frente a cambios directos en ella. Defínelas en wp-config.php.';
	const RESCUE_TEXT    = 'Guarda este enlace: si algo deja de funcionar, te deja entrar sin fragmentos durante 7 días y una sola vez. También te lo enviamos por correo.';
	const DRAFT_DB_TEXT  = 'El borrador cambió fuera de DIGITALÍSIMO Tools: alguien modificó su código directamente en la base de datos. Revisa el código (abajo, las diferencias con la última versión firmada) antes de activarlo; si no reconoces el cambio, trátalo como un incidente de seguridad.';
	const DRAFT_KEYS_TEXT = 'La firma de este borrador ya no es válida porque cambiaron las claves de wp-config.php (por ejemplo, al migrar o restaurar el sitio). Revisa su código antes de activarlo.';
	/** Sitios que muestra cada lista de la pantalla de red antes de «y n más». */
	const LIST_SITES = 20;

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		Import::init();
	}

	/* ------------------------------------------------------------------ *
	 * Contexto
	 * ------------------------------------------------------------------ */

	private static function scope_for( $network ) {
		return ( $network && is_multisite() ) ? 'network' : 'site';
	}

	/** El botón y la pestaña: con can_edit, o con can_stop si hay fragmentos. */
	public static function visible( $network ) {
		$scope = self::scope_for( $network );
		if ( Access::can_edit( $scope ) ) return true;
		return Access::can_stop( $scope ) && self::has_snippets( $scope );
	}

	private static function has_snippets( $scope ) {
		if ( Store::index( $scope )['items'] || Store::run( $scope )['items'] ) return true;
		return 'site' === $scope && is_multisite() && (bool) Store::run( 'network' )['items'];
	}

	/** La petición es la pestaña «Código PHP» (de sitio o de red). */
	private static function on_screen() {
		return function_exists( 'is_admin' ) && is_admin() && Store::PAGE === ( $_GET['page'] ?? '' ) && 'php' === ( $_GET['tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** full, reduced (constante o cookie), blocked (DISALLOW_FILE_EDIT/MODS) o readonly (puede detener, no editar). */
	public static function mode( $scope ) {
		if ( Access::reduced() ) return 'reduced';
		if ( Access::editing_blocked() ) return 'blocked';
		return Access::can_edit( $scope ) ? 'full' : 'readonly';
	}

	/** Motivo por el que «Guardar y activar» no puede usarse ahora, o ''. */
	private static function stopped_reason( $scope ) {
		if ( defined( 'DIGITALISIMO_TOOLS_PHP_SAFE_MODE' ) && DIGITALISIMO_TOOLS_PHP_SAFE_MODE ) return self::CONSTANT_TEXT;
		$net = is_multisite() ? Store::run( 'network' ) : null;
		if ( 'network' === $scope ) return ! empty( $net['safe'] ) ? 'Los fragmentos de red están detenidos en todos los sitios: pulsa «Reanudar los fragmentos de red» para activar.' : '';
		if ( Store::run( 'site' )['safe'] ) return 'Los fragmentos están detenidos en este sitio: pulsa «Reanudar los fragmentos» para activar.';
		if ( ! empty( $net['safe_sites'] ) ) return 'Los fragmentos de los sitios están detenidos desde la administración de la red.';
		return '';
	}

	private static function url( $scope, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => Store::PAGE, 'tab' => 'php' ), $args ), 'network' === $scope ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) );
	}

	private static function nonce( $do, $scope, $id = '' ) {
		return wp_create_nonce( Access::nonce_action( $do, $scope, $id, get_current_blog_id() ) );
	}

	/** Nonces del editor de un fragmento (después de crearlo, el JavaScript recibe los del id nuevo). */
	public static function nonces( $scope, $id ) {
		return array( 'save' => self::nonce( 'save', $scope, $id ), 'prepare' => self::nonce( 'prepare', $scope, $id ), 'activate' => self::nonce( 'activate', $scope, $id ) );
	}

	/** Campos ocultos del manejador único. */
	private static function fields( $do, $scope, $id = '' ) {
		return '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="scope" value="' . esc_attr( $scope ) . '"><input type="hidden" name="blog_id" value="' . esc_attr( (string) get_current_blog_id() ) . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="hidden" name="nonce" value="' . esc_attr( self::nonce( $do, $scope, $id ) ) . '">';
	}

	/** Botón de una acción de la lista, en su propio formulario POST a admin-post.php. */
	private static function button( $do, $scope, $id, $label, $class = 'button', $confirm = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"' . ( '' !== $confirm ? ' data-digitalisimo-php-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . self::fields( $do, $scope, $id ) . '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form> ';
	}

	/** Botón de una reanudación con prueba («Reanudar en este sitio» o «en todos los sitios»): lo maneja el JavaScript. */
	private static function resume_button( $do, $scope, $id, $label ) {
		return '<button type="button" class="button" data-digitalisimo-php-resume="' . esc_attr( $do ) . '" data-scope="' . esc_attr( $scope ) . '" data-id="' . esc_attr( $id ) . '" data-prepare="' . esc_attr( self::nonce( $do, $scope, $id ) ) . '" data-activate="' . esc_attr( self::nonce( 'activate', $scope, $id ) ) . '" disabled>' . esc_html( $label ) . '</button> ';
	}

	private static function who( $uid ) {
		$uid = (int) $uid;
		if ( ! $uid ) return 'Sistema';
		$user = get_userdata( $uid );
		return $user ? (string) $user->display_name : '#' . $uid;
	}

	private static function date( $t ) {
		return $t ? wp_date( 'Y-m-d H:i', (int) $t ) : '—';
	}

	private static function state_html( $active, $label ) {
		return '<span class="digitalisimo-effective-state ' . ( $active ? 'is-active' : 'is-inactive' ) . '">' . esc_html( $label ) . '</span>';
	}

	/** Aviso en línea: $links va dentro del párrafo (enlaces); $forms, después (un formulario no puede ir dentro de un <p>). */
	private static function notice_html( $type, $text, $links = '', $forms = '' ) {
		return '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $text ) . $links . '</p>' . ( '' !== $forms ? self::bar( $forms ) : '' ) . '</div>';
	}

	/** Fila de botones (cada uno en su formulario). */
	private static function bar( $html ) {
		return '<div class="digitalisimo-php-actions" style="margin:8px 0">' . $html . '</div>';
	}

	/* ------------------------------------------------------------------ *
	 * Pantalla
	 * ------------------------------------------------------------------ */

	/** Pestaña «Código PHP»: la llama Network_Admin::page() con el contexto de la pantalla. */
	public static function page( $network ) {
		$scope = self::scope_for( $network );
		if ( ! self::visible( $network ) ) {
			echo self::notice_html( 'error', 'No tienes permiso para ver los fragmentos PHP.' ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		$mode = self::mode( $scope );
		// Al abrir la pestaña: concilia (sólo quita) y da por vistos los errores; con el bloqueo ocupado se deja para el cron.
		Store::reconcile( $scope );
		Store::mark_seen( $scope );
		echo '<div id="digitalisimo-php" class="digitalisimo-php">';
		self::flash_notice();
		self::screen_notices( $scope, $mode );
		$edit = $_GET['edit'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'full' === $mode && is_string( $edit ) && ( 'new' === $edit || Store::valid_id( $edit ) ) ) self::editor( $scope, $edit );
		elseif ( 'network' === $scope ) self::network_list( $mode );
		else self::site_list( $mode );
		echo '</div>';
	}

	private static function flash_notice() {
		$key    = Store::TRANSIENT . 'notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) return;
		delete_transient( $key );
		echo self::notice_html( in_array( $notice['type'] ?? '', array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info', (string) ( $notice['text'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Avisos propios de la pestaña: modo, servidor, claves y firmas. */
	private static function screen_notices( $scope, $mode ) {
		$cookie = Rescue::cookie_user();
		// La cookie sólo actúa para su usuario (o la de un enlace pedido a la dirección de administración, uid 0).
		if ( null !== $cookie && 0 !== $cookie && (int) get_current_user_id() !== $cookie ) $cookie = null;
		if ( defined( 'DIGITALISIMO_TOOLS_PHP_SAFE_MODE' ) && DIGITALISIMO_TOOLS_PHP_SAFE_MODE ) {
			echo self::notice_html( 'warning', self::CONSTANT_TEXT . ' Sólo puedes desactivar o detener fragmentos.' ); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( null !== $cookie ) {
			$text = ( 'reduced' === $mode ? self::REDUCED_TEXT . ' ' : '' ) . sprintf( 'Modo seguro de Código PHP: en esta pantalla y al iniciar sesión no se ejecuta ningún fragmento hasta las %s.', wp_date( 'H:i', Rescue::cookie_expires() ) );
			echo self::notice_html( 'warning', $text, '', self::button( 'rescue_exit', $scope, '', 'Salir del modo seguro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'reduced' === $mode ) {
			echo self::notice_html( 'warning', self::REDUCED_TEXT ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( 'blocked' === $mode ) echo self::notice_html( 'warning', self::BLOCKED_TEXT ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'readonly' === $mode ) echo self::notice_html( 'info', 'Tu usuario puede desactivar y detener los fragmentos, pero no ver ni editar su código.' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'full' === $mode && ! Stream::register() ) echo self::notice_html( 'error', Stream::problem_message() ); // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( self::signature_messages( $scope ) as $text ) echo self::notice_html( 'error', $text ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( self::has_snippets( $scope ) && ! Store::signature_effective() ) echo self::notice_html( 'warning', self::KEYS_DB_TEXT ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Textos de §7.6 para las firmas no válidas del ámbito (índice tras conciliar y `_run` aún sin conciliar). */
	private static function signature_messages( $scope ) {
		$index = Store::index( $scope );
		$run   = Store::run( $scope );
		$bad   = array( 'keys' => 0, 'database' => 0 );
		foreach ( $index['items'] as $meta ) {
			// Siguen hasta que cada uno se revise y reactive (o se elimine): abrir la pestaña no los da por vistos.
			if ( 'failed' === $meta['status'] && 'signature' === ( $meta['error']['kind'] ?? '' ) ) $bad[ 'keys' === ( $meta['error']['cause'] ?? '' ) ? 'keys' : 'database' ]++;
		}
		$kind = Store::bad_signature_kind( $run );
		foreach ( $run['items'] as $id => $item ) if ( ! Store::verify( array_merge( $item, array( 'scope' => $scope, 'id' => $id ) ) ) ) $bad[ $kind ]++;
		$out = array();
		if ( $bad['keys'] ) $out[] = Store::signature_text( 'keys', $bad['keys'] );
		if ( $bad['database'] ) $out[] = Store::signature_text( 'database', $bad['database'] );
		return $out;
	}

	/** Fila de la tabla de un fragmento propio del ámbito. */
	private static function row( $scope, array $row, $mode, $extra_cell = null ) {
		$title = '' !== $row['title'] ? $row['title'] : 'Fragmento sin nombre';
		$name  = 'full' === $mode ? '<a href="' . esc_url( self::url( $scope, array( 'edit' => $row['id'] ) ) ) . '"><strong>' . esc_html( $title ) . '</strong></a>' : '<strong>' . esc_html( $title ) . '</strong>';
		$error = is_array( $row['error'] ) && 'fatal' === ( $row['error']['kind'] ?? '' ) && 'failed' === $row['state']
			? '<br><span class="description">' . esc_html( sprintf( 'Error: %s%s%s', (string) ( $row['error']['message'] ?? '' ), empty( $row['error']['line'] ) ? '' : sprintf( ' (línea %d)', (int) $row['error']['line'] ), empty( $row['error']['path'] ) ? '' : ' en ' . $row['error']['path'] ) ) . '</span>'
			: '';
		$actions = $row['active'] ? self::button( 'deactivate', $scope, $row['id'], 'Desactivar' ) : '';
		echo '<tr><td>' . $name . $error . '</td><td>' . self::state_html( $row['active'], $row['label'] ) . '</td>' . ( null === $extra_cell ? '' : '<td>' . $extra_cell . '</td>' ) . '<td>' . esc_html( self::date( $row['modified'] ) . ' · ' . self::who( $row['by'] ) ) . '</td><td>' . $actions . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function site_list( $mode ) {
		$run = Store::run( 'site' );
		$net = is_multisite() ? Store::run( 'network' ) : null;
		echo '<h2>Código PHP</h2><p>Código PHP propio de este sitio, sin tema hijo. Cada fragmento se revisa y se prueba antes de activarse; si después provoca un error, se desactiva solo.</p>';
		$stopped = $run['safe'] ? 'Detenida en este sitio' : ( ! empty( $net['safe_sites'] ) ? 'Detenida desde la red' : '' );
		echo '<p><strong>Ejecución:</strong> ' . self::state_html( '' === $stopped, '' === $stopped ? 'activa' : $stopped ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$bar = $run['safe'] ? ( 'full' === $mode ? self::button( 'resume', 'site', '', 'Reanudar los fragmentos' ) : '' ) : self::button( 'stop', 'site', '', 'Detener todos los fragmentos en este sitio' );
		if ( 'full' === $mode ) $bar = '<a class="button button-primary" href="' . esc_url( self::url( 'site', array( 'edit' => 'new' ) ) ) . '">Agregar fragmento</a> ' . $bar;
		if ( '' !== $bar ) echo self::bar( $bar ); // phpcs:ignore WordPress.Security.EscapeOutput
		$rows = Store::overview( 'site' );
		echo '<table class="widefat striped"><thead><tr><th>Nombre</th><th>Estado</th><th>Modificado</th><th>Acción</th></tr></thead><tbody>';
		if ( ! $rows ) echo '<tr><td colspan="4">Todavía no hay fragmentos en este sitio.</td></tr>';
		foreach ( $rows as $row ) self::row( 'site', $row, $mode );
		echo '</tbody></table>';
		if ( $net && $net['items'] ) self::network_here( $net, $run, $mode );
		self::recovery( 'site', $mode );
		if ( 'full' === $mode ) self::log_table( 'site' );
	}

	/** «De la red en este sitio» (sólo lectura del código; pausar y reanudar aquí). */
	private static function network_here( array $net, array $run, $mode ) {
		$all = wp_load_alloptions();
		echo '<h3>De la red en este sitio</h3><table class="widefat striped"><thead><tr><th>Nombre</th><th>Estado aquí</th><th>Acción</th></tr></thead><tbody>';
		foreach ( $net['items'] as $id => $item ) {
			$here  = Store::network_state_here( $id, $net, $run, $all );
			$state = $here ? $here['state'] : 'here_stopped';
			$label = Store::STATES[ $state ];
			if ( 'here_error' === $state ) $label = sprintf( 'Pausado aquí por un error el %s en %s', self::date( $here['row']['t'] ?? 0 ), '' !== (string) ( $here['row']['path'] ?? '' ) ? $here['row']['path'] : '—' );
			$actions = '';
			if ( 'here_active' === $state ) $actions .= self::button( 'pause_site', 'site', $id, 'Pausar en este sitio' );
			if ( 'full' === $mode && in_array( $state, array( 'here_error', 'here_manual' ), true ) ) $actions .= self::resume_button( 'resume_site', 'site', $id, 'Reanudar en este sitio' );
			if ( Access::can_edit( 'network' ) && 'full' === $mode ) $actions .= '<a href="' . esc_url( self::url( 'network', array( 'edit' => $id ) ) ) . '">Abrir en la red</a>';
			echo '<tr><td><strong>' . esc_html( '' !== $item['title'] ? $item['title'] : 'Fragmento sin nombre' ) . '</strong></td><td>' . self::state_html( 'here_active' === $state, $label ) . '</td><td>' . $actions . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	}

	private static function network_list( $mode ) {
		$run = Store::run( 'network' );
		echo '<h2>Código PHP</h2><p>Código PHP de toda la red. Se ejecuta en todos los sitios, antes que el código de cada sitio. Si falla en un sitio, se pausa sólo en ese sitio.</p>';
		echo '<p><strong>Fragmentos de red:</strong> ' . self::state_html( ! $run['safe'], $run['safe'] ? 'Detenidos en todos los sitios' : 'activos' ) . '<br><strong>Fragmentos de cada sitio:</strong> ' . self::state_html( ! $run['safe_sites'], $run['safe_sites'] ? 'Detenidos en todos los sitios' : 'activos' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$bar  = 'full' === $mode ? '<a class="button button-primary" href="' . esc_url( self::url( 'network', array( 'edit' => 'new' ) ) ) . '">Agregar fragmento</a> ' : '';
		$bar .= $run['safe'] ? ( 'full' === $mode ? self::button( 'resume', 'network', '', 'Reanudar los fragmentos de red' ) : '' ) : self::button( 'stop', 'network', '', 'Detener los fragmentos de red' );
		$bar .= $run['safe_sites'] ? ( 'full' === $mode ? self::button( 'resume_sites', 'network', '', 'Reanudar los fragmentos de los sitios' ) : '' ) : self::button( 'stop_sites', 'network', '', 'Detener los fragmentos de todos los sitios' );
		if ( '' !== $bar ) echo self::bar( $bar ); // phpcs:ignore WordPress.Security.EscapeOutput
		$inventory = Store::network_inventory();
		$rows      = Store::overview( 'network' );
		// Cada lista muestra como mucho LIST_SITES sitios y «y n más», con enlaces construidos sin cambiar de sitio: si un
		// fragmento de red falla en miles de sitios, esta pantalla es la que tiene «Detener» y «Reanudar en todos».
		$ids = array_slice( array_keys( $inventory['sites'] ), 0, self::LIST_SITES );
		foreach ( $inventory['paused'] as $paused ) $ids = array_merge( $ids, array_slice( array_column( $paused['error'], 'blog_id' ), 0, self::LIST_SITES ), array_slice( $paused['manual'], 0, self::LIST_SITES ) );
		$links = Store::site_links( $ids );
		echo '<table class="widefat striped"><thead><tr><th>Nombre</th><th>Estado</th><th>En los sitios</th><th>Modificado</th><th>Acción</th></tr></thead><tbody>';
		if ( ! $rows ) echo '<tr><td colspan="5">Todavía no hay fragmentos de red.</td></tr>';
		foreach ( $rows as $row ) {
			$paused = $inventory['paused'][ $row['id'] ] ?? array( 'error' => array(), 'manual' => array() );
			$cell   = '—';
			if ( $row['active'] ) {
				$n    = count( $paused['error'] );
				$m    = count( $paused['manual'] );
				$cell = esc_html( $n || $m ? sprintf( 'Pausado en %s por un error · %d a mano', Store::plural( $n, '%d sitio', '%d sitios' ), $m ) : 'En todos' );
				if ( $n || $m ) {
					$cell .= '<details><summary>Ver los sitios</summary><ul>';
					foreach ( array_slice( $paused['error'], 0, self::LIST_SITES ) as $site ) $cell .= '<li>' . self::site_link( $links, $site['blog_id'] ) . ' · ' . esc_html( sprintf( 'error el %s en %s: %s', self::date( $site['t'] ), '' !== $site['path'] ? $site['path'] : '—', Store::one_line( $site['msg'], 200 ) ) ) . '</li>';
					if ( $n > self::LIST_SITES ) $cell .= '<li>' . esc_html( sprintf( 'y %d más con error.', $n - self::LIST_SITES ) ) . '</li>';
					foreach ( array_slice( $paused['manual'], 0, self::LIST_SITES ) as $blog ) $cell .= '<li>' . self::site_link( $links, $blog ) . ' · pausado a mano</li>';
					if ( $m > self::LIST_SITES ) $cell .= '<li>' . esc_html( sprintf( 'y %d más pausados a mano.', $m - self::LIST_SITES ) ) . '</li>';
					$cell .= '</ul></details>';
					if ( $n && 'full' === $mode ) $cell .= self::resume_button( 'resume_all', 'network', $row['id'], 'Reanudar en todos los sitios' );
				}
			}
			self::row( 'network', $row, $mode, $cell );
		}
		echo '</tbody></table>';
		$sites = $inventory['sites'];
		echo '<details><summary>' . esc_html( sprintf( 'Sitios con fragmentos propios (%d)', count( $sites ) ) ) . '</summary><ul>';
		if ( ! $sites ) echo '<li>Ningún sitio tiene fragmentos propios.</li>';
		foreach ( array_slice( $sites, 0, self::LIST_SITES, true ) as $blog => $count ) echo '<li>' . self::site_link( $links, $blog ) . ' · ' . esc_html( Store::plural( $count, '%d fragmento', '%d fragmentos' ) ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( count( $sites ) > self::LIST_SITES ) echo '<li>' . esc_html( sprintf( 'y %d más.', count( $sites ) - self::LIST_SITES ) ) . '</li>';
		echo '</ul></details>';
		self::recovery( 'network', $mode );
		if ( 'full' === $mode ) self::log_table( 'network' );
	}

	/** Enlace a la pestaña «Código PHP» de un sitio, con su portada como texto (de Store::site_links(), sin cambiar de sitio). */
	private static function site_link( array $links, $blog ) {
		$blog = (int) $blog;
		if ( ! isset( $links[ $blog ] ) ) return esc_html( sprintf( 'Sitio %d', $blog ) );
		return '<a href="' . esc_url( $links[ $blog ]['tab'] ) . '">' . esc_html( $links[ $blog ]['home'] ) . '</a>';
	}

	/** «Si algo deja de funcionar»: enlace de rescate, petición pública y constante. */
	private static function recovery( $scope, $mode ) {
		$exp = Rescue::status( get_current_user_id() );
		echo '<details><summary>Si algo deja de funcionar</summary>';
		echo '<p>' . ( $exp ? '<span class="digitalisimo-secret is-set">' . esc_html( sprintf( 'Tienes un enlace de rescate vigente hasta el %s', self::date( $exp ) ) ) . '</span>' : '<span class="digitalisimo-secret is-empty">No tienes un enlace de rescate vigente</span>' ) . '</p>';
		if ( 'full' === $mode ) {
			echo self::bar( self::button( 'rescue_new', $scope, '', 'Crear enlace de rescate' ) . self::button( 'rescue_revoke', $scope, '', 'Revocar enlaces y modos seguros', 'button', 'revoke' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div id="digitalisimo-php-rescue-out" aria-live="polite"></div>';
		}
		echo '<p>' . esc_html( sprintf( 'Si no puedes entrar al escritorio, abre %s para recibir un enlace en la dirección de administración.', Rescue::request_link() ) ) . '</p>';
		echo '<p>Con acceso a los archivos: añade <code>define( \'DIGITALISIMO_TOOLS_PHP_SAFE_MODE\', true );</code> a wp-config.php.</p></details>';
	}

	private static function log_table( $scope ) {
		$index = Store::index( $scope );
		echo '<details><summary>Registro</summary><table class="widefat striped"><thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Fragmento</th><th>Detalle</th><th>IP</th></tr></thead><tbody>';
		$entries = Store::log_entries( $scope, 30 );
		if ( ! $entries ) echo '<tr><td colspan="6">Sin eventos.</td></tr>';
		foreach ( $entries as $entry ) {
			$id    = (string) ( $entry['id'] ?? '' );
			$title = '' === $id ? '—' : ( $index['items'][ $id ]['title'] ?? $id );
			echo '<tr><td>' . esc_html( self::date( $entry['t'] ?? 0 ) ) . '</td><td>' . esc_html( self::who( $entry['user'] ?? 0 ) ) . '</td><td>' . esc_html( self::EVENTS[ $entry['event'] ?? '' ] ?? (string) ( $entry['event'] ?? '' ) ) . '</td><td>' . esc_html( $title ) . '</td><td>' . esc_html( (string) ( $entry['msg'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $entry['ip'] ?? '' ) ) . '</td></tr>';
		}
		echo '</tbody></table></details>';
	}

	/* ------------------------------------------------------------------ *
	 * Editor
	 * ------------------------------------------------------------------ */

	private static function editor( $scope, $id ) {
		$new   = 'new' === $id;
		$index = Store::index( $scope );
		$back  = '<p><a href="' . esc_url( self::url( $scope ) ) . '">← Volver a la lista</a></p>';
		if ( ! $new && ! isset( $index['items'][ $id ] ) ) {
			echo $back . self::notice_html( 'error', 'El fragmento no existe.' ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		$run    = Store::run( $scope );
		$item   = $new ? Store::normalize_item( array() ) : Store::item( $scope, $id );
		$meta   = $new ? array( 'title' => '', 'review' => false, 'conflict' => false, 'error' => null ) : $index['items'][ $id ];
		$state  = $new ? 'inactive' : Store::state( $scope, $id, $index, $run );
		$active = $run['items'][ $id ] ?? null;
		$code   = $new ? "<?php\n" : $item['code'];
		echo $back . '<h2>' . esc_html( $new ? 'Agregar fragmento' : 'Editar fragmento' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $new ) echo '<p id="digitalisimo-php-state">' . self::state_html( in_array( $state, array( 'active', 'changed' ), true ), Store::STATES[ $state ] ) . ( 'network' === $scope ? ' <span class="description">Fragmento de red: se ejecuta en todos los sitios.</span>' : '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$version = $_GET['version'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $new && is_string( $version ) && ctype_digit( $version ) ) {
			$entry = Store::version( $scope, $id, (int) $version );
			if ( $entry ) {
				$code = (string) $entry['code'];
				echo self::notice_html( 'info', sprintf( 'Estás viendo la versión del %s. Pulsa «Guardar y activar» para volver a ella.', self::date( $entry['at'] ?? 0 ) ) . ( ! empty( $entry['activated'] ) && ! $entry['sig_valid'] ? ' Su firma ya no es válida: activarla exige revisarla.' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		if ( 'failed' === $state && is_array( $meta['error'] ?? null ) ) {
			$e = $meta['error'];
			echo self::notice_html( 'error', sprintf( 'Se desactivó por un error fatal el %s%s: %s%s. Corrígelo y pulsa «Guardar y activar» para volver a probarlo.', self::date( $e['at'] ?? 0 ), empty( $e['path'] ) ? '' : ' en ' . $e['path'], (string) ( $e['message'] ?? '' ), empty( $e['line'] ) ? '' : sprintf( ' (línea %d)', (int) $e['line'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( 'signature' === $state ) {
			$cause = 'keys' === ( $meta['error']['cause'] ?? '' ) || ( $active && 'keys' === Store::bad_signature_kind( $run ) );
			echo self::notice_html( 'error', $cause ? 'Este fragmento se detuvo porque su firma ya no es válida: cambiaron las claves de wp-config.php. Revisa su código, marca la casilla y pulsa «Guardar y activar».' : 'Este fragmento cambió fuera de DIGITALÍSIMO Tools y no se ejecuta. Revisa su código antes de reactivarlo; si no reconoces el cambio, trátalo como un incidente de seguridad.' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		// Un borrador sin firma válida lo cambió alguien directamente en la BD (o cambiaron las claves): aviso, diferencias con
		// la última versión firmada y la casilla de revisión (needs_review() lo exige para ese código).
		$draft    = $new ? 'saved' : Store::draft_status( $scope, $id, $item );
		$tampered = in_array( $draft, array( 'database', 'keys' ), true );
		if ( $tampered ) echo self::notice_html( 'error', 'keys' === $draft ? self::DRAFT_KEYS_TEXT : self::DRAFT_DB_TEXT ); // phpcs:ignore WordPress.Security.EscapeOutput
		$needs  = ! $new && Tester::needs_review( $scope, $id, hash( 'sha256', $code ) );
		$reason = self::stopped_reason( $scope );
		if ( '' === $reason && ! Stream::register() ) $reason = Stream::problem_message();
		echo '<div id="digitalisimo-php-messages" aria-live="polite"></div>';
		echo '<form id="digitalisimo-php-editor" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . self::fields( 'save', $scope, $new ? 'new' : $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p><label for="digitalisimo-php-title"><strong>Nombre</strong></label><br><input type="text" class="regular-text" id="digitalisimo-php-title" name="title" maxlength="' . esc_attr( (string) Store::MAX_TITLE ) . '" value="' . esc_attr( $meta['title'] ) . '"></p>';
		echo '<p><label for="digitalisimo-php-code"><strong>Código</strong></label></p><textarea id="digitalisimo-php-code" name="code" rows="22" class="large-text code" spellcheck="false" style="font-family:monospace">' . esc_textarea( $code ) . '</textarea>';
		if ( $needs ) {
			echo self::review_notes( $item['origin'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<p><label><input type="checkbox" name="reviewed" value="1" id="digitalisimo-php-reviewed"> ' . esc_html( self::REVIEW_TEXT ) . '</label></p>';
		}
		echo '<p><button type="button" class="button button-primary" id="digitalisimo-php-activate" disabled' . ( '' !== $reason ? ' data-reason="' . esc_attr( $reason ) . '"' : '' ) . '>Guardar y activar</button> <button type="submit" class="button" id="digitalisimo-php-save">Guardar sin activar</button></p>';
		echo '<p class="description">' . esc_html( 'network' === $scope ? 'Para probarlo, tu navegador abre una vez el escritorio de la red y la portada de hasta cinco sitios con el código nuevo, sin que lo vean los visitantes. Si el código envía correos o guarda datos, también lo hará durante la prueba.' : 'Para probarlo, tu navegador abre una vez el escritorio y otra la portada con el código nuevo, sin que lo vean los visitantes. Si el código envía correos o guarda datos, también lo hará durante la prueba.' ) . '</p>';
		if ( '' !== $reason ) echo '<p class="description"><strong>' . esc_html( $reason ) . '</strong></p>';
		echo '<noscript><p class="description">Activar necesita JavaScript. Puedes guardar sin activar.</p></noscript></form>';
		if ( ! $new ) {
			$bar = '';
			if ( 'changed' === $state ) $bar .= self::button( 'discard', $scope, $id, 'Descartar cambios' );
			if ( $active && in_array( $state, array( 'active', 'changed' ), true ) ) $bar .= self::button( 'deactivate', $scope, $id, 'Desactivar' );
			if ( ! $active ) $bar .= self::button( 'delete', $scope, $id, 'Eliminar', 'button button-link-delete', 'delete' );
			if ( '' !== $bar ) echo self::bar( $bar ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $item['origin'] ) self::origin_details( $item['origin'] );
			if ( 'changed' === $state && $active ) self::diff( 'Cambios respecto de la versión activa', $active['code'], $item['code'], 'Versión activa', 'Borrador' );
			if ( $tampered ) {
				$trusted = Store::last_trusted( $scope, $id, $item['history'], hash( 'sha256', $item['code'] ) );
				if ( $trusted ) self::diff( 'Cambios respecto de la última versión con firma válida', (string) $trusted['code'], $item['code'], 'Última versión con firma válida', 'Borrador' );
			} elseif ( 'signature' === $state ) {
				foreach ( $item['history'] as $entry ) {
					if ( empty( $entry['activated'] ) || ! Store::history_signature_valid( $scope, $id, $entry ) ) continue;
					self::diff( 'Cambios respecto de la última versión con firma válida', (string) $entry['code'], $item['code'], 'Última versión con firma válida', 'Borrador' );
					break;
				}
			}
			self::versions( $scope, $id, $item );
		}
	}

	/** Avisos de una importación que exige revisión (autor sin permiso de código, PHP que no se ejecutaba en el origen). */
	private static function review_notes( array $origin ) {
		$out = '';
		foreach ( array( 'author' => 'Lo escribió %s (%s), que no tenía permiso para código PHP.', 'last_editor' => 'Lo editó por última vez %s (%s), que no tenía permiso para código PHP.' ) as $key => $text ) {
			$person = is_array( $origin[ $key ] ?? null ) ? $origin[ $key ] : null;
			if ( $person && '' !== (string) ( $person['name'] ?? '' ) && empty( $person['can_code'] ) ) $out .= self::notice_html( 'warning', sprintf( $text, $person['name'], '' !== (string) ( $person['role'] ?? '' ) ? $person['role'] : 'sin rol' ) );
		}
		if ( $origin && empty( $origin['executed_in_origin'] ) ) $out .= self::notice_html( 'warning', sprintf( 'En el origen este PHP no se ejecutaba (%s); activarlo cambia el comportamiento.', '' !== (string) ( $origin['reason'] ?? '' ) ? $origin['reason'] : 'sin motivo indicado' ) );
		return $out;
	}

	private static function origin_details( array $origin ) {
		$rows = array(
			'Fuente'           => $origin['source'] ?? '',
			'Versión'          => $origin['version'] ?? '',
			'Identificador'    => $origin['id'] ?? '',
			'Sitio de origen'  => $origin['site'] ?? '',
			'Gancho de origen' => $origin['hook'] ?? '',
			'Autor'            => isset( $origin['author']['name'] ) ? trim( $origin['author']['name'] . ( '' !== (string) ( $origin['author']['role'] ?? '' ) ? ' (' . $origin['author']['role'] . ')' : '' ) ) : '',
			'Último editor'    => isset( $origin['last_editor']['name'] ) ? trim( $origin['last_editor']['name'] . ( '' !== (string) ( $origin['last_editor']['role'] ?? '' ) ? ' (' . $origin['last_editor']['role'] . ')' : '' ) ) : '',
			'Importado por'    => self::who( $origin['by'] ?? 0 ) . ' · ' . self::date( $origin['at'] ?? 0 ),
		);
		echo '<details><summary>Origen</summary><table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( '' !== (string) $value ? (string) $value : '—' ) . '</td></tr>';
		echo '</tbody></table>';
		if ( ! empty( $origin['notes'] ) ) {
			echo '<p><strong>Notas del importador</strong></p><ul>';
			foreach ( (array) $origin['notes'] as $note ) echo '<li>' . esc_html( (string) $note ) . '</li>';
			echo '</ul>';
		}
		if ( isset( $origin['original'] ) && '' !== (string) $origin['original'] ) echo '<p><strong>Código original</strong></p><textarea class="large-text code" rows="10" readonly spellcheck="false" style="font-family:monospace">' . esc_textarea( (string) $origin['original'] ) . '</textarea>';
		echo '</details>';
	}

	private static function diff( $summary, $left, $right, $title_left, $title_right ) {
		$html = function_exists( 'wp_text_diff' ) ? wp_text_diff( (string) $left, (string) $right, array( 'title_left' => $title_left, 'title_right' => $title_right ) ) : '';
		// wp_text_diff() ya escapa el contenido.
		echo '<details><summary>' . esc_html( $summary ) . '</summary>' . ( $html ? $html : '<p>Sin diferencias de texto.</p>' ) . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function versions( $scope, $id, array $item ) {
		$history = $item['history'];
		echo '<details><summary>' . esc_html( sprintf( 'Versiones anteriores (%d)', count( $history ) ) ) . '</summary><table class="widefat striped"><thead><tr><th>Fecha</th><th>Persona</th><th>Tipo</th><th>Firma</th><th></th></tr></thead><tbody>';
		foreach ( $history as $n => $entry ) {
			$sig = ! empty( $entry['activated'] ) ? ( Store::history_signature_valid( $scope, $id, $entry ) ? 'Válida' : 'No válida' ) : '—';
			echo '<tr><td>' . esc_html( self::date( $entry['at'] ?? 0 ) ) . '</td><td>' . esc_html( self::who( $entry['by'] ?? 0 ) ) . '</td><td>' . esc_html( self::HISTORY_EVENTS[ $entry['event'] ?? 'saved' ] ?? 'Guardada' ) . '</td><td>' . esc_html( $sig ) . '</td><td><a href="' . esc_url( self::url( $scope, array( 'edit' => $id, 'version' => (int) $n ) ) ) . '">Abrir</a></td></tr>';
		}
		echo '</tbody></table>' . self::bar( self::button( 'purge_history', $scope, $id, 'Vaciar versiones anteriores', 'button', 'purge' ) ) . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ------------------------------------------------------------------ *
	 * Recursos
	 * ------------------------------------------------------------------ */

	/** Sólo en la pestaña: el editor de código (en el editor) y php-snippets.js con su configuración en línea. */
	public static function assets() {
		if ( ! self::on_screen() ) return;
		$network = function_exists( 'is_network_admin' ) && is_network_admin();
		$scope   = self::scope_for( $network );
		if ( ! self::visible( $network ) ) return;
		$edit    = $_GET['edit'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
		$editing = 'full' === self::mode( $scope ) && is_string( $edit ) && ( 'new' === $edit || Store::valid_id( $edit ) );
		$editor  = $editing && function_exists( 'wp_enqueue_code_editor' ) ? wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php', 'codemirror' => array( 'indentUnit' => 4, 'indentWithTabs' => true ) ) ) : false;
		wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/js/php-snippets.js', DIGITALISIMO_TOOLS_FILE ), array(), DIGITALISIMO_TOOLS_VERSION, true );
		wp_add_inline_script( self::SCRIPT, self::config_script( self::config( $scope, $editing ? $edit : '', $editor ) ), 'before' );
	}

	/** Configuración del JavaScript (sin secretos guardados). */
	public static function config( $scope, $id, $editor = false ) {
		return array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'action' => self::ACTION,
			'param'  => Tester::PARAM,
			'scope'  => $scope,
			'blog'   => (int) get_current_blog_id(),
			'id'     => (string) $id,
			'nonces' => '' === (string) $id ? array() : self::nonces( $scope, $id ),
			'editor' => $editor ? $editor : false,
			'texts'  => array(
				'activate' => 'Guardar y activar',
				'busy'     => 'Revisando y probando…',
				'saving'   => 'Guardando…',
				'network'  => 'No se pudo conectar con el servidor. Vuelve a intentarlo; el código sigue en el editor.',
				'warnings' => 'Avisos:',
				'copy'     => 'Copiar',
				'copied'   => 'Copiado',
				'resuming' => 'Probando…',
				'review'   => self::REVIEW_TEXT,
				'confirm'  => array(
					'delete' => '¿Eliminar este fragmento? No se puede deshacer.',
					'purge'  => '¿Vaciar las versiones anteriores? Se conservan el borrador y la versión activa.',
					'revoke' => '¿Revocar todos los enlaces de rescate y modos seguros de este sitio?',
				),
			),
		);
	}

	/** `window.digitalisimoToolsPhp = …;` con JSON_HEX_*: ningún dato puede cerrar el <script>. */
	public static function config_script( array $config ) {
		return 'window.digitalisimoToolsPhp = ' . wp_json_encode( $config, self::JSON ) . ';';
	}

	/* ------------------------------------------------------------------ *
	 * Manejador único (§5.3)
	 * ------------------------------------------------------------------ */

	public static function handle() {
		$ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
		self::respond( self::dispatch( $_POST, $ajax ), $ajax ); // phpcs:ignore WordPress.Security.NonceVerification -- Access::authorize() verifica el nonce.
	}

	private static function reply( $ok, $message, array $data = array(), array $extra = array() ) {
		// Un fallo de negocio (revisión, prueba, topes) responde 200 con ok=false; los de permisos y formato, su código.
		return array_merge( array( 'ok' => (bool) $ok, 'status' => 200, 'type' => $ok ? 'success' : 'error', 'message' => (string) $message, 'data' => $data, 'redirect' => '' ), $extra );
	}

	private static function from_error( \WP_Error $error, array $data = array(), $redirect = '' ) {
		$info = $error->get_error_data();
		return self::reply( false, $error->get_error_message(), $data, array( 'status' => (int) ( is_array( $info ) && isset( $info['status'] ) ? $info['status'] : 400 ), 'redirect' => $redirect ) );
	}

	/**
	 * Atiende una petición del manejador sin terminarla.
	 *
	 * @return array { ok, status, type, message, data, redirect, page? }
	 */
	public static function dispatch( array $post, $ajax = false ) {
		$do    = is_string( $post['do'] ?? null ) ? $post['do'] : '';
		$scope = is_string( $post['scope'] ?? null ) ? $post['scope'] : '';
		$blog  = is_scalar( $post['blog_id'] ?? null ) ? absint( $post['blog_id'] ) : 0;
		$id    = is_string( $post['id'] ?? null ) ? $post['id'] : '';
		$nonce = is_string( $post['nonce'] ?? null ) ? $post['nonce'] : '';
		$ok    = Access::authorize( $do, $scope, $blog, $id, $nonce );
		if ( is_wp_error( $ok ) ) return self::from_error( $ok );
		if ( in_array( $do, self::NEED_ID, true ) && ! Store::valid_id( $id ) && ! ( 'new' === $id && in_array( $do, array( 'save', 'prepare' ), true ) ) ) return self::reply( false, 'Fragmento no válido.', array(), array( 'status' => 400 ) );
		if ( ! in_array( $do, self::NEED_ID, true ) && '' !== $id ) return self::reply( false, 'Fragmento no válido.', array(), array( 'status' => 400 ) );
		$scope = Store::scope( $scope );
		$uid   = (int) get_current_user_id();
		$list  = self::url( $scope );
		switch ( $do ) {
			case 'save':
				return self::do_save( $post, $scope, $id, $uid );
			case 'prepare':
				return self::do_prepare( $post, $scope, $id, $uid );
			case 'resume_site':
			case 'resume_all':
				return self::do_resume( $do, $scope, $id );
			case 'activate':
				return self::do_activate( $post, $scope, $id, $uid );
			case 'discard':
				return self::done( Store::discard( $scope, $id, $uid ), 'Cambios descartados: el borrador vuelve a ser la versión activa.', self::url( $scope, array( 'edit' => $id ) ) );
			case 'deactivate':
				return self::done( Store::deactivate( $scope, $id, $uid ), 'Fragmento desactivado.', $list );
			case 'delete':
				return self::done( Store::delete( $scope, $id, $uid ), 'Fragmento eliminado.', $list );
			case 'purge_history':
				return self::done( Store::purge_history( $scope, $id, $uid ), 'Versiones anteriores vaciadas. Se conservan el borrador y la versión activa.', self::url( $scope, array( 'edit' => $id ) ) );
			case 'pause_site':
				if ( 'site' !== $scope ) return self::reply( false, 'Esta acción sólo existe en la administración de cada sitio.', array(), array( 'status' => 400 ) );
				return self::done( Store::pause_here( $id, $uid ), 'Fragmento de red pausado en este sitio.', $list );
			case 'stop':
				return self::done( Store::set_safe( $scope, 'safe', true, $uid ), 'network' === $scope ? 'Fragmentos de red detenidos en todos los sitios.' : 'Fragmentos detenidos en este sitio, también los de la red.', $list );
			case 'resume':
				return self::done( Store::set_safe( $scope, 'safe', false, $uid ), 'network' === $scope ? 'Fragmentos de red reanudados.' : 'Fragmentos reanudados en este sitio.', $list );
			case 'stop_sites':
				return self::done( Store::set_safe( $scope, 'safe_sites', true, $uid ), 'Fragmentos de todos los sitios detenidos.', $list );
			case 'resume_sites':
				return self::done( Store::set_safe( $scope, 'safe_sites', false, $uid ), 'Fragmentos de los sitios reanudados.', $list );
			case 'rescue_new':
				$link = Rescue::create( $uid, 'network' === $scope );
				if ( is_wp_error( $link ) ) return self::from_error( $link, array(), $list );
				return self::reply( true, self::RESCUE_TEXT, array( 'link' => $link ), array( 'page' => '<p>' . esc_html( self::RESCUE_TEXT ) . '</p><p><code>' . esc_html( $link ) . '</code></p><p><a href="' . esc_url( $list ) . '">Volver a Código PHP</a></p>' ) );
			case 'rescue_revoke':
				return self::done( Rescue::revoke( $uid ), 'Enlaces de rescate y modos seguros revocados.', $list );
			case 'rescue_exit':
				Rescue::clear_cookie();
				return self::reply( true, 'Saliste del modo seguro de Código PHP.', array(), array( 'redirect' => $list ) );
			case 'dismiss':
				Store::mark_seen( $scope );
				return self::reply( true, '', array(), array( 'redirect' => $list ) );
		}
		return self::reply( false, 'Acción no válida.', array(), array( 'status' => 400 ) );
	}

	private static function done( $result, $message, $redirect ) {
		if ( is_wp_error( $result ) ) return self::from_error( $result, array(), $redirect );
		return self::reply( true, $message, array(), array( 'redirect' => $redirect ) );
	}

	/** Borrador: crea o guarda, con la revisión anotada en la ficha. */
	private static function save_draft( array $post, $scope, $id, $uid ) {
		$in = Store::sanitize_item( $post, $scope );
		if ( is_wp_error( $in ) ) return $in;
		$check    = Tester::check( $in['code'] );
		$tampered = '';
		if ( 'new' === $id ) {
			$id = Store::create( $scope, $in['title'], $in['code'], $uid, array( 'item' => array( 'check' => $check ) ) );
			if ( is_wp_error( $id ) ) return $id;
			$active = '';
		} else {
			$saved = Store::save( $scope, $id, $in['title'], $in['code'], $uid, array( 'check' => $check ) );
			if ( is_wp_error( $saved ) ) return $saved;
			$active   = $saved['active_hash'];
			$tampered = $saved['tampered'];
		}
		return array( 'id' => $id, 'code' => $in['code'], 'hash' => hash( 'sha256', $in['code'] ), 'check' => $check, 'active' => $active, 'tampered' => $tampered );
	}

	/** Datos comunes de la respuesta del editor. */
	private static function editor_data( $scope, $id, array $extra = array() ) {
		return array_merge( array( 'id' => $id, 'nonces' => self::nonces( $scope, $id ), 'url' => self::url( $scope, array( 'edit' => $id ) ), 'state' => Store::STATES[ Store::state( $scope, $id ) ] ), $extra );
	}

	private static function do_save( array $post, $scope, $id, $uid ) {
		$draft = self::save_draft( $post, $scope, $id, $uid );
		if ( is_wp_error( $draft ) ) return self::from_error( $draft, array(), self::url( $scope, array( 'edit' => $id ) ) );
		$check   = $draft['check'];
		$message = '' !== $draft['active'] && $draft['active'] !== $draft['hash'] ? 'Cambios guardados sin activar. Sigue ejecutándose la versión anterior.' : 'Guardado sin activar.';
		if ( '' !== $draft['tampered'] ) $message .= ' El borrador anterior había cambiado fuera de DIGITALÍSIMO Tools: quedó en «Versiones anteriores» como «Cambiada fuera de Tools (sin firma)» y activar ese código exige revisarlo.';
		if ( ! $check['ok'] ) $message .= ' Antes de activarlo, corrige esto: ' . preg_replace( '/^No se activó: /', '', $check['message'] );
		$args = array( 'edit' => $draft['id'] );
		if ( $check['line'] ) $args['line'] = (int) $check['line'];
		return self::reply( true, $message, self::editor_data( $scope, $draft['id'], array( 'line' => (int) $check['line'], 'warnings' => $check['warnings'] ) ), array( 'type' => $check['ok'] ? 'success' : 'warning', 'redirect' => self::url( $scope, $args ) ) );
	}

	/** Paso 1 de «Guardar y activar» (§4.4). */
	private static function do_prepare( array $post, $scope, $id, $uid ) {
		$draft = self::save_draft( $post, $scope, $id, $uid );
		if ( is_wp_error( $draft ) ) return self::from_error( $draft );
		$id    = $draft['id'];
		$check = $draft['check'];
		$fail  = static function ( $message, $line = 0, array $extra = array() ) use ( $scope, $id ) {
			return self::reply( false, $message, self::editor_data( $scope, $id, array_merge( array( 'saved' => true, 'line' => (int) $line ), $extra ) ) );
		};
		if ( ! $check['ok'] ) {
			Store::log( $scope, 'check_failed', array( 'id' => $id, 'hash' => $draft['hash'], 'user' => $uid, 'msg' => $check['message'] ) );
			return $fail( $check['message'], $check['line'] );
		}
		$reason = self::stopped_reason( $scope );
		if ( '' !== $reason ) return $fail( $reason );
		if ( ! Stream::register() ) return $fail( Stream::problem_message() );
		$limits = Store::check_limits( $scope, $id, $draft['code'] );
		if ( is_wp_error( $limits ) ) return $fail( $limits->get_error_message() );
		// El borrador que había antes de este guardado cambió fuera de Tools: aunque se envíe otro código, esta vez hay que
		// marcar la casilla (el código alterado queda en el historial como «sin firma» y la exige siempre).
		$review = '' !== $draft['tampered'] || Tester::needs_review( $scope, $id, $draft['hash'] );
		if ( $review && empty( $post['reviewed'] ) ) return $fail( self::REVIEW_MISSING, 0, array( 'review' => true ) );
		$network = 'network' === $scope;
		$test    = Tester::start( array( 'mode' => 'activate', 'scope' => $scope, 'id' => $id, 'store' => $network ? 'network' : 'site', 'candidates' => array( array( 'scope' => $scope, 'id' => $id, 'hash' => $draft['hash'], 'source' => 'draft' ) ), 'targets' => Tester::targets( $network ) ) );
		return self::reply( true, '', self::editor_data( $scope, $id, $test + array( 'saved' => true, 'warnings' => $check['warnings'] ) ) );
	}

	/** Paso 1 de «Reanudar en este sitio» (fragmento de red en el sitio actual) o «en todos los sitios» (muestra de red). */
	private static function do_resume( $do, $scope, $id ) {
		if ( ! is_multisite() ) return self::reply( false, 'Esta acción sólo existe en una red.', array(), array( 'status' => 400 ) );
		if ( ( 'resume_site' === $do ) !== ( 'site' === $scope ) ) return self::reply( false, 'Ámbito no válido.', array(), array( 'status' => 400 ) );
		$net = Store::run( 'network' );
		if ( ! isset( $net['items'][ $id ] ) ) return self::reply( false, 'El fragmento de red ya no está activo.', array(), array( 'status' => 404 ) );
		$item = $net['items'][ $id ];
		if ( ! Store::verify( array_merge( $item, array( 'scope' => 'network', 'id' => $id ) ) ) ) return self::reply( false, 'La firma de este fragmento no es válida: ábrelo en la red, revisa su código y pulsa «Guardar y activar».' );
		if ( $net['safe'] ) return self::reply( false, 'Los fragmentos de red están detenidos en todos los sitios: reanúdalos antes.' );
		if ( 'resume_site' === $do && Store::run( 'site' )['safe'] ) return self::reply( false, 'Los fragmentos están detenidos en este sitio: pulsa «Reanudar los fragmentos» antes.' );
		if ( ! Stream::register() ) return self::reply( false, Stream::problem_message() );
		$all  = 'resume_all' === $do;
		$test = Tester::start( array( 'mode' => $do, 'scope' => $scope, 'id' => $id, 'store' => $all ? 'network' : 'site', 'candidates' => array( array( 'scope' => 'network', 'id' => $id, 'hash' => $item['hash'], 'source' => 'run' ) ), 'targets' => Tester::targets( $all ) ) );
		return self::reply( true, '', $test + array( 'id' => $id ) );
	}

	/** Paso 3: lee los resultados, aplica el criterio y, si pasa, activa el hash probado (§4.4, «Activación»). */
	private static function do_activate( array $post, $scope, $id, $uid ) {
		$sid     = is_string( $post['sid'] ?? null ) ? $post['sid'] : '';
		$session = Tester::session( $sid );
		if ( ! $session ) return self::reply( false, 'La prueba caducó o no es tuya. Vuelve a pulsar «Guardar y activar».' );
		if ( $session['scope'] !== $scope || $session['id'] !== $id ) return self::reply( false, 'La prueba no corresponde a este fragmento.', array(), array( 'status' => 400 ) );
		$verdict = Tester::evaluate( $sid, self::seen( $post['seen'] ?? '' ) );
		if ( 'retry' === $verdict['status'] ) return self::reply( true, '', array( 'retry' => $verdict['retry'] ) );
		if ( 'pending' === $verdict['status'] ) {
			// Un resultado aún no llegó (una respuesta opaca termina antes de que el navegador lo sepa): se espera una vez.
			if ( empty( $post['final'] ) ) return self::reply( false, $verdict['message'], array( 'pending' => true ) );
			$verdict['status'] = 'fail';
		}
		$mode = $session['mode'];
		$hash = (string) $session['candidates'][0]['hash'];
		$data = 'activate' === $mode ? self::editor_data( $scope, $id ) : array( 'id' => $id );
		if ( 'fail' === $verdict['status'] ) {
			Tester::finish( $sid );
			Store::log( $scope, 'test_failed', array( 'id' => $id, 'hash' => $hash, 'user' => $uid, 'msg' => $verdict['message'] ) );
			return self::reply( false, $verdict['message'], array_merge( $data, array( 'line' => (int) $verdict['line'] ) ) );
		}
		if ( 'resume_site' === $mode ) $done = Store::resume_here( $id, $hash, $uid );
		elseif ( 'resume_all' === $mode ) $done = Store::resume_everywhere( $id, $hash, $uid );
		else $done = Store::activate( $scope, $id, $hash, array( 'by' => $uid ) );
		if ( is_wp_error( $done ) ) {
			Tester::finish( $sid );
			return self::from_error( $done, $data );
		}
		$network = 'network' === $session['store'];
		$link    = Rescue::create( $uid, $network );
		$title   = self::title_of( 'activate' === $mode ? $scope : 'network', $id );
		self::mail_activation( $mode, $title, is_string( $link ) ? $link : '', $network );
		Tester::finish( $sid );
		$fronts   = count( array_filter( $session['targets'], static function ( $t ) { return 'front' === $t['kind']; } ) );
		$warnings = $verdict['warnings'];
		if ( 'activate' === $mode ) {
			$item = Store::item( $scope, $id );
			if ( $item && ! empty( $item['check']['warnings'] ) ) $warnings = array_merge( (array) $item['check']['warnings'], $warnings );
		}
		$data = 'activate' === $mode ? self::editor_data( $scope, $id ) : array( 'id' => $id );
		return self::reply( true, self::success_message( $mode, $network, $fronts, $verdict ), array_merge( $data, array( 'warnings' => array_values( array_unique( $warnings ) ), 'rescue' => is_string( $link ) ? $link : '', 'rescue_text' => self::RESCUE_TEXT ) ) );
	}

	/**
	 * Códigos HTTP que vio el navegador en cada destino de su mismo origen: `token:código,…` → sha256(token) → código. Sólo
	 * sirven para una portada sin resultado tras la autenticación del servidor (Tester::evaluate()); nunca hacen pasar un
	 * destino que escribió su resultado.
	 */
	private static function seen( $raw ) {
		$out = array();
		foreach ( array_slice( explode( ',', is_string( $raw ) ? $raw : '' ), 0, 10 ) as $pair ) {
			if ( preg_match( '/^([0-9a-f]{32}):([1-5]\d\d)$/D', $pair, $m ) ) $out[ hash( 'sha256', $m[1] ) ] = (int) $m[2];
		}
		return $out;
	}

	private static function success_message( $mode, $network, $fronts, array $verdict ) {
		$what = 'resume_site' === $mode ? 'Fragmento reanudado en este sitio.' : ( 'resume_all' === $mode ? 'Fragmento reanudado en todos los sitios.' : 'Fragmento activado.' );
		if ( ! empty( $verdict['maintenance'] ) ) return $what . ' La portada respondió 503 (mantenimiento): se probó hasta donde llegó.';
		if ( ! empty( $verdict['private'] ) ) return $what . ' Se probó en el escritorio sin errores; alguna portada no es pública y sólo se probó hasta donde llegó (lee los avisos).';
		$secs  = number_format( max( 0, (int) ( $verdict['ms'] ?? 0 ) ) / 1000, 1, ',', '.' );
		$where = $network ? sprintf( 'en el escritorio de la red y en %s', $fronts > 1 ? sprintf( 'la portada de %d sitios', $fronts ) : 'la portada del sitio principal' ) : 'en el escritorio y en la portada';
		return sprintf( '%s Se probó %s sin errores (%s s).', $what, $where, $secs );
	}

	private static function title_of( $scope, $id ) {
		$index = Store::index( $scope );
		if ( isset( $index['items'][ $id ] ) ) return $index['items'][ $id ]['title'];
		$run = Store::run( $scope );
		return $run['items'][ $id ]['title'] ?? $id;
	}

	/** Correo de activación (§6.3): a quien activa, con su enlace de rescate; al admin_email, sin el enlace, si es otra dirección. */
	private static function mail_activation( $mode, $title, $link, $network ) {
		$user  = wp_get_current_user();
		$site  = $network && function_exists( 'get_network' ) && get_network() ? wp_specialchars_decode( (string) get_network()->site_name, ENT_QUOTES ) : wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$where = $network ? 'la red ' . network_home_url( '/' ) : home_url( '/' );
		$verb  = 'activate' === $mode ? 'Se activó' : 'Se reanudó';
		$mine  = $user && isset( $user->user_email ) ? (string) $user->user_email : '';
		if ( '' !== $link && '' !== $mine ) {
			Store::mail( $mine, sprintf( '[%s] %s un fragmento PHP', $site, $verb ), array(
				sprintf( '%s el fragmento PHP «%s» en %s. Si algo deja de funcionar, este enlace te deja entrar sin fragmentos durante 7 días y una sola vez; sustituye a los enlaces anteriores que recibiste: %s', $verb, $title, $where, $link ),
			) );
		}
		$admin = Store::mail_to( $network ? 'network' : 'site' );
		if ( '' !== $admin && strtolower( $admin ) !== strtolower( $mine ) ) {
			Store::mail( $admin, sprintf( '[%s] %s un fragmento PHP', $site, $verb ), array(
				sprintf( '%s el fragmento PHP «%s» en %s. Lo hizo %s.', $verb, $title, $where, $user && ! empty( $user->display_name ) ? $user->display_name : 'un usuario' ),
			) );
		}
	}

	/** Envía la respuesta: JSON en AJAX; en admin-post, aviso y redirección (o la página de error o del enlace de rescate). */
	public static function respond( array $r, $ajax ) {
		if ( $ajax ) {
			$data = $r['data'];
			if ( '' !== $r['message'] ) $data['message'] = $r['message'];
			wp_send_json( array( 'success' => $r['ok'], 'data' => $data ), $r['status'] );
		}
		if ( ! $r['ok'] && in_array( $r['status'], array( 400, 401, 403, 404 ), true ) ) wp_die( esc_html( $r['message'] ), 'DIGITALÍSIMO Tools', array( 'response' => $r['status'], 'back_link' => true ) );
		if ( isset( $r['page'] ) ) wp_die( $r['page'], 'Enlace de rescate de Código PHP', array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- dispatch() escapa cada dato.
		if ( '' !== $r['message'] ) set_transient( Store::TRANSIENT . 'notice_' . get_current_user_id(), array( 'type' => $r['ok'] ? $r['type'] : 'error', 'text' => $r['message'] ), 60 );
		$fallback = admin_url( 'admin.php?page=' . Store::PAGE . '&tab=php' );
		wp_safe_redirect( '' !== $r['redirect'] ? $r['redirect'] : $fallback );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Avisos del escritorio y fila del plugin (§7.6)
	 * ------------------------------------------------------------------ */

	public static function notices() {
		if ( self::on_screen() ) return; // La pestaña pinta los suyos.
		$network = function_exists( 'is_network_admin' ) && is_network_admin();
		$scope   = self::scope_for( $network );
		if ( ! Access::can_stop( $scope ) ) return;
		foreach ( self::notice_messages( $scope ) as $m ) {
			$link  = '' !== $m['link'] ? ' <a href="' . esc_url( $m['link'] ) . '">Revisar</a>' : '';
			$forms = ! empty( $m['dismiss'] ) ? self::bar( self::button( 'dismiss', $scope, '', 'Ocultar', 'button-link' ) ) : '';
			echo '<div class="notice notice-' . esc_attr( $m['type'] ) . '"><p>' . esc_html( $m['text'] ) . $link . '</p>' . $forms . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Avisos del ámbito: [ { type, text, link, dismiss } ]. Sólo leen alloptions, `_run`, el índice y, en la red, los correos
	 * de pausa ya enviados; nunca concilian ni escriben.
	 */
	public static function notice_messages( $scope ) {
		$scope = Store::scope( $scope );
		$all   = wp_load_alloptions();
		$index = Store::index( $scope );
		$run   = ( 'network' === $scope || isset( $all[ Store::FLAG ] ) ) ? Store::run( $scope ) : Store::normalize_run( array(), $scope );
		$tab   = self::url( $scope );
		$out   = array();
		$any   = $index['items'] || $run['items'];
		if ( $any && defined( 'DIGITALISIMO_TOOLS_PHP_SAFE_MODE' ) && DIGITALISIMO_TOOLS_PHP_SAFE_MODE ) $out[] = array( 'type' => 'warning', 'text' => self::CONSTANT_TEXT, 'link' => $tab );
		$listed = array();
		if ( 'site' === $scope ) {
			foreach ( $all as $name => $value ) {
				if ( 0 !== strpos( $name, Store::OFF_SITE ) || ! preg_match( '/^' . preg_quote( Store::OFF_SITE, '/' ) . '([0-9a-f]{8})_([0-9a-f]{12})$/D', $name, $m ) ) continue;
				if ( ! isset( $run['items'][ $m[1] ] ) || Store::h12( $run['items'][ $m[1] ]['hash'] ) !== $m[2] ) continue;
				$row = (array) Store::unserialize( $value );
				$out[] = array( 'type' => 'error', 'text' => self::failed_text( $run['items'][ $m[1] ]['title'], (string) ( $row['path'] ?? '' ), (string) ( $row['msg'] ?? '' ), (int) ( $row['line'] ?? 0 ) ), 'link' => self::url( $scope, array( 'edit' => $m[1] ) ) );
				$listed[ $m[1] ] = true;
			}
		}
		foreach ( $index['items'] as $id => $meta ) {
			if ( isset( $listed[ $id ] ) || 'failed' !== $meta['status'] || 'fatal' !== ( $meta['error']['kind'] ?? '' ) || ! empty( $meta['error']['seen'] ) ) continue;
			$e     = $meta['error'];
			$out[] = array( 'type' => 'error', 'text' => self::failed_text( $meta['title'], (string) ( $e['path'] ?? '' ), (string) ( $e['message'] ?? '' ), (int) ( $e['line'] ?? 0 ) ), 'link' => self::url( $scope, array( 'edit' => $id ) ), 'dismiss' => true );
		}
		foreach ( self::signature_messages( $scope ) as $text ) $out[] = array( 'type' => 'error', 'text' => $text, 'link' => $tab );
		if ( 'site' === $scope && is_multisite() ) {
			$net = Store::run( 'network' );
			foreach ( $net['items'] as $id => $item ) {
				if ( isset( $all[ Store::off_name( 'network', $id, $item['hash'], $item['rev'] ) ] ) ) $out[] = array( 'type' => 'warning', 'text' => sprintf( 'El fragmento de red «%s» se pausó en este sitio por un error. En los demás sitios sigue ejecutándose.', $item['title'] ), 'link' => $tab );
			}
		}
		if ( 'network' === $scope ) {
			$mailed = get_site_option( Store::NETWORK_MAILED, array() );
			foreach ( is_array( $mailed ) ? $mailed : array() as $key => $info ) {
				$id = substr( (string) $key, 0, 8 );
				if ( ! isset( $run['items'][ $id ] ) ) continue;
				$n     = is_array( $info ) ? (int) ( $info['n'] ?? 0 ) : 0;
				$out[] = array( 'type' => 'warning', 'text' => $n ? sprintf( 'El fragmento de red «%s» se pausó en %s por un error.', $run['items'][ $id ]['title'], Store::plural( $n, '%d sitio', '%d sitios' ) ) : sprintf( 'El fragmento de red «%s» se pausó en algunos sitios por un error.', $run['items'][ $id ]['title'] ), 'link' => $tab );
			}
		}
		if ( $any && ! Store::signature_effective() ) $out[] = array( 'type' => 'warning', 'text' => self::KEYS_DB_TEXT, 'link' => '' );
		return array_map( static function ( $m ) { return $m + array( 'link' => '', 'dismiss' => false ); }, $out );
	}

	private static function failed_text( $title, $path, $message, $line ) {
		return sprintf( 'DIGITALÍSIMO Tools desactivó el fragmento PHP «%s» porque provocó un error fatal en %s: %s%s. El sitio sigue funcionando sin él.', $title, '' !== $path ? $path : 'una petición', rtrim( $message, '.' ), $line ? sprintf( ' (línea %d)', $line ) : '' );
	}

	/** Fila del plugin: cuántos fragmentos dejarían de ejecutarse al desactivar Tools. */
	public static function row_meta( $links, $file ) {
		if ( ! defined( 'DIGITALISIMO_TOOLS_FILE' ) || ! function_exists( 'plugin_basename' ) || plugin_basename( DIGITALISIMO_TOOLS_FILE ) !== $file ) return $links;
		$network = function_exists( 'is_network_admin' ) && is_network_admin();
		$scope   = self::scope_for( $network );
		if ( ! Access::can_stop( $scope ) ) return $links;
		$all = wp_load_alloptions();
		$n   = 'network' === $scope ? Store::active_count( 'network' ) : ( isset( $all[ Store::FLAG ] ) ? Store::active_count( 'site' ) : 0 );
		if ( $n ) $links[] = '<span>' . esc_html( Store::plural( $n, 'Hay %d fragmento PHP activo: dejará de ejecutarse si desactivas DIGITALÍSIMO Tools.', 'Hay %d fragmentos PHP activos: dejarán de ejecutarse si desactivas DIGITALÍSIMO Tools.' ) ) . '</span>';
		return $links;
	}
}
