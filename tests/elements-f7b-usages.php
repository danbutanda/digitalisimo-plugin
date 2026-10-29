<?php
/**
 * F7b, frente A: usos y borrado (V.6, C1-9, C2-19, C2-20).
 *
 * - `_digitalisimo_uses`: meta multivalor (`t:<id>` del widget `template`, `q:<id>` de `digitalisimo_saved_query` en
 *   cualquier elemento), reescrita sólo si cambia en `after_save` y en las metas de `_elementor_data` fuera del editor;
 *   nunca en revisiones. `Usages::of()` cuenta `publish`, `future` y `private`, lista aparte los borradores, excluye el
 *   propio objeto y la papelera, y memoriza; `prime()` resuelve una página con una consulta. No viaja en un WXR.
 * - Recorrido por lotes de 50 por cron: un `do_action` por documento, el siguiente lote programado, al final los
 *   contadores y `digitalisimo_elements_f7b_scan_done`; al cambiar de versión, al importar un kit y si se para.
 * - Bloqueo con `pre_trash_post`/`pre_delete_post` (nunca `wp_die()`): aviso con la lista; sin bloqueo mientras el
 *   recorrido calcula, en sesiones de K (importar y revertir), dentro de `wp_delete_user()`, en WP-CLI y con
 *   `DIGITALISIMO_FORCE_DELETE` (sólo aviso).
 * - `edit.php`: «En uso (N)» en lugar de «Papelera»/«Borrar», columna «Usos» («Calculando…»), acciones en lote y
 *   «Vaciar papelera» filtradas antes, enlace directo de `post.php` con vuelta a la lista.
 * - Biblioteca del editor: `delete_template` y `bulk_delete_templates` sustituidas por nombre (prioridad 20), con el
 *   original del núcleo leído de su registro; con un suscriptor y con un autor ajeno, responde el original y no se
 *   calcula ni se revela ningún uso. Contrato con el núcleo leído de sus fuentes.
 */
namespace Digitalisimo\Elements\Import\Kits {
	/** Doble del contexto de K. */
	final class Context {
		public static $active = false;
		public static function active(): bool {
			return self::$active;
		}
	}
}
namespace {
	require __DIR__ . '/fixtures/f7b-wp.php';
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$dir  = $root . '/modules/digitalisimo-f7b/';
	require $dir . 'class-module.php';
	$M = '\\Digitalisimo\\Elements\\F7b\\Module';
	$U = '\\Digitalisimo\\Elements\\F7b\\Usages';
	$S = '\\Digitalisimo\\Elements\\F7b\\Scan';
	$D = '\\Digitalisimo\\Elements\\F7b\\Deletion';
	$F = '\\Digitalisimo\\Elements\\F7b\\Flags';
	$M::set_editing( false );
	$GLOBALS['f7b']['admin'] = true;
	$M::load( $dir, array() );
	$D::reset( false );
	$D::init();

	$el = static function ( string $type, array $settings = array(), array $children = array(), string $widget = '' ): array {
		$out = array( 'id' => substr( md5( json_encode( func_get_args() ) ), 0, 7 ), 'elType' => $type, 'settings' => $settings, 'elements' => $children );
		if ( '' !== $widget ) {
			$out['widgetType'] = $widget;
		}
		return $out;
	};
	$tpl   = static function ( $id ) use ( $el ) { return $el( 'widget', array( 'template_id' => $id ), array(), 'template' ); };
	$doc   = static function ( int $id ) {
		return new class( $id ) {
			private $id;
			public function __construct( $id ) { $this->id = $id; }
			public function get_main_id() { return $this->id; }
			public function is_autosave() { return false; }
		};
	};
	$uses_of = static function ( int $id ): array {
		$values = $GLOBALS['f7b']['meta'][ $id ]['_digitalisimo_uses'] ?? array();
		sort( $values );
		return $values;
	};

	// ── 1. Referencias ───────────────────────────────────────────────────────────────────────────────────────────
	$tree = array(
		$el( 'container', array(), array(
			$tpl( '12' ),
			$tpl( 12 ),
			$tpl( 'abc' ),
			$tpl( '' ),
			$el( 'container', array(), array( $tpl( '7' ), $el( 'widget', array( 'digitalisimo_saved_query' => '44' ), array(), 'loop-grid' ) ) ),
		) ),
		$el( 'widget', array( 'digitalisimo_saved_query' => 45, 'template_id' => '99' ), array(), 'posts' ),
		$el( 'section', array( 'digitalisimo_saved_query' => '-3' ) ),
		'basura',
	);
	f7b_check( array( 'q:44', 'q:45', 't:12', 't:7' ) === $U::refs( $tree ), 'refs(): template_id sólo del widget template, la consulta en cualquier elemento, únicas y ordenadas; nada inválido.' );
	f7b_check( $U::refs( $tree ) === $U::refs( json_encode( $tree ) ) && array() === $U::refs( 'no es json' ) && array() === $U::refs( null ), 'refs() acepta la cadena JSON; lo ilegible no da referencias.' );
	$deep = $tpl( '3' );
	for ( $i = 0; $i < 80; $i++ ) {
		$deep = $el( 'container', array(), array( $deep ) );
	}
	f7b_check( array() === $U::refs( array( $deep ) ), 'Un árbol más hondo que el tope no se recorre sin fin.' );

