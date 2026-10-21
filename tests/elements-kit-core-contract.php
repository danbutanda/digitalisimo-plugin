<?php
/**
 * Kits (etapa K, §13.1 «core-contract»): lo que K usa del núcleo incluido sigue ahí (lectura estática de
 * `digitalisimo-elements/elementor/`, que nunca se edita).
 *
 * K no toca el núcleo: engancha filtros públicos y sustituye runners por nombre. Una sincronización del núcleo
 * (`scripts/sync-elementor-core.sh`) puede renombrar o quitar algo de eso sin que nada falle a la vista; esta prueba
 * lo detecta. Comprueba:
 *  1. Las acciones y los filtros de §5.4 y §7.1 existen en sus archivos (WXR, exportador, documento, procesos,
 *     rutas REST, biblioteca, kit, imágenes, `should_render`), y los del flujo heredado y WP-CLI que guarda §7.6.
 *  2. Los nombres de los runners por defecto (importar, exportar y revertir) y de los que K sustituye.
 *  3. `register()` indexa por nombre en los tres procesos (sustituir es reemplazar en su sitio).
 *  4. `run_runner()` guarda los documentos sólo en el último runner, y la captura de elementos sigue en 10.
 *  5. Los métodos que K sobrescribe o llama siguen públicos y con la misma firma.
 *  6. `IS_IMPORT_EXPORT_SUPPORTED` sigue en `false` (si cambia: revisar el motivo `component` del marcador).
 *  7. `fetch_remote_file()` sigue pidiendo `stream` con `filename` (el proxy escribe el archivo, V-19).
 */

$core = __DIR__ . '/../digitalisimo-elements/elementor/';
$iec  = $core . 'app/modules/import-export-customization/';

$check = function ( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
};
$source = function ( $relative ) use ( $core, $check ) {
	$file = $core . $relative;
	$check( is_file( $file ), "Falta el archivo del núcleo {$relative}: ¿cambió en la última sincronización?" );
	return (string) file_get_contents( $file );
};
$has = function ( $relative, $needle, $what ) use ( $source, $check ) {
	$check( false !== strpos( $source( $relative ), $needle ), "{$what}: no se encuentra «{$needle}» en {$relative}." );
};
$matches = function ( $relative, $pattern, $what ) use ( $source, $check ) {
	$check( 1 === preg_match( $pattern, $source( $relative ) ), "{$what}: {$relative} ya no cumple {$pattern}." );
};

