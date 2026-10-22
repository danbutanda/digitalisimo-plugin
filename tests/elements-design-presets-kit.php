<?php
/**
 * F7a · frente E: la parte `presets` del kit (V.2.5 de la especificación) y el filtro
 * `digitalisimo_elements_kit_used_globals_trees` de `Kit_Settings::export()`, la única línea nueva en K.
 *
 * Con el núcleo real del arnés `tests/elements-design/core.php`, el `Store` real (frente B) y las clases reales de K:
 *  1. Contrato: `key`, `version`, `kit_meta_keys` vacío (no hay meta del kit), registro por el filtro de partes detrás
 *     de las de K y la referencia `element · * · digitalisimo_preset · map:presets` sin `gate`.
 *  2. Exportar: la opción entera, también los `inert`, con `hash` y `updated`.
 *  3. Globales que sólo usa un preset entran en `used` por el filtro nuevo; sin el filtro, no. El filtro sólo suma.
 *  4. «Nuevo»: reemplaza; mapa de identidad para todos; `hash` y `updated` del kit; `inert` recalculado.
 *  5. «Agregar»: misma huella → reutiliza; otra huella → `<slug>-k<hex>` estable; libre → añade; lo del sitio no
 *     cambia; reimportar no crea presets; `__globals__` reescritos con el mapa de colores.
 *  6. Fallar cerrado: un preset rechazado o fuera del tope nunca apunta al preset del sitio con su slug.
 *  7. `digitalisimo_preset` reescrito sólo en los valores por defecto que añadió `element-defaults`.
 *  8. Revertir: sin cambios, todo vuelve; con cambios, sólo se quita lo añadido que sigue igual.
 *  9. Cada código del informe tiene sección; PHP 7.4 sin construcciones de PHP 8.
 * 10. Editor: encola el script sólo detrás del editor, con su configuración (presets no inertes con sus ajustes
 *     validados, «con error», permisos) y con todos los textos que usa el script.
 */
require __DIR__ . '/elements-design/core.php';

$E = realpath( __DIR__ . '/../digitalisimo-elements' );
require_once $E . '/modules/digitalisimo-design/class-module.php';
\Digitalisimo\Elements\Design\Module::register_autoloader();
require_once $E . '/modules/digitalisimo-import/class-module.php';
\Digitalisimo\Elements\Import\Module::register_autoloader();

use Digitalisimo\Elements\Design\Presets\Editor;
use Digitalisimo\Elements\Design\Presets\Kit_Part;
use Digitalisimo\Elements\Design\Presets\Store;
use Digitalisimo\Elements\Import\Kits\Parts;
use Digitalisimo\Elements\Import\Kits\References;
use Digitalisimo\Elements\Import\Kits\Report;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Parts\Kit_Settings;

$check = 'check_design';

/** Kit activo con valores por defecto (los guarda `update_json_meta()`, como el documento del kit). */
final class Kits_Kit_Doc {
	public $meta = array();
	public function get_main_id() { return 5; }
	public function get_id() { return 5; }
	public function get_json_meta( $key ) { return $this->meta[ $key ] ?? array(); }
	public function update_json_meta( $key, $value ) { $this->meta[ $key ] = $value; return true; }
}

/** Sitio limpio: núcleo, opciones, presets y kit. */
$site = function () {
	design_wp_reset();
	design_boot();
	Store::reset_cache();
	if ( class_exists( '\Digitalisimo\Elements\Design\Presets\Compiler', false ) ) {
		\Digitalisimo\Elements\Design\Presets\Compiler::reset_cache();
	}
	$kit = new Kits_Kit_Doc();
	\Elementor\Plugin::$instance->documents->docs[5] = $kit;
	return $kit;
};
/** Presets sembrados con `Store::replace()` (como el arnés de kits). */
$seed = function ( array $presets ) {
	Store::reset_cache();
	Store::replace( array( 'v' => 1, 'presets' => $presets ) );
	Store::reset_cache();
};
$import_context = function ( string $mode, array $maps = array(), string $session = 'kses0000000a1' ) {
	return new Import_Context(
		array(
			'session' => $session,
			'mode'    => $mode,
			'kit_id'  => 5,
			'maps'    => $maps,
		)
	);
};
/** Corre `import()` como lo hace el runner: dentro de su parte. */
$run_import = function ( Import_Context $context, array $data ) {
	$context->begin_part( 'presets' );
	$summary = ( new Kit_Part() )->import( $data, 1, $context );
	$context->end_part();
	Store::reset_cache();
	return $summary;
};
$run_revert = function ( array $stored, string $mode = 'add' ) {
	$context = new Revert_Context( array( 'session' => 'kses0000000a1', 'mode' => $mode, 'kit_id' => 5 ) );
	$context->begin_part( 'presets' );
	( new Kit_Part() )->revert( $stored, $context );
	$context->end_part();
	Store::reset_cache();
	return $context;
};
$codes = function ( Part_Context $context, string $code ) {
	return array_values( array_filter( $context->get_report()['entries'], function ( $entry ) use ( $code ) { return $code === $entry['code']; } ) );
};