	// ── 2. Escritura de la meta ──────────────────────────────────────────────────────────────────────────────────
	f7b_post( 100, 'elementor_library', 'publish', array(), 'Cabecera' );
	f7b_post( 101, 'elementor_library', 'publish', array(), 'Pie' );
	f7b_post( 200, 'page', 'publish', array( '_elementor_edit_mode' => 'builder' ), 'Inicio' );
	f7b_post( 201, 'page', 'private', array( '_elementor_edit_mode' => 'builder' ), 'Privada' );
	f7b_post( 202, 'page', 'draft', array( '_elementor_edit_mode' => 'builder' ), 'Borrador' );
	f7b_post( 203, 'page', 'trash', array( '_elementor_edit_mode' => 'builder' ), 'Papelera' );
	f7b_post( 204, 'post', 'future', array( '_elementor_edit_mode' => 'builder' ), 'Programada' );
	f7b_post( 205, 'revision', 'inherit', array(), 'Revisión' );
	f7b_post( 206, 'page', 'publish', array(), 'Sin constructor' );
	f7b_post( 300, 'digitalisimo_query', 'publish', array(), 'Noticias' );

	$GLOBALS['f7b']['meta_ops'] = array();
	do_action( 'elementor/document/before_save', $doc( 200 ), array() );
	do_action( 'elementor/document/after_save', $doc( 200 ), array( 'elements' => array( $tpl( '100' ), $el( 'widget', array( 'digitalisimo_saved_query' => '300' ), array(), 'posts' ) ) ) );
	f7b_check( array( 'q:300', 't:100' ) === $uses_of( 200 ), 'after_save escribe una fila por referencia.' );
	$ops = count( $GLOBALS['f7b']['meta_ops'] );
	do_action( 'elementor/document/after_save', $doc( 200 ), array( 'elements' => array( $el( 'widget', array( 'digitalisimo_saved_query' => '300' ), array(), 'posts' ), $tpl( '100' ) ) ) );
	$written = array_filter( array_slice( $GLOBALS['f7b']['meta_ops'], $ops ), static function ( $op ) { return 'get' !== $op[0]; } );
	f7b_check( array() === $written, 'Sin cambios, no se reescribe (sólo se lee la caché).' );
	do_action( 'elementor/document/before_save', $doc( 201 ), array() );
	update_post_meta( 201, '_elementor_data', json_encode( array( $tpl( '101' ) ) ) );
	f7b_check( array() === $uses_of( 201 ), 'Durante un guardado del editor, la meta de _elementor_data no escribe usos.' );
	do_action( 'elementor/document/after_save', $doc( 201 ), array( 'elements' => array( $tpl( '101' ), $tpl( '100' ) ) ) );
	f7b_check( array( 't:100', 't:101' ) === $uses_of( 201 ), 'after_save de la página privada.' );
	update_post_meta( 201, '_elementor_data', json_encode( array( $tpl( '101' ), $tpl( '100' ) ) ) );
	f7b_check( array( 't:100', 't:101' ) === $uses_of( 201 ), 'La misma meta escrita fuera del editor no cambia nada.' );
	update_post_meta( 202, '_elementor_data', json_encode( array( $tpl( '100' ) ) ) );
	update_post_meta( 203, '_elementor_data', json_encode( array( $tpl( '100' ) ) ) );
	update_post_meta( 204, '_elementor_data', json_encode( array( $tpl( '100' ) ) ) );
	update_post_meta( 205, '_elementor_data', json_encode( array( $tpl( '100' ) ) ) );
	update_post_meta( 100, '_elementor_data', json_encode( array( $tpl( '100' ) ) ) );
	f7b_check( array( 't:100' ) === $uses_of( 202 ) && array( 't:100' ) === $uses_of( 204 ) && array() === $uses_of( 205 ), 'Fuera del editor (Migración, kits) se escribe; nunca en una revisión.' );
	update_post_meta( 204, '_elementor_data', '[]' );
	f7b_check( array() === $uses_of( 204 ), 'Quitar la referencia borra la fila.' );
	update_post_meta( 204, '_elementor_data', json_encode( array( $tpl( '100' ) ) ) );
	f7b_check( true === $U::skip_export( true, 'x' ) && true === $U::skip_export( false, '_digitalisimo_uses' ) && false === $U::skip_export( false, '_thumbnail_id' ), 'wxr_export_skip_postmeta: respeta un true previo y sólo veta _digitalisimo_uses.' );
	f7b_check( true === apply_filters( 'wxr_export_skip_postmeta', false, '_digitalisimo_uses', null ), 'El filtro está enganchado.' );

