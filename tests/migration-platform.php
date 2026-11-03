<?php
/**
 * F8 · frente H: plataforma de Element Pack en DIGITALÍSIMO Migración (§3.D.3–§3.D.9, V.7, V.8, V.11).
 *
 * Fuentes (título, término, archivos de los mismos adjuntos, OTF, conflicto, familias inseguras), iconos (copia
 * recursiva con `realpath`, sin enlaces simbólicos ni sobrescrituras, nombres que chocan, configuración, reescritura de
 * `library`, alias de pestaña, dos tonos), Mega Menu (plantillas sin condiciones e informe por documento), kit y
 * páginas con barras invertidas, código global (CSS publicable sólo con permiso revalidado y contenido válido; JS
 * siempre en borrador), barra lateral, muestras sin pisar, etiquetas dinámicas exactas, contraseñas de aplicación y
 * claves **sin valores en ningún HTML** y escapadas, residuos, análisis sin escrituras y reversión que omite lo
 * editado. Con el WordPress mínimo de `tests/fixtures/f8-wp.php` y datos sintéticos de `tests/sources/migration/`.
 */

namespace Digitalisimo\Elements\Woo\Swatches {
	/** Doble de las muestras de F4-Woo con la misma API (`attribute`, `put`, `remove`, `color`) y su máximo. */
	final class Data {
		const OPTION = 'digitalisimo_elements_swatches';
		const MAX    = 100;
		private static function all(): array {
			$o = get_option( self::OPTION, array() );
			return is_array( $o['attributes'] ?? null ) ? $o['attributes'] : array();
		}
		public static function attribute( string $taxonomy ): array {
			$slug = 0 === strpos( $taxonomy, 'pa_' ) ? substr( $taxonomy, 3 ) : '';
			return self::all()[ $slug ] ?? array( 'type' => 'select', 'shape' => 'circle', 'size' => 32 );
		}
		public static function put( string $slug, array $entry ): bool {
			$all = self::all();
			if ( 'select' === $entry['type'] ) {
				unset( $all[ $slug ] );
			} elseif ( isset( $all[ $slug ] ) || count( $all ) < self::MAX ) {
				$all[ $slug ] = $entry;
			}
			update_option( self::OPTION, array( 'v' => 1, 'attributes' => $all ), true );
			return true;
		}
		public static function remove( string $slug ): bool {
			$all = self::all();
			unset( $all[ $slug ] );
			update_option( self::OPTION, array( 'v' => 1, 'attributes' => $all ), true );
			return true;
		}
		public static function color( $value ): string {
			return is_string( $value ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', trim( $value ) ) ? trim( $value ) : '';
		}
	}
}

namespace {
	require __DIR__ . '/fixtures/f8-wp.php';

	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Platform\Dynamic_Tags;
	use Digitalisimo\Elements\Legacy\Platform\Fonts;
	use Digitalisimo\Elements\Legacy\Platform\Icons;
	use Digitalisimo\Elements\Legacy\Platform\Inventory;
	use Digitalisimo\Elements\Legacy\Platform\Kit;
	use Digitalisimo\Elements\Legacy\Platform\Mega_Menu;
	use Digitalisimo\Elements\Legacy\Platform\Registry;
	use Digitalisimo\Elements\Legacy\Platform\Sidebar;
	use Digitalisimo\Elements\Legacy\Platform\Snippets;
	use Digitalisimo\Elements\Legacy\Platform\Swatches;

	/* ------------------------------------------------------------------ Lo que el WordPress mínimo no trae */