// ── 1. Ganchos ───────────────────────────────────────────────────────────────────────────────────────────────────
$hooks = array(
	'core/utils/import-export/wp-import.php'                         => array(
		"apply_filters( 'wp_import_posts'",
		"apply_filters( 'wp_import_post_data_raw'",
		"apply_filters( 'wp_import_post_data_processed'",
		"do_action( 'wp_import_insert_post'",
		"apply_filters( 'wp_import_post_meta'",
		"apply_filters( 'wp_import_term_meta'",
		"apply_filters( 'wp_import_post_comments'",
		"apply_filters( 'import_attachment_size_limit'",
		"do_action( 'import_end' )",
	),
	'core/utils/import-export/wp-exporter.php'                       => array(
		"apply_filters( 'wxr_export_skip_postmeta'",
		"apply_filters( 'wxr_export_skip_termmeta'",
	),
	'core/base/document.php'                                         => array(
		"apply_filters( 'elementor/document/save/data'",
		"apply_filters( 'elementor/document/load/data'",
		"apply_filters( 'elementor/document/element/replace_id'",
	),
	'app/modules/import-export-customization/processes/import.php'  => array(
		"apply_filters( 'elementor/import/get_default_settings_conflicts'",
	),
	'app/modules/import-export-customization/module.php'             => array(
		"do_action( 'elementor/import-export-customization/import-kit'",
		"do_action( 'elementor/import-export-customization/export-kit'",
		"do_action( 'elementor/import-export-customization/revert-kit'",
		"\\WP_CLI::add_command( 'elementor kit'",
		'public $import;',
	),
	'app/modules/import-export-customization/data/routes/import-runner.php' => array(
		"do_action( 'elementor/import-export-customization/import-kit/runner/after-run'",
	),
	'app/modules/import-export-customization/data/routes/upload.php' => array(
		"apply_filters( 'elementor/import/kit/result/' . \$source",
		"\$request->get_param( 'file_url' )",
		"\$files['e_import_file']",
		'remove_file_or_dir( dirname( $import_result[\'file_name\'] ) )',
	),
	'app/modules/import-export-customization/runners/import/wp-content.php' => array(
		"apply_filters( 'elementor/import-export-customization/import/wp-content/query-args/customization'",
	),
	'app/modules/import-export-customization/runners/export/wp-content.php' => array(
		"apply_filters( 'elementor/import-export-customization/export/wp-content/query-args/customization'",
		"\$exporter_args['limit'] = 20;",
	),
	'app/modules/import-export-customization/runners/export/elementor-content.php' => array(
		'get_the_post_thumbnail_url( $post )',
	),
	'includes/controls/media.php'                                    => array( "do_action( 'elementor/templates/collect_media_url'" ),
	'includes/controls/gallery.php'                                  => array( "do_action( 'elementor/templates/collect_media_url'" ),
	'core/kits/manager.php'                                          => array( "apply_filters( 'elementor/kit/meta_to_preserve_on_kit_import'" ),
	'includes/template-library/sources/base.php'                     => array( '"elementor/template_library/sources/{$this->get_id()}/import/elements"' ),
	'includes/base/element-base.php'                                 => array( 'apply_filters( "elementor/frontend/{$element_type}/should_render"' ),
	'includes/template-library/classes/class-import-images.php'     => array(
		"apply_filters( 'elementor/template_library/import_images/new_attachment'",
		'wp_safe_remote_get( $attachment[\'url\'] )',
		'_elementor_source_image_hash',
	),
	'includes/template-library/manager.php'                          => array( "'wp_ajax_elementor_library_direct_actions'" ),
	'app/modules/import-export/module.php'                           => array(
		"do_action( 'elementor/import-export/import-kit'",
		"do_action( 'elementor/import-export/export-kit'",
		"do_action( 'elementor/import-export/revert-kit'",
		"add_action( 'admin_post_elementor_revert_kit'",
		"const IMPORT_TRIGGER_KEY = 'elementor_import_kit';",
		"const IMPORT_RUNNER_TRIGGER_KEY = 'elementor_import_kit__runner';",
		"const EXPORT_TRIGGER_KEY = 'elementor_export_kit';",
		"const UPLOAD_TRIGGER_KEY = 'elementor_upload_kit';",
		"\\WP_CLI::add_command( 'elementor kit'",
	),
	'modules/wp-cli/module.php'                                      => array( "\\WP_CLI::add_command( 'elementor library'" ),
	'modules/wp-cli/library.php'                                     => array( 'public function import( $args, $assoc_args )', 'public function import_dir( $args )' ),
	'core/common/modules/connect/apps/library.php'                   => array( "add_filter( 'elementor/common/localize_settings'", "'plan_type' =>" ),
);
foreach ( $hooks as $file => $needles ) {
	foreach ( $needles as $needle ) {
		$has( $file, $needle, 'Gancho o punto de entrada que usa K' );
	}
}
foreach ( array( 'upload', 'import', 'import-runner', 'revert', 'process-media', 'export' ) as $route ) {
	$file = 'app/modules/import-export-customization/data/routes/' . $route . '.php';
	$matches( $file, "#protected function get_route\(\): string \{\s*return '" . preg_quote( $route, '#' ) . "';#", 'Ruta REST que reconoce el contexto de K' );
}
$has( 'app/modules/import-export-customization/data/controller.php', "const API_NAMESPACE = 'elementor/v1';", 'Espacio de nombres REST del importador' );
$has( 'app/modules/import-export-customization/data/controller.php', "const API_BASE = 'import-export-customization';", 'Base REST del importador' );
$matches( 'app/modules/import-export-customization/data/routes/base-route.php', "#'permission_callback' => \\\$this->permission_callback\(\)#", 'Las rutas REST siguen comprobando su capacidad después de rest_request_before_callbacks' );
$matches( 'app/modules/import-export-customization/data/routes/base-route.php', "#fn\(\) => current_user_can\( 'manage_options' \)#", 'Las rutas REST del importador exigen manage_options' );
// V-21: los elementos de menú salen del bucle antes del filtro de metas: sólo wp_import_post_data_raw los ve.
$wxr = $source( 'core/utils/import-export/wp-import.php' );
$check( strpos( $wxr, "apply_filters( 'wp_import_post_data_raw'" ) < strpos( $wxr, "'nav_menu_item' === \$post['post_type']" ) && strpos( $wxr, "'nav_menu_item' === \$post['post_type']" ) < strpos( $wxr, "apply_filters( 'wp_import_post_meta'" ), 'V-21: el WXR debe seguir viendo los elementos de menú en wp_import_post_data_raw, antes de wp_import_post_meta.' );
$check( false !== strpos( $wxr, "maybe_unserialize( \$post_meta_key_value['_menu_item_classes'] )" ), 'V-21: process_menu_item() sigue deserializando _menu_item_classes (lo cubre wp_import_post_data_raw).' );
$check( false !== strpos( $wxr, "\$this->args['posts']" ) && false !== strpos( $wxr, '$this->processed_posts = $this->args[\'posts\'];' ), 'CV-1: el WXR sigue aceptando pares viejo → nuevo en args[posts] (los menús de lo reutilizado).' );