	// ── 3. Lectura ───────────────────────────────────────────────────────────────────────────────────────────────
	$GLOBALS['wpdb']->log = array();
	$U::forget();
	$of = $U::of( 100, 't' );
	f7b_check( array( 'count' => 3, 'posts' => array( 200, 201, 204 ), 'drafts' => array( 202 ) ) === $of, 'of(): publicada, privada y programada cuentan; el borrador aparte; ni la papelera, ni la revisión, ni ella misma.' );
	f7b_check( 1 === count( $GLOBALS['wpdb']->log ), 'Una consulta.' );
	list( $sql, $args ) = $GLOBALS['wpdb']->log[0];
	f7b_check( false !== strpos( $sql, 'pm.meta_key = %s AND pm.meta_value IN (%s)' ) && '_digitalisimo_uses' === $args[0] && 't:100' === $args[1] && false === stripos( $sql, 'DISTINCT' ), 'Búsqueda inversa por clave y valor exactos, sin DISTINCT.' );
	$U::of( 100, 't' );
	f7b_check( 1 === count( $GLOBALS['wpdb']->log ), 'Memorizada por petición.' );
	f7b_check( array( 'count' => 1, 'posts' => array( 200 ), 'drafts' => array() ) === $U::of( 300, 'q' ) && array( 'count' => 0, 'posts' => array(), 'drafts' => array() ) === $U::of( 300, 'x' ), 'Consultas guardadas; una clase desconocida no consulta.' );
	$GLOBALS['wpdb']->log = array();
	$U::forget();
	$U::prime( array( 100, 101, 999 ), 't' );
	f7b_check( 1 === count( $GLOBALS['wpdb']->log ) && 1 === $U::of( 101, 't' )['count'] && 0 === $U::of( 999, 't' )['count'] && 1 === count( $GLOBALS['wpdb']->log ), 'prime(): varias plantillas con una sola consulta.' );