$heading = function ( string $color, array $globals = array() ) {
	$settings = array( 'title_color' => $color );
	if ( $globals ) {
		$settings['__globals__'] = $globals;
	}
	return $settings;
};
$preset = function ( string $type, string $label, array $settings, string $hash = '', int $updated = 1792000000, bool $inert = false ) {
	return array( 'type' => $type, 'label' => $label, 'settings' => $settings, 'hash' => $hash, 'updated' => $updated, 'inert' => $inert );
};

// ── 1. Contrato y registro ───────────────────────────────────────────────────────────────────────────────────────
$site();
$part = new Kit_Part();
$check( $part instanceof \Digitalisimo\Elements\Import\Kits\Part, '1: Kit_Part implementa Part.' );
$check( 'presets' === $part->key() && 1 === $part->version() && array() === $part->kit_meta_keys(), '1: clave «presets», versión 1 y sin metas del kit (los presets viven en la opción del sitio).' );
$check( 'digitalisimo_preset' === Kit_Part::CONTROL && 'presets' === Kit_Part::MAP, '1: el vínculo es digitalisimo_preset y el mapa, «presets».' );
if ( class_exists( '\Digitalisimo\Elements\Design\Presets\Controls' ) ) {
	$check( \Digitalisimo\Elements\Design\Presets\Controls::CONTROL === Editor::CONTROL, '1: Editor y Controls nombran el mismo control.' );
}
Editor::init();
$check( (bool) has_filter( 'digitalisimo_elements_kit_parts' ) && (bool) has_filter( 'digitalisimo_elements_kit_references' ) && (bool) has_filter( 'digitalisimo_elements_kit_used_globals_trees' ), '1: Editor::init() registra la parte, la referencia y el filtro de globales en el mismo init().' );
$check( (bool) has_action( 'elementor/editor/after_enqueue_scripts' ), '1: el script del editor se encola sólo en elementor/editor/after_enqueue_scripts.' );
$keys = Parts::keys();
$check( 'presets' === end( $keys ) && array_slice( $keys, 0, count( Parts::BUILTIN ) ) === array_keys( Parts::BUILTIN ), '1: la parte va detrás de las de K (' . implode( ',', $keys ) . ').' );
$refs = References::matching( 'element', 'heading' );
$ref  = null;
foreach ( $refs as $candidate ) {
	if ( 'digitalisimo_preset' === $candidate['path'] ) {
		$ref = $candidate;
	}
}
$check( null !== $ref && 'map:presets' === $ref['kind'] && false === $ref['gate'] && null === $ref['never'], '1: referencia element · * · digitalisimo_preset · map:presets sin gate.' );
$check( 'presets' === References::map_name( $ref['kind'] ), '1: el mapa de la referencia es «presets».' );

// ── 2. Exportar ──────────────────────────────────────────────────────────────────────────────────────────────────
$site();
$seed(
	array(
		'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000' ) ),
		'precio'  => $preset( 'acme-precio', 'Precio', array( 'price_color' => '#00aa00' ) ),
	)
);
$stored = Store::all();
$check( ! empty( $stored['precio']['inert'] ) && empty( $stored['tarjeta']['inert'] ), '2: un tipo que el sitio no registra queda inerte.' );
$export = new Export_Context( array( 'limits' => array( 'file' => 0, 'total' => 0 ) ) );
$export->begin_part( 'presets' );
$out = ( new Kit_Part() )->export( $export );
$export->end_part();
$check( is_array( $out ) && 1 === $out['v'] && array( 'tarjeta', 'precio' ) === array_keys( $out['presets'] ), '2: exporta la opción entera, también el preset inerte.' );
foreach ( array( 'tarjeta', 'precio' ) as $slug ) {
	$check( $stored[ $slug ]['hash'] === $out['presets'][ $slug ]['hash'] && $stored[ $slug ]['updated'] === $out['presets'][ $slug ]['updated'] && $stored[ $slug ]['settings'] === $out['presets'][ $slug ]['settings'], "2: «{$slug}» viaja con su hash, su updated y sus ajustes." );
}
$check( true === $out['presets']['precio']['inert'] && 1 === count( $codes( $export, 'settings.presets.inert' ) ), '2: el inerte se marca y se informa.' );
$site();
$export = new Export_Context( array( 'limits' => array( 'file' => 0, 'total' => 0 ) ) );
$check( null === ( new Kit_Part() )->export( $export ), '2: sin presets, la parte no escribe archivo.' );

