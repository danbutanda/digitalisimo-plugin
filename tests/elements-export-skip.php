<?php
/**
 * F8, frente D: los kits (y cualquier WXR) no exportan las metas de DIGITALÍSIMO Migración (V.11, contrato C17; C2-25).
 *
 * - `Export_Skip` engancha un solo callback de `wxr_export_skip_postmeta` y respeta un `true` previo, como
 *   `Meta_Blacklist::skip()` de K y `F7b\Usages::skip_export()`, sin tocar la lista negra de K.
 * - El bucle de metas del exportador del núcleo (el mismo `apply_filters` que `wp-exporter.php`) con los tres callbacks
 *   que hay durante la exportación de un kit: quedan fuera las metas de Migración, las de la lista negra y los usos de
 *   F7b; salen las demás.
 * - Los metadatos de documento del núcleo ya omiten las metas protegidas (`_*`), así que el WXR era la única vía.
 * - Toda meta `_digitalisimo_ep_*` o `_digitalisimo_migration_*` que el código de Migración escriba queda cubierta.
 * - Los nombres pasan la guarda de menciones del paquete de Elements.
 * Uso: node <php-wasm-cli> tests/elements-export-skip.php
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$repo = dirname( __DIR__ );
	$root = $repo . '/digitalisimo-elements';
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );
	$checks = 0;
	function check_skip( $ok, $message ) { global $checks; if ( ! $ok ) { throw new \RuntimeException( $message ); } $checks++; }

	$GLOBALS['wp_filters'] = array();
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( $callback, $args ); return true; }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { return add_filter( $hook, $callback, $priority, $args ); }
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['wp_filters'][ $hook ][ $priority ] ?? array() as $i => $entry ) {
			if ( $entry[0] === $callback ) { unset( $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ] ); return true; }
		}
		return false;
	}
	function apply_filters( $hook, $value, ...$args ) {
		$callbacks = $GLOBALS['wp_filters'][ $hook ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
		return $value;
	}
	function is_protected_meta( $key, $type = '' ) { return '' !== $key && '_' === $key[0]; }

	$file = $root . '/modules/digitalisimo-widgets/class-export-skip.php';
	require $file;
	require $root . '/modules/digitalisimo-import/kits/export/class-meta-blacklist.php';
	require $root . '/modules/digitalisimo-f7b/class-usages.php';
	$S  = '\Digitalisimo\Elements\Export_Skip';
	$MB = '\Digitalisimo\Elements\Import\Kits\Export\Meta_Blacklist';
	$U  = '\Digitalisimo\Elements\F7b\Usages';

	// 1. Arranque: un solo callback, sólo en las metas de entradas.
	$S::init();
	$S::init();
	check_skip( array( 'wxr_export_skip_postmeta' ) === array_keys( $GLOBALS['wp_filters'] ) && 1 === count( $GLOBALS['wp_filters']['wxr_export_skip_postmeta'][10] ) && 2 === $GLOBALS['wp_filters']['wxr_export_skip_postmeta'][10][0][1], 'Un solo callback de wxr_export_skip_postmeta (10, dos argumentos), aunque init() se llame dos veces.' );
	check_skip( str_contains( file_get_contents( $root . '/modules/digitalisimo-widgets/class-registry.php' ), "'class-export-skip.php' => Export_Skip::class" ), 'Arranca desde la lista de Widget_Registry::init() (contrato C17).' );

	// 2. Decisión.
	$migration = array( '_digitalisimo_ep_backup', '_digitalisimo_ep_backup_page', '_digitalisimo_ep_backup_f8', '_digitalisimo_ep_f8', '_digitalisimo_migration_demo', '_digitalisimo_migration_origin' );
	foreach ( $migration as $key ) {
		check_skip( true === $S::skip( false, $key ), $key . ' no sale en el WXR.' );
	}
	foreach ( array( '_thumbnail_id', '_wp_page_template', 'precio', '_digitalisimo_uses', '_digitalisimo_import_origin', '_digitalisimo_ep_backupx', '_digitalisimo_migrationx', 'digitalisimo_migration_demo', '_DIGITALISIMO_EP_F8' ) as $key ) {
		check_skip( false === $S::skip( false, $key ), $key . ' no es suya: la decisión queda para los demás callbacks.' );
	}
	check_skip( true === $S::skip( true, '_thumbnail_id' ) && true === $S::skip( 1, 'precio' ), 'Respeta un true previo (como Meta_Blacklist::skip()).' );
	check_skip( false === $S::skip( false, null ) && false === $S::skip( false, array( '_digitalisimo_ep_f8' ) ) && false === $S::skip( false ), 'Una clave que no es texto no decide nada.' );

	// 3. Exportación de un kit: el bucle de metas del exportador del núcleo con los callbacks de K y de F7b.
	$exporter = file_get_contents( $root . '/elementor/core/utils/import-export/wp-exporter.php' );
	check_skip( str_contains( $exporter, "apply_filters( 'wxr_export_skip_postmeta', false, \$meta->meta_key, \$meta )" ), 'El exportador del núcleo decide cada meta con wxr_export_skip_postmeta (false, clave, meta).' );
	$wp_content = file_get_contents( $root . '/modules/digitalisimo-import/kits/export/class-wp-content.php' );
	check_skip( str_contains( $wp_content, "add_filter( 'wxr_export_skip_postmeta', \$skip, 10, 2 )" ) && str_contains( $wp_content, 'array( Meta_Blacklist::class, \'skip\' )' ), 'K engancha Meta_Blacklist::skip() durante la exportación del contenido.' );
	$U::init();
	add_filter( 'wxr_export_skip_postmeta', array( $MB, 'skip' ), 10, 2 ); // Lo que hace Wp_Content::export() de K.
	$metas = array(
		'_elementor_data' => '[]', '_elementor_page_settings' => 'a:0:{}', '_thumbnail_id' => '12', '_wp_page_template' => 'default', 'precio' => '10',
		'_digitalisimo_uses' => 't:5', '_digitalisimo_ep_backup' => '[{"widgetType":"x"}]', '_digitalisimo_ep_backup_page' => 'a:1:{s:4:"code";s:21:"<script>x()</script>";}',
		'_digitalisimo_ep_backup_f8' => '[]', '_digitalisimo_ep_f8' => '{"v":1}', '_digitalisimo_migration_demo' => 'imagen.png', '_digitalisimo_import_origin' => '{}',
	);
	$exported = array();
	foreach ( $metas as $key => $value ) {
		$meta = (object) array( 'meta_key' => $key, 'meta_value' => $value );
		if ( apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) ) {
			continue;
		}
		$exported[] = $key;
	}
	check_skip( array( '_thumbnail_id', '_wp_page_template', 'precio' ) === $exported, 'En el WXR de un kit sólo salen las metas ajenas: ni las de Migración, ni la lista negra de K, ni los usos de F7b (' . implode( ', ', $exported ) . ').' );
	remove_filter( 'wxr_export_skip_postmeta', array( $MB, 'skip' ), 10 );
	$native = array();
	foreach ( $metas as $key => $value ) {
		if ( ! apply_filters( 'wxr_export_skip_postmeta', false, $key, (object) array( 'meta_key' => $key ) ) ) { $native[] = $key; }
	}
	check_skip( ! array_intersect( $migration, $native ) && in_array( '_elementor_data', $native, true ), 'También fuera de un kit (el exportador de WordPress): las copias de Migración nunca salen; lo demás no cambia.' );

	// 4. Los metadatos de documento del núcleo ya omiten las metas protegidas: el WXR era la única vía.
	$document = file_get_contents( $root . '/elementor/core/base/document.php' );
	check_skip( (bool) preg_match( '/function get_export_metadata\(\)\s*\{[^}]*is_protected_meta\( \$meta_key, \'post\' \)/s', $document ) && array() === array_filter( $migration, function ( $k ) { return ! is_protected_meta( $k ); } ), 'Los metadatos de documento omiten las metas protegidas (_*), y todas las de Migración lo son.' );

	// 5. La lista negra de K no cambia (sin tocar el código de K) ni repite las de Migración.
	check_skip( array() === array_intersect( $migration, $MB::KEYS ) && array( '_digitalisimo_tools_network_' ) === $MB::PREFIXES, 'Meta_Blacklist de K queda como estaba: el rasgo se suma a ella por el filtro.' );

	// 6. Toda meta con nombre de Migración que escriba su código queda cubierta.
	$keys = array();
	$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $repo . '/digitalisimo-migration', \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $path ) {
		if ( '.php' !== substr( (string) $path, -4 ) ) { continue; }
		preg_match_all( "/'(_digitalisimo_(?:ep|migration)_[a-z0-9_]+)'/", (string) file_get_contents( (string) $path ), $found );
		$keys = array_merge( $keys, $found[1] );
	}
	$keys = array_values( array_unique( $keys ) );
	check_skip( in_array( '_digitalisimo_ep_backup', $keys, true ), 'Se leyeron las metas de Migración (' . count( $keys ) . ').' );
	$uncovered = array_filter( $keys, function ( $k ) use ( $S ) { return ! $S::blocks( $k ) && ! preg_match( '/_$/', $k ); } );
	check_skip( array() === array_values( $uncovered ), 'Ninguna meta de Migración queda fuera de Export_Skip: ' . implode( ', ', $uncovered ) );

	// 7. Guarda del paquete: los nombres llevan «ep_» detrás de «_», que la expresión no cuenta.
	$guard  = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$source = file_get_contents( $file );
	check_skip( ! preg_match( $guard, $source ), 'class-export-skip.php pasa la guarda de menciones de scripts/build-packages.sh.' );
	check_skip( str_contains( (string) file_get_contents( $repo . '/scripts/build-packages.sh' ), "(^|[^a-z0-9_])ep_[a-z]" ), 'La guarda del paquete sigue siendo la que se comprueba aquí.' );

	echo "F8, metas de Migración fuera de los kits: {$checks} comprobaciones en verde.\n";
}