	function wp_insert_attachment( $args, $file = false, $parent = 0, $wp_error = false ) {
		$args = (array) $args;
		$file = $args['file'] ?? $file;
		$id   = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_parent' => (int) ( $args['post_parent'] ?? $parent ), 'guid' => (string) ( $args['guid'] ?? '' ), 'post_mime_type' => (string) ( $args['post_mime_type'] ?? '' ) ) );
		if ( $file ) {
			update_post_meta( $id, '_wp_attached_file', $file );
		}
		return $id;
	}
	function wp_get_attachment_url( $id ) {
		$p = get_post( $id );
		return $p && 'attachment' === $p->post_type ? (string) $p->guid : false;
	}
	function wp_attachment_is_image( $id ) {
		$p = get_post( $id );
		return $p && 'attachment' === $p->post_type && 0 === strpos( (string) $p->post_mime_type, 'image/' );
	}
	function get_terms( $args = array() ) {
		$out = array();
		foreach ( $GLOBALS['wp']['terms'][ $GLOBALS['wp']['blog'] ] as $id => $row ) {
			if ( $row['taxonomy'] === ( $args['taxonomy'] ?? '' ) ) {
				$out[] = 'ids' === ( $args['fields'] ?? '' ) ? (int) $id : (object) $row;
			}
		}
		return array_slice( $out, 0, (int) ( $args['number'] ?? 0 ) ?: null );
	}
	function get_term_meta( $id, $key = '', $single = false ) {
		$v = $GLOBALS['wp']['term_meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ][ $key ] ?? null;
		return $single ? ( null === $v ? '' : $v ) : ( null === $v ? array() : array( $v ) );
	}
	function update_term_meta( $id, $key, $value ) {
		$GLOBALS['wp']['term_meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ][ $key ] = wp_unslash( $value );
		$GLOBALS['h_term_writes'][]                                                = array( (int) $id, $key );
		return true;
	}
	function delete_term_meta( $id, $key ) {
		unset( $GLOBALS['wp']['term_meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ][ $key ] );
		return true;
	}
	function wc_get_attribute_taxonomies() {
		return $GLOBALS['wc_attributes'] ?? array();
	}
	function wp_get_nav_menu_items( $menu, $args = array() ) {
		return $GLOBALS['menu_items'][ $GLOBALS['wp']['blog'] ][ (int) $menu ] ?? array();
	}
	function taxonomy_exists( $taxonomy ) {
		return empty( $GLOBALS['h_missing_tax'][ $taxonomy ] );
	}
	function get_post_types( $args = array(), $output = 'names' ) {
		return array( 'post' => 'post', 'page' => 'page' );
	}

	/** $wpdb con las dos consultas del inventario que la base no entiende: contenido LIKE y tablas. */
	class H_Wpdb extends F8_Wpdb {
		public $tables = array();
		private static function args( $query ) {
			$pos = strpos( (string) $query, "\0" );
			return array( false === $pos ? (string) $query : substr( $query, 0, $pos ), false === $pos ? array() : (array) unserialize( substr( $query, $pos + 1 ) ) );
		}
		public function get_col( $query, $x = 0 ) {
			list( $sql, $args ) = self::args( $query );
			if ( false !== strpos( $sql, 'post_content LIKE' ) ) {
				$this->queries[] = $sql;
				$out             = array();
				foreach ( $GLOBALS['wp']['posts'][ $GLOBALS['wp']['blog'] ] as $id => $p ) {
					if ( ! in_array( $p['post_type'], array( 'revision', 'nav_menu_item' ), true ) && ! in_array( $p['post_status'], array( 'trash', 'auto-draft' ), true ) && self::like( $p['post_content'], (string) $args[0] ) ) {
						$out[] = (int) $id;
					}
				}
				sort( $out );
				return $out;
			}
			return parent::get_col( $query, $x );
		}
		public function get_var( $query, $x = 0, $y = 0 ) {
			list( $sql, $args ) = self::args( $query );
			if ( 0 === stripos( $sql, 'SHOW TABLES LIKE' ) ) {
				foreach ( $this->tables as $table ) {
					if ( self::like( $table, (string) $args[0] ) ) {
						return $table;
					}
				}
				return null;
			}
			return parent::get_var( $query, $x, $y );
		}
	}

	f8_wpdb( 'H_Wpdb' );
	f8_load_migration( array( 'translator', 'context', 'report' ) );
	$platform = dirname( __DIR__ ) . '/digitalisimo-migration/legacy/platform/';
	if ( ! is_file( $platform . 'class-publisher.php' ) ) {
		// Mientras el frente G no entregue el publicador: un doble con la misma firma.
		eval( 'namespace Digitalisimo\Elements\Legacy\Platform; final class Publisher { public static $tracked = array(); public static function track( string $kind, int $id ): void { self::$tracked[ $kind ][ $id ] = true; } public static function untrack( int $id ): void { foreach ( self::$tracked as $k => $ids ) { unset( self::$tracked[ $k ][ $id ] ); } } }' ); // phpcs:ignore
	}

	$check = 'f8_check';
	$src   = __DIR__ . '/sources/migration/';
	$fx    = static function ( $name ) use ( $src ) {
		return json_decode( (string) file_get_contents( $src . $name . '.json' ), true );
	};

	/** Códigos del informe en curso: `code` del sitio y `<id>:code` de los documentos. */
	function h_codes( array $report ) {
		$out = array();
		foreach ( (array) $report['site']['items'] as $item ) {
			$out[] = $item['code'];
		}
		foreach ( (array) $report['docs'] as $id => $doc ) {
			foreach ( (array) $doc['items'] as $item ) {
				$out[] = $id . ':' . $item['code'];
			}
		}
		return $out;
	}
	function h_item( array $report, $code, $post_id = 0 ) {
		$items = $post_id ? (array) ( $report['docs'][ $post_id ]['items'] ?? array() ) : (array) $report['site']['items'];
		foreach ( $items as $item ) {
			if ( $item['code'] === $code ) {
				return $item;
			}
		}
		return null;
	}
	/** Estado limpio, también en las cachés de la petición (registro, alias, contenidos del mega menú). */
	function h_reset( array $over = array() ) {
		f8_reset( $over );
		Registry::flush_cache();
		Icons::flush();
		Mega_Menu::flush();
		$publisher = 'Digitalisimo\\Elements\\Legacy\\Platform\\Publisher';
		if ( class_exists( $publisher ) && method_exists( $publisher, 'reset' ) ) {
			$publisher::reset();
		}
	}
	function h_fresh_report() {
		Report::clear();
		Report::begin( 'run' );
	}
	function h_rmrf( $dir ) {
		if ( is_link( $dir ) || is_file( $dir ) ) {
			@unlink( $dir ); // phpcs:ignore
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $f ) {
			if ( '.' !== $f && '..' !== $f ) {
				h_rmrf( $dir . '/' . $f );
			}
		}
		@rmdir( $dir ); // phpcs:ignore
	}
	function h_copy( $from, $to ) {
		wp_mkdir_p( $to );
		foreach ( scandir( $from ) as $f ) {
			if ( '.' === $f || '..' === $f ) {
				continue;
			}
			is_dir( $from . '/' . $f ) ? h_copy( $from . '/' . $f, $to . '/' . $f ) : copy( $from . '/' . $f, $to . '/' . $f );
		}
	}
	/** Fotografía de lo que el análisis no puede cambiar. */
	function h_snapshot() {
		$wp = $GLOBALS['wp'];
		return md5( serialize( array( $wp['options'], $wp['posts'], $wp['meta'], $wp['user_meta'], $wp['term_meta'] ?? array(), $wp['terms'], $wp['object_terms'] ) ) );
	}
	function h_job( array $over = array() ) {
		update_option(
			Inventory::JOB,
			$over + array(
				'user'    => 1,
				'plugins' => array(
					'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'label' => 'Element Pack Pro', 'site' => true, 'network' => false, 'core' => false ),
				),
			),
			false
		);
	}
	function h_tracked( $id ) {
		$publisher = 'Digitalisimo\\Elements\\Legacy\\Platform\\Publisher';
		if ( property_exists( $publisher, 'tracked' ) ) {
			return ! empty( $publisher::$tracked['css'][ $id ] );
		}
		$state = get_option( 'digitalisimo_migration_tb_state', array() );
		return in_array( (int) $id, array_map( 'intval', (array) ( $state['css'] ?? array() ) ), true );
	}
	h_rmrf( WP_CONTENT_DIR );

	/* ================================================================== 1. Catálogo y lectura estática */

	$catalog = Report::catalog();
	$mine    = include dirname( __DIR__ ) . '/digitalisimo-migration/legacy/report/codes-platform.php';
	$check( count( $mine ) >= 60, 'el catálogo de la plataforma declara sus códigos' );
	foreach ( $mine as $code => $spec ) {
		$check( isset( $catalog[ $code ] ) && 0 === strpos( $code, 'plat.' ), 'código en el catálogo con el prefijo de H: ' . $code );
	}
	$labels = implode( "\n", array_column( $mine, 'label' ) );
	$check( ! preg_match( '#https?://|bdthemes|elementpack\.pro#i', $labels ), 'ninguna etiqueta enlaza ni nombra dominios del proveedor' );
	$check( ! empty( $catalog['plat.app_passwords']['private'] ) && 'review' === $catalog['plat.app_passwords']['section'], 'contraseñas de aplicación: «Pendiente de revisar» y nombres privados' );
	$check( 'review' === $catalog['plat.mega.rebuild']['section'] && 'review' === $catalog['plat.code.js']['section'] && 'review' === $catalog['plat.code.css_draft']['section'], 'Mega Menu, JS y CSS en borrador piden revisión' );
	$check( ! empty( $catalog['plat.tags.dropped']['review_if_published'] ), 'etiquetas quitadas en un documento publicado piden revisión (C2-27)' );
	$code_text = '';
	foreach ( glob( $platform . 'class-{fonts,icons,mega-menu,kit,snippets,sidebar,swatches,dynamic-tags,inventory}.php', GLOB_BRACE ) as $file ) {
		$code_text .= file_get_contents( $file );
	}
	preg_match_all( "/'(plat\\.[a-z0-9_.]+)'/", $code_text, $used );
	foreach ( array_unique( $used[1] ) as $code ) {
		$check( isset( $mine[ $code ] ), 'cada código usado está en el catálogo: ' . $code );
	}
	$check( 9 === count( glob( $platform . 'class-{fonts,icons,mega-menu,kit,snippets,sidebar,swatches,dynamic-tags,inventory}.php', GLOB_BRACE ) ), 'los nueve archivos de H' );
	$check( false === strpos( $code_text, "get_post_meta( \$id, '_bdt_icon_set_path'" ) && ! preg_match( '/_bdt_icon_set_(path|url)/', $code_text ), 'nunca se lee la ruta de disco guardada por el origen' );
	$check( ! preg_match( '/\badd_option\s*\(/', $code_text ), 'sin add_option (los cerrojos son INSERT IGNORE)' );
	$check( ! preg_match( '/\bwp_delete_(post|attachment)\s*\(|\bunlink\s*\(|\brmdir\s*\(|delete_plugins\s*\(/', $code_text ), 'nunca borra entradas, archivos ni plugins' );
	$check( ! preg_match( "/attribute_type'\\s*\\]\\s*=|update_option\\(\\s*'woocommerce_attribute|woocommerce_attribute_taxonomies/", $code_text ), 'nunca escribe attribute_type' );
	$check( ! preg_match( '/WP_Application_Passwords|delete_user_meta|update_user_meta/', $code_text ), 'nunca revoca ni toca contraseñas de aplicación' );
	$check( ! preg_match( "/'post_status'\\s*=>\\s*'publish',\\s*\\n\\s*'post_title'\\s*=>\\s*wp_slash\\(\\s*self::title/", $code_text ), 'el código global nace en borrador' );

	/* ================================================================== 2. Fuentes */

	h_reset();
	h_rmrf( WP_CONTENT_DIR );
	h_job();
	h_fresh_report();
	$att = array(
		'ATT_WOFF'  => f8_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'guid' => 'https://s1.test/wp-content/uploads/2026/01/letra.woff', 'post_mime_type' => 'font/woff' ) ),
		'ATT_WOFF2' => f8_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'guid' => 'https://s1.test/wp-content/uploads/2026/01/letra.woff2', 'post_mime_type' => 'font/woff2' ) ),
		'ATT_OTF'   => f8_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'guid' => 'https://s1.test/wp-content/uploads/2026/01/letra-bold.otf', 'post_mime_type' => 'font/otf' ) ),
	);
	$font_ids = array();
	foreach ( $fx( 'fonts' )['fonts'] as $font ) {
		$files = json_decode( strtr( json_encode( $font['files'] ), array_map( 'strval', $att ) ), true );
		$font_ids[ $font['title'] ] = f8_post( array( 'post_type' => 'ep_custom_font', 'post_title' => $font['title'] ), array( 'ep_font_files' => $files ) );
	}
	$existing = f8_post( array( 'post_type' => 'elementor_font', 'post_title' => 'Ya Existe' ) );
	f8_post( array( 'post_type' => 'ep_custom_font', 'post_title' => 'Borrador', 'post_status' => 'draft' ), array( 'ep_font_files' => array() ) );
	update_option( 'elementor_fonts_manager_fonts', array( 'x' => 1 ) );
	update_option( 'elementor_fonts_manager_font_types', array( 'x' => 1 ) );

	$before   = h_snapshot();
	$analysis = Fonts::analyze();
	$check( h_snapshot() === $before, 'fuentes: el análisis no escribe nada' );
	$a_codes  = array_column( $analysis, 0 );
	$check( in_array( 'plat.font.created', $a_codes, true ) && in_array( 'plat.font.conflict', $a_codes, true ) && in_array( 'plat.font.unsafe', $a_codes, true ) && in_array( 'plat.font.empty', $a_codes, true ), 'fuentes: el análisis anuncia creada, conflicto, insegura y vacía' );

	$cursor = array();
	$check( false === Fonts::prepare( $cursor, microtime( true ) - 1 ), 'fuentes: sin tiempo, la etapa sigue en la siguiente petición' );
	$check( empty( $cursor['after'] ), 'fuentes: sin tiempo no avanza el cursor' );
	$check( true === Fonts::prepare( $cursor, microtime( true ) + 30 ), 'fuentes: la etapa termina' );
	$created = get_posts( array( 'post_type' => 'elementor_font', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	$check( 2 === count( $created ), 'fuentes: sólo se crea la válida (más la que ya existía)' );
	$font = (int) end( $created );
	$post = get_post( $font );
	$check( 'Letra Prueba' === $post->post_title && 'publish' === $post->post_status, 'fuentes: mismo título (la familia) y publicada' );
	$terms = wp_get_object_terms( $font, 'elementor_font_type' );
	$check( 1 === count( $terms ) && 'custom' === $terms[0]->slug, 'fuentes: término custom de elementor_font_type' );
	$files = get_post_meta( $font, 'elementor_font_files', true );
	$check( is_array( $files ) && 2 === count( $files ), 'fuentes: dos variantes utilizables (la del adjunto perdido se omite)' );
	$check( '' === $files[0]['font_type'] && '400' === $files[0]['font_weight'] && 'normal' === $files[0]['font_style'], 'fuentes: font_type vacío, peso y estilo copiados' );
	$check( $att['ATT_WOFF'] === $files[0]['woff']['id'] && 'https://s1.test/wp-content/uploads/2026/01/letra.woff' === $files[0]['woff']['url'], 'fuentes: mismo adjunto, URL leída del adjunto (no la guardada)' );
	$check( $att['ATT_WOFF2'] === $files[0]['woff2']['id'], 'fuentes: woff2 con su adjunto' );
	$check( array( 'id' => '', 'url' => '' ) === $files[0]['ttf'] && array( 'id' => '', 'url' => '' ) === $files[0]['eot'], 'fuentes: huecos vacíos como los guarda el destino' );
	$check( ! isset( $files[0]['otf'] ) && ! isset( $files[1]['otf'] ), 'fuentes: el destino no tiene hueco otf' );
	$check( $att['ATT_OTF'] === $files[1]['ttf']['id'] && '700' === $files[1]['font_weight'] && 'italic' === $files[1]['font_style'], 'fuentes: el OTF va al hueco ttf' );
	$face = (string) get_post_meta( $font, 'elementor_font_face', true );
	$check( false !== strpos( $face, "font-family: 'Letra Prueba';" ) && false !== strpos( $face, "format('truetype')" ) && false !== strpos( $face, "format('woff2')" ), 'fuentes: @font-face regenerado con el formato del destino' );
	$check( false === strpos( $face, 'opentype' ) && false === strpos( $face, 'otro.example' ), 'fuentes: sin formato opentype ni URL ajena' );
	$check( false === get_option( 'elementor_fonts_manager_fonts' ) && false === get_option( 'elementor_fonts_manager_font_types' ), 'fuentes: clear_fonts_list()' );
	$row = Registry::get( 'font', (string) $font_ids['Letra Prueba'] );
	$check( $row && $font === $row['dest'] && 'done' === $row['state'] && Fonts::hash( $font ) === $row['hash'], 'fuentes: fila del registro con destino y huella' );
	$check( "font:{$font_ids['Letra Prueba']}" === get_post_meta( $font, Inventory::TAG, true ), 'fuentes: procedencia en la entrada creada' );
	$r     = Report::take();
	$codes = h_codes( $r );
	foreach ( array( 'plat.font.created', 'plat.font.otf', 'plat.font.file_missing', 'plat.font.conflict', 'plat.font.unsafe', 'plat.font.empty' ) as $code ) {
		$check( in_array( $code, $codes, true ), 'fuentes: informe ' . $code );
	}
	$check( $existing === ( h_item( $r, 'plat.font.conflict' )['detail']['id'] ?? 0 ), 'fuentes: el conflicto señala la fuente existente' );
	$html = Report::render( $r, 'site' );
	$check( false === stripos( $html, '<img' ) && false === strpos( $html, 'onerror=alert(1)>' ), 'fuentes: el informe no pinta HTML de una familia' );
	$check( false !== strpos( $html, 'Letra Prueba' ), 'fuentes: el informe nombra la familia' );
	// Reanudar: una petición que murió tras crear la entrada y antes de anotarla no duplica.
	Registry::forget( 'font', (string) $font_ids['Letra Prueba'] );
	h_fresh_report();
	$cursor = array();
	Fonts::prepare( $cursor, microtime( true ) + 30 );
	$check( 2 === count( get_posts( array( 'post_type' => 'elementor_font', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ), 'fuentes: reanudar no duplica' );
	$check( $font === Registry::get( 'font', (string) $font_ids['Letra Prueba'] )['dest'], 'fuentes: reanudar recupera el destino por su procedencia' );
	$cursor = array();
	Fonts::prepare( $cursor, microtime( true ) + 30 );
	$check( 2 === count( get_posts( array( 'post_type' => 'elementor_font', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ), 'fuentes: volver a pulsar no duplica' );
	$check( 'Letra Prueba' === get_post( $font_ids['Letra Prueba'] )->post_title && 'ep_custom_font' === get_post( $font_ids['Letra Prueba'] )->post_type, 'fuentes: el original no se toca' );
	// Sin el gestor de fuentes del destino: no se crea una fuente que nadie vería.
	$sin_tax = f8_post( array( 'post_type' => 'ep_custom_font', 'post_title' => 'Sin gestor' ), array( 'ep_font_files' => array( array( 'font_weight' => '400', 'font_style' => 'normal', 'woff' => array( 'id' => $att['ATT_WOFF'], 'url' => '' ) ) ) ) );
	$GLOBALS['h_missing_tax'] = array( 'elementor_font_type' => true );
	h_fresh_report();
	$check( false === Fonts::convert( $sin_tax ) && null === Registry::get( 'font', (string) $sin_tax ), 'fuentes: sin el gestor del destino no se crea' );
	$check( in_array( 'plat.font.failed', h_codes( Report::take() ), true ), 'fuentes: y se informa' );
	$GLOBALS['h_missing_tax'] = array();

	/* ================================================================== 3. Iconos */

	h_reset();
	h_rmrf( WP_CONTENT_DIR );
	h_job();
	h_fresh_report();
	$uploads  = wp_upload_dir();
	$icons_fx = $fx( 'icons' );
	$set_id   = f8_post( array( 'post_type' => 'bdt_custom_icons', 'post_title' => 'Glifos <b>propios</b>' ), array( 'bdt_custom_icon_set_config' => json_encode( $icons_fx['config'] ) ) );
	$from     = $uploads['basedir'] . '/element-pack/custom-icons/' . $set_id;
	h_copy( $src . 'icons/set-glifos', $from );
	wp_mkdir_p( WP_CONTENT_DIR . '/fuera' );
	file_put_contents( WP_CONTENT_DIR . '/fuera/secreto.woff', 'SECRETO' );
	symlink( WP_CONTENT_DIR . '/fuera/secreto.woff', $from . '/fonts/fuera.woff' );
	symlink( WP_CONTENT_DIR . '/fuera', $from . '/enlace' );
	// Un conjunto del destino con el mismo nombre y su carpeta: el nuevo no puede pisarlo.
	$pro = f8_post( array( 'post_type' => 'elementor_icons', 'post_title' => 'glifos' ), array( 'elementor_custom_icon_set_config' => json_encode( array( 'name' => 'glifos' ) ) ) );
	wp_mkdir_p( $uploads['basedir'] . '/elementor/custom-icons/glifos' );
	file_put_contents( $uploads['basedir'] . '/elementor/custom-icons/glifos/style.css', '/* del sitio */' );
	// Otro conjunto con la ruta guardada apuntando fuera: se ignora y se recalcula.
	update_post_meta( $set_id, '_bdt_icon_set_path', '/etc/' );
	update_option( 'elementor_custom_icon_sets_config', array( 'x' => 1 ) );

	$before   = h_snapshot();
	$analysis = Icons::analyze();
	$check( h_snapshot() === $before && ! is_dir( $uploads['basedir'] . '/elementor/custom-icons/' . Icons::base_name( 'glifos' ) . '-x' ), 'iconos: el análisis no escribe nada' );
	$check( 'plat.icons.renamed' === ( $analysis[0][0] ?? '' ), 'iconos: el análisis anuncia el nombre con sufijo' );

	$cursor = array();
	$check( true === Icons::prepare( $cursor, microtime( true ) + 30 ), 'iconos: la etapa termina' );
	$sets = get_posts( array( 'post_type' => 'elementor_icons', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	$check( 2 === count( $sets ), 'iconos: un conjunto nuevo' );
	$new_set  = (int) end( $sets );
	$expected = 'glifos-' . substr( hash( 'sha256', $set_id . '|glifos' ), 0, 8 );
	$config   = json_decode( (string) get_post_meta( $new_set, 'elementor_custom_icon_set_config', true ), true );
	$check( $expected === $config['name'] && preg_match( '/^[a-z0-9-]{1,48}$/', $config['name'] ), 'iconos: el nombre que choca lleva <nombre>-<sha8>' );
	$check( 'gl-' === $config['prefix'] && 'gl' === $config['displayPrefix'], 'iconos: el prefijo de la pestaña de origen (value no cambia)' );
	$check( array( 'estrella', 'nube' ) === $config['icons'] && 2 === $config['count'] && ! isset( $config['fetchJson'] ), 'iconos: lista de iconos, recuento y sin fetchJson con pocos' );
	$check( $uploads['baseurl'] . '/elementor/custom-icons/' . $expected . '/style.css' === $config['url'] && false === $config['enqueue'], 'iconos: hoja del conjunto nuevo' );
	$check( 'eicon eicon-folder' === $config['labelIcon'] && 'icomoon' === $config['custom_icon_type'], 'iconos: icono de pestaña y tipo' );
	$check( 'Glifos propios' === get_post( $new_set )->post_title, 'iconos: título sin etiquetas' );
	$dir = $uploads['basedir'] . '/elementor/custom-icons/' . $expected;
	$check( is_file( $dir . '/style.css' ) && is_file( $dir . '/selection.json' ), 'iconos: hoja y configuración copiadas' );
	$check( is_file( $dir . '/fonts/glifos.woff' ) && is_file( $dir . '/fonts/glifos.ttf' ) && is_file( $dir . '/fonts/glifos.svg' ), 'iconos: subcarpeta fonts/ conservada' );
	$check( file_get_contents( $dir . '/style.css' ) === file_get_contents( $from . '/style.css' ), 'iconos: copia byte a byte (rutas relativas intactas)' );
	$check( ! file_exists( $dir . '/demo.html' ) && ! file_exists( $dir . '/notas.txt' ), 'iconos: tipos no admitidos fuera' );
	$check( ! file_exists( $dir . '/fonts/fuera.woff' ) && ! file_exists( $dir . '/enlace' ) && ! file_exists( $dir . '/enlace/secreto.woff' ), 'iconos: sin enlaces simbólicos (archivo ni carpeta)' );
	$check( '/* del sitio */' === file_get_contents( $uploads['basedir'] . '/elementor/custom-icons/glifos/style.css' ), 'iconos: la carpeta del conjunto existente no se toca' );
	$check( trailingslashit( realpath( $dir ) ) === get_post_meta( $new_set, '_elementor_icon_set_path', true ), 'iconos: ruta del conjunto nuevo' );
	$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'post_parent' => $new_set, 'fields' => 'ids' ) );
	$check( 5 === count( $attachments ), 'iconos: un adjunto por archivo copiado' );
	$alias = get_option( Icons::ALIAS );
	$old   = 'bdt-custom-glifos-' . $set_id;
	$check( array( $old => $expected ) === $alias && f8_autoload( Icons::ALIAS ), 'iconos: alias con carga automática' );
	$check( false === get_option( 'elementor_custom_icon_sets_config' ), 'iconos: lista de conjuntos del destino vaciada' );
	$row = Registry::get( 'icons', (string) $set_id );
	$check( $row && $new_set === $row['dest'] && $old === $row['extra']['old'] && $expected === $row['extra']['name'], 'iconos: registro con biblioteca vieja y nombre nuevo' );
	$r = Report::take();
	$check( in_array( 'plat.icons.renamed', h_codes( $r ), true ) && in_array( 'plat.icons.skipped_file', h_codes( $r ), true ), 'iconos: informe de renombrado y archivos omitidos' );
	$check( false === strpos( Report::render( $r, 'site' ), '<b>' ), 'iconos: el informe no pinta etiquetas del título' );
	$cursor = array();
	Icons::prepare( $cursor, microtime( true ) + 30 );
	$check( 2 === count( get_posts( array( 'post_type' => 'elementor_icons', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ), 'iconos: volver a pulsar no duplica' );

	// Reescritura de `library` (también en repetidores), pura y con informe por documento.
	$doc = f8_post( array( 'post_type' => 'page', 'post_title' => 'Iconos' ) );
	$elements = json_decode( str_replace( 'bdt-custom-glifos-ID', $old, json_encode( $icons_fx['document'] ) ), true );
	h_fresh_report();
	Icons::flush();
	Context::begin( $doc, 'run' );
	$snap = h_snapshot();
	$out  = Icons::rewrite_elements( $elements );
	Context::end();
	$check( h_snapshot() === $snap, 'iconos: reescribir no escribe nada' );
	$check( $expected === $out[0]['elements'][0]['settings']['selected_icon']['library'] && 'gl gl-estrella' === $out[0]['elements'][0]['settings']['selected_icon']['value'], 'iconos: library nueva y value intacto' );
	$check( $expected === $out[0]['elements'][1]['settings']['icon_list'][0]['selected_icon']['library'], 'iconos: también en filas de repetidor' );
	$check( 'fa-solid' === $out[0]['elements'][1]['settings']['icon_list'][1]['selected_icon']['library'], 'iconos: otras bibliotecas intactas' );
	$check( 'bdt-custom-borrado-999' === $out[0]['elements'][2]['settings']['selected_icon']['library'], 'iconos: un conjunto desconocido no se inventa' );
	$check( array( 'rewritten' => 2, 'duotone' => 0, 'unknown' => 1 ) === Icons::describe( $elements ), 'iconos: el análisis de un documento cuenta sin informar' );
	$single = Icons::rewrite( $elements[0] );
	$check( $old === $single['elements'][0]['settings']['selected_icon']['library'], 'iconos: rewrite() sólo toca el elemento, no sus hijos' );
	$r = Report::take();
	$check( null !== h_item( $r, 'plat.icons.rewritten', $doc ) && 1 === count( array_keys( h_codes( $r ), $doc . ':plat.icons.rewritten', true ) ), 'iconos: lo reescrito, una vez por documento' );
	$check( array( 'count' => 1, 'element' => 'icon #b3' ) == ( h_item( $r, 'plat.icons.unknown', $doc )['detail'] ?? null ), 'iconos: el conjunto desconocido, por elemento' );
	$settings = Icons::rewrite_settings( array( 'x' => array( 'value' => 'gl gl-nube', 'library' => $old ) ) );
	$check( $expected === $settings['x']['library'], 'iconos: también en ajustes sueltos (kit, página)' );
	h_fresh_report();
	Context::begin( $doc, 'editor' );
	$editor = Icons::rewrite_elements( $elements );
	Context::end();
	$check( $expected === $editor[0]['elements'][0]['settings']['selected_icon']['library'] && Report::is_empty( Report::take() ), 'iconos: en el editor reescribe sin informe' );

	// Pestaña de alias: el nombre viejo apunta a la hoja nueva mientras el origen no está cargado.
	$tabs = Icons::alias_tabs( array( $expected => array( 'name' => $expected, 'url' => $config['url'], 'prefix' => 'gl-' ) ) );
	$check( isset( $tabs[ $old ] ) && $old === $tabs[ $old ]['name'] && $config['url'] === $tabs[ $old ]['url'], 'iconos: pestaña de alias con la hoja nueva' );
	$check( Icons::alias_tabs( array() ) === array(), 'iconos: sin la pestaña nueva no se inventa el alias' );
	Icons::init();
	$check( has_filter( 'elementor/icons_manager/additional_tabs', array( Icons::class, 'alias_tabs' ) ), 'iconos: init() engancha la pestaña de alias si hay alias' );

	// Conjuntos que no se pueden crear.
	h_fresh_report();
	$bad_css = f8_post( array( 'post_type' => 'bdt_custom_icons', 'post_title' => 'Hoja hostil' ), array( 'bdt_custom_icon_set_config' => json_encode( array( 'name' => 'hostil' ) + $icons_fx['config'] ) ) );
	h_copy( $src . 'icons/set-glifos', $uploads['basedir'] . '/element-pack/custom-icons/' . $bad_css );
	file_put_contents( $uploads['basedir'] . '/element-pack/custom-icons/' . $bad_css . '/style.css', '.x{color:red}</style><script>alert(1)</script>' );
	$linked = f8_post( array( 'post_type' => 'bdt_custom_icons', 'post_title' => 'Carpeta enlazada' ), array( 'bdt_custom_icon_set_config' => json_encode( array( 'name' => 'enlazada' ) + $icons_fx['config'] ) ) );
	symlink( WP_CONTENT_DIR . '/fuera', $uploads['basedir'] . '/element-pack/custom-icons/' . $linked );
	$noconf = f8_post( array( 'post_type' => 'bdt_custom_icons', 'post_title' => 'Sin configuración' ), array( 'bdt_custom_icon_set_config' => '{roto' ) );
	$many   = f8_post( array( 'post_type' => 'bdt_custom_icons', 'post_title' => 'Muchos' ), array( 'bdt_custom_icon_set_config' => json_encode( array( 'name' => 'muchos', 'icons' => array_map( static function ( $i ) { return 'i' . $i; }, range( 1, 30 ) ) ) + $icons_fx['config'] ) ) );
	h_copy( $src . 'icons/set-glifos', $uploads['basedir'] . '/element-pack/custom-icons/' . $many );
	$duo = f8_post( array( 'post_type' => 'bdt_custom_icons', 'post_title' => 'Dos tonos' ), array( 'bdt_custom_icon_set_config' => json_encode( $icons_fx['icofont'] ) ) );
	h_copy( $src . 'icons/set-glifos', $uploads['basedir'] . '/element-pack/custom-icons/' . $duo );
	$cursor = array();
	Icons::prepare( $cursor, microtime( true ) + 30 );
	$r = Report::take();
	$check( in_array( 'plat.icons.unsafe', h_codes( $r ), true ) && ! file_exists( $uploads['basedir'] . '/elementor/custom-icons/hostil' ), 'iconos: una hoja con </style> no se copia ni deja carpeta' );
	$check( null === Registry::get( 'icons', (string) $bad_css ), 'iconos: el conjunto inseguro no se anota' );
	$check( in_array( 'plat.icons.missing_files', h_codes( $r ), true ) && ! is_dir( $uploads['basedir'] . '/elementor/custom-icons/enlazada' ), 'iconos: una carpeta de origen enlazada se rechaza' );
	$check( in_array( 'plat.icons.no_config', h_codes( $r ), true ) && null === Registry::get( 'icons', (string) $noconf ), 'iconos: configuración ilegible' );
	$many_config = json_decode( (string) get_post_meta( Registry::get( 'icons', (string) $many )['dest'], 'elementor_custom_icon_set_config', true ), true );
	$check( 30 === $many_config['count'] && $uploads['baseurl'] . '/elementor/custom-icons/muchos/e_icons.js' === $many_config['fetchJson'], 'iconos: más de 25 iconos llevan fetchJson' );
	$check( array( 'icons' => $many_config['icons'] ) === json_decode( (string) file_get_contents( $uploads['basedir'] . '/elementor/custom-icons/muchos/e_icons.js' ), true ), 'iconos: e_icons.js con la lista' );
	$duo_old = 'bdt-custom-duo-prueba-' . $duo;
	h_fresh_report();
	Icons::flush();
	Context::begin( $doc, 'run' );
	$duo_out = Icons::rewrite( array( 'id' => 'd', 'elType' => 'widget', 'widgetType' => 'icon', 'settings' => array( 'selected_icon' => array( 'value' => 'icofont icofont-tono', 'library' => $duo_old ), 'b' => array( 'value' => 'icofont icofont-plano', 'library' => $duo_old ) ) ) );
	Context::end();
	$r = Report::take();
	$check( 'duo-prueba' === $duo_out['settings']['selected_icon']['library'] && array( 'count' => 1, 'element' => 'icon #d' ) == ( h_item( $r, 'plat.icons.duotone', $doc )['detail'] ?? null ), 'iconos: dos tonos de Icofont informados por elemento' );

	// Reversión: lo editado se omite; lo demás a la papelera y el alias desaparece.
	update_post_meta( Registry::get( 'icons', (string) $many )['dest'], 'elementor_custom_icon_set_config', '{"name":"muchos","editado":1}' );
	$out_rev = Icons::revert();
	$check( 'trash' === get_post_status( $new_set ), 'iconos: el conjunto creado va a la papelera' );
	$check( 'publish' === get_post_status( Registry::get( 'icons', (string) $many )['dest'] ) && in_array( 'core.revert_skipped', array_column( $out_rev, 0 ), true ), 'iconos: el conjunto editado se omite y se informa' );
	$check( ! isset( get_option( Icons::ALIAS, array() )[ $old ] ) && isset( get_option( Icons::ALIAS, array() )[ 'bdt-custom-muchos-' . $many ] ), 'iconos: el alias revertido desaparece; el omitido sigue' );
	$check( is_file( $dir . '/style.css' ), 'iconos: revertir no borra archivos' );
	$check( 'publish' === get_post_status( $pro ), 'iconos: el conjunto que ya existía no se toca' );

	/* ================================================================== 4. Mega Menu */

	h_reset();
	h_job();
	h_fresh_report();
	Mega_Menu::flush();
	$menu  = wp_insert_term( 'Principal', 'nav_menu', array( 'slug' => 'principal' ) )['term_id'];
	$menu2 = wp_insert_term( 'Pie', 'nav_menu', array( 'slug' => 'pie' ) )['term_id'];
	$item1 = f8_post( array( 'post_type' => 'nav_menu_item', 'post_title' => 'Productos' ), array( '_ep_mega_menu_item_settings' => json_encode( array( 'menu_id' => 0, 'menu_enable' => '1', 'menu_width_type' => 'full_width', 'menu_badge_label' => 'Nuevo', 'megamenu_ajax_load' => 'yes' ) ) ) );
	$item2 = f8_post( array( 'post_type' => 'nav_menu_item', 'post_title' => 'Vacío' ), array( '_ep_mega_menu_item_settings' => json_encode( array( 'menu_enable' => '1' ) ) ) );
	$item3 = f8_post( array( 'post_type' => 'nav_menu_item', 'post_title' => 'Normal' ), array( '_ep_mega_menu_item_settings' => json_encode( array( 'menu_enable' => '' ) ) ) );
	$item4 = f8_post( array( 'post_type' => 'nav_menu_item', 'post_title' => 'De otro menú' ), array( '_ep_mega_menu_item_settings' => json_encode( array( 'menu_enable' => '1' ) ) ) );
	$GLOBALS['menu_items'][1][ $menu ]  = array( (object) array( 'ID' => $item1, 'title' => 'Productos <em>ya</em>' ), (object) array( 'ID' => $item2, 'title' => 'Vacío' ), (object) array( 'ID' => $item3, 'title' => 'Normal' ) );
	$GLOBALS['menu_items'][1][ $menu2 ] = array( (object) array( 'ID' => $item4, 'title' => 'De otro menú' ) );
	update_option( '_ep_mega_menu_options', array( '_ep_mega_menu_settings' => array( 'menu_location_' . $menu => array( 'ep_megamenu_enabled' => '1' ), 'menu_location_' . $menu2 => array( 'ep_megamenu_enabled' => '0' ) ) ) );
	$content_data = '[{"id":"c1","elType":"container","settings":{"title":"Comillas \\" y barra \\\\"},"elements":[{"id":"w1","elType":"widget","widgetType":"heading","settings":{"title":"Panel"},"elements":[]}]}]';
	$content      = f8_post( array( 'post_type' => 'ep_megamenu_content', 'post_title' => 'bdt-ep-megamenu-content-' . $item1 ), array( '_elementor_data' => $content_data, '_elementor_version' => '3.30.0' ) );
	f8_post( array( 'post_type' => 'ep_megamenu_content', 'post_title' => 'bdt-ep-megamenu-content-' . $item4 ), array( '_elementor_data' => $content_data ) );
	$mm_doc = f8_post( array( 'post_type' => 'page', 'post_title' => 'Con mega menú' ), array(
		'_elementor_data'          => '[{"id":"n","elType":"widget","widgetType":"nav-menu","settings":{"menu":"principal"},"elements":[]}]',
		'_digitalisimo_ep_backup' => json_encode( array( 'time' => 1, 'data' => '[{"id":"n","elType":"widget","widgetType":"bdt-mega-menu","settings":{"navbar":"principal"},"elements":[]}]' ) ),
	) );
	$before   = h_snapshot();
	$analysis = Mega_Menu::analyze();
	$check( h_snapshot() === $before, 'mega menú: el análisis no escribe nada' );
	$check( array( 'plat.mega.template', 'plat.mega.empty' ) === array_column( $analysis, 0 ), 'mega menú: el análisis anuncia una plantilla y un desplegable vacío' );
	$cursor = array();
	$check( true === Mega_Menu::finish( $cursor, microtime( true ) + 30 ), 'mega menú: la etapa termina' );
	$templates = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	$check( 1 === count( $templates ), 'mega menú: una plantilla por desplegable con contenido de un menú habilitado' );
	$tpl = (int) $templates[0];
	$check( 'Mega menú: Productos ya (Principal)' === get_post( $tpl )->post_title, 'mega menú: título «Mega menú: <ítem> (<menú>)» sin etiquetas' );
	$check( 'container' === get_post_meta( $tpl, '_elementor_template_type', true ), 'mega menú: tipo según la raíz (contenedor)' );
	$check( $content_data === get_post_meta( $tpl, '_elementor_data', true ), 'mega menú: contenido copiado byte a byte (comillas y barras)' );
	$check( '' === get_post_meta( $tpl, '_elementor_conditions', true ), 'mega menú: sin condiciones (sin efecto en el front)' );
	$check( 'publish' === get_post_status( $tpl ), 'mega menú: publicada (el widget Plantilla puede elegirla)' );
	$check( 'migration' === ( get_post_meta( $tpl, '_digitalisimo_import_origin', true )['origin'] ?? '' ), 'mega menú: procedencia de Migración' );
	$check( $content_data === get_post_meta( $content, '_elementor_data', true ) && 'publish' === get_post_status( $content ), 'mega menú: el contenido original no se toca' );
	$r = Report::take();
	$rebuild = h_item( $r, 'plat.mega.rebuild', $mm_doc );
	$check( $rebuild && 'principal' === $rebuild['detail']['name'] && 1 === $rebuild['detail']['count'] && false !== strpos( $rebuild['detail']['note'], (string) $tpl ), 'mega menú: «rehacer» por documento con el menú y la plantilla' );
	$lost = h_item( $r, 'plat.mega.lost', $mm_doc );
	$check( $lost && false !== strpos( $lost['detail']['note'], 'ancho' ) && false !== strpos( $lost['detail']['note'], 'insignia' ) && false !== strpos( $lost['detail']['note'], 'carga diferida' ), 'mega menú: lo perdido (ancho, insignia, carga diferida)' );
	$check( in_array( 'plat.mega.empty', h_codes( $r ), true ) && in_array( 'plat.mega.template', h_codes( $r ), true ), 'mega menú: informe de la plantilla y del desplegable vacío' );
	$check( Report::pending( $r ), 'mega menú: deja asuntos por revisar' );
	$cursor = array();
	Mega_Menu::finish( $cursor, microtime( true ) + 30 );
	$check( 1 === count( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ), 'mega menú: volver a pulsar no duplica' );
	$rev = Mega_Menu::revert();
	$check( 'trash' === get_post_status( $tpl ) && array( 'plat.mega.reverted' ) === array_column( $rev, 0 ), 'mega menú: revertir retira la plantilla sin cambios' );
	$cursor = array();
	h_fresh_report();
	Mega_Menu::finish( $cursor, microtime( true ) + 30 );
	$tpl2 = (int) Registry::get( 'mega', (string) $item1 )['dest'];
	update_post_meta( $tpl2, '_elementor_data', '[{"id":"editado"}]' );
	$rev = Mega_Menu::revert();
	$check( 'publish' === get_post_status( $tpl2 ) && array( 'core.revert_skipped' ) === array_column( $rev, 0 ), 'mega menú: una plantilla editada no se revierte' );

	/* ================================================================== 5. Kit y ajustes de página */

	h_reset();
	h_job();
	h_fresh_report();
	$kit_fx = $fx( 'kit' );
	$kit    = f8_post( array( 'post_type' => 'elementor_library', 'post_title' => 'Kit' ), array( '_elementor_template_type' => 'kit', '_elementor_page_settings' => $kit_fx['kit'], '_elementor_css' => array( 'x' => 1 ) ) );
	update_option( 'elementor_active_kit', $kit );
	$before   = h_snapshot();
	$analysis = Kit::analyze();
	$check( h_snapshot() === $before, 'kit: el análisis no escribe nada' );
	$check( array( 'plat.kit.values', 'plat.kit.context_menu', 'plat.kit.context_menu_styles' ) === array_column( $analysis, 0 ), 'kit: el análisis anuncia valores, menú contextual y estilos' );
	$cursor = array();
	$check( true === Kit::finish( $cursor, microtime( true ) + 30 ), 'kit: la etapa termina' );
	$after = get_post_meta( $kit, '_elementor_page_settings', true );
	foreach ( array_keys( $kit_fx['kit'] ) as $key ) {
		$gone = 0 === strpos( $key, 'element_pack_' ) || 0 === strpos( $key, 'ep_context_menu_' ) || 'menus' === $key;
		$check( $gone ? ! array_key_exists( $key, $after ) : $kit_fx['kit'][ $key ] === $after[ $key ], 'kit: ' . ( $gone ? 'retirada ' : 'intacta ' ) . $key );
	}
	$check( 'selector .x:after{content:"\\f105";} .y{font-family:"A\\\\B";}' === $after['custom_css'], 'kit: barras invertidas del CSS intactas' );
	$check( 'Mi \\"sitio\\" con barra \\\\ y comillas' === $after['site_name'], 'kit: barras y comillas de un texto intactas' );
	$backup = json_decode( (string) get_post_meta( $kit, '_digitalisimo_ep_backup_page', true ), true );
	$check( is_array( $backup ) && $kit_fx['kit'] === $backup['data'], 'kit: respaldo exacto, barras incluidas' );
	$check( '' === get_post_meta( $kit, '_elementor_css', true ), 'kit: CSS del kit vaciado' );
	$r      = Report::take();
	$values = h_item( $r, 'plat.kit.values' );
	$check( $values && 5 === $values['detail']['count'] && false !== strpos( $values['detail']['note'], 'caja de luz' ) && false !== strpos( $values['detail']['note'], 'información emergente' ), 'kit: recuento de valores no vacíos por grupo' );
	$check( 2 === ( h_item( $r, 'plat.kit.context_menu' )['detail']['count'] ?? 0 ) && 2 === ( h_item( $r, 'plat.kit.context_menu_styles' )['detail']['count'] ?? 0 ), 'kit: menú contextual y estilos sin prefijo' );
	$check( false === strpos( serialize( $r ), '#000000' ) && false === strpos( serialize( $r ), '#111111' ), 'kit: el informe nunca lleva los valores' );
	$check( ! Report::pending( $r ), 'kit: sólo información' );
	$cursor = array();
	Kit::finish( $cursor, microtime( true ) + 30 );
	$check( $backup === json_decode( (string) get_post_meta( $kit, '_digitalisimo_ep_backup_page', true ), true ), 'kit: el respaldo se escribe sólo la primera vez' );
	// Documentos de 9.9.1: limpieza pura.
	$page = f8_post( array( 'post_type' => 'page', 'post_title' => 'Página 9.9.1' ), array( '_elementor_page_settings' => $kit_fx['page'] ) );
	h_fresh_report();
	$snap  = h_snapshot();
	$clean = Kit::clean_page( $kit_fx['page'], $page );
	$check( h_snapshot() === $snap, 'kit: clean_page() no escribe' );
	$check( array( 'hide_title' => 'yes', 'custom_css' => 'a{content:"\\e900"}' ) === $clean, 'kit: la página pierde sólo las claves globales' );
	$check( 1 === ( h_item( Report::take(), 'plat.kit.page_values', $page )['detail']['count'] ?? 0 ), 'kit: informe por página (sólo los valores no vacíos)' );
	$check( $kit_fx['kit'] === Kit::clean_page( $kit_fx['kit'], $kit ), 'kit: clean_page() deja el kit a su paso' );
	// Reversión por clave: lo añadido después se queda.
	$edited                             = get_post_meta( $kit, '_elementor_page_settings', true );
	$edited['element_pack_lightbox_bg'] = '#abcdef';
	update_post_meta( $kit, '_elementor_page_settings', wp_slash( $edited ) );
	$rev      = Kit::revert();
	$restored = get_post_meta( $kit, '_elementor_page_settings', true );
	$check( '#abcdef' === $restored['element_pack_lightbox_bg'], 'kit: revertir no pisa una clave que existe hoy' );
	$check( $kit_fx['kit']['element_pack_cb_color'] === $restored['element_pack_cb_color'] && $kit_fx['kit']['menus'] === $restored['menus'], 'kit: revertir devuelve lo retirado' );
	$check( $kit_fx['kit']['custom_css'] === $restored['custom_css'], 'kit: revertir conserva las barras' );
	$check( '' === get_post_meta( $kit, '_digitalisimo_ep_backup_page', true ) && array( 'plat.kit.reverted' ) === array_column( $rev, 0 ), 'kit: el respaldo propio se retira al revertir' );

	/* ================================================================== 6. Código global */

	h_reset();
	h_job();
	h_fresh_report();
	f8_user( 2, array( 'display_name' => 'Editora', 'caps' => array( 'editor' => true ) ) );
	f8_user( 3, array( 'display_name' => 'Suscriptor', 'caps' => array( 'read' => true ) ) );
	$code_fx = $fx( 'code' );
	$check( Snippets::validate_css( $code_fx['valid'] ) && Snippets::validate_css( $code_fx['footer'] ), 'código: CSS válido (con escapes y @media) pasa' );
	foreach ( $code_fx['hostile'] as $i => $css ) {
		$check( ! Snippets::validate_css( $css ), 'código: CSS hostil rechazado #' . $i );
	}
	$check( ! Snippets::validate_css( '' ) && ! Snippets::validate_css( "a{}\0" ), 'código: vacío o con NUL rechazado' );
	foreach ( array( array( 1, true ), array( 2, true ), array( 3, false ), array( 0, false ), array( 99, false ) ) as $case ) {
		h_job( array( 'user' => $case[0] ) );
		$check( $case[1] === Snippets::can_publish_css(), 'código: permiso de publicar CSS del usuario ' . $case[0] );
	}
	h_job( array( 'plugins' => array( 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'label' => 'Element Pack Pro', 'site' => true, 'network' => false ), 'bdthemes-prime-slider/bdthemes-prime-slider.php' => array( 'label' => 'Prime Slider Pro', 'site' => true, 'network' => false ) ) ) );
	$front    = f8_post( array( 'post_type' => 'page', 'post_title' => 'Portada' ) );
	$blog     = f8_post( array( 'post_type' => 'page', 'post_title' => 'Blog' ) );
	$excluded = f8_post( array( 'post_type' => 'page', 'post_title' => 'Excluida' ) );
	$product  = f8_post( array( 'post_type' => 'product', 'post_title' => 'Producto' ) );
	update_option( 'page_on_front', $front );
	update_option( 'page_for_posts', $blog );
	$conds = Snippets::conditions( array( $front, (string) $blog, $excluded, 999999, 0, 'x' ) );
	$check( array( 'include/general', 'exclude/singular/front_page', 'exclude/archive/post_archive', 'exclude/singular/page/' . $excluded ) === $conds['conditions'] && $conds['exact'], 'código: portada, entradas, página y una ID perdida descartada' );
	$check( false === Snippets::conditions( array( $product ) )['exact'], 'código: una exclusión sin condición exacta' );

	update_option( 'ep_custom_css', $code_fx['valid'] );
	update_option( 'ep_custom_css_2', $code_fx['footer'] );
	update_option( 'ep_custom_js', $code_fx['js'] );
	update_option( 'ep_custom_js_2', '   ' );
	update_option( 'ep_excluded_pages', array( $front, $excluded ) );
	update_option( 'ps_custom_css', $code_fx['hostile'][0] );
	update_option( 'ps_excluded_pages', array( $product ) );
	$before   = h_snapshot();
	$analysis = Snippets::analyze();
	$check( h_snapshot() === $before, 'código: el análisis no escribe nada' );
	$check( in_array( 'plat.code.js_lost', array_column( $analysis, 0 ), true ), 'código: el análisis destaca el JavaScript que deja de ejecutarse' );
	$cursor = array();
	$check( true === Snippets::prepare( $cursor, microtime( true ) + 30 ), 'código: la etapa termina' );
	$snips = get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	$check( 4 === count( $snips ), 'código: un fragmento por opción con código (el vacío no cuenta)' );
	$head = (int) Registry::get( 'snippet', 'ep_custom_css' )['dest'];
	$foot = (int) Registry::get( 'snippet', 'ep_custom_css_2' )['dest'];
	$js   = (int) Registry::get( 'snippet', 'ep_custom_js' )['dest'];
	$ps   = (int) Registry::get( 'snippet', 'ps_custom_css' )['dest'];
	foreach ( array( $head, $foot, $js, $ps ) as $id ) {
		$check( 'draft' === get_post_status( $id ), 'código: todo nace en borrador #' . $id );
		$check( '10' === (string) get_post_meta( $id, '_elementor_priority', true ) && 'code_snippet' === get_post_meta( $id, '_elementor_template_type', true ), 'código: prioridad 10 y tipo de documento #' . $id );
	}
	$check( 'elementor_head' === get_post_meta( $head, '_elementor_location', true ) && 'elementor_body_end' === get_post_meta( $foot, '_elementor_location', true ), 'código: cabecera y pie' );
	$check( "<style>\n" . $code_fx['valid'] . "\n</style>" === get_post_meta( $head, '_elementor_code', true ), 'código: CSS envuelto y con sus barras invertidas' );
	$check( "<script>\n" . $code_fx['js'] . "\n</script>" === get_post_meta( $js, '_elementor_code', true ), 'código: JS envuelto' );
	$check( array( 'include/general', 'exclude/singular/front_page', 'exclude/singular/page/' . $excluded ) === get_post_meta( $head, '_elementor_conditions', true ), 'código: condiciones con las páginas excluidas' );
	$check( h_tracked( $head ) && h_tracked( $foot ), 'código: el CSS publicable entra en la lista del publicador' );
	$check( ! h_tracked( $js ) && ! h_tracked( $ps ), 'código: ni el JS ni el CSS inválido entran' );
	$check( 1 === (int) get_post_meta( $head, Snippets::USER_META, true ) && 1 === (int) get_post( $head )->post_author, 'código: quién lanzó la tarea queda en el fragmento' );
	$check( false === strpos( get_post( $head )->post_title, 'Element' ) && false === strpos( get_post( $ps )->post_title, 'Prime' ), 'código: títulos sin el nombre del proveedor' );
	$r = Report::take();
	foreach ( array( 'plat.code.css_published', 'plat.code.js', 'plat.code.css_draft' ) as $code ) {
		$check( in_array( $code, h_codes( $r ), true ), 'código: informe ' . $code );
	}
	$check( false === strpos( serialize( $r ), 'js-migrado' ) && false === strpos( serialize( $r ), '<script>' ), 'código: el informe nunca lleva código' );
	$check( Snippets::revalidate( $head ) && ! Snippets::revalidate( $js ) && ! Snippets::revalidate( $ps ), 'código: revalidar acepta sólo CSS válido' );
	f8_user( 1, array( 'caps' => array( 'editor' => false, 'read' => true ), 'super' => false ) );
	$check( ! Snippets::revalidate( $head ), 'código: revalidar al publicar falla si quien lanzó perdió el permiso' );
	f8_user( 1, array( 'caps' => array( 'administrator' => true ), 'super' => true ) );
	update_post_meta( $foot, '_elementor_code', "<style>\n.a{}</style><script>x</script>\n</style>" );
	$check( ! Snippets::revalidate( $foot ), 'código: revalidar falla con código editado inseguro' );
	$cursor = array();
	Snippets::prepare( $cursor, microtime( true ) + 30 );
	$check( 4 === count( get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ), 'código: volver a pulsar no duplica' );
	// Contrato C8 (JavaScript de página, frente C).
	$page_js = Snippets::draft( 'JS de página', 'console.log(1)', 'elementor_body_end', array( 'include/singular/page/' . $excluded, 'bad condition' ), array( 'part' => 'page-js', 'source' => $excluded ) );
	$check( $page_js > 0 && 'draft' === get_post_status( $page_js ) && array( 'include/singular/page/' . $excluded ) === get_post_meta( $page_js, '_elementor_conditions', true ), 'código: draft() crea un borrador con condiciones saneadas' );
	$check( $page_js === Snippets::draft( 'JS de página', 'console.log(1)', 'elementor_body_end', array(), array( 'part' => 'page-js', 'source' => $excluded ) ), 'código: draft() no duplica' );
	$check( 0 === Snippets::draft( 'x', 'y', 'otro_sitio', array(), array() ), 'código: draft() rechaza una ubicación desconocida' );
	Context::begin( $excluded, 'editor' );
	$check( 0 === Snippets::draft( 'x', 'z()', 'elementor_head', array(), array() ), 'código: draft() no escribe en una conversión pura' );
	Context::end();
	h_job( array( 'user' => 3 ) );
	$check( 0 === Snippets::draft( 'x', 'z()', 'elementor_head', array(), array() ), 'código: draft() exige permiso de código sin filtrar' );

	// Sin permiso: el JS sólo se informa y el CSS queda en borrador.
	h_reset();
	h_job( array( 'user' => 3 ) );
	h_fresh_report();
	update_option( 'ep_custom_css', $code_fx['valid'] );
	update_option( 'ep_custom_js', $code_fx['js'] );
	$cursor = array();
	Snippets::prepare( $cursor, microtime( true ) + 30 );
	$r = Report::take();
	$check( 1 === count( get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ), 'código: sin permiso no se crea el borrador de JS' );
	$check( in_array( 'plat.code.js_lost', h_codes( $r ), true ) && in_array( 'plat.code.css_draft', h_codes( $r ), true ), 'código: sin permiso, JS informado y CSS en borrador' );
	$check( ! h_tracked( (int) Registry::get( 'snippet', 'ep_custom_css' )['dest'] ), 'código: sin permiso no se publica el CSS' );
	// Con Element Pack inactivo al empezar: nada se publica.
	h_reset();
	h_job( array( 'plugins' => array( 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'label' => 'Element Pack Pro', 'site' => false, 'network' => false ) ) ) );
	h_fresh_report();
	update_option( 'ep_custom_css', $code_fx['valid'] );
	$cursor = array();
	Snippets::prepare( $cursor, microtime( true ) + 30 );
	$check( in_array( 'plat.code.inactive', h_codes( Report::take() ), true ) && ! h_tracked( (int) Registry::get( 'snippet', 'ep_custom_css' )['dest'] ), 'código: plugin inactivo al empezar, borrador e informe' );
	// Sólo Prime Slider: lo publica la etapa `platform`.
	h_reset();
	h_job( array( 'plugins' => array( 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php' => array( 'label' => 'Prime Slider', 'site' => true, 'network' => false, 'group' => 'prime-slider' ) ) ) );
	h_fresh_report();
	update_option( 'ps_custom_css', $code_fx['footer'] );
	$cursor = array();
	Snippets::prepare( $cursor, microtime( true ) + 30 );
	$ps_only = (int) Registry::get( 'snippet', 'ps_custom_css' )['dest'];
	$check( 'draft' === get_post_status( $ps_only ) && ! h_tracked( $ps_only ), 'código: sólo Prime Slider, borrador hasta la etapa platform' );
	$cursor = array();
	Snippets::finish( $cursor, microtime( true ) + 30 );
	$check( 'publish' === get_post_status( $ps_only ) && 'done' === Registry::get( 'snippet', 'ps_custom_css' )['state'], 'código: la etapa platform lo publica revalidado' );
	// Reversión.
	update_post_meta( $ps_only, '_elementor_code', '<style>.editado{}</style>' );
	$rev = Snippets::revert();
	$check( 'publish' === get_post_status( $ps_only ) && array( 'core.revert_skipped' ) === array_column( $rev, 0 ), 'código: un fragmento editado no se revierte' );
	h_reset();
	h_job();
	update_option( 'ep_custom_css', $code_fx['valid'] );
	$cursor = array();
	Snippets::prepare( $cursor, microtime( true ) + 30 );
	$tracked_id = (int) Registry::get( 'snippet', 'ep_custom_css' )['dest'];
	$rev        = Snippets::revert();
	$check( 'trash' === get_post_status( $tracked_id ) && ! h_tracked( $tracked_id ) && null === Registry::get( 'snippet', 'ep_custom_css' ), 'código: revertir retira, saca de la lista y olvida' );
	$check( $code_fx['valid'] === get_option( 'ep_custom_css' ), 'código: la opción de origen nunca se toca' );

	/* ================================================================== 7. Barra lateral */

	h_reset();
	h_job();
	h_fresh_report();
	$side_fx = $fx( 'sidebar' );
	foreach ( $side_fx as $key => $value ) {
		if ( '_' !== $key ) {
			update_option( $key, json_decode( str_replace( '"TPL"', '"77"', json_encode( $value ) ), true ) );
		}
	}
	$check( 3 === count( Sidebar::placed() ), 'barra lateral: tres instancias colocadas (también la inactiva)' );
	$check( array( array( 'plat.sidebar', array( 'count' => 3 ) ) ) === Sidebar::analyze(), 'barra lateral: el análisis anuncia la sustitución' );
	$orig_sidebars = get_option( 'sidebars_widgets' );
	$out           = Sidebar::before_deactivate();
	$check( array( 'plat.sidebar.inactive' ) === array_column( $out, 0 ) && $orig_sidebars === get_option( 'sidebars_widgets' ), 'barra lateral: sin Element Pack cargado no se sustituye (no se veía)' );
	eval( 'namespace ElementPack; class Element_Pack_Loader {}' ); // phpcs:ignore -- desde aquí, Element Pack «cargado».
	$check( Inventory::ep_loaded(), 'Element Pack simulado como cargado' );
	$cursor = array();
	Sidebar::prepare( $cursor, microtime( true ) + 30 );
	$backup = get_option( Sidebar::BACKUP );
	$check( is_array( $backup ) && $orig_sidebars === $backup['sidebars'] && $side_fx['widget_elementor-library'] == $backup['new'] && ! f8_autoload( Sidebar::BACKUP ), 'barra lateral: respaldo previo sin carga automática' );
	$out      = Sidebar::before_deactivate();
	$sidebars = get_option( 'sidebars_widgets' );
	$library  = get_option( 'widget_elementor-library' );
	$check( array( 'search-2', 'elementor-library-4', 'elementor-library-5' ) === $sidebars['sidebar-1'] && array( 'elementor-library-3' ) === $sidebars['wp_inactive_widgets'], 'barra lateral: sustituidas en su sitio, con números libres' );
	$check( 3 === $sidebars['array_version'] && array() === $sidebars['sidebar-2'], 'barra lateral: el resto de las barras intactas' );
	$check( array( 'title' => 'Ya estaba', 'template_id' => '9' ) === $library[2], 'barra lateral: la instancia existente no se pisa' );
	$check( array( 'title' => 'Promo <b>nueva</b>', 'template_id' => '77' ) === $library[4] && array( 'title' => 'Con \\ barra', 'template_id' => '77' ) === $library[5] && 'Inactivo' === $library[3]['title'], 'barra lateral: mismas claves y valores (también con barra invertida)' );
	$check( 1 === $library['_multiwidget'], 'barra lateral: widget de varias instancias' );
	$check( 3 === count( get_option( 'widget_elementortemplate_widget' ) ) - 1, 'barra lateral: las instancias originales no se tocan' );
	$check( array( 'plat.sidebar' ) === array_column( $out, 0 ) && 3 === $out[0][1]['count'], 'barra lateral: devuelve su código' );
	$again = Sidebar::before_deactivate();
	$check( array() === $again && $library === get_option( 'widget_elementor-library' ), 'barra lateral: sin colocar no hace nada otra vez' );
	// Reversión: cada una vuelve si sigue en su sitio; la instancia editada se queda.
	$library[4]['title'] = 'Editado';
	update_option( 'widget_elementor-library', $library );
	$rev  = Sidebar::revert();
	$side = get_option( 'sidebars_widgets' );
	$lib  = get_option( 'widget_elementor-library' );
	$check( $orig_sidebars === $side, 'barra lateral: revertir devuelve las barras' );
	$check( ! isset( $lib[3] ) && ! isset( $lib[5] ) && isset( $lib[4] ) && isset( $lib[2] ), 'barra lateral: se retiran sólo las instancias sin cambios' );
	$check( false === get_option( Sidebar::BACKUP ) && array( 'plat.sidebar.reverted' ) === array_column( $rev, 0 ), 'barra lateral: respaldo retirado al revertir' );

	/* ================================================================== 8. Muestras de variación */

	$sw_fx = $fx( 'swatches' );
	$setup = static function ( array $settings ) use ( $sw_fx ) {
		h_reset();
		h_job();
		h_fresh_report();
		$GLOBALS['wp']['term_meta'] = array();
		$GLOBALS['h_term_writes']   = array();
		$img                        = f8_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'guid' => 'https://s1.test/lona.png', 'post_mime_type' => 'image/png' ) );
		$GLOBALS['wc_attributes']   = array();
		$ids                        = array();
		foreach ( $sw_fx['attributes'] as $attribute ) {
			$GLOBALS['wc_attributes'][] = (object) array( 'attribute_name' => $attribute['attribute_name'], 'attribute_type' => $attribute['attribute_type'] );
			foreach ( $attribute['terms'] as $term ) {
				$id                   = wp_insert_term( $term['name'], 'pa_' . $attribute['attribute_name'] )['term_id'];
				$ids[ $term['name'] ] = $id;
				foreach ( $term['meta'] as $k => $v ) {
					$GLOBALS['wp']['term_meta'][1][ $id ][ $k ] = 'ATT_IMG' === $v ? (string) $img : $v;
				}
				foreach ( $term['own'] ?? array() as $k => $v ) {
					$GLOBALS['wp']['term_meta'][1][ $id ][ $k ] = $v;
				}
			}
		}
		update_option( 'element_pack_other_settings', $settings );
		f8_post( array( 'post_type' => 'product' ), array( 'ep_variation_swatches' => array( 'pa_tono' => array( 'type' => 'color' ) ) ) );
		return array( $ids, $img );
	};
	list( $terms_ids, $img ) = $setup( $sw_fx['settings'] );
	$attr_before = serialize( $GLOBALS['wc_attributes'] );
	$before      = h_snapshot();
	$analysis    = Swatches::analyze();
	$check( h_snapshot() === $before, 'muestras: el análisis no escribe nada' );
	$check( array( 'plat.swatch.attribute' ) === array_column( $analysis, 0 ) && 3 === $analysis[0][1]['count'] && 'tono, talla, foto' === $analysis[0][1]['note'], 'muestras: sólo atributos con metas del origen (ni el ajeno ni el desplegable)' );
	$cursor = array();
	Swatches::finish( $cursor, microtime( true ) + 30 );
	$data_class = 'Digitalisimo\\Elements\\Woo\\Swatches\\Data';
	$check( array( 'type' => 'visual', 'shape' => 'rounded', 'size' => 64 ) === $data_class::attribute( 'pa_tono' ), 'muestras: color → visual, cuadrado → redondeado, tamaño al máximo' );
	$check( array( 'type' => 'label', 'shape' => 'pill', 'size' => 64 ) === $data_class::attribute( 'pa_talla' ), 'muestras: botón → etiqueta (píldora)' );
	$check( 'visual' === $data_class::attribute( 'pa_foto' )['type'], 'muestras: imagen → visual' );
	$check( 'select' === $data_class::attribute( 'pa_ajeno' )['type'] && 'select' === $data_class::attribute( 'pa_material' )['type'], 'muestras: sin metas del origen no se convierte' );
	$check( '#ff0000' === get_term_meta( $terms_ids['Rojo'], 'color', true ), 'muestras: color hexadecimal copiado a la meta de WooCommerce' );
	$check( '' === get_term_meta( $terms_ids['Azul'], 'color', true ), 'muestras: un color no hexadecimal no se copia' );
	$check( '#00aa00' === get_term_meta( $terms_ids['Verde'], 'color', true ), 'muestras: un término con color propio no se pisa' );
	$check( (string) $img === (string) get_term_meta( $terms_ids['Lona'], 'image', true ), 'muestras: imagen copiada a la meta image' );
	$check( '#000000' === get_term_meta( $terms_ids['Negro'], 'color', true ) && '' === get_term_meta( $terms_ids['S'], 'color', true ), 'muestras: nada más se escribe' );
	$check( $attr_before === serialize( $GLOBALS['wc_attributes'] ), 'muestras: attribute_type nunca se escribe' );
	$r = Report::take();
	foreach ( array( 'plat.swatch.attribute', 'plat.swatch.term', 'plat.swatch.kept', 'plat.swatch.color_invalid', 'plat.swatch.label_lost', 'plat.swatch.shape', 'plat.swatch.size', 'plat.swatch.products', 'plat.swatch.options' ) as $code ) {
		$check( in_array( $code, h_codes( $r ), true ), 'muestras: informe ' . $code );
	}
	$check( 2 === ( h_item( $r, 'plat.swatch.label_lost' )['detail']['count'] ?? 0 ) && 'tono, talla' === h_item( $r, 'plat.swatch.label_lost' )['detail']['note'], 'muestras: un elemento por código con los atributos en la nota (Verde lima, Mediana)' );
	$check( 3 === ( h_item( $r, 'plat.swatch.attribute' )['detail']['count'] ?? 0 ) && 1 === count( array_keys( h_codes( $r ), 'plat.swatch.attribute', true ) ), 'muestras: los atributos convertidos en un solo elemento' );
	$rows = Registry::all( 'swatch' );
	$check( isset( $rows['attr:tono'], $rows['attr:talla'], $rows[ 'term:' . $terms_ids['Rojo'] . ':color' ], $rows[ 'term:' . $terms_ids['Lona'] . ':image' ] ), 'muestras: cada escritura anotada' );
	$cursor = array();
	Swatches::finish( $cursor, microtime( true ) + 30 );
	$check( 2 === count( $GLOBALS['h_term_writes'] ), 'muestras: volver a pulsar no escribe otra vez' );
	update_term_meta( $terms_ids['Rojo'], 'color', '#990000' );
	$rev = Swatches::revert();
	$check( '#990000' === get_term_meta( $terms_ids['Rojo'], 'color', true ) && in_array( 'core.revert_skipped', array_column( $rev, 0 ), true ), 'muestras: un color editado no se revierte' );
	$check( '' === get_term_meta( $terms_ids['Lona'], 'image', true ) && 'select' === $data_class::attribute( 'pa_tono' )['type'], 'muestras: revertir retira lo escrito sin cambios' );
	$check( '#00aa00' === get_term_meta( $terms_ids['Verde'], 'color', true ), 'muestras: revertir no toca lo que ya era del sitio' );
	// Entrada existente: no se pisa.
	list( $terms_ids ) = $setup( $sw_fx['settings'] );
	$data_class::put( 'tono', array( 'type' => 'label', 'shape' => 'pill', 'size' => 30 ) );
	$cursor = array();
	Swatches::finish( $cursor, microtime( true ) + 30 );
	$check( array( 'type' => 'label', 'shape' => 'pill', 'size' => 30 ) === $data_class::attribute( 'pa_tono' ) && in_array( 'plat.swatch.kept', h_codes( Report::take() ), true ), 'muestras: una entrada que ya existe no se pisa' );
	// Otro plugin de muestras, muestras apagadas o Element Pack inactivo: nada se escribe.
	list( $terms_ids ) = $setup( $sw_fx['settings'] );
	update_option( 'active_plugins', array( 'variation-swatches-for-woocommerce/variation-swatches-for-woocommerce.php' ) );
	$cursor = array();
	Swatches::finish( $cursor, microtime( true ) + 30 );
	$check( array() === $GLOBALS['h_term_writes'] && false === get_option( 'digitalisimo_elements_swatches' ) && in_array( 'plat.swatch.other_plugin', h_codes( Report::take() ), true ), 'muestras: con otro plugin de muestras no se convierte' );
	list( $terms_ids ) = $setup( array( 'variation-swatches' => 'off' ) + $sw_fx['settings'] );
	$cursor = array();
	Swatches::finish( $cursor, microtime( true ) + 30 );
	$check( array() === $GLOBALS['h_term_writes'] && in_array( 'plat.swatch.disabled', h_codes( Report::take() ), true ), 'muestras: apagadas en el origen, no se convierten' );
	list( $terms_ids ) = $setup( $sw_fx['settings'] );
	h_job( array( 'plugins' => array( 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'label' => 'Element Pack Pro', 'site' => false, 'network' => false ) ) ) );
	$cursor = array();
	Swatches::finish( $cursor, microtime( true ) + 30 );
	$check( array() === $GLOBALS['h_term_writes'] && in_array( 'plat.swatch.inactive', h_codes( Report::take() ), true ), 'muestras: Element Pack inactivo al empezar, sólo informe' );

	/* ================================================================== 9. Etiquetas dinámicas */

	h_reset();
	h_job();
	h_fresh_report();
	$tag = static function ( $name, array $settings = array(), $id = 'a1b2c3d' ) {
		return sprintf( '[elementor-tag id="%s" name="%s" settings="%s"]', $id, $name, urlencode( json_encode( $settings, JSON_FORCE_OBJECT ) ) );
	};
	$parsed = Dynamic_Tags::parse( $tag( 'element-pack-post-title', array( 'before' => 'Antes: ' ) ) );
	$check( array( 'id' => 'a1b2c3d', 'name' => 'element-pack-post-title', 'settings' => array( 'before' => 'Antes: ' ) ) === $parsed, 'etiquetas: lectura de una etiqueta' );
	$check( null === Dynamic_Tags::parse( '[elementor-tag id="x" name="a" settings="%7B"] y más' ) && null === Dynamic_Tags::parse( 'texto' ), 'etiquetas: lo mal formado no se lee' );
	$cases = array(
		array( 'element-pack-post-title', array( 'before' => 'A', 'after' => 'B', 'fallback' => 'C', 'ep_post_type' => 'current' ), 'post-title', array( 'before' => 'A', 'after' => 'B', 'fallback' => 'C' ) ),
		array( 'element-pack-post-title', array( 'ep_word_limit' => '0' ), 'post-title', array() ),
		array( 'element-pack-post-title', array( 'ep_word_limit' => '5' ), null, null ),
		array( 'element-pack-post-title', array( 'ep_post_type' => 'selected', 'ep_posts_selected_id_legacy' => '12' ), null, null ),
		array( 'element-pack-site-title', array(), 'site-title', array() ),
		array( 'element-pack-site-tagline', array( 'after' => '!' ), 'site-tagline', array( 'after' => '!' ) ),
		array( 'element-pack-archive-title', array( 'ep_include_context' => 'yes' ), 'archive-title', array() ),
		array( 'element-pack-archive-title', array( 'ep_include_context' => '' ), null, null ),
		array( 'element-pack-archive-description', array(), 'archive-description', array() ),
		array( 'element-pack-post-id', array(), 'post-id', array() ),
		array( 'element-pack-post-author', array( 'ep_author_data_type' => 'display_name' ), 'author-name', array() ),
		array( 'element-pack-post-author', array( 'ep_author_data_type' => 'user_email' ), null, null ),
		array( 'element-pack-post-url', array(), 'post-url', array() ),
		array( 'element-pack-site-url', array( 'ep_url_type' => 'home' ), 'site-url', array() ),
		array( 'element-pack-site-url', array( 'ep_url_type' => 'admin' ), null, null ),
		array( 'element-pack-archive-url', array(), 'archive-url', array() ),
		array( 'element-pack-post-featured-image', array( 'fallback' => array( 'id' => '5', 'url' => 'https://s1.test/a.png' ) ), 'post-featured-image', array( 'fallback' => array( 'id' => '5', 'url' => 'https://s1.test/a.png' ) ) ),
		array( 'element-pack-site-logo', array( 'fallback' => array( 'id' => '', 'url' => '' ) ), 'site-logo', array() ),
		array( 'element-pack-site-logo', array( 'fallback' => array( 'id' => '5', 'url' => 'https://s1.test/a.png' ) ), null, null ),
		array( 'element-pack-term-meta', array( 'ep_term_meta_key' => 'x' ), null, null ),
		array( 'element-pack-acf-text', array(), null, null ),
		array( 'element-pack-post-title', array( 'desconocido' => 'x' ), null, null ),
	);
	foreach ( $cases as $i => $case ) {
		$out = Dynamic_Tags::translate( Dynamic_Tags::parse( $tag( $case[0], $case[1] ) ) );
		if ( null === $case[2] ) {
			$check( null === $out, 'etiquetas: sin equivalente exacta #' . $i . ' ' . $case[0] );
			continue;
		}
		$back = Dynamic_Tags::parse( (string) $out );
		$check( $back && 'a1b2c3d' === $back['id'] && $case[2] === $back['name'] && $case[3] == $back['settings'], 'etiquetas: equivalente exacta #' . $i . ' ' . $case[0] . ' → ' . $case[2] );
	}
	$tags_doc = f8_post( array( 'post_type' => 'page', 'post_title' => 'Etiquetas', 'post_status' => 'publish' ) );
	$element  = array(
		'id'         => 'e1',
		'elType'     => 'widget',
		'widgetType' => 'heading',
		'settings'   => array(
			'title'       => 'Texto fijo',
			'__dynamic__' => array( 'title' => $tag( 'element-pack-post-title' ), 'link' => $tag( 'element-pack-term-meta', array( 'ep_term_meta_key' => 'k' ) ), 'other' => $tag( 'post-date' ) ),
			'items'       => array( array( '_id' => 'r', '__dynamic__' => array( 'text' => $tag( 'element-pack-site-title' ) ) ) ),
		),
	);
	Context::begin( $tags_doc, 'run' );
	$snap = h_snapshot();
	$out  = Dynamic_Tags::rewrite( $element );
	Context::end();
	$check( h_snapshot() === $snap, 'etiquetas: reescribir no escribe nada' );
	$check( 'post-title' === Dynamic_Tags::parse( $out['settings']['__dynamic__']['title'] )['name'], 'etiquetas: reescrita en __dynamic__' );
	$check( ! isset( $out['settings']['__dynamic__']['link'] ) && 'Texto fijo' === $out['settings']['title'], 'etiquetas: la que no tiene equivalente se quita y queda el valor fijo' );
	$check( $tag( 'post-date' ) === $out['settings']['__dynamic__']['other'], 'etiquetas: las del constructor no se tocan' );
	$check( 'site-title' === Dynamic_Tags::parse( $out['settings']['items'][0]['__dynamic__']['text'] )['name'], 'etiquetas: también en filas de repetidor' );
	$r = Report::take();
	$check( null !== h_item( $r, 'plat.tags.converted', $tags_doc ), 'etiquetas: convertidas, una vez por documento' );
	$dropped = h_item( $r, 'plat.tags.dropped', $tags_doc );
	$check( $dropped && 1 === $dropped['detail']['count'] && 'heading #e1' === $dropped['detail']['element'] && 'term-meta' === $dropped['detail']['note'], 'etiquetas: quitadas, por elemento con su nombre' );
	$check( ! empty( $dropped['review'] ) && 'review' === Report::section_of( $dropped ), 'etiquetas: quitadas en un documento publicado piden revisión' );
	$check( array( 'converted' => 2, 'dropped' => 1 ) === Dynamic_Tags::describe( array( $element ) ), 'etiquetas: el análisis cuenta convertibles y no' );
	h_fresh_report();
	$tree = array( array( 'id' => 'c', 'elType' => 'container', 'settings' => array(), 'elements' => array( $element, $element ) ) );
	Context::begin( $tags_doc, 'run' );
	$tree_out = Dynamic_Tags::rewrite_elements( $tree );
	Context::end();
	$r = Report::take();
	$check( 'post-title' === Dynamic_Tags::parse( $tree_out[0]['elements'][1]['settings']['__dynamic__']['title'] )['name'], 'etiquetas: rewrite_elements() recorre el árbol' );
	$check( 1 === count( array_keys( h_codes( $r ), $tags_doc . ':plat.tags.converted', true ) ) && 1 === count( array_keys( h_codes( $r ), $tags_doc . ':plat.tags.dropped', true ) ) && 2 === h_item( $r, 'plat.tags.dropped', $tags_doc )['detail']['count'], 'etiquetas: el mismo elemento dos veces se cuenta en un solo elemento del informe' );
	$plain_tree = array( array( 'id' => 'p', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( '__dynamic__' => array( 'title' => $tag( 'post-title' ) ) ) ) );
	$check( $plain_tree === Dynamic_Tags::rewrite_elements( $plain_tree ) && $plain_tree === Icons::rewrite_elements( $plain_tree ), 'etiquetas e iconos: un documento sin nada del origen sale igual' );
	$only = array( '__dynamic__' => array( 'title' => $tag( 'element-pack-acf-text' ) ) );
	$check( array() === Dynamic_Tags::rewrite_settings( $only ), 'etiquetas: un __dynamic__ vacío desaparece' );
	$check( $element['settings']['items'] === Dynamic_Tags::rewrite( array( 'settings' => array( 'items' => $element['settings']['items'] ) ) )['settings']['items'] || true, 'etiquetas: rewrite() acepta elementos sin tipo' );
	$broken = Dynamic_Tags::rewrite_settings( array( '__dynamic__' => array( 'title' => '[elementor-tag id="1" name="element-pack-post-title" settings="%7Broto"]' ) ) );
	$check( array() === $broken, 'etiquetas: una etiqueta ilegible del origen se quita (fallar cerrado)' );

	/* ================================================================== 10. Inventario: contraseñas, claves, residuos */

	h_reset();
	h_job();
	h_fresh_report();
	$secret = 'SECRETO-NO-MOSTRAR-' . md5( 'x' );
	f8_user( 2, array( 'display_name' => 'Ana', 'caps' => array( 'administrator' => true ) ) );
	f8_user( 3, array( 'display_name' => '<img src=x onerror=alert(1)>', 'caps' => array( 'editor' => true ) ) );
	$GLOBALS['wp']['user_meta'][2]['_application_passwords'] = array(
		array( 'uuid' => 'uuid-' . $secret, 'app_id' => '', 'name' => 'Element Pack AI — Claude', 'password' => '$P$' . $secret, 'created' => 1767225600, 'last_used' => 1769904000, 'last_ip' => '203.0.113.9' ),
		array( 'uuid' => 'u2', 'name' => 'Otra aplicación', 'password' => '$P$otra', 'created' => 1, 'last_used' => null ),
	);
	$GLOBALS['wp']['user_meta'][3]['_application_passwords'] = array(
		array( 'uuid' => 'u3', 'name' => 'Element Pack AI <script>alert(2)</script>', 'password' => '$P$' . $secret, 'created' => 1767225600, 'last_used' => null ),
	);
	update_option( 'element_pack_api_settings', array( 'google_map_key' => $secret, 'mailchimp_api_key' => $secret . '-us1', 'recaptcha_secret_key' => $secret, 'disqus_user_name' => '', 'contact_form_email' => 'a@b.test', '<b>raro</b>' => 'x' ) );
	update_option( 'element_pack_google_oauth_data', array( 'access_token' => $secret ) );
	update_option( 'element_pack_ai_settings', array( 'provider' => 'x' ) );
	update_option( 'element_pack_shape_divider_copied', '1' );
	update_option( 'element_pack_other_settings', array( 'smooth-scroller' => 'on' ) );
	update_option( 'widget_text', array( 2 => array( 'text' => 'Hola [ep_button text="x"]' ), '_multiwidget' => 1 ) );
	update_option( 'element_pack_license_key', $secret );
	$sc_doc = f8_post( array( 'post_type' => 'page', 'post_title' => 'Con shortcode', 'post_content' => 'Antes [ep_accordion] después' ) );
	f8_post( array( 'post_type' => 'revision', 'post_content' => '[ep_x]' ) );
	f8_post( array( 'post_type' => 'page', 'post_status' => 'trash', 'post_content' => '[ep_x]' ) );
	f8_post( array( 'post_type' => 'page', 'post_title' => 'Snapshot' ), array( '_ep_ai_undo_snapshot' => '[]' ) );
	$GLOBALS['wpdb']->tables = array( 'wp_ep_template_library_post' );
	wp_mkdir_p( wp_upload_dir()['basedir'] . '/element-pack/minified' );
	$before = h_snapshot();
	$items  = Inventory::analyze();
	$check( h_snapshot() === $before, 'inventario: el análisis no escribe nada' );
	$codes = array_column( $items, 0 );
	foreach ( array( 'plat.app_passwords', 'plat.api_keys', 'plat.api_oauth', 'plat.ai_settings', 'plat.ai_snapshots', 'plat.shortcodes', 'plat.shortcodes.widgets', 'plat.core_files', 'plat.smooth_scroll', 'plat.residue' ) as $code ) {
		$check( in_array( $code, $codes, true ), 'inventario: ' . $code );
	}
	$check( 2 === count( array_keys( $codes, 'plat.app_passwords', true ) ), 'inventario: una fila por usuario con contraseñas de la IA' );
	$check( false === strpos( serialize( $items ), $secret ) && false === strpos( serialize( $items ), '203.0.113.9' ), 'inventario: ningún valor, UUID ni IP' );
	$check( false === strpos( serialize( $items ), 'Otra aplicación' ), 'inventario: sólo las contraseñas de la IA' );
	$keys_item = $items[ array_search( 'plat.api_keys', $codes, true ) ];
	$check( 'contact_form_email, google_map_key, mailchimp_api_key, raro, recaptcha_secret_key' === $keys_item[1]['note'] && 5 === $keys_item[1]['count'], 'inventario: sólo nombres de claves no vacías, saneados' );
	$sc = array_values( array_filter( $items, static function ( $i ) { return 'plat.shortcodes' === $i[0]; } ) );
	$check( 1 === count( $sc ) && $sc_doc === $sc[0][2], 'inventario: shortcodes por documento (sin revisiones ni papelera)' );
	$residue = $items[ array_search( 'plat.residue', $codes, true ) ];
	$check( false !== strpos( $residue[1]['note'], 'tablas' ) && false !== strpos( $residue[1]['note'], 'uploads/element-pack' ) && false !== strpos( $residue[1]['note'], 'licencia' ), 'inventario: residuos por nombre' );
	$cursor = array();
	Inventory::finish( $cursor, microtime( true ) + 30 );
	$r    = Report::take();
	$html = Report::render( $r, 'site' );
	$check( null !== h_item( $r, 'plat.shortcodes', $sc_doc ), 'inventario: shortcodes en el informe del documento' );
	$check( false === strpos( $html, $secret ) && false === strpos( serialize( $r ), $secret ), 'inventario: ningún secreto en el informe ni en su HTML' );
	$check( false === stripos( $html, '<img' ) && false === stripos( $html, '<script' ), 'inventario: nombres escapados en el informe' );
	$check( false !== strpos( $html, 'Ana: Element Pack AI — Claude' ), 'inventario: un administrador del sitio individual ve los nombres' );
	$rows = Inventory::app_passwords();
	$list = Inventory::app_passwords_html( $rows );
	$check( false === stripos( $list, '<img' ) && false === stripos( $list, '<script' ) && false !== strpos( $list, '#3' ) && false !== strpos( $list, 'Element Pack AI alert(2)' ), 'inventario: la lista de contraseñas escapa usuario y nombre' );
	$check( false !== strpos( $list, esc_url( admin_url( 'user-edit.php?user_id=2#application-passwords-section' ) ) ), 'inventario: enlace al perfil del sitio en curso' );
	$check( false === strpos( $list, $secret ) && false !== strpos( $list, '2026-01-01' ), 'inventario: fechas sí, valores no' );

	// Red: miembros del sitio, nombres sólo para un superadministrador y una vez por usuario en la red.
	h_reset( array( 'multisite' => true, 'sites' => 2 ) );
	h_fresh_report();
	f8_user( 2, array( 'display_name' => 'Ana', 'caps' => array( 'administrator' => true ), 'blogs' => array( 1, 2 ) ) );
	f8_user( 4, array( 'display_name' => 'Ajena', 'caps' => array( 'administrator' => true ), 'blogs' => array( 2 ) ) );
	$GLOBALS['wp']['user_meta'][2]['_application_passwords'] = array( array( 'name' => 'Element Pack AI — Uno', 'password' => $secret, 'created' => 1767225600 ) );
	$GLOBALS['wp']['user_meta'][4]['_application_passwords'] = array( array( 'name' => 'Element Pack AI — Dos', 'password' => $secret, 'created' => 1767225600 ) );
	$site_rows = Inventory::app_passwords();
	$check( array( 2 ) === array_column( $site_rows, 'user_id' ), 'red: sólo usuarios miembros del sitio' );
	$network_rows = Inventory::network_app_passwords();
	$check( array( 2, 4 ) === array_column( $network_rows, 'user_id' ), 'red: una vez por usuario en la red' );
	$net_list = Inventory::app_passwords_html( $network_rows, 'network' );
	$check( false !== strpos( $net_list, esc_url( network_admin_url( 'user-edit.php?user_id=4#application-passwords-section' ) ) ), 'red: enlace de la administración de red' );
	foreach ( Inventory::collect() as $pair ) {
		Report::site( $pair[0], $pair[1] );
	}
	$r = Report::take();
	wp_set_current_user( 2 );
	$admin_html = Report::render( $r, 'site' );
	$check( false === strpos( $admin_html, 'Element Pack AI — Uno' ) && false === strpos( $admin_html, 'Ana:' ), 'red: el administrador del sitio no ve los nombres' );
	$check( false === strpos( Inventory::app_passwords_html( $site_rows ), 'Ana' ) && false !== strpos( Inventory::app_passwords_html( $site_rows ), '1' ), 'red: el administrador del sitio ve el recuento' );
	$check( array() === Inventory::network_app_passwords(), 'red: la lista de red sólo para un superadministrador' );
	wp_set_current_user( 1 );
	$check( false !== strpos( Report::render( $r, 'site' ), 'Ana: Element Pack AI — Uno' ), 'red: un superadministrador ve los nombres' );
	$check( false === strpos( Report::render( $r, 'site' ), $secret ), 'red: nunca el valor' );
	// CSS en una red: sólo un superadministrador.
	update_option( Inventory::JOB, array( 'user' => 2 ), false );
	$check( false === Snippets::can_publish_css(), 'red: un administrador de sitio no publica CSS' );
	update_option( Inventory::JOB, array( 'user' => 1 ), false );
	$check( true === Snippets::can_publish_css(), 'red: un superadministrador sí' );

	/* ================================================================== 11. Papelera desactivada: borrador */

	h_reset();
	h_job();
	h_fresh_report();
	define( 'EMPTY_TRASH_DAYS', 0 );
	update_option( 'ep_custom_css', $code_fx['valid'] );
	$cursor = array();
	Snippets::prepare( $cursor, microtime( true ) + 30 );
	$sn = (int) Registry::get( 'snippet', 'ep_custom_css' )['dest'];
	wp_publish_post( $sn );
	Snippets::revert();
	$check( 'draft' === get_post_status( $sn ) && ! in_array( array( 1, $sn ), $GLOBALS['wp']['trashed'], true ), 'con EMPTY_TRASH_DAYS = 0 lo creado vuelve a borrador, nunca a la papelera' );

	h_rmrf( WP_CONTENT_DIR );
	f8_done( 'migration-platform', 220 );
}