// ── 2. Nombres de los runners ───────────────────────────────────────────────────────────────────────────────────
$names = array(
	'runners/import/site-settings.php'      => 'site-settings',
	'runners/import/plugins.php'            => 'plugins',
	'runners/import/templates.php'          => 'templates',
	'runners/import/taxonomies.php'         => 'taxonomies',
	'runners/import/elementor-content.php'  => 'elementor-content',
	'runners/import/wp-content.php'         => 'wp-content',
	'runners/import/floating-elements.php'  => 'floating-elements',
	'runners/export/templates.php'          => 'templates',
	'runners/export/elementor-content.php'  => 'elementor-content',
	'runners/export/wp-content.php'         => 'wp-content',
	'runners/revert/site-settings.php'      => 'site-settings',
);
foreach ( $names as $file => $name ) {
	$matches( 'app/modules/import-export-customization/' . $file, "#public static function get_name\(\): string \{\s*return '" . preg_quote( $name, '#' ) . "';#", "Nombre del runner {$name}" );
}
foreach ( array(
	'modules/variables/import-export-customization/runners/import.php'      => 'global-variables',
	'modules/global-classes/import-export-customization/runners/import.php' => 'global-classes',
	'modules/default-styles/import-export-customization/runners/import.php' => 'default-styles',
	'modules/kit-elements-defaults/import-export-customization/runners/import.php' => 'elements-default-values',
) as $file => $name ) {
	$matches( $file, "#public static function get_name\(\): string \{\s*return '" . preg_quote( $name, '#' ) . "';#", "Nombre del runner v4 o de valores por defecto {$name}" );
}
$has( 'modules/variables/import-export-customization/runners/import.php', "\$data['customization']['settings']['variables']", 'El runner de variables sigue respetando customization.settings.variables (skip en «agregar»)' );
$has( 'modules/global-classes/import-export-customization/runners/import.php', "\$data['customization']['settings']['classes']", 'El runner de clases sigue respetando customization.settings.classes (skip en «agregar»)' );
$has( 'app/modules/import-export-customization/design-system-import-context.php', "\$data['customization']['settings'][ \$override_all_key ]", 'Los runners v4 siguen leyendo *OverrideAll de customization.settings' );
$has( 'modules/kit-elements-defaults/module.php', 'if ( is_admin() ) {', 'El runner nativo de valores por defecto sólo se registra con is_admin() (K registra el suyo, §7.3)' );
$import_process = $source( 'app/modules/import-export-customization/processes/import.php' );
$check( 1 === preg_match( '#public function register_default_runners\(\) \{\s*\$this->register\( new Site_Settings\(\) \);\s*\$this->register\( new Plugins\(\) \);\s*\$this->register\( new Templates\(\) \);\s*\$this->register\( new Taxonomies\(\) \);\s*\$this->register\( new Elementor_Content\(\) \);\s*\$this->register\( new Wp_Content\(\) \);\s*\$this->register\( new Floating_Elements\(\) \);\s*\}#', $import_process ), 'Orden de los runners de importación por defecto (IEC:processes/import.php:261-268): plugins sigue en la posición 2.' );
$matches( 'app/modules/import-export-customization/processes/export.php', '#public function register_default_runners\(\) \{\s*\$this->register\( new Site_Settings\(\) \);\s*\$this->register\( new Plugins\(\) \);\s*\$this->register\( new Templates\(\) \);\s*\$this->register\( new Taxonomies\(\) \);\s*\$this->register\( new Elementor_Content\(\) \);\s*\$this->register\( new Wp_Content\(\) \);\s*\}#', 'Runners de exportación por defecto' );
$matches( 'app/modules/import-export-customization/processes/revert.php', '#\$this->register\( new Site_Settings\(\) \);#', 'Runner de reversión site-settings' );