// ── 3. Globales que sólo usa un preset (filtro nuevo de K) ──────────────────────────────────────────────────────────
$site();
// `Editor::init()` ya corrió (sección 1) y `$site()` vacía los ganchos: se engancha lo mismo que registra.
add_filter( 'digitalisimo_elements_kit_used_globals_trees', array( Editor::class, 'kit_used_trees' ) );
$seed(
	array(
		'acento' => $preset( 'heading', 'Acento', $heading( '', array( 'title_color' => 'globals/colors?id=solopreset' ) ) ),
		'inerte' => $preset( 'acme-precio', 'Inerte', array( '__globals__' => array( 'price_typography_typography' => 'globals/typography?id=soloinerte' ) ) ),
	)
);
$kit_settings = function () {
	$export = new Export_Context( array( 'limits' => array( 'file' => 0, 'total' => 0 ) ) );
	$export->begin_part( 'kit-settings' );
	$out = ( new Kit_Settings() )->export( $export );
	$export->end_part();
	return $out;
};
$used = $kit_settings();
$check( is_array( $used ) && array( 'solopreset' ) === $used['used']['colors'] && array( 'soloinerte' ) === $used['used']['typography'], '3: el color y la tipografía que sólo usan presets (también inertes) entran en used: ' . json_encode( $used ) );
$GLOBALS['dh']['hooks']['digitalisimo_elements_kit_used_globals_trees'] = array();
$check( null === $kit_settings(), '3: sin el filtro, Kit_Settings no ve los globales de los presets (lo que corrige R-I9).' );
add_filter(
	'digitalisimo_elements_kit_used_globals_trees',
	function ( $trees ) {
		return array( 'no es un árbol', 7, array( array( 'settings' => array( '__globals__' => array( 'x' => 'globals/colors?id=otro' ) ) ) ) );
	}
);
$used = $kit_settings();
$check( is_array( $used ) && array( 'otro' ) === $used['used']['colors'], '3: lo que no es un árbol se ignora; el filtro sólo suma árboles.' );
$k_sources = '';
foreach ( glob( $E . '/modules/digitalisimo-import/{,*/,*/*/}*.php', GLOB_BRACE ) as $file ) {
	$k_sources .= file_get_contents( $file );
}
$check( 1 === substr_count( $k_sources, "apply_filters( 'digitalisimo_elements_kit_used_globals_trees'" ), '3: K aplica el filtro nuevo exactamente una vez.' );
$settings_src = file_get_contents( $E . '/modules/digitalisimo-import/kits/parts/class-kit-settings.php' );
$filter_at    = strpos( $settings_src, "apply_filters( 'digitalisimo_elements_kit_used_globals_trees', array(), \$context )" );
$check( false !== $filter_at && $filter_at > strpos( $settings_src, 'public function export(' ) && $filter_at < strpos( $settings_src, '$used = self::used_globals( $trees );' ), '3: el filtro está en Kit_Settings::export(), antes de calcular used, y parte de un array vacío.' );

