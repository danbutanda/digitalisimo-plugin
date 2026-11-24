<?php
/**
 * La plantilla de página (`_wp_page_template`) sólo cambia cuando se pide (`modules/digitalisimo-mcp/class-page-template.php`,
 * `Page_Template`). Incidente de digitalisimo.lat (2026-11-13 y 2026-11-23): una landing con lienzo lo perdió al
 * reconstruirla con el MCP y al cambiarle el slug por la REST.
 *
 * Sin WordPress: dobles mínimos de ganchos con prioridad, metas (`update_post_meta()` redirige una revisión a su entrada,
 * `update_metadata()` no; filtros `update_post_metadata`/`add_post_metadata` y acciones `updated_post_meta`/
 * `added_post_meta`, como `wp-includes/meta.php`) y del tramo de `wp_insert_post()` de WordPress 7.1 que importa
 * (`pre_post_update`, el bloque de `page_template` con su validación por tipo de entrada y `edit_post`). El camino real
 * (MCP, REST, autoguardado y publicación) se comprueba en Playground (`tests/playground/elements-page-template.sh`).
 *
 * - P1: guardar el autoguardado de una publicada (`DB::save_plain_text()` → `wp_update_post()` de la revisión) no le
 *   pone `default` a la entrada (plantilla «inválida» para el tipo `revision`) ni la vieja de la revisión (válida).
 * - P2: cambiar la plantilla de la entrada pasa la nueva a los autoguardados que tenían la anterior; uno con su propio
 *   cambio pendiente lo conserva; la promoción (`restore_revision`) deja la pedida.
 * - P3: lo de siempre sigue igual: actualizar la entrada con una plantilla válida la escribe, una inválida da `default`,
 *   escribir la meta de una revisión no la propaga ni la bloquea, y una actualización de revisión que no llega a
 *   `edit_post` no bloquea la siguiente escritura de su entrada.
 * Falla con el código anterior (no existe `class-page-template.php`): P1 y P2.
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	error_reporting( E_ALL );
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			fwrite( STDERR, "PHP $severity: $message en $file:$line\n" );
			$GLOBALS['pt_php_errors'] = ( $GLOBALS['pt_php_errors'] ?? 0 ) + 1;
			return true;
		}
	);
	$GLOBALS['pt_failures'] = 0;
	$GLOBALS['pt_checks']   = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['pt_checks'];
		if ( $ok ) {
			echo "ok · $message\n";
		} else {
			++$GLOBALS['pt_failures'];
			echo "FALLA · $message\n";
		}
	}

	// Ganchos con prioridad, como WordPress.
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['pt_hooks'][ $hook ][ $priority ][] = array( $callback, $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function apply_filters( $hook, $value, ...$args ) {
		$all = $GLOBALS['pt_hooks'][ $hook ] ?? array();
		ksort( $all );
		foreach ( $all as $list ) {
			foreach ( $list as $entry ) {
				$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		apply_filters( $hook, ...( $args ? $args : array( null ) ) );
	}

	// Entradas: [ id => [ type, parent, name ] ].
	function wp_is_post_revision( $id ) {
		$p = $GLOBALS['pt']['posts'][ (int) $id ] ?? null;
		return $p && 'revision' === $p['type'] ? (int) $p['parent'] : false;
	}
	function get_posts( $args ) {
		$out = array();
		foreach ( $GLOBALS['pt']['posts'] as $id => $p ) {
			if ( 'revision' === $p['type'] && (int) $args['post_parent'] === $p['parent'] && $args['name'] === $p['name'] ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	// Metas como `wp-includes/meta.php`: filtro previo (corta si no es null), escritura, acción posterior.
	function get_metadata_raw( $type, $id, $key, $single = false ) {
		return $GLOBALS['pt']['meta'][ (int) $id ][ $key ] ?? null;
	}
	function get_metadata( $type, $id, $key, $single = false ) {
		return get_metadata_raw( $type, $id, $key, $single ) ?? '';
	}
	function update_metadata( $type, $id, $key, $value ) {
		$check = apply_filters( 'update_post_metadata', null, $id, $key, $value, '' );
		if ( null !== $check ) {
			return (bool) $check;
		}
		$had = array_key_exists( $key, $GLOBALS['pt']['meta'][ (int) $id ] ?? array() );
		if ( $had && $GLOBALS['pt']['meta'][ (int) $id ][ $key ] === $value ) {
			return false;
		}
		if ( ! $had ) {
			$check = apply_filters( 'add_post_metadata', null, $id, $key, $value, false );
			if ( null !== $check ) {
				return $check;
			}
		}
		$GLOBALS['pt']['meta'][ (int) $id ][ $key ] = $value;
		$GLOBALS['pt']['writes'][]                  = array( (int) $id, $value );
		do_action( $had ? 'updated_post_meta' : 'added_post_meta', 1, $id, $key, $value );
		return true;
	}
	/** `update_post_meta()`: la escritura de una revisión va a su entrada. */
	function update_post_meta( $id, $key, $value ) {
		$parent = wp_is_post_revision( $id );
		return update_metadata( 'post', $parent ? $parent : $id, $key, $value );
	}

	/**
	 * El tramo de `wp_insert_post()` (actualización) de WordPress 7.1 que toca la plantilla: `pre_post_update`, el bloque
	 * de `page_template` (validado contra las plantillas del tipo de la entrada: una revisión no tiene ninguna salvo que
	 * algo las agregue) y `edit_post`. `page_template` sale de la meta de la entrada (`WP_Post::to_array()`) si no viene.
	 * `$fail` corta como el `db_update_error` (sin `edit_post`).
	 */
	function pt_update_post( int $id, array $postarr = array(), bool $fail = false ) {
		$p = $GLOBALS['pt']['posts'][ $id ];
		if ( ! array_key_exists( 'page_template', $postarr ) ) {
			$postarr['page_template'] = get_metadata( 'post', $id, '_wp_page_template', true );
		}
		do_action( 'pre_post_update', $id, array( 'post_type' => $p['type'], 'post_parent' => $p['parent'] ) );
		if ( $fail ) {
			return 0;
		}
		if ( ! empty( $postarr['page_template'] ) ) {
			$templates = 'revision' === $p['type'] ? ( $GLOBALS['pt']['revision_templates'] ?? array() ) : array( 'elementor_canvas' => 1, 'elementor_header_footer' => 1 );
			if ( 'default' !== $postarr['page_template'] && ! isset( $templates[ $postarr['page_template'] ] ) ) {
				update_post_meta( $id, '_wp_page_template', 'default' );
			} else {
				update_post_meta( $id, '_wp_page_template', $postarr['page_template'] );
			}
		}
		do_action( 'edit_post', $id, (object) array( 'ID' => $id ) );
		return $id;
	}
	/** `DB::copy_elementor_meta()` de una revisión a su entrada (promoción de `publish-document`). */
	function pt_restore( int $from, int $to ) {
		update_metadata( 'post', $to, '_wp_page_template', get_metadata( 'post', $from, '_wp_page_template', true ) );
	}

	const PAGE  = 10;
	const AUTO  = 11;
	const AUTO2 = 12;
	const OTHER = 20;
	function pt_reset( string $page_tpl = 'elementor_canvas', string $auto_tpl = 'elementor_canvas', ?string $auto2_tpl = null ): void {
		$GLOBALS['pt'] = array(
			'posts'  => array(
				PAGE  => array( 'type' => 'page', 'parent' => 0, 'name' => 'landing' ),
				AUTO  => array( 'type' => 'revision', 'parent' => PAGE, 'name' => PAGE . '-autosave-v1' ),
				OTHER => array( 'type' => 'page', 'parent' => 0, 'name' => 'otra' ),
			),
			'meta'   => array(
				PAGE  => array( '_wp_page_template' => $page_tpl ),
				AUTO  => array( '_wp_page_template' => $auto_tpl ),
				OTHER => array( '_wp_page_template' => 'default' ),
			),
			'writes' => array(),
		);
		if ( null !== $auto2_tpl ) {
			$GLOBALS['pt']['posts'][ AUTO2 ] = array( 'type' => 'revision', 'parent' => PAGE, 'name' => PAGE . '-autosave-v1' );
			$GLOBALS['pt']['meta'][ AUTO2 ]  = array( '_wp_page_template' => $auto2_tpl );
		}
	}
	function tpl( int $id ) {
		return $GLOBALS['pt']['meta'][ $id ]['_wp_page_template'] ?? null;
	}

	$file  = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-mcp/class-page-template.php';
	$fixed = is_file( $file );
	if ( $fixed ) {
		require $file;
		\Digitalisimo\Elements\Mcp\Page_Template::init();
		\Digitalisimo\Elements\Mcp\Page_Template::init();
	}
	check( $fixed, 'existe Page_Template (modules/digitalisimo-mcp/class-page-template.php)' );
	$module = (string) @file_get_contents( dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-mcp/class-module.php' );
	check( false !== strpos( $module, "class-page-template.php'" ) && false !== strpos( $module, 'Page_Template::init()' ), 'el módulo del MCP lo carga e inicia' );
	check( 1 === count( $GLOBALS['pt_hooks']['update_post_metadata'][ PHP_INT_MAX ] ?? array() ), 'init() es idempotente' );

	// P1: guardar el autoguardado de una publicada no toca la plantilla de la entrada.
	pt_reset();
	pt_update_post( AUTO, array( 'post_content' => 'texto plano' ) );
	check( 'elementor_canvas' === tpl( PAGE ), 'P1.1: guardar la revisión (plantilla «inválida» para revision) deja el lienzo en la entrada (antes: default)' );
	check( 'elementor_canvas' === tpl( AUTO ), 'P1.2: y el autoguardado conserva la suya' );
	pt_reset( 'elementor_canvas', 'default' );
	$GLOBALS['pt']['revision_templates'] = array( 'elementor_canvas' => 1 );
	pt_update_post( AUTO );
	check( 'elementor_canvas' === tpl( PAGE ), 'P1.3: con plantillas válidas para la revisión, la vieja de la revisión tampoco pasa a la entrada' );
	unset( $GLOBALS['pt']['revision_templates'] );

	// P2: la plantilla pedida en la entrada llega a sus autoguardados.
	pt_reset( 'default', 'default', 'elementor_header_footer' );
	update_post_meta( PAGE, '_wp_page_template', 'elementor_canvas' );
	check( 'elementor_canvas' === tpl( PAGE ), 'P2.1: la entrada recibe la plantilla pedida' );
	check( 'elementor_canvas' === tpl( AUTO ), 'P2.2: el autoguardado que tenía la anterior recibe la nueva (antes: default)' );
	check( 'elementor_header_footer' === tpl( AUTO2 ), 'P2.3: uno con su propio cambio pendiente lo conserva' );
	pt_restore( AUTO, PAGE );
	check( 'elementor_canvas' === tpl( PAGE ), 'P2.4: publicar el autoguardado deja la plantilla pedida (antes: default)' );
	pt_reset( 'elementor_canvas', 'elementor_canvas' );
	unset( $GLOBALS['pt']['meta'][ AUTO ]['_wp_page_template'] );
	update_post_meta( PAGE, '_wp_page_template', 'default' );
	check( 'default' === tpl( PAGE ), 'P2.5: quitar el lienzo de la entrada se escribe' );
	check( 'elementor_canvas' !== tpl( AUTO ), 'P2.6: un autoguardado sin plantilla (por defecto) no se queda con el lienzo viejo' );
	pt_reset( '', 'default' );
	unset( $GLOBALS['pt']['meta'][ PAGE ]['_wp_page_template'] );
	update_post_meta( PAGE, '_wp_page_template', 'elementor_canvas' );
	check( 'elementor_canvas' === tpl( PAGE ) && 'elementor_canvas' === tpl( AUTO ), 'P2.7: una entrada sin la meta (add_post_metadata) también la pasa a su autoguardado «default»' );

	// P3: lo de siempre sigue igual.
	pt_reset( 'default', 'default' );
	pt_update_post( PAGE, array( 'page_template' => 'elementor_header_footer' ) );
	check( 'elementor_header_footer' === tpl( PAGE ), 'P3.1: actualizar la entrada con una plantilla válida la escribe' );
	pt_update_post( PAGE, array( 'page_template' => 'no-existe.php' ) );
	check( 'default' === tpl( PAGE ), 'P3.2: una plantilla inválida en la entrada da default, como en WordPress' );
	pt_reset();
	update_metadata( 'post', AUTO, '_wp_page_template', 'elementor_header_footer' );
	check( 'elementor_header_footer' === tpl( AUTO ) && 'elementor_canvas' === tpl( PAGE ), 'P3.3: escribir la meta de una revisión no se bloquea ni toca la entrada' );
	pt_reset();
	pt_update_post( AUTO, array(), true );
	pt_update_post( OTHER, array( 'page_template' => 'elementor_canvas' ) );
	check( 'elementor_canvas' === tpl( OTHER ), 'P3.4: una actualización de revisión cortada no bloquea la siguiente de otra entrada' );
	pt_reset();
	pt_update_post( AUTO, array(), true );
	pt_update_post( PAGE, array( 'page_template' => 'elementor_header_footer' ) );
	check( 'elementor_header_footer' === tpl( PAGE ), 'P3.5: ni la siguiente de su propia entrada' );
	pt_reset( 'default', 'default' );
	update_post_meta( OTHER, '_wp_page_template', 'elementor_canvas' );
	check( 'default' === tpl( AUTO ) && 'default' === tpl( PAGE ), 'P3.6: la plantilla de otra entrada no llega a autoguardados ajenos' );

	echo "\n{$GLOBALS['pt_checks']} comprobaciones, {$GLOBALS['pt_failures']} fallas" . ( empty( $GLOBALS['pt_php_errors'] ) ? '' : ", {$GLOBALS['pt_php_errors']} avisos de PHP" ) . "\n";
	exit( $GLOBALS['pt_failures'] || ! empty( $GLOBALS['pt_php_errors'] ) ? 1 : 0 );
}