// ── 3. register() indexa por nombre ─────────────────────────────────────────────────────────────────────────────
foreach ( array( 'import', 'export', 'revert' ) as $process ) {
	$matches( 'app/modules/import-export-customization/processes/' . $process . '.php', '#public function register\( [A-Za-z_]+ \$runner_instance \) \{\s*\$this->runners\[ \$runner_instance::get_name\(\) \] = \$runner_instance;#', "register() del proceso {$process} indexa por nombre" );
}

// ── 4. run_runner() guarda en el último y la captura sigue en 10 ────────────────────────────────────────────────
$check( 1 === preg_match( "#\\\$is_last_runner = key\( array_slice\( \\\$this->runners, -1, 1, true \) \) === \\\$runner_name;\s*if \( \\\$is_last_runner \) \{\s*\\\$this->finalize_import_session_option\(\);\s*\\\$this->save_elements_of_imported_posts\(\);#", $import_process ), 'run_runner() sólo guarda los documentos en el último runner (V-10).' );
$check( 2 === substr_count( $import_process, "add_filter( 'elementor/document/save/data', [ \$this, 'prevent_saving_elements_on_post_creation' ], 10, 2 );" ), 'La captura de elementos del núcleo sigue en la prioridad 10 (K envuelve en 5 y remapea en 20, V-25).' );
$check( false !== strpos( $import_process, "Components_Module::prepare_imported_elements( \$data['elements']" ) && false !== strpos( $import_process, '$document->save( $data );' ), 'save_elements_of_imported_posts() sigue preparando componentes y guardando con save().' );
$check( false !== strpos( $import_process, "\$import_sessions[ \$this->session_id ]['instance_data'] = [" ) && false !== strpos( $import_process, "'runners' => \$this->runners," ), 'La sesión del núcleo sigue guardando los runners serializados (las clases de K se cargan al deserializar).' );
$has( 'app/modules/import-export-customization/module.php', '$runners = $this->import->get_runners_name();', 'import_kit_by_runner() sigue comparando con el último runner' );
$has( 'app/modules/import-export-customization/module.php', 'return $this->import->get_imported_data();', 'El último runner devuelve lo importado, sin status (after-run)' );