// ── 4. «Nuevo» ───────────────────────────────────────────────────────────────────────────────────────────────────
$site();
$seed( array( 'viejo' => $preset( 'heading', 'Viejo', $heading( '#111111' ) ) ) );
$kit_data = array(
	'v'       => 1,
	'presets' => array(
		'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000' ), str_repeat( 'a', 40 ), 1700000001 ),
		'precio'  => $preset( 'acme-precio', 'Precio', array( 'price_color' => '#00aa00' ), str_repeat( 'b', 40 ), 1700000002, false ),
		'divisor' => $preset( 'divider', 'Divisor', array( 'color' => '#ff0000' ), '', 1700000003, true ),
	),
);
$fresh   = $import_context( 'fresh' );
$summary = $run_import( $fresh, $kit_data );
$after   = Store::all();
$check( array( 'tarjeta', 'precio', 'divisor' ) === array_keys( $after ), '4: «nuevo» reemplaza los presets del sitio: ' . implode( ',', array_keys( $after ) ) );
$check( array( 'tarjeta' => 'tarjeta', 'precio' => 'precio', 'divisor' => 'divisor' ) === $fresh->map( 'presets' ), '4: mapa de identidad para todos los slugs (V-I8).' );
$check( 1700000001 === $after['tarjeta']['updated'] && 1700000002 === $after['precio']['updated'] && 1700000003 === $after['divisor']['updated'], '4: se conserva updated del kit.' );
$check( str_repeat( 'b', 40 ) === $after['precio']['hash'], '4: se conserva el hash de un preset que el saneado no cambia.' );
$check( true === $after['precio']['inert'] && false === $after['divisor']['inert'], '4: inert se recalcula en el destino (precio inerte aquí, divisor no).' );
$check( 3 === $summary['added'] && 0 === $summary['renamed'] && 1 === $summary['inert'], '4: resumen: ' . json_encode( $summary ) );
$fresh_stored = $fresh->stored( 'presets' );
$check( isset( $fresh_stored['before']['viejo'] ) && array( 'tarjeta', 'precio', 'divisor' ) === $fresh_stored['added'], '4: guarda lo de antes y lo añadido para revertir.' );

// ── 5. «Agregar» ─────────────────────────────────────────────────────────────────────────────────────────────────
$site();
$seed(
	array(
		'tarjeta' => $preset( 'heading', 'Tarjeta del sitio', $heading( '#000000' ), '', 1600000000 ),
		'igual'   => $preset( 'heading', 'Igual', $heading( '#00ff00' ), '', 1600000001 ),
	)
);
$site_before = Store::all();
$add_data    = array(
	'v'       => 1,
	'presets' => array(
		'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000', array( 'title_color' => 'globals/colors?id=acento' ) ), '', 1700000001 ),
		'igual'   => $preset( 'heading', 'Igual en el kit', $heading( '#00ff00' ), '', 1700000002 ),
		'nuevo'   => $preset( 'heading', 'Nuevo', $heading( '#0000ff' ), '', 1700000003 ),
	),
);
$add     = $import_context( 'add', array( 'global_color' => array( 'acento' => 'k1a2b3c' ) ) );
$summary = $run_import( $add, $add_data );
$after   = Store::all();
$map     = $add->map( 'presets' );
$renamed = $map['tarjeta'];
$check( 1 === preg_match( '/^tarjeta-k[0-9a-f]{6}$/', $renamed ) && Kit_Part::valid_slug( $renamed ), '5: otra huella → tarjeta-k<hex>: ' . $renamed );
$check( 'igual' === $map['igual'] && 'nuevo' === $map['nuevo'], '5: misma huella reutiliza y slug libre añade, ambos con su identidad en el mapa.' );
$check( $site_before['tarjeta'] === $after['tarjeta'] && $site_before['igual'] === $after['igual'], '5: los presets del sitio no cambian (ni nombre, ni ajustes, ni hash, ni updated).' );
$check( isset( $after[ $renamed ], $after['nuevo'] ) && 4 === count( $after ), '5: se añaden el renombrado y el nuevo.' );
$check( 'globals/colors?id=k1a2b3c' === $after[ $renamed ]['settings']['__globals__']['title_color'], '5: __globals__ reescritos con el mapa de colores (la función de element-defaults).' );
$check( 1700000001 === $after[ $renamed ]['updated'] && 1700000003 === $after['nuevo']['updated'], '5: updated del kit también en «agregar».' );
$renames = $codes( $add, 'settings.presets.renamed' );
$check( 1 === count( $renames ) && 'tarjeta' === $renames[0]['data']['from'] && $renamed === $renames[0]['data']['to'], '5: el cambio de nombre se informa.' );
$check( 2 === $summary['added'] && 1 === $summary['reused'] && 1 === $summary['renamed'], '5: resumen: ' . json_encode( $summary ) );
$check( array() === $codes( $add, 'map-conflict' ), '5: sin conflictos de mapa.' );
$first_add = $add->stored( 'presets' );
$check( array( $renamed, 'nuevo' ) === $first_add['added'], '5: sólo lo añadido queda para revertir.' );
// Reimportar el mismo kit (R5): reutiliza el renombrado y no crea nada.
$again   = $import_context( 'add', array( 'global_color' => array( 'acento' => 'k1a2b3c' ) ), 'kses0000000b2' );
$summary = $run_import( $again, $add_data );
$check( $after === Store::all() && 3 === $summary['reused'] && 0 === $summary['added'], '5: reimportar no crea presets (R5): ' . json_encode( $summary ) );
$check( $renamed === $again->map( 'presets' )['tarjeta'], '5: el renombrado es estable entre sesiones.' );
// Sin mapa de colores, el global del kit queda como estaba (lo decide el runner de ajustes del kit).
$site();
$plain = $import_context( 'add' );
$run_import( $plain, array( 'presets' => array( 'g' => $preset( 'heading', 'G', $heading( '', array( 'title_color' => 'globals/colors?id=acento' ) ) ) ) ) );
$check( 'globals/colors?id=acento' === Store::all()['g']['settings']['__globals__']['title_color'], '5: sin mapa, el global no cambia.' );

