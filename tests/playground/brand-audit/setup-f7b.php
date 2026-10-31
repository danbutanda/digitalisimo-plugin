<?php
/**
 * Paso del blueprint de brand-audit.sh (F7b), en otra petición con DIGITALÍSIMO Elements ya cargado: la página
 * `digi-marca-f7b` con un elemento por capacidad de F7b (acciones con destino, «Oculto al cargar», consulta guardada,
 * los cuatro filtros nuevos, sobrescrituras del Template, datos dentro del texto, formulario condicional, slider anidado
 * y presentación de pestañas), la plantilla `digi-marca-f7b-plantilla` con una clave de sobrescritura y una consulta
 * guardada. Todo con `Document::save()` como administrador (banderas y usos al día) y `Queries\Store::save()` como la
 * pantalla «Consultas». Añade a `ids.json` las claves `f7b` (página), `f7bTemplate` (plantilla) y `f7bQuery` (consulta).
 */
require '/wordpress/wp-load.php';
$digi_dir = '/wordpress/wp-content/probe';
$digi_fail = static function ( $message ) use ( $digi_dir ) {
	file_put_contents( $digi_dir . '/setup-error.txt', 'F7b: ' . $message . "\n", FILE_APPEND );
};
if ( ! class_exists( '\\Digitalisimo\\Elements\\F7b\\Module' ) || ! class_exists( '\\Elementor\\Plugin' ) ) {
	$digi_fail( 'el módulo de F7b o el constructor no están cargados' );
	return;
}
wp_set_current_user( 1 );