// ── 5. Métodos que K sobrescribe o llama ────────────────────────────────────────────────────────────────────────
$signatures = array(
	'app/modules/import-export-customization/runners/import/import-runner-base.php' => array(
		'abstract public function should_import( array $data );',
		'abstract public function import( array $data, array $imported_data );',
		'public function get_import_session_metadata(): array {',
		'public function set_session_post_meta( $post_id, $meta_value ) {',
		'public function set_session_term_meta( $term_id, $meta_value ) {',
	),
	'app/modules/import-export-customization/runners/export/export-runner-base.php' => array(
		'abstract public function should_export( array $data );',
		'abstract public function export( array $data );',
	),
	'app/modules/import-export-customization/runners/revert/revert-runner-base.php' => array(
		'abstract public function should_revert( array $data ): bool;',
		'abstract public function revert( array $data );',
	),
	'app/modules/import-export-customization/runners/import/templates.php' => array(
		'public function process_templates_import( array $data, array $template_types ) {',
		'public function import_template( $id, array $template_settings, array $template_data ) {',
	),
	'app/modules/import-export-customization/runners/import/elementor-content.php' => array(
		'public function import( array $data, array $imported_data ) {',
		'public function read_and_import_post( $path, $id, $post_settings, $post_type, $imported_terms ) {',
		'const IMPORT_STATUS_SUCCEEDED = \'succeed\';',
	),
	'app/modules/import-export-customization/runners/import/taxonomies.php' => array(
		'public function import( array $data, array $imported_data ) {',
		'public function import_taxonomies( array $taxonomies, $path ) {',
	),
	'app/modules/import-export-customization/runners/import/wp-content.php' => array(
		'public function import( array $data, array $imported_data ) {',
		"\$result['wp-content'][ \$post_type ] = \$import;",
	),
	'app/modules/import-export-customization/runners/export/templates.php' => array( 'public function export( array $data ) {' ),
	'app/modules/import-export-customization/runners/export/elementor-content.php' => array( 'public function export( array $data ) {' ),
	'app/modules/import-export-customization/runners/export/wp-content.php' => array( 'public function export( array $data ) {' ),
	'app/modules/import-export-customization/runners/revert/site-settings.php' => array(
		'public function revert( array $data ) {',
		'protected function revert_theme( $data ) {',
		'protected function revert_experiments( array $data ) {',
		'if ( empty( $installed_theme ) && empty( $activated_theme ) ) {',
		'if ( empty( $previous_experiments ) ) {',
	),
	'app/modules/import-export-customization/processes/import.php' => array(
		'public function settings_include( array $settings_include ) {',
		'public function get_settings_include() {',
		'public function settings_selected_override_conditions( array $settings_selected_override_conditions ) {',
		'public function settings_selected_plugins( array $settings_selected_plugins ) {',
		'public function settings_customization( $customization ) {',
		'public function get_settings_customization() {',
		'public function get_session_id() {',
		'public function get_manifest() {',
		'public function get_runners_name(): array {',
		'public function prevent_saving_elements_on_post_creation( array $data, Document $document ) {',
		'public static function from_session( string $session_id ): Import {',
	),
	'app/modules/import-export-customization/processes/export.php' => array( 'public function get_settings_include() {' ),
	'app/modules/import-export-customization/module.php' => array(
		'public function upload_kit( $file, $referrer, $kit_id = null ) {',
		'public function import_kit( string $path, array $settings, bool $split_to_chunks = false ): array {',
		'public function import_kit_by_runner( string $session_id, string $runner_name ): array {',
		'public function export_kit( array $settings ) {',
		'public function revert_last_imported_kit(): array {',
	),
	'app/modules/import-export-customization/utils.php' => array(
		'public static function read_json_file( $path ) {',
		'public static function map_old_new_post_ids( array $imported_data ) {',
		'public static function map_old_new_term_ids( array $imported_data ) {',
	),
	'core/kits/manager.php' => array(
		'public function revert( int $imported_kit_id, int $active_kit_id, int $previous_kit_id ) {',
		"if ( ! \$this->is_kit( \$active_kit_id ) ) {\n\t\t\treturn;\n\t\t}",
	),
	'core/base/document.php' => array(
		'public function process_element_import_export( Controls_Stack $element, $method, $element_data = null ) {',
		'public function import( array $data ) {',
	),
	'core/utils/svg/svg-sanitizer.php' => array( 'public function sanitize( $content ) {' ),
	'core/files/uploads-manager.php' => array( 'final public static function are_unfiltered_uploads_enabled() {' ),
);
foreach ( $signatures as $file => $needles ) {
	foreach ( $needles as $needle ) {
		$has( $file, $needle, 'Método o firma que K sobrescribe o llama' );
	}
}

// ── 6. Componentes v4 en kits ───────────────────────────────────────────────────────────────────────────────────
$components = $source( 'modules/components/module.php' );
$check( false !== strpos( $components, 'const IS_IMPORT_EXPORT_SUPPORTED = false;' ), 'IS_IMPORT_EXPORT_SUPPORTED ya no es false: revisar el motivo `component` del marcador (D9, V-25) y la regla del contexto de K.' );
$check( false !== strpos( $components, 'public static function is_import_export_supported(): bool {' ) && false !== strpos( $components, 'public static function prepare_imported_elements( array $elements, array $post_ids_map ): array {' ), 'El módulo de componentes sigue publicando is_import_export_supported() y prepare_imported_elements().' );

// ── 7. Descargas en flujo del WXR ───────────────────────────────────────────────────────────────────────────────
$check( 1 === preg_match( "#private function fetch_remote_file\( \\\$url, \\\$post \) \{.*?wp_safe_remote_get\( \\\$url, \[\s*'timeout' => 300,\s*'stream' => true,\s*'filename' => \\\$tmp_file_name,#s", $wxr ), 'fetch_remote_file() sigue pidiendo stream con filename (el proxy de K escribe el archivo, V-19).' );
$check( false !== strpos( $wxr, "isset( \$headers['content-length'] ) && \$filesize !== (int) \$headers['content-length']" ), 'El WXR sigue comprobando content-length contra el archivo escrito (el proxy responde el tamaño exacto).' );

echo "Kits: contrato con el núcleo incluido en verde (ganchos, runners, register(), run_runner(), firmas, componentes v4 y descargas en flujo).\n";