	// ── 4. Recorrido por lotes ───────────────────────────────────────────────────────────────────────────────────
	f7b_check( 'digitalisimo_elements_f7b_scan_document' === $S::ACTION_DOCUMENT && 'digitalisimo_elements_f7b_scan_done' === $S::ACTION_DONE && 50 === $S::BATCH, 'Contrato del recorrido: los dos do_action y lotes de 50.' );
	f7b_check( false === $S::running() && array() === $GLOBALS['f7b']['cron'], 'Sin estado, no calcula.' );
	foreach ( range( 1000, 1119 ) as $id ) {
		f7b_post( $id, 'page', 0 === $id % 7 ? 'draft' : 'publish', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => json_encode( array( 0 === $id % 3 ? $tpl( '101' ) : $el( 'widget', array( 'title' => 'x' ), array(), 'heading' ) ) ) ) );
	}
	f7b_post( 1120, 'page', 'auto-draft', array( '_elementor_data' => json_encode( array( $tpl( '101' ) ) ) ) );
	$GLOBALS['f7b']['meta'][201]['_digitalisimo_uses'] = array( 't:555' );
	$seen = array();
	add_action( 'digitalisimo_elements_f7b_scan_document', static function ( $id, $elements ) use ( &$seen ) { $seen[] = array( $id, is_array( $elements ) ); }, 10, 2 );
	$done = 0;
	add_action( 'digitalisimo_elements_f7b_scan_done', static function () use ( &$done ) { ++$done; }, 10, 0 );
	do_action( 'admin_init' );
	f7b_check( $S::running() && array( 'digitalisimo_elements_f7b_scan' ) === $GLOBALS['f7b']['cron'] && '4.3.0.169' === $S::state()['version'], 'Versión nueva: el recorrido empieza en admin_init y programa un lote.' );
	f7b_check( true === $GLOBALS['f7b']['autoload']['digitalisimo_elements_f7b_scan'], 'El estado tiene carga automática (se lee sin consultas).' );
	do_action( 'admin_init' );
	f7b_check( 1 === count( $GLOBALS['f7b']['cron'] ), 'Una sola vez por versión, y un solo evento.' );
	$GLOBALS['wpdb']->log = array();
	f7b_run_cron( 'digitalisimo_elements_f7b_scan' );
	f7b_check( 50 === count( $seen ) && 1 === count( $GLOBALS['wpdb']->log ) && $S::running() && array( 'digitalisimo_elements_f7b_scan' ) === $GLOBALS['f7b']['cron'], 'Un lote: 50 documentos con una consulta, y el siguiente programado.' );
	f7b_check( $seen[0][1] && 100 === $seen[0][0] && array( 't:100', 't:101' ) === $uses_of( 201 ), 'Cada documento pasa por el gancho con sus elementos, y sus usos se reescriben (la fila vieja de 201 se va).' );
	f7b_check( '%d' === substr( $GLOBALS['wpdb']->log[0][0], strpos( $GLOBALS['wpdb']->log[0][0], 'p.ID > ' ) + 7, 2 ) && 50 === $GLOBALS['wpdb']->log[0][1][1], 'Avanza por ID, con LIMIT 50.' );
	while ( f7b_run_cron( 'digitalisimo_elements_f7b_scan' ) ) {
		// Lotes siguientes.
	}
	$ids = array_column( $seen, 0 );
	f7b_check( ! $S::running() && 'done' === $S::state()['status'] && 1 === $done && $ids === array_values( array_unique( $ids ) ) && ! in_array( 1120, $ids, true ) && ! in_array( 205, $ids, true ) && in_array( 203, $ids, true ), 'Termina: cada documento una vez (también la papelera; ni revisiones ni auto-draft) y avisa una vez.' );
	f7b_check( array( 't:101' ) === $uses_of( 1002 ) && array() === $uses_of( 1001 ), 'Los usos de lo que ya existía quedan escritos.' );
	f7b_check( 0 === $F::counts()['actions'] && true === $GLOBALS['f7b']['autoload']['digitalisimo_elements_f7b_counts'], 'Al terminar se recalculan los contadores.' );
	do_action( 'admin_init' );
	f7b_check( array() === $GLOBALS['f7b']['cron'], 'Misma versión y terminado: nada.' );
	do_action( 'digitalisimo_elements_kit_imported', 'sesion', array() );
	f7b_check( $S::running() && 0 === $S::state()['after'] && 1 === count( $GLOBALS['f7b']['cron'] ), 'Al terminar una importación de K, vuelve a empezar.' );
	$GLOBALS['f7b']['cron'] = array();
	$state                  = $GLOBALS['f7b']['options']['digitalisimo_elements_f7b_scan'];
	$state['updated']       = time() - 30;
	$GLOBALS['f7b']['options']['digitalisimo_elements_f7b_scan'] = $state;
	do_action( 'admin_init' );
	f7b_check( array() === $GLOBALS['f7b']['cron'], 'Un lote reciente sin evento todavía no se reprograma.' );
	$state['updated'] = time() - 3600;
	$GLOBALS['f7b']['options']['digitalisimo_elements_f7b_scan'] = $state;
	do_action( 'admin_init' );
	f7b_check( 1 === count( $GLOBALS['f7b']['cron'] ), 'Parado más de diez minutos sin evento: se vuelve a programar.' );

	// ── 5. Bloqueo ───────────────────────────────────────────────────────────────────────────────────────────────
	f7b_check( null === apply_filters( 'pre_trash_post', null, get_post( 100 ), 'publish' ), 'Mientras el recorrido calcula, nada se bloquea.' );
	while ( f7b_run_cron( 'digitalisimo_elements_f7b_scan' ) ) {
		// Termina.
	}
	$U::forget();
	f7b_check( false === apply_filters( 'pre_trash_post', null, get_post( 100 ), 'publish' ) && false === apply_filters( 'pre_delete_post', null, get_post( 100 ), true ), 'Plantilla en uso: pre_trash_post y pre_delete_post devuelven false (no nulo).' );
	$notice = get_user_meta( 1, 'digitalisimo_elements_f7b_notice', true );
	f7b_check( is_array( $notice ) && 1 === count( $notice ) && false !== strpos( $notice[0]['text'], '«Cabecera» no se puede borrar' ) && false !== strpos( $notice[0]['text'], 'Inicio' ) && false !== strpos( $notice[0]['url'], 'page=digitalisimo-elements-uses' ), 'Un aviso para quien lo intentó, con la lista y el enlace (sin repetirse).' );
	f7b_check( 'x' === apply_filters( 'pre_trash_post', 'x', get_post( 100 ), 'publish' ), 'Respeta un valor previo de otro filtro.' );
	f7b_check( null === apply_filters( 'pre_trash_post', null, get_post( 206 ), 'publish' ) && null === apply_filters( 'pre_delete_post', null, get_post( 200 ), true ), 'Una página no se bloquea.' );
	$GLOBALS['f7b']['meta'][202]['_digitalisimo_uses'] = array( 't:101' );
	foreach ( array( 200, 201, 204 ) as $using ) {
		$GLOBALS['f7b']['meta'][ $using ]['_digitalisimo_uses'] = array_values( array_diff( $GLOBALS['f7b']['meta'][ $using ]['_digitalisimo_uses'] ?? array(), array( 't:101' ) ) );
	}
	foreach ( range( 1000, 1119 ) as $id ) {
		unset( $GLOBALS['f7b']['meta'][ $id ]['_digitalisimo_uses'] );
	}
	$U::forget();
	f7b_check( null === apply_filters( 'pre_trash_post', null, get_post( 101 ), 'publish' ), 'Usada sólo en un borrador: no se bloquea.' );
	f7b_check( false === apply_filters( 'pre_delete_post', null, get_post( 300 ), true ), 'Consulta guardada en uso: bloqueada.' );
	// Excepciones.
	$GLOBALS['f7b']['user_meta'] = array();
	do_action( 'delete_user', 9 );
	f7b_check( null === apply_filters( 'pre_delete_post', null, get_post( 100 ), true ), 'Dentro de wp_delete_user() no se bloquea.' );
	f7b_check( false !== strpos( get_user_meta( 1, 'digitalisimo_elements_f7b_notice', true )[0]['text'], 'Se borró «Cabecera»' ), 'Sólo un aviso.' );
	do_action( 'deleted_user', 9 );
	f7b_check( false === apply_filters( 'pre_delete_post', null, get_post( 100 ), true ), 'Terminado el borrado del usuario, vuelve a bloquear.' );
	\Digitalisimo\Elements\Import\Kits\Context::$active = true;
	f7b_check( null === apply_filters( 'pre_delete_post', null, get_post( 100 ), true ) && 'kit' === $D::bypass(), 'Con una sesión de importación de K activa no se bloquea.' );
	\Digitalisimo\Elements\Import\Kits\Context::$active = false;
	f7b_check( '' === $D::bypass(), 'Sin la sesión, sí.' );
	do_action( 'elementor/import-export-customization/revert-kit', new \stdClass() );
	f7b_check( 'kit' === $D::bypass() && null === apply_filters( 'pre_trash_post', null, get_post( 100 ), 'publish' ), 'Una reversión de kits (revert-kit) no se bloquea en esa petición.' );
	$D::reset( false );
	f7b_check( '' === $D::bypass(), 'Otra petición vuelve a bloquear.' );

	// ── 6. edit.php ──────────────────────────────────────────────────────────────────────────────────────────────
	$U::forget();
	$GLOBALS['f7b']['user_meta'] = array();
	$GLOBALS['wp_query']         = (object) array( 'posts' => array( get_post( 100 ), get_post( 101 ) ) );
	$GLOBALS['wpdb']->log        = array();
	$row                         = $D::row_actions( array( 'edit' => 'e', 'trash' => 't', 'view' => 'v' ), get_post( 100 ) );
	f7b_check( ! isset( $row['trash'] ) && isset( $row['edit'], $row['view'], $row['digitalisimo_in_use'] ) && false !== strpos( $row['digitalisimo_in_use'], '>En uso (3)</a>' ) && false !== strpos( $row['digitalisimo_in_use'], 'kind=t&amp;id=100' ), '«En uso (3)» con enlace en lugar de «Papelera».' );
	$row = $D::row_actions( array( 'untrash' => 'u', 'delete' => 'd' ), get_post( 100 ) );
	f7b_check( ! isset( $row['delete'] ) && isset( $row['untrash'], $row['digitalisimo_in_use'] ), 'También en lugar de «Borrar permanentemente».' );
	f7b_check( array( 'trash' => 't' ) === $D::row_actions( array( 'trash' => 't' ), get_post( 101 ) ) && array( 'trash' => 't' ) === $D::row_actions( array( 'trash' => 't' ), get_post( 200 ) ), 'Sin usos, o si no es una plantilla, la fila no cambia.' );
	f7b_check( 1 === count( $GLOBALS['wpdb']->log ), 'Toda la página de la lista con una consulta.' );
	f7b_check( array( 'cb' => 'c', 'title' => 'T', 'digitalisimo_uses' => 'Usos', 'date' => 'D' ) === $D::columns( array( 'cb' => 'c', 'title' => 'T', 'date' => 'D' ) ), 'Columna «Usos» antes de la fecha.' );
	ob_start();
	$D::column( 'digitalisimo_uses', 100 );
	$D::column( 'digitalisimo_uses', 101 );
	$D::column( 'otra', 100 );
	$cells = ob_get_clean();
	f7b_check( false !== strpos( $cells, '>3 documentos</a>' ) && false !== strpos( $cells, '>0 documentos · 1 borrador</a>' ) && 2 === substr_count( $cells, '<a ' ), 'La celda: «3 documentos» enlazado; la otra, sólo con un borrador.' );
	ob_start();
	$D::column( 'digitalisimo_uses', 102 );
	$cells = ob_get_clean();
	f7b_check( false !== strpos( $cells, 'Sin usos' ) && false === strpos( $cells, '<a ' ), 'Sin usos: una raya, con texto para lectores de pantalla.' );
	// Acciones en lote.
	$bulk = static function ( array $request ) {
		$_GET = $_REQUEST = array_merge( array( 'post_type' => 'elementor_library', '_wpnonce' => 'nonce-bulk-posts' ), $request );
		$GLOBALS['f7b']['redirect'] = '';
		do_action( 'load-edit.php' );
	};
	$bulk( array( 'action' => 'trash', 'post' => array( '100', '101' ) ) );
	f7b_check( array( 101 ) === $_REQUEST['post'] && array( 101 ) === $_GET['post'] && '' === $GLOBALS['f7b']['redirect'], 'Papelera en lote: la plantilla en uso sale de la petición antes de edit.php.' );
	f7b_check( 1 === count( get_user_meta( 1, 'digitalisimo_elements_f7b_notice', true ) ), 'Y se avisa.' );
	$bulk( array( 'action' => '-1', 'action2' => 'trash', 'post' => array( '100' ) ) );
	f7b_check( 'https://example.test/wp-admin/edit.php?post_type=elementor_library' === $GLOBALS['f7b']['redirect'] && $D::redirected() === $GLOBALS['f7b']['redirect'], 'Si no queda ninguna, vuelve a la lista sin wp_die().' );
	$bulk( array( 'action' => 'trash', 'post' => array( '100' ), '_wpnonce' => 'otro' ) );
	f7b_check( array( '100' ) === $_REQUEST['post'] && '' === $GLOBALS['f7b']['redirect'], 'Sin el nonce de la lista no toca nada (edit.php se detiene solo).' );
	$bulk( array( 'action' => 'edit', 'post' => array( '100' ) ) );
	f7b_check( array( '100' ) === $_REQUEST['post'], 'Otra acción en lote no se toca.' );
	$bulk( array( 'post_type' => 'page', 'action' => 'trash', 'post' => array( '200' ) ) );
	f7b_check( array( '200' ) === $_REQUEST['post'], 'Otro tipo no se toca.' );
	$bulk( array( 'action' => 'trash', 'ids' => '100,101' ) );
	f7b_check( '101' === $_REQUEST['ids'], 'También con ids=.' );
	f7b_post( 102, 'elementor_library', 'trash', array(), 'Vieja' );
	$GLOBALS['f7b']['posts'][100]['status'] = 'trash';
	$bulk( array( 'delete_all' => 'Vaciar papelera', 'post_status' => 'trash' ) );
	f7b_check( ! isset( $_REQUEST['delete_all'] ) && 'delete' === $_REQUEST['action'] && array( 102 ) === $_REQUEST['post'], '«Vaciar papelera» pasa a borrar sólo las que no están en uso.' );
	$GLOBALS['f7b']['posts'][100]['status'] = 'publish';
	// Enlace directo de post.php.
	$GLOBALS['pagenow'] = 'post.php';
	$_GET               = $_REQUEST = array( 'action' => 'trash', 'post' => '100', '_wpnonce' => 'nonce-trash-post_100' );
	$GLOBALS['f7b']['redirect'] = '';
	do_action( 'admin_action_trash' );
	f7b_check( 'https://example.test/wp-admin/edit.php?post_type=elementor_library' === $GLOBALS['f7b']['redirect'], 'post.php?action=trash de una plantilla en uso vuelve a la lista con el aviso.' );
	$GLOBALS['f7b']['redirect'] = '';
	$_GET                       = $_REQUEST = array( 'action' => 'trash', 'post' => '101', '_wpnonce' => 'nonce-trash-post_101' );
	do_action( 'admin_action_trash' );
	$_GET = $_REQUEST = array( 'action' => 'delete', 'post' => '100', '_wpnonce' => 'falso' );
	do_action( 'admin_action_delete' );
	f7b_check( '' === $GLOBALS['f7b']['redirect'], 'Sin usos, o con un nonce inválido, sigue el flujo de WordPress.' );
	$GLOBALS['pagenow'] = 'edit.php';
	ob_start();
	do_action( 'admin_notices' );
	$html = ob_get_clean();
	f7b_check( false !== strpos( $html, 'notice-warning' ) && false !== strpos( $html, 'Ver dónde se usa' ) && '' === get_user_meta( 1, 'digitalisimo_elements_f7b_notice', true ), 'Los avisos se muestran una vez y se borran.' );
	// Pantalla de usos.
	$_GET                  = array( 'page' => 'digitalisimo-elements-uses', 'kind' => 't', 'id' => '100' );
	$GLOBALS['f7b']['can'] = static function ( $cap, $id = 0 ) { return ! ( 'edit_post' === $cap && 201 === $id ); };
	ob_start();
	$D::render_page();
	$page = ob_get_clean();
	f7b_check( false !== strpos( $page, 'Dónde se usa «Cabecera»' ) && false !== strpos( $page, '>Inicio</a>' ) && false === strpos( $page, 'Privada' ) && false !== strpos( $page, 'Y 1 documento que no puedes editar.' ), 'La pantalla de usos lista lo que el usuario puede editar y sólo cuenta el resto.' );
	$GLOBALS['f7b']['can'] = static function ( $cap, $id = 0 ) { return ! ( 'edit_post' === $cap && 100 === $id ); };
	ob_start();
	$D::render_page();
	$page = ob_get_clean();
	f7b_check( false === strpos( $page, 'Inicio' ) && false !== strpos( $page, 'No existe o no tienes permiso' ), 'Sin permiso sobre la plantilla, nada.' );
	$GLOBALS['f7b']['can'] = null;
	do_action( 'admin_menu' );
	f7b_check( 'digitalisimo-elements-uses' === $GLOBALS['f7b']['submenu'][0][4] && '' === $GLOBALS['f7b']['submenu'][0][0], 'Pantalla oculta con slug digitalisimo-… (recibe digitalisimo-ui).' );

	// ── 7. Biblioteca del editor (AJAX) ──────────────────────────────────────────────────────────────────────────
	$ajax = new class() {
		private $ajax_actions = array();
		public $calls         = array();
		public function register_ajax_action( $tag, $callback ) { $this->ajax_actions[ $tag ] = compact( 'tag', 'callback' ); }
		public function run( $tag, $data ) {
			return call_user_func( $this->ajax_actions[ $tag ]['callback'], $data, $this );
		}
		public function callback( $tag ) { return $this->ajax_actions[ $tag ]['callback'] ?? null; }
	};
	$core_calls = array();
	foreach ( array( 'delete_template', 'bulk_delete_templates', 'get_library_data' ) as $tag ) {
		$ajax->register_ajax_action(
			$tag,
			static function ( $data ) use ( $tag, &$core_calls ) {
				$core_calls[] = $tag;
				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new \Exception( 'Access denied.' );
				}
				return 'borrado';
			}
		);
	}
	$original = $ajax->callback( 'delete_template' );
	do_action( 'elementor/ajax/register_actions', $ajax );
	f7b_check( 20 === f7b_hooks( 'elementor/ajax/register_actions' )[0][1], 'Se registra con prioridad 20 (el núcleo, 10).' );
	$cb = $ajax->callback( 'delete_template' );
	f7b_check( is_array( $cb ) && 'Digitalisimo\\Elements\\F7b\\Deletion' === $cb[0] && 'ajax_delete_template' === $cb[1] && $original !== $cb, 'delete_template queda sustituida por nombre.' );
	f7b_check( is_array( $ajax->callback( 'bulk_delete_templates' ) ) && $ajax->callback( 'get_library_data' ) instanceof \Closure, 'También bulk_delete_templates; las demás acciones, intactas.' );
	$U::forget();
	$GLOBALS['wpdb']->log = array();
	$error                = null;
	try {
		$ajax->run( 'delete_template', array( 'source' => 'local', 'template_id' => '100' ) );
	} catch ( \Exception $e ) {
		$error = $e;
	}
	f7b_check( $error instanceof \Exception && 409 === $error->getCode() && false !== strpos( $error->getMessage(), '«Cabecera» no se puede borrar' ) && false !== strpos( $error->getMessage(), 'Inicio' ) && array() === $core_calls, 'Con usos: error con la lista, sin llamar al original.' );
	f7b_check( 'borrado' === $ajax->run( 'delete_template', array( 'source' => 'local', 'template_id' => '101' ) ) && array( 'delete_template' ) === $core_calls, 'Sin usos: delega en el original del núcleo.' );
	f7b_check( 'borrado' === $ajax->run( 'delete_template', array( 'source' => 'cloud', 'template_id' => '100' ) ), 'Otra fuente: el original.' );
	do_action( 'elementor/ajax/register_actions', $ajax );
	f7b_check( $cb === $ajax->callback( 'delete_template' ) && 'borrado' === $ajax->run( 'delete_template', array( 'source' => 'local', 'template_id' => '101' ) ), 'Si el gancho corre dos veces, nunca delega en sí misma.' );
	$error = null;
	try {
		$ajax->run( 'bulk_delete_templates', array( 'source' => 'local', 'template_ids' => array( '101', '100' ) ) );
	} catch ( \Exception $e ) {
		$error = $e;
	}
	f7b_check( $error instanceof \Exception && ! in_array( 'bulk_delete_templates', $core_calls, true ), 'En lote, una sola en uso detiene todo el lote con la lista.' );
	// Permisos: un suscriptor y un autor ajeno.
	foreach (
		array(
			'suscriptor'  => static function ( $cap, $id = 0 ) { return 'read' === $cap; },
			'autor ajeno' => static function ( $cap, $id = 0 ) { return in_array( $cap, array( 'read', 'edit_posts' ), true ) || ( in_array( $cap, array( 'edit_post', 'delete_post' ), true ) && 202 === $id ); },
		) as $who => $can
	) {
		$GLOBALS['f7b']['can'] = $can;
		foreach ( array( 'delete_template' => array( 'source' => 'local', 'template_id' => '100' ), 'bulk_delete_templates' => array( 'source' => 'local', 'template_ids' => array( '100' ) ) ) as $tag => $data ) {
			$U::forget();
			$GLOBALS['wpdb']->log = array();
			$core_calls           = array();
			$message              = '';
			try {
				$ajax->run( $tag, $data );
			} catch ( \Exception $e ) {
				$message = $e->getMessage();
			}
			f7b_check( array( $tag ) === $core_calls && array() === $GLOBALS['wpdb']->log && false === strpos( $message, 'Inicio' ) && false === strpos( $message, 'Cabecera' ), "{$who}, {$tag}: responde el original (con su propia comprobación), sin consultar ni revelar usos." );
		}
	}
	$GLOBALS['f7b']['can'] = null;
	$broken                = new class() {
		public $registered = array();
		public function register_ajax_action( $tag, $callback ) { $this->registered[] = $tag; }
	};
	$D::register_ajax( $broken );
	f7b_check( array() === $broken->registered, 'Sin poder leer el original, no se sustituye nada (el bloqueo de pre_delete_post sigue).' );

	// ── 8. Contrato con el núcleo ────────────────────────────────────────────────────────────────────────────────
	$ajax_src = (string) file_get_contents( $root . '/elementor/core/common/modules/ajax/module.php' );
	f7b_check( false !== strpos( $ajax_src, 'private $ajax_actions = [];' ) && false !== strpos( $ajax_src, "\$this->ajax_actions[ \$tag ] = compact( 'tag', 'callback' );" ) && false !== strpos( $ajax_src, "call_user_func( \$this->ajax_actions[ \$action_data['action'] ]['callback'], \$data, \$this )" ), 'El gestor AJAX del núcleo guarda cada acción como [tag, callback] en $ajax_actions y la llama con ($data, $this).' );
	f7b_check( false !== strpos( $ajax_src, '} catch ( \\Exception $e ) {' ) && false !== strpos( $ajax_src, 'add_response_data( false, $e->getMessage(), $e->getCode() )' ), 'Una excepción llega a la biblioteca como error con su mensaje.' );
	$manager = (string) file_get_contents( $root . '/elementor/includes/template-library/manager.php' );
	f7b_check( false !== strpos( $manager, "add_action( 'elementor/ajax/register_actions', [ \$this, 'register_ajax_actions' ] );" ) && false !== strpos( $manager, "'delete_template'," ) && false !== strpos( $manager, "'bulk_delete_templates'," ), 'La biblioteca registra delete_template y bulk_delete_templates en elementor/ajax/register_actions con la prioridad por defecto (10).' );
	$local = (string) file_get_contents( $root . '/elementor/includes/template-library/sources/local.php' );
	f7b_check( 2 <= substr_count( $local, 'wp_delete_post( $template_id, true )' ), 'El borrado de la biblioteca pasa por wp_delete_post(): pre_delete_post lo detiene también sin la sustitución.' );

	// ── 9. WP-CLI y la constante (al final: no se pueden deshacer) ───────────────────────────────────────────────
	$GLOBALS['f7b']['user_meta'] = array();
	define( 'DIGITALISIMO_FORCE_DELETE', true );
	f7b_check( 'force' === $D::bypass() && null === apply_filters( 'pre_delete_post', null, get_post( 100 ), true ), 'Con DIGITALISIMO_FORCE_DELETE no se bloquea.' );
	f7b_check( 1 === count( get_user_meta( 1, 'digitalisimo_elements_f7b_notice', true ) ), 'Sólo un aviso.' );
	f7b_check( array( 'trash' => 't' ) === $D::row_actions( array( 'trash' => 't' ), get_post( 100 ) ), 'Con la constante, la fila conserva «Papelera».' );

	echo 'DIGITALÍSIMO Elements: usos y borrado de F7b (frente A) en verde (' . $GLOBALS['f7b_passed'] . " comprobaciones).\n";
}