// ── 6. Fallar cerrado ────────────────────────────────────────────────────────────────────────────────────────────
$site();
$seed( array( 'tarjeta' => $preset( 'heading', 'Tarjeta del sitio', $heading( '#000000' ) ) ) );
$closed  = $import_context( 'add' );
$summary = $run_import(
	$closed,
	array(
		'presets' => array(
			'tarjeta' => $preset( 'heading', 'Sin estilo', array( 'title' => 'Sólo contenido', 'link' => array( 'url' => 'https://a.test' ) ) ),
			'libre'   => $preset( 'heading', 'Libre', array( 'title' => 'Sólo contenido' ) ),
			'Mal Slug' => $preset( 'heading', 'Mal', $heading( '#ffffff' ) ),
		),
	)
);
$map = $closed->map( 'presets' );
$check( isset( $map['tarjeta'] ) && 'tarjeta' !== $map['tarjeta'] && ! isset( Store::all()[ $map['tarjeta'] ] ), '6: un preset rechazado con el slug de uno del sitio apunta a un slug que no existe: ' . json_encode( $map ) );
$check( 'libre' === $map['libre'] && ! isset( Store::all()['libre'] ), '6: rechazado con slug libre: identidad, sin preset (nada se aplica).' );
$check( ! isset( $map['Mal Slug'] ) && 1 === count( $codes( $closed, 'settings.presets.invalid' ) ), '6: un slug inválido no entra ni en el mapa.' );
$check( 2 === count( $codes( $closed, 'settings.presets.rejected' ) ) && 3 === $summary['rejected'], '6: rechazados informados: ' . json_encode( $summary ) );
$check( array( 'tarjeta' ) === array_keys( Store::all() ), '6: el preset del sitio sigue intacto.' );
// Tope de presets (filtro digitalisimo_elements_preset_limits).
$site();
add_filter( 'digitalisimo_elements_preset_limits', function ( $limits ) { $limits['count'] = 2; return $limits; } );
$seed( array( 'uno' => $preset( 'heading', 'Uno', $heading( '#010101' ) ), 'dos' => $preset( 'heading', 'Dos', $heading( '#020202' ) ) ) );
$limited = $import_context( 'add' );
$summary = $run_import( $limited, array( 'presets' => array( 'uno' => $preset( 'heading', 'Otro uno', $heading( '#0a0a0a' ) ), 'tres' => $preset( 'heading', 'Tres', $heading( '#030303' ) ) ) ) );
$map     = $limited->map( 'presets' );
$check( array( 'uno', 'dos' ) === array_keys( Store::all() ) && 2 === count( $codes( $limited, 'settings.presets.limit' ) ), '6: fuera del tope no se añade y se informa.' );
$check( 'uno' !== $map['uno'] && ! isset( Store::all()[ $map['uno'] ] ) && 'tres' === $map['tres'], '6: fuera del tope, el que choca no toma el preset del sitio: ' . json_encode( $map ) );

