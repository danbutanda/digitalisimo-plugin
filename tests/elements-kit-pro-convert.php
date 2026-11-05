<?php
/**
 * Kits (etapa K, §17 de docs/fusion/k-kits.md): los widgets de PRO con mapa listo llegan convertidos a su widget propio
 * al importar un kit (programa «sin widgets de PRO», C1, frente K).
 *
 * Sin WordPress: tests/sources/kits/wp-stubs.php y los archivos reales de digitalisimo-elements/.
 *  1. Sin el núcleo de conversión cargado, `Pro_Convert` no está disponible y `Context::preserve()` deja el documento
 *     exactamente igual (y sin códigos).
 *  2. Con el núcleo y el mapa real de la Cita (`maps/blockquote.php`, `ready`): cada Blockquote de PRO pasa a
 *     `digitalisimo-blockquote` con el mismo `id`, el mismo `elType` y sus hijos, a cualquier profundidad (sección y
 *     columna, contenedores anidados, hijos de un widget anidado); lo demás no cambia; los códigos (`pro.converted`,
 *     `pro.css`, `pro.unmapped`…) llevan widget, destino, elemento y ajuste.
 *  3. Idempotente: el segundo guardado del núcleo no cambia nada ni repite códigos. Un mapa en borrador no convierte.
 *  4. Falla cerrado: con el widget propio sin registrar, el widget de PRO se queda y el informe dice `pro.target_missing`.
 *  5. `Context::preserve()` (save/data, prioridad 5) convierte antes del marcador y lleva los códigos al informe de la
 *     sesión, con el documento; un error se informa (`pro.error`) y el documento llega como estaba.
 *  6. Informe: sección propia «Widgets convertidos a widgets propios» para todos los códigos `pro.*`, con texto, y la
 *     pantalla «Kits» la pinta.
 *  7. Sólo en la importación: el contexto de exportación, la biblioteca de plantillas sueltas y el resto de K no llaman
 *     a la conversión; K no contiene reglas de traducción (ni `Translator` ni mapas), sólo `Converter` y `Registry`.
 *  8. La referencia `slides.*.digitalisimo_template_id` vale también para las Diapositivas propias (`digitalisimo-slides`).
 */

namespace {
	require __DIR__ . '/sources/kits/wp-stubs.php';
	$E = dirname( __DIR__ ) . '/digitalisimo-elements';
	$M = $E . '/modules/digitalisimo-import';
	require $M . '/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	use Digitalisimo\Elements\Import\Kits\Context;
	use Digitalisimo\Elements\Import\Kits\Pro_Convert;
	use Digitalisimo\Elements\Import\Kits\Report;
	use Digitalisimo\Elements\Import\Kits\References;

	$failures = 0;
	$checks   = 0;
	$check    = static function ( $ok, string $message ) use ( &$failures, &$checks ) {
		++$checks;
		if ( ! $ok ) {
			++$failures;
			echo "FALLA: {$message}\n";
		}
	};
	$read = static function ( string $file ): string {
		return (string) file_get_contents( $file );
	};

