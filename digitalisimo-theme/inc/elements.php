<?php
/**
 * DIGITALÍSIMO: ubicaciones del constructor de temas. Cabecera y pie se registran en los ganchos del tema: el
 * constructor imprime su plantilla en la prioridad 5 y, si imprimió algo, retira el respaldo de la 10.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'elementor/theme/register_locations', 'digitalisimo_theme_register_locations' ); // 10 < 99 (theme-support.php:30).
add_filter( 'digitalisimo_conditions_theme_locations', 'digitalisimo_theme_condition_locations' );

/** Imprime la ubicación del constructor; false sin constructor o sin plantilla que aplique. */
function digitalisimo_theme_location( $location ) {
	return function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location );
}

/** Ubicaciones por gancho del tema (gancho => etiqueta). */
function digitalisimo_theme_hook_locations() {
	return array(
		'digitalisimo_before_header'       => __( 'Before header', 'digitalisimo-theme' ),
		'digitalisimo_after_header'        => __( 'After header', 'digitalisimo-theme' ),
		'digitalisimo_before_main_content' => __( 'Before main content', 'digitalisimo-theme' ),
		'digitalisimo_before_entry_title'  => __( 'Before title', 'digitalisimo-theme' ),
		'digitalisimo_after_entry_title'   => __( 'After title', 'digitalisimo-theme' ),
		'digitalisimo_after_entry_content' => __( 'After content', 'digitalisimo-theme' ),
		'digitalisimo_before_comments'     => __( 'Before comments', 'digitalisimo-theme' ),
		'digitalisimo_after_main_content'  => __( 'After main content', 'digitalisimo-theme' ),
		'digitalisimo_before_footer'       => __( 'Before footer', 'digitalisimo-theme' ),
		'digitalisimo_after_footer'        => __( 'After footer', 'digitalisimo-theme' ),
	);
}

/** Registra las cuatro ubicaciones del núcleo y las de los ganchos `digitalisimo_*`. */
function digitalisimo_theme_register_locations( $manager ) {
	if ( ! is_object( $manager ) || ! apply_filters( 'digitalisimo_theme_register_locations', true, $manager ) ) {
		return;
	}
	$manager->register_core_location(
		'header',
		array(
			'hook'         => 'digitalisimo_header',
			'remove_hooks' => array( 'digitalisimo_theme_header' ),
		)
	);
	$manager->register_core_location(
		'footer',
		array(
			'hook'         => 'digitalisimo_footer',
			'remove_hooks' => array( 'digitalisimo_theme_footer' ),
		)
	);
	$manager->register_core_location( 'archive' );
	$manager->register_core_location( 'single' );
	foreach ( (array) apply_filters( 'digitalisimo_theme_hook_locations', digitalisimo_theme_hook_locations() ) as $hook => $label ) {
		if ( 0 === strpos( (string) $hook, 'digitalisimo_' ) ) {
			$manager->register_location(
				$hook,
				array(
					'label'           => $label,
					'multiple'        => true,
					'public'          => true,
					'edit_in_content' => false,
					'hook'            => $hook,
				)
			);
		}
	}
}

/** Condiciones de visitante: con la cabecera se imprimen sus cuatro ganchos; con single o archive, los seis del contenido. */
function digitalisimo_theme_condition_locations( $locations ) {
	$locations = array_map( 'strval', (array) $locations );
	if ( in_array( 'header', $locations, true ) ) {
		array_push( $locations, 'digitalisimo_before_header', 'digitalisimo_after_header', 'digitalisimo_before_footer', 'digitalisimo_after_footer' );
	}
	if ( in_array( 'single', $locations, true ) || in_array( 'archive', $locations, true ) ) {
		array_push( $locations, 'digitalisimo_before_main_content', 'digitalisimo_before_entry_title', 'digitalisimo_after_entry_title', 'digitalisimo_after_entry_content', 'digitalisimo_before_comments', 'digitalisimo_after_main_content' );
	}
	return array_values( array_unique( $locations ) );
}