// Un preset del sitio que hoy no supera el saneado nunca se pierde por importar en «agregar» (D7).
$site();
update_option( 'digitalisimo_elements_presets', array( 'v' => 1, 'presets' => array( 'viejo' => $preset( 'heading', 'Viejo', array( 'control_que_ya_no_existe' => '#ffffff' ), str_repeat( 'c', 40 ), 1 ) ) ), false );
Store::reset_cache();
$fragile = $import_context( 'add' );
$run_import( $fragile, array( 'presets' => array( 'viejo' => $preset( 'heading', 'Viejo del kit', $heading( '#cc0000' ) ), 'nuevo' => $preset( 'heading', 'Nuevo', $heading( '#0000ff' ) ) ) ) );
$keeps = ( new ReflectionMethod( Store::class, 'replace' ) )->getNumberOfParameters() > 1;
$check( isset( Store::all()['viejo'] ) && array( 'control_que_ya_no_existe' => '#ffffff' ) === Store::all()['viejo']['settings'], '6: el preset del sitio que ya no supera el saneado sigue tal cual.' );
$map = $fragile->map( 'presets' );
$check( 'viejo' !== $map['viejo'] && ( $keeps ? 'Viejo del kit' === ( Store::all()[ $map['viejo'] ]['label'] ?? '' ) : ! isset( Store::all()[ $map['viejo'] ] ) ), '6: el del kit con su slug no toma el del sitio (con la lista de conservados, entra renombrado): ' . json_encode( $map ) );
if ( ! $keeps ) {
	$check( 1 === count( $codes( $fragile, 'settings.presets.site-invalid' ) ) && ! isset( Store::all()['nuevo'] ), '6: sin la lista de conservados de Store::replace(), la parte no escribe y lo informa.' );
} else {
	$check( isset( Store::all()['nuevo'] ) && 0 === count( $codes( $fragile, 'settings.presets.site-invalid' ) ) && 0 === count( $codes( $fragile, 'settings.presets.dropped' ) ), '6: con la lista de conservados de Store::replace(), se añade lo nuevo sin el respaldo de «no escribir».' );
}

// ── 7. Valores por defecto que añadió esta importación ──────────────────────────────────────────────────────────
$kit = $site();
$seed( array( 'tarjeta' => $preset( 'heading', 'Tarjeta del sitio', $heading( '#000000' ) ) ) );
$kit->meta['_elementor_elements_default_values'] = array(
	'heading' => array( 'digitalisimo_preset' => 'tarjeta', 'title_color' => '#cc0000' ),
	'divider' => array( 'digitalisimo_preset' => 'tarjeta' ),
	'container' => array( 'digitalisimo_preset' => 'ausente' ),
);
$defaults_before = $kit->meta['_elementor_elements_default_values'];
$with_defaults   = $import_context( 'add' );
$with_defaults->begin_part( 'element-defaults' );
$with_defaults->store( array( 'kit' => 5, 'added' => array( 'heading', 'container' ) ) );
$with_defaults->end_part();
$summary  = $run_import( $with_defaults, array( 'presets' => array( 'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000' ) ) ) ) );
$renamed  = $with_defaults->map( 'presets' )['tarjeta'];
$defaults = $kit->meta['_elementor_elements_default_values'];
$check( $renamed === $defaults['heading']['digitalisimo_preset'] && '#cc0000' === $defaults['heading']['title_color'], '7: el vínculo del valor por defecto añadido pasa al slug final.' );
$check( 'tarjeta' === $defaults['divider']['digitalisimo_preset'], '7: un valor por defecto que no añadió esta importación no se toca.' );
$check( 'ausente' === $defaults['container']['digitalisimo_preset'] && 1 === count( $codes( $with_defaults, 'map.miss' ) ), '7: un slug sin destino se deja como estaba y se informa como map.miss.' );
$check( 1 === $summary['defaults'], '7: resumen: ' . json_encode( $summary ) );
$defaults_stored = $with_defaults->stored( 'presets' );