	final class Kit_Pro_Document {
		public $id;
		public function __construct( int $id ) {
			$this->id = $id;
		}
		public function get_main_id() {
			return $this->id;
		}
	}
	function kpc_w( string $type, array $settings, string $id, array $children = array() ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => $children );
	}
	function kpc_box( string $type, array $children, string $id, array $settings = array() ): array {
		return array( 'id' => $id, 'elType' => $type, 'settings' => $settings, 'elements' => $children, 'isInner' => false );
	}
	function kpc_find( array $elements, string $id ): ?array {
		foreach ( $elements as $element ) {
			if ( (string) ( $element['id'] ?? '' ) === $id ) {
				return $element;
			}
			$hit = kpc_find( (array) ( $element['elements'] ?? array() ), $id );
			if ( null !== $hit ) {
				return $hit;
			}
		}
		return null;
	}
	/** Códigos del informe de la sesión desde `$from`: `código:widget:ajuste`. */
	function kpc_codes( int $from = 0 ): array {
		$notes   = Context::notes();
		$entries = array_slice( (array) ( $notes['report']['entries'] ?? array() ), $from );
		return array_map(
			static function ( $entry ) {
				return $entry['code'] . ':' . ( $entry['data']['widget'] ?? '' ) . ':' . ( $entry['data']['ajuste'] ?? '' );
			},
			$entries
		);
	}
	function kpc_entries(): int {
		$notes = Context::notes();
		return count( (array) ( $notes['report']['entries'] ?? array() ) );
	}

	kt_reset();

	// Un documento con todo lo que puede llevar un kit: sección/columna, contenedores anidados, un widget anidado con
	// hijos, un widget de PRO sin mapa listo y uno propio.
	$quote = array(
		'blockquote_content' => 'Citar es recordar',
		'author_name'        => 'Ana',
		'blockquote_skin'    => 'quotation',
		'tweet_button'       => '',
		'custom_css'         => 'selector .elementor-blockquote__content { color: red; }',
		'_margin'            => array( 'top' => '10', 'unit' => 'px' ),
		'__globals__'        => array( 'content_text_color' => 'globals/colors?id=primary' ),
	);
	$tree  = array(
		kpc_box( 'section', array( kpc_box( 'column', array( kpc_w( 'blockquote', $quote, 'q1' ), kpc_w( 'nav-menu', array( 'menu' => '3' ), 'n1' ) ), 'c1' ) ), 's1' ),
		kpc_box( 'container', array( kpc_box( 'container', array( kpc_w( 'blockquote', array( 'blockquote_content' => 'Anidada', 'misterio' => 'x' ), 'q2' ) ), 'k2' ) ), 'k1' ),
		kpc_box( 'container', array( kpc_w( 'nested-tabs', array(), 't1', array( kpc_box( 'container', array( kpc_w( 'blockquote', array(), 'q3' ), kpc_w( 'heading', array( 'title' => 'Hola' ), 'h1' ) ), 'tc1' ) ) ) ), 'k3' ),
	);
	$data = array( 'elements' => $tree, 'settings' => array( 'hide_title' => 'yes' ) );

	/* 1. Sin el núcleo de conversión */
	$check( ! Pro_Convert::available(), '1: sin el módulo digitalisimo-convert cargado no hay conversión.' );
	list( $same, $log ) = Pro_Convert::tree( $tree );
	$check( $tree === $same && array() === $log, '1: tree() devuelve el árbol igual y sin códigos.' );
	$before = kpc_entries();
	$check( $data === Context::preserve( $data, new Kit_Pro_Document( 11 ) ) && kpc_entries() === $before, '1: Context::preserve() deja el documento exactamente igual y no informa nada.' );

	/* 2. Con el núcleo y el mapa real de la Cita */
	require $E . '/modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	$registry = '\Digitalisimo\Elements\Convert\Registry';
	// Desde C3 el Menú de WordPress de PRO tiene su mapa listo: aquí hace de widget de PRO sin mapa.
	$registry::set( 'nav-menu', null );
	$check( Pro_Convert::available() && 'digitalisimo-blockquote' === $registry::target( 'blockquote' ), '2: con el módulo cargado, la Cita tiene su mapa listo.' );
	list( $converted, $log ) = Pro_Convert::tree( $tree );
	foreach ( array( 'q1', 'q2', 'q3' ) as $id ) {
		$element = kpc_find( $converted, $id );
		$check( is_array( $element ) && 'digitalisimo-blockquote' === $element['widgetType'] && 'widget' === $element['elType'] && array() === $element['elements'], "2: {$id} llega como Cita con el mismo id." );
	}
	$check( 'Citar es recordar' === kpc_find( $converted, 'q1' )['settings']['blockquote_content'] && 'quotation' === kpc_find( $converted, 'q1' )['settings']['blockquote_skin'] && '' === kpc_find( $converted, 'q1' )['settings']['share_button'], '2: los ajustes los traduce el mapa (keep y rename tweet_button → share_button).' );
	$check( false !== strpos( (string) kpc_find( $converted, 'q1' )['settings']['custom_css'], '.digi-blockquote__content' ) && false === strpos( (string) kpc_find( $converted, 'q1' )['settings']['custom_css'], 'elementor-blockquote' ), '2: el CSS personalizado del elemento apunta a las clases propias.' );
	$check( array( 'top' => '10', 'unit' => 'px' ) === kpc_find( $converted, 'q1' )['settings']['_margin'] && 'globals/colors?id=primary' === ( kpc_find( $converted, 'q1' )['settings']['__globals__']['content_text_color'] ?? '' ), '2: los ajustes comunes y los globales del kit pasan.' );
	$check( 'nav-menu' === kpc_find( $converted, 'n1' )['widgetType'] && kpc_find( $tree, 'n1' ) === kpc_find( $converted, 'n1' ), '2: un widget de PRO sin mapa listo no cambia.' );
	$check( kpc_find( $tree, 'h1' ) === kpc_find( $converted, 'h1' ) && 'nested-tabs' === kpc_find( $converted, 't1' )['widgetType'] && 'tc1' === kpc_find( $converted, 't1' )['elements'][0]['id'], '2: los hijos de un widget anidado se recorren y lo que no es de PRO no cambia.' );
	$check( array_column( $tree, 'id' ) === array_column( $converted, 'id' ) && 'section' === $converted[0]['elType'] && 'column' === $converted[0]['elements'][0]['elType'], '2: la estructura del documento no cambia.' );
	$codes = array_map(
		static function ( $item ) {
			return $item['code'] . ':' . $item['data']['widget'] . ':' . ( $item['data']['ajuste'] ?? '' ) . ':' . $item['level'];
		},
		$log
	);
	$check( 3 === count( array_keys( array_column( $log, 'code' ), 'pro.converted', true ) ), '2: un pro.converted por elemento convertido: ' . implode( ', ', $codes ) );
	$check( in_array( 'pro.css:blockquote:custom_css:info', $codes, true ) && in_array( 'pro.unmapped:blockquote:misterio:warning', $codes, true ), '2: los códigos del traductor con su ajuste y su nivel: ' . implode( ', ', $codes ) );
	foreach ( $log as $item ) {
		$check( 0 === strpos( $item['code'], 'pro.' ) && 'digitalisimo-blockquote' === $item['data']['destino'] && '' !== $item['data']['elemento'], '2: cada código lleva destino y elemento: ' . $item['code'] );
	}

	/* 3. Idempotente y sólo mapas listos */
	list( $again, $log2 ) = Pro_Convert::tree( $converted );
	$check( $again === $converted && array() === $log2, '3: lo ya convertido no cambia ni repite códigos (segundo guardado del núcleo).' );
	$real = $registry::map( 'blockquote' );
	$registry::set( 'blockquote', array( 'status' => 'draft' ) + $real );
	list( $draft, $log3 ) = Pro_Convert::tree( $tree );
	$check( $draft === $tree && array() === $log3, '3: con el mapa en borrador no se convierte nada.' );
	$registry::set( 'media-carousel', array( 'source' => 'media-carousel', 'target' => 'digitalisimo-media-carousel', 'status' => 'draft', 'keep' => array( 'slides' ) ) );
	list( $draft2 ) = Pro_Convert::tree( array( kpc_w( 'media-carousel', array( 'slides' => array() ), 'm1' ) ) );
	$check( 'media-carousel' === $draft2[0]['widgetType'], '3: un mapa de C1 en borrador no convierte.' );
	$registry::set( 'blockquote', $real );

	/* 4. Falla cerrado sin el widget propio */
	list( $kept, $log4 ) = Pro_Convert::tree( $tree, array( 'known' => static function ( $widget ) { return 'digitalisimo-blockquote' !== $widget; } ) );
	$check( $kept === $tree && array( 'pro.target_missing', 'pro.target_missing', 'pro.target_missing' ) === array_column( $log4, 'code' ) && Report::section_for( 'pro.target_missing' ) === 'converted', '4: sin la Cita registrada el Blockquote se queda y se informa pro.target_missing.' );

	/* 5. Context::preserve() y el informe de la sesión */
	$before = kpc_entries();
	$saved  = Context::preserve( $data, new Kit_Pro_Document( 42 ) );
	$check( 'digitalisimo-blockquote' === kpc_find( $saved['elements'], 'q2' )['widgetType'] && $data['settings'] === $saved['settings'], '5: save/data (5) convierte y deja los ajustes del documento.' );
	$notes = Context::notes();
	$new   = array_slice( (array) $notes['report']['entries'], $before );
	$check( count( $new ) === count( $log ) && 42 === (int) ( $new[0]['data']['post'] ?? 0 ) && 'pro.converted' === $new[0]['code'] && Report::section_for( $new[0]['code'] ) === 'converted', '5: los códigos van al informe de la sesión con su documento.' );
	$check( 3 === (int) $notes['report']['counts']['pro.converted'], '5: el contador de pro.converted suma los elementos convertidos.' );
	$before = kpc_entries();
	$check( $saved === Context::preserve( $saved, new Kit_Pro_Document( 42 ) ) && kpc_entries() === $before, '5: el segundo guardado del mismo documento no cambia nada ni informa otra vez.' );
	$registry::set( 'blockquote', array( 'filter' => static function () { throw new RuntimeException( 'mapa roto' ); } ) + $real );
	$out = Context::preserve( $data, new Kit_Pro_Document( 43 ) );
	$check( 'blockquote' === kpc_find( $out['elements'], 'q1' )['widgetType'] && in_array( 'pro.error::', kpc_codes( $before ), true ), '5: un error en la conversión se informa (pro.error) y el documento llega como estaba.' );
	$registry::set( 'blockquote', $real );

	/* 6. Informe */
	$check( isset( Report::SECTIONS['converted'] ) && array( 'pro' ) === Report::SECTION_PREFIXES['converted'], '6: sección propia en Report::SECTIONS y SECTION_PREFIXES.' );
	foreach ( array_merge( array_keys( Pro_Convert::LEVELS ), array( 'pro.error' ) ) as $code ) {
		$check( 'converted' === Report::section_for( $code ) && '' !== Report::code_label( $code ), "6: {$code} cae en «Widgets convertidos» y tiene texto." );
	}
	$check( 'preserved' === Report::section_for( 'preserve.error' ) && 'preserved' === Report::section_for( 'preserved' ), '6: el prefijo pro no se lleva los códigos de preserve.' );
	$page = $read( $M . '/kits/class-report-page.php' );
	$check( false !== strpos( $page, "self::section_open( 'converted' );" ) && false !== strpos( $page, "self::render_entries( \$groups, 'converted' );" ), '6: la pantalla «Kits» pinta la sección.' );
	$grouped = Report::group( array( 'entries' => array( array( 'part' => '', 'code' => 'pro.converted', 'level' => 'info', 'data' => array( 'widget' => 'blockquote' ) ) ), 'counts' => array( 'pro.converted' => 1 ) ) );
	$check( isset( $grouped['converted'] ) && 1 === count( $grouped['converted']['entries'] ), '6: Report::group() agrupa los códigos pro.* en su sección.' );

	/* 7. Sólo en la importación, y sin reglas de traducción en K */
	$hits = array();
	foreach ( glob( $M . '/{,*/,*/*/,*/*/*/}*.php', GLOB_BRACE ) as $file ) {
		if ( 1 === preg_match( "/Pro_Convert(?:::|')/", $read( $file ) ) && basename( $file ) !== 'class-pro-convert.php' ) {
			$hits[] = basename( $file );
		}
	}
	$check( array( 'class-context.php' ) === $hits, '7: sólo Context llama a Pro_Convert: ' . implode( ', ', $hits ) );
	$context = $read( $M . '/kits/class-context.php' );
	$preserve_body = substr( $context, (int) strpos( $context, 'public static function preserve(' ), 1400 );
	$check( false !== strpos( $preserve_body, 'Pro_Convert' ) && strpos( $preserve_body, 'Pro_Convert' ) < strpos( $preserve_body, "'\\\\Preserve'" ), '7: la conversión corre en Context::preserve(), antes del marcador.' );
	$export_hooks = substr( $context, (int) strpos( $context, 'public static function export_hooks(' ), 900 );
	$check( false === strpos( $export_hooks, 'preserve' ) && false === strpos( $export_hooks, 'Pro_Convert' ), '7: exportar no convierte nada.' );
	$own = $read( $M . '/kits/class-pro-convert.php' );
	$check( false === strpos( $own, 'Translator' ) && false === strpos( $own, 'maps/' ) && false !== strpos( $own, 'Convert\\\\Converter' ), '7: K no tiene reglas de traducción: sólo usa Converter y Registry.' );
	$check( false === strpos( $read( $M . '/kits/class-preserve.php' ), 'Pro_Convert' ), '7: las plantillas sueltas de la biblioteca no se convierten (límite declarado en §17).' );
	$guard = array( 'bdthemes', 'element pack', 'element-pack', 'prime slider', 'prime-slider', 'bdt-', 'bdt_', 'ep_' );
	foreach ( $guard as $word ) {
		$check( false === stripos( $own, $word ), "7: K no nombra «{$word}»." );
	}

	/* 8. Referencias de las Diapositivas propias */
	$paths = static function ( string $type ) {
		$out = array();
		foreach ( References::all()['element'][ $type ] ?? array() as $ref ) {
			$out[ $ref['path'] ] = $ref['kind'];
		}
		return $out;
	};
	$check( 'template' === ( $paths( 'digitalisimo-slides' )['slides.*.digitalisimo_template_id'] ?? '' ) && 'template' === ( $paths( 'slides' )['slides.*.digitalisimo_template_id'] ?? '' ), '8: slides.*.digitalisimo_template_id es una plantilla en slides y en digitalisimo-slides.' );

	if ( $failures ) {
		echo "elements-kit-pro-convert: {$failures} de {$checks} comprobaciones fallaron.\n";
		exit( 1 );
	}
	echo "elements-kit-pro-convert: ok ({$checks} comprobaciones: widgets de PRO convertidos al importar un kit, §17).\n";
}
