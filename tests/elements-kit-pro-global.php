<?php
/**
 * Kits (etapa K, §17 de docs/fusion/k-kits.md) y widget global de PRO (C9 del programa «sin widgets de PRO», contrato
 * docs/pro/c9.md): `Kits\Pro_Convert::on_remapped_data()` y `Kits\Context::global_template()`.
 *
 * Sin WordPress: tests/sources/kits/wp-stubs.php y los archivos reales de digitalisimo-elements/.
 *  1. Sin el núcleo de conversión, nada cambia.
 *  2. Con el núcleo: cada instancia (`widgetType: global` con su `templateID` ya remapeado) pasa al widget global propio
 *     (`digitalisimo-global`, corrección de fidelidad de C9) con el mismo id, el mismo `templateID` y los mismos ajustes,
 *     así que sigue leyendo su plantilla al pintar (la plantilla, un documento más del kit, se convierte al guardarse);
 *     sin plantilla (o con una plantilla de otro tipo o en la papelera), la instancia se queda y se informa
 *     `pro.global_missing`; una plantilla compartida se lee una sola vez; códigos con su nivel y su texto. Con el núcleo
 *     sin el widget global propio registrado, la instancia pasa a ser el elemento de su plantilla (como hasta 4.3.0.179).
 *  3. `Context::global_template()` sólo devuelve plantillas de widget de la biblioteca fuera de la papelera.
 *  4. Lo engancha `Context::remap()` después del remapeo, y nada más llama a la sustitución.
 *  5. Al exportar, `Export\Documents::restore_ids()` repone el id de una instancia exportada como su contenido.
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

	$failures = 0;
	$checks   = 0;
	$check    = static function ( $ok, string $message ) use ( &$failures, &$checks ) {
		++$checks;
		if ( ! $ok ) {
			++$failures;
			echo "FALLA: {$message}\n";
		}
	};
	function kpg_w( string $type, array $settings, string $id ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	function kpg_global( int $template, string $id ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => $template, 'settings' => array( 'title' => 'copia' ), 'elements' => array() );
	}
	function kpg_find( array $elements, string $id ): ?array {
		foreach ( $elements as $element ) {
			if ( (string) ( $element['id'] ?? '' ) === $id ) {
				return $element;
			}
			$hit = kpg_find( (array) ( $element['elements'] ?? array() ), $id );
			if ( null !== $hit ) {
				return $hit;
			}
		}
		return null;
	}

	kt_reset();
	$tree = array( array( 'id' => 'k1', 'elType' => 'container', 'settings' => array(), 'isInner' => false, 'elements' => array( kpg_global( 501, 'g1' ), kpg_global( 502, 'g2' ), kpg_global( 501, 'g3' ), kpg_global( 503, 'g4' ) ) ) );
	$data = array( 'elements' => $tree, 'settings' => array() );

	/* 1. Sin el núcleo */
	$check( ! Pro_Convert::globals_available() && $data === Pro_Convert::on_remapped_data( $data, 7, static function () { return null; } ), '1: sin el núcleo de conversión, el documento queda igual.' );

	/* 2. Con el núcleo */
	require $E . '/modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	$check( Pro_Convert::globals_available(), '2: con el núcleo, la sustitución está disponible.' );
	$reads    = array();
	$contents = array(
		501 => array( kpg_w( 'heading', array( 'title' => 'Global' ), 'th' ) ),
		502 => array( kpg_w( 'blockquote', array( 'blockquote_content' => 'Cita global', 'author_name' => 'Ana' ), 'tq' ) ),
	);
	$resolve  = static function ( int $template ) use ( &$reads, $contents ) {
		$reads[] = $template;
		return $contents[ $template ] ?? null;
	};
	$codes    = array();
	$report   = static function ( string $code, array $data, string $level ) use ( &$codes ) {
		$codes[] = $code . ':' . ( $data['widget'] ?? '' ) . ':' . $level . ':' . ( $data['post'] ?? '' );
	};
	$out = Pro_Convert::on_remapped_data( $data, 7, $resolve, $report );
	$g1  = kpg_find( $out['elements'], 'g1' );
	$g2  = kpg_find( $out['elements'], 'g2' );
	$g1_in = kpg_find( $data['elements'], 'g1' );
	$check( 'digitalisimo-global' === $g1['widgetType'] && 501 === $g1['templateID'] && $g1_in['settings'] === $g1['settings'] && 'g1' === $g1['id'], '2: la instancia pasa al widget global propio, con su plantilla, su id y sus ajustes: ' . json_encode( $g1 ) );
	$check( 'digitalisimo-global' === kpg_find( $out['elements'], 'g3' )['widgetType'] && array( 501, 502, 503 ) === $reads, '2: la plantilla compartida se lee una vez por documento: ' . json_encode( $reads ) );
	$check( 'digitalisimo-global' === $g2['widgetType'] && 502 === $g2['templateID'] && 'g2' === $g2['id'], '2: una Cita de PRO guardada como widget global sigue vinculada a su plantilla (la convierte su propio guardado).' );
	$check( 'global' === kpg_find( $out['elements'], 'g4' )['widgetType'] && 503 === kpg_find( $out['elements'], 'g4' )['templateID'], '2: sin plantilla, la instancia se queda tal cual (falla cerrado).' );
	sort( $codes );
	$check( array( 'pro.global:global:info:7', 'pro.global:global:info:7', 'pro.global:global:info:7', 'pro.global_missing:global:warning:7' ) === $codes, '2: códigos con su nivel y el documento: ' . json_encode( $codes ) );
	$check( $out['elements'] === Pro_Convert::on_remapped_data( $out, 7, $resolve, $report )['elements'], '2: una segunda pasada no cambia nada.' );
	$labels = Report::labels();
	$check( isset( $labels['pro.global'], $labels['pro.global_missing'] ), '2: los dos códigos tienen texto en el informe.' );

	/* 3. Context::global_template() */
	$ok      = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Global' ) );
	$trash   = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'trash', 'post_title' => 'Papelera' ) );
	$section = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Sección' ) );
	$page    = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página' ) );
	foreach ( array( $ok => 'widget', $trash => 'widget', $section => 'section', $page => 'widget' ) as $id => $type ) {
		update_post_meta( $id, '_elementor_template_type', $type );
		update_post_meta( $id, '_elementor_data', wp_json_encode( array( kpg_w( 'heading', array( 'title' => 'T' . $id ), 'x' ) ) ) );
	}
	$check( 'T' . $ok === ( Context::global_template( $ok )[0]['settings']['title'] ?? '' ), '3: una plantilla de widget publicada se lee.' );
	$check( null === Context::global_template( $trash ) && null === Context::global_template( $section ) && null === Context::global_template( $page ) && null === Context::global_template( 99999 ) && null === Context::global_template( 0 ), '3: en la papelera, de otro tipo, fuera de la biblioteca o inexistente: nada.' );

	/* 4. Dónde se engancha */
	$context = (string) file_get_contents( $M . '/kits/class-context.php' );
	$check( false !== strpos( $context, "on_remapped_data( \$result, \$id, array( __CLASS__, 'global_template' ) )" ) && strpos( $context, 'on_remapped_data' ) > strpos( $context, '$class::apply( $data, self::$import, $id )' ), '4: Context::remap() sustituye los widgets globales después del remapeo.' );
	$callers = 0;
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $M, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( '.php' === substr( (string) $file, -4 ) && false !== strpos( (string) file_get_contents( (string) $file ), '::on_remapped_data(' ) ) {
			++$callers;
		}
	}
	$check( 1 === $callers, '4: sólo Context llama a la sustitución (' . $callers . ').' );

	/* 5. Exportar: PRO exporta la instancia como el contenido de su plantilla (on_export) y el núcleo regenera los ids */
	$saved    = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array( kpg_global( 5, 'g1' ), kpg_w( 'heading', array(), 'h1' ) ) ) );
	$box      = array( 'id' => 'rnd2', 'elType' => 'widget', 'widgetType' => 'nested-tabs', 'settings' => array(), 'elements' => array( array( 'id' => 'rnd3', 'elType' => 'container', 'settings' => array(), 'elements' => array( kpg_w( 'heading', array(), 'rnd4' ) ) ) ) );
	$exported = array( array( 'id' => 'rnd0', 'elType' => 'container', 'settings' => array(), 'elements' => array( kpg_w( 'button', array( 'text' => 'De la plantilla' ), 'rnd1' ), kpg_w( 'heading', array(), 'rnd9' ) ) ) );
	$restored = \Digitalisimo\Elements\Import\Kits\Export\Documents::restore_ids( $exported, $saved );
	$check( is_array( $restored ) && 'c1' === $restored[0]['id'] && 'g1' === $restored[0]['elements'][0]['id'] && 'button' === $restored[0]['elements'][0]['widgetType'] && 'h1' === $restored[0]['elements'][1]['id'], '5: la instancia global exportada como su contenido conserva el id de la instancia y el documento, los suyos (antes, el árbol no casaba y quedaban ids aleatorios).' );
	$saved[0]['elements'][0] = kpg_global( 6, 'g2' );
	$exported[0]['elements'][0] = $box;
	$one = \Digitalisimo\Elements\Import\Kits\Export\Documents::restore_ids( $exported, $saved );
	$box['elements'][0]['id'] = 'otro';
	$exported[0]['elements'][0] = $box;
	$two = \Digitalisimo\Elements\Import\Kits\Export\Documents::restore_ids( $exported, $saved );
	$check( is_array( $one ) && $one === $two && 'g2' === $one[0]['elements'][0]['id'] && 7 === strlen( $one[0]['elements'][0]['elements'][0]['id'] ) && 'rnd4' !== $one[0]['elements'][0]['elements'][0]['elements'][0]['id'], '5: los hijos del contenido reciben ids estables (dos exportaciones dan el mismo árbol).' );
	$saved[0]['elements'][1] = kpg_w( 'image', array(), 'i1' );
	$check( null === \Digitalisimo\Elements\Import\Kits\Export\Documents::restore_ids( $exported, $saved ), '5: un nodo que no casa fuera de una instancia global sigue dejando los ids nuevos (null).' );

	if ( $failures ) {
		echo "elements-kit-pro-global: {$failures} de {$checks} comprobaciones fallaron.\n";
		exit( 1 );
	}
	echo "elements-kit-pro-global: ok ({$checks} comprobaciones: widget global de PRO pasado al widget global propio, vinculado a su plantilla, al importar un kit, §17).\n";
}