// ── 8. Revertir ──────────────────────────────────────────────────────────────────────────────────────────────────
$run_revert( $defaults_stored );
$check( array( 'tarjeta' ) === array_keys( Store::all() ) && '#000000' === Store::all()['tarjeta']['settings']['title_color'], '8: sin cambios después, la opción vuelve a lo de antes.' );
$check( $defaults_before === $kit->meta['_elementor_elements_default_values'], '8: y los valores por defecto reescritos vuelven a su vínculo de origen (antes que element-defaults).' );
// Con cambios después: sólo se quita lo añadido que sigue igual.
$kit = $site();
$seed( array( 'viejo' => $preset( 'heading', 'Viejo', $heading( '#111111' ) ) ) );
$kit->meta['_elementor_elements_default_values'] = array( 'heading' => array( 'digitalisimo_preset' => 'tarjeta' ) );
$edited = $import_context( 'add' );
$edited->begin_part( 'element-defaults' );
$edited->store( array( 'kit' => 5, 'added' => array( 'heading' ) ) );
$edited->end_part();
$seed( array( 'viejo' => $preset( 'heading', 'Viejo', $heading( '#111111' ) ), 'tarjeta' => $preset( 'heading', 'Del sitio', $heading( '#000000' ) ) ) );
$run_import( $edited, array( 'presets' => array( 'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000' ) ), 'nuevo' => $preset( 'heading', 'Nuevo', $heading( '#0000ff' ) ) ) ) );
$stored_edit = $edited->stored( 'presets' );
$renamed     = $edited->map( 'presets' )['tarjeta'];
$now         = Store::all();
$now[ $renamed ]['label'] = 'Editado';
$seed( $now );
$kit->meta['_elementor_elements_default_values']['heading']['title_color'] = '#abcdef';
$reverted = $run_revert( $stored_edit );
$final    = Store::all();
$check( isset( $final[ $renamed ], $final['viejo'], $final['tarjeta'] ) && ! isset( $final['nuevo'] ), '8: con un preset editado, se quita sólo lo añadido que sigue igual: ' . implode( ',', array_keys( $final ) ) );
$check( 1 === count( $codes( $reverted, 'settings.presets.edited' ) ) && 1 === count( $codes( $reverted, 'settings.presets.defaults-edited' ) ), '8: lo editado se conserva y se informa.' );
$check( $renamed === $kit->meta['_elementor_elements_default_values']['heading']['digitalisimo_preset'], '8: un valor por defecto editado no se pisa.' );
// «Nuevo»: los del sitio vuelven aunque alguien haya editado un preset importado.
$site();
$seed( array( 'viejo' => $preset( 'heading', 'Viejo', $heading( '#111111' ) ) ) );
$fresh = $import_context( 'fresh' );
$run_import( $fresh, array( 'presets' => array( 'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000' ) ) ) ) );
$stored_fresh = $fresh->stored( 'presets' );
$now          = Store::all();
$now['tarjeta']['label'] = 'Editado';
$seed( $now );
$run_revert( $stored_fresh, 'fresh' );
$check( array( 'tarjeta', 'viejo' ) === array_keys( Store::all() ), '8: en «nuevo», vuelven los del sitio y se conserva el editado: ' . implode( ',', array_keys( Store::all() ) ) );
// «Nuevo» con un preset del sitio que hoy no supera el saneado: al revertir vuelve tal cual (`$keep` de Store::replace()).
$site();
update_option( 'digitalisimo_elements_presets', array( 'v' => 1, 'presets' => array( 'viejo' => $preset( 'heading', 'Viejo', array( 'control_que_ya_no_existe' => '#ffffff' ), str_repeat( 'c', 40 ), 1 ) ) ), false );
Store::reset_cache();
$fresh_fragile = $import_context( 'fresh' );
$run_import( $fresh_fragile, array( 'presets' => array( 'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000' ) ) ) ) );
$check( array( 'tarjeta' ) === array_keys( Store::all() ), '8: «nuevo» reemplaza los presets del sitio.' );
$reverted = $run_revert( $fresh_fragile->stored( 'presets' ), 'fresh' );
$restored = Store::all();
$check( array( 'viejo' ) === array_keys( $restored ) && array( 'control_que_ya_no_existe' => '#ffffff' ) === $restored['viejo']['settings'] && str_repeat( 'c', 40 ) === $restored['viejo']['hash'] && 0 === count( $codes( $reverted, 'settings.presets.dropped' ) ), '8: al revertir «nuevo», el preset del sitio que ya no supera el saneado vuelve tal cual: ' . json_encode( $restored ) );
// Sin nada guardado, revertir no hace nada.
$site();
$seed( array( 'viejo' => $preset( 'heading', 'Viejo', $heading( '#111111' ) ) ) );
$before = Store::all();
$run_revert( array() );
$check( $before === Store::all(), '8: sin datos de la importación, revertir no toca nada.' );

// ── 9. Informe y PHP 7.4 ─────────────────────────────────────────────────────────────────────────────────────────
$source = file_get_contents( $E . '/modules/digitalisimo-design/presets/class-kit-part.php' );
preg_match_all( "/(?:->report)\(\s*'([a-z0-9_:.-]+)'/", $source, $found );
$check( count( $found[1] ) >= 8, '9: se encontraron los códigos del informe de la parte.' );
foreach ( array_unique( $found[1] ) as $code ) {
	$check( 'other' !== Report::section_for( $code ), "9: el código {$code} tiene sección en el informe." );
	$check( 1 === preg_match( Part_Context::CODE_PATTERN, $code ), "9: el código {$code} cumple el patrón." );
}
foreach ( array( 'presets/class-kit-part.php', 'presets/class-editor.php' ) as $file ) {
	$code = file_get_contents( $E . '/modules/digitalisimo-design/' . $file );
	foreach ( array( '/\bmatch\s*\(/' => 'match', '/\bstr_contains\s*\(/' => 'str_contains', '/\bstr_starts_with\s*\(/' => 'str_starts_with', '/\?->/' => 'nullsafe', '/\bfn\s*\(/' => 'fn', '/function\s+\w+\s*\([^)]*\b\w+\|\w+/' => 'tipos de unión', '/\)\s*:\s*(?:mixed|never|static)\b/' => 'tipos de PHP 8' ) as $pattern => $what ) {
		$check( 0 === preg_match( $pattern, $code ), "9: {$file} es compatible con PHP 7.4 (sin {$what})." );
	}
}