// Consulta guardada (C2).
$store    = '\\Digitalisimo\\Elements\\F7b\\Queries\\Store';
$query_id = 0;
if ( class_exists( $store ) ) {
	$store::register();
	$found    = get_posts( array( 'post_type' => 'digitalisimo_query', 'title' => 'Entradas recientes (marca)', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	$saved    = $store::save( array( 'title' => 'Entradas recientes (marca)', 'settings' => array( 'post_type' => 'post', 'orderby' => 'post_date', 'order' => 'desc' ), 'relation' => 'none', 'limits' => array( 'per_page' => 3 ) ), $found ? (int) $found[0] : 0 );
	$query_id = is_wp_error( $saved ) ? 0 : (int) $saved;
	if ( ! $query_id ) {
		$digi_fail( 'no se pudo guardar la consulta' );
	}
}

$reserve = static function ( $post_type, $slug, $title, $type ) {
	$found = get_posts( array( 'post_type' => $post_type, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	$id    = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', $type );
	if ( 'elementor_library' === $post_type ) {
		wp_set_object_terms( $id, $type, 'elementor_library_type' );
		if ( 'loop-item' === $type ) {
			update_post_meta( $id, '_elementor_source', 'post' );
		}
	}
	return $id;
};
$w   = static function ( $id, $type, array $settings = array(), array $children = array() ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'isInner' => false, 'settings' => $settings, 'elements' => $children );
};
$c   = static function ( $id, array $settings, array $children ) {
	return array( 'id' => $id, 'elType' => 'container', 'isInner' => false, 'settings' => $settings, 'elements' => $children );
};
$save = static function ( $id, array $elements ) use ( $digi_fail ) {
	$document = \Elementor\Plugin::$instance->documents->get( $id, false );
	if ( ! $document || false === $document->save( array( 'elements' => $elements, 'settings' => array( 'post_status' => 'publish' ) ) ) ) {
		$digi_fail( 'Document::save() falló en ' . $id );
	}
};

$template = $reserve( 'elementor_library', 'digi-marca-f7b-plantilla', 'Plantilla con claves (marca)', 'section' );
$loop     = $reserve( 'elementor_library', 'digi-marca-f7b-bucle', 'Bucle (marca)', 'loop-item' );
$page     = $reserve( 'page', 'digi-marca-f7b', 'Capacidades F7b (marca)', 'wp-page' );
$save( $template, array( $c( 'mf7bt01', array(), array( $w( 'mf7bt02', 'heading', array( 'title' => 'Título de la plantilla', '_digitalisimo_slot' => 'titulo' ) ) ) ) ) );
$save( $loop, array( $c( 'mf7bl01', array(), array( $w( 'mf7bl02', 'theme-post-title', array( 'header_size' => 'h3' ) ) ) ) ) );
$tabs = array();
foreach ( array( 'Uno', 'Dos' ) as $i => $label ) {
	$tabs[] = $c( 'mf7bp1' . $i, array( 'content_width' => 'full' ), array( $w( 'mf7bp2' . $i, 'heading', array( 'title' => 'Panel ' . $label ) ) ) );
}
$slides = array();
foreach ( array( 'Uno', 'Dos', 'Tres' ) as $i => $label ) {
	$slides[] = $c( 'mf7bs1' . $i, array( 'content_width' => 'full' ), array( $w( 'mf7bs2' . $i, 'heading', array( 'title' => 'Diapositiva ' . $label ) ) ) );
}
$save(
	$page,
	array(
		$c(
			'mf7b000',
			array( 'flex_direction' => 'column' ),
			array(
				$w( 'mf7b001', 'button', array( 'text' => 'Mostrar el panel', 'link' => array( 'url' => '#' ), '_digitalisimo_actions_on' => 'yes', '_digitalisimo_actions' => array( array( '_id' => 'a1', 'trigger' => 'click', 'action' => 'toggle', 'target_kind' => 'element', 'target' => 'mf7b002' ) ) ) ),
				$c( 'mf7b002', array( '_digitalisimo_hidden_initial' => 'yes' ), array( $w( 'mf7b012', 'heading', array( 'title' => 'Panel oculto al cargar' ) ) ) ),
				$w( 'mf7b003', 'heading', array( 'title' => 'Hola {post:title}', 'digitalisimo_inline_tags' => 'yes' ) ),
				$w( 'mf7b005', 'digitalisimo-loop-search', array( 'destination' => 'loop', 'selected_element' => 'mf7b004', 'label' => 'Buscar' ) ),
				$w( 'mf7b015', 'digitalisimo-loop-meta-filter', array( 'destination' => 'loop', 'selected_element' => 'mf7b004', 'meta_key' => '__custom', 'meta_key_custom' => 'precio', 'value_type' => 'DECIMAL', 'mode' => 'range', 'range_min' => '0', 'range_max' => '100' ) ),
				$w( 'mf7b025', 'digitalisimo-loop-orderby', array( 'destination' => 'loop', 'selected_element' => 'mf7b004', 'orders' => array( array( '_id' => 'o1', 'order_key' => 'title_asc' ), array( '_id' => 'o2', 'order_key' => 'date_desc' ) ) ) ),
				$w( 'mf7b035', 'digitalisimo-loop-per-page', array( 'destination' => 'loop', 'selected_element' => 'mf7b004', 'values' => '3, 6' ) ),
				$w( 'mf7b004', 'loop-grid', array( 'template_id' => (string) $loop, 'posts_per_page' => 3, 'digitalisimo_saved_query' => (string) $query_id ) ),
				$w( 'mf7b006', 'template', array( 'template_id' => (string) $template, 'digitalisimo_overrides' => array( array( '_id' => 'o1', 'slot' => 'titulo', 'kind' => 'text', 'text' => 'Título sobrescrito' ) ) ) ),
				$w(
					'mf7b007',
					'form',
					array(
						'form_name'      => 'Formulario condicional (marca)',
						'submit_actions' => array(),
						'form_fields'    => array(
							array( '_id' => 'mfc0001', 'custom_id' => 'tipo', 'field_type' => 'radio', 'field_label' => 'Tipo', 'field_options' => "Persona|persona\nEmpresa|empresa" ),
							array( '_id' => 'mfc0002', 'custom_id' => 'rfc', 'field_type' => 'text', 'field_label' => 'RFC', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( '_id' => 'mfr0001', 'field' => 'tipo', 'operator' => 'is', 'value' => 'empresa' ) ) ),
						),
					)
				),
				$w( 'mf7b008', 'digitalisimo-nested-slider', array( 'carousel_label' => 'Slider de marca', 'slides' => array( array( '_id' => 's1', 'slide_title' => 'Uno' ), array( '_id' => 's2', 'slide_title' => 'Dos' ), array( '_id' => 's3', 'slide_title' => 'Tres' ) ) ), $slides ),
				$w( 'mf7b009', 'nested-tabs', array( 'digitalisimo_presentation' => 'pills', 'tabs' => array( array( '_id' => 't1', 'tab_title' => 'Uno' ), array( '_id' => 't2', 'tab_title' => 'Dos' ) ) ), $tabs ),
				$w( 'mf7b010', 'heading', array( 'title' => 'Otro encabezado (Aplicar a todos)' ) ),
			)
		),
	)
);
if ( class_exists( '\\Digitalisimo\\Elements\\F7b\\Flags' ) ) {
	\Digitalisimo\Elements\F7b\Flags::refresh();
}
$digi_ids                = json_decode( (string) @file_get_contents( $digi_dir . '/ids.json' ), true ) ?: array(); // phpcs:ignore
$digi_ids['f7b']         = (int) $page;
$digi_ids['f7bTemplate'] = (int) $template;
$digi_ids['f7bQuery']    = (int) $query_id;
file_put_contents( $digi_dir . '/ids.json', wp_json_encode( $digi_ids ) );