// ── 10. Editor: encolado y configuración del script (frente D) ─────────────────────────────────────────────────────
$site();
$seed(
	array(
		'tarjeta' => $preset( 'heading', 'Tarjeta', $heading( '#cc0000' ) ),
		'precio'  => $preset( 'acme-precio', 'Precio', array( 'price_color' => '#00aa00' ) ),
	)
);
update_option( 'digitalisimo_elements_presets', array( 'v' => 1, 'presets' => Store::all() + array( 'roto' => $preset( 'heading', 'Roto', array( 'control_que_ya_no_existe' => '#ffffff' ) ) ) ), false );
Store::reset_cache();
Editor::enqueue();
$script = $GLOBALS['dh']['scripts'][ Editor::HANDLE ] ?? array();
$check( ! empty( $script['enqueued'] ) && array( 'elementor-editor' ) === $script['deps'] && '/assets/presets-editor.js' === substr( $script['src'], -strlen( '/assets/presets-editor.js' ) ), '10: encola presets-editor.js detrás del editor.' );
$inline = array_values( array_filter( $GLOBALS['dh']['inline'], function ( $entry ) { return Editor::HANDLE === $entry[0]; } ) );
$check( 1 === count( $inline ) && 'before' === $inline[0][2] && 0 === strpos( $inline[0][1], 'window.digitalisimoPresets={' ), '10: la configuración va antes del script, en window.digitalisimoPresets.' );
$config = json_decode( substr( $inline[0][1], strlen( 'window.digitalisimoPresets=' ), -1 ), true );
$check( 'digitalisimo_preset' === $config['control'] && true === $config['canManage'] && true === $config['canDesign'], '10: control y permisos del administrador.' );
$check( array( 'tarjeta', 'roto' ) === array_keys( $config['presets'] ), '10: sin los inertes (su tipo no existe aquí).' );
$check( 'heading' === $config['presets']['tarjeta']['type'] && false === $config['presets']['tarjeta']['error'] && '#cc0000' === $config['presets']['tarjeta']['settings']['title_color'], '10: cada preset con su tipo y sus ajustes validados.' );
$check( true === $config['presets']['roto']['error'] && array() === $config['presets']['roto']['settings'], '10: el que no pasa el saneado va «con error» y sin ajustes.' );
$check( 0 === strpos( substr( $inline[0][1], strlen( 'window.digitalisimoPresets=' ) ), '{' ) && false !== strpos( $inline[0][1], '"settings":{}' ), '10: los ajustes vacíos viajan como objeto, no como lista.' );
$js = file_get_contents( $E . '/modules/digitalisimo-design/presets/assets/presets-editor.js' );
preg_match_all( '/\bT\.([a-z0-9]+)/i', $js, $used );
preg_match_all( "/\ba\('([a-z]+)'/", $js, $menu );
$keys = array_unique( array_merge( $used[1], $menu[1] ) );
$check( count( $keys ) >= 14 && array() === array_diff( $keys, array_keys( Editor::strings() ) ), '10: cada texto que usa el script existe en Editor::strings(): ' . implode( ',', array_diff( $keys, array_keys( Editor::strings() ) ) ) );
foreach ( Editor::strings() as $key => $text ) {
	$check( false === stripos( $text, 'elementor' ) && false === stripos( $text, 'divi' ) && false === stripos( $text, 'betheme' ), "10: el texto {$key} no nombra otros constructores." );
}
$GLOBALS['dh']['user'] = 2;
$GLOBALS['dh']['users'][2] = array( 'roles' => array( 'editor' ), 'caps' => array( 'edit_posts' ) );
$check( false === Editor::config()['canManage'], '10: un editor no puede gestionar presets.' );

echo "F7a · parte «presets» del kit: contrato, exportación con inertes, globales sólo de presets en used (filtro nuevo de K), «nuevo» y «agregar» con mapa de identidad, renombrado estable, fallar cerrado, valores por defecto añadidos, reversión y configuración del editor validados.\n";
