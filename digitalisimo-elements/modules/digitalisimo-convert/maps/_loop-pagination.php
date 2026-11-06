<?php
/**
 * Lo común de los mapas de los listados de C5 con la paginación de Entradas de PRO (`posts/widgets/posts-base.php` con
 * `Button_Widget_Trait`), que la base propia (`modules/digitalisimo-loop/class-posts-base.php`) repite con los mismos
 * nombres: claves que se conservan, grupos, controles sin valor, pérdidas declaradas y clases del pie. Lo suman los
 * mapas que lo usan (los bucles y, por `_posts.php`, Entradas y Entradas del archivo);
 * `Registry` ignora los archivos de `maps/` que empiezan por `_`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'keep'    => array(
		'pagination_type',
		'pagination_page_limit',
		'pagination_numbers_shorten',
		'pagination_prev_label',
		'pagination_next_label',
		'pagination_align',
		'pagination_individual_handle',
		'load_more_spinner',
		'load_more_no_posts_message_align',
		'load_more_no_posts_message_switcher',
		'load_more_no_posts_custom_message',
		'pagination_color',
		'pagination_hover_color',
		'pagination_active_color',
		'pagination_spacing',
		'pagination_spacing_top',
		'load_more_no_posts_message_color',
		'load_more_spinner_color',
		// Botón «Cargar más».
		'text',
		'selected_icon',
		'icon',
		'icon_align',
		'icon_indent',
		'button_css_id',
		'content_align',
		'button_text_color',
		'hover_color',
		'button_hover_border_color',
		'button_hover_transition_duration',
		'hover_animation',
		'border_radius',
		'text_padding',
	),
	'groups'  => array(
		'pagination_typography'      => 'pagination_typography',
		'load_more_no_posts_message' => 'load_more_no_posts_message',
		'typography'                 => 'typography',
		'text_shadow'                => 'text_shadow',
		'background'                 => 'background',
		'button_box_shadow'          => 'button_box_shadow',
		'button_background_hover'    => 'button_background_hover',
		'button_hover_box_shadow'    => 'button_hover_box_shadow',
		'border'                     => 'border',
	),
	// «Separación» de «Cargar más»: el control de PRO escribe `--load-more—spacing` (raya larga), pero sus hojas
	// (`widget-posts.min.css`, `widget-loop-grid.min.css`; igual en PRO Elements 4.3.2 oficial) leen la variable con la
	// raya codificada dos veces (`--load-moreвЂ”spacing`), así que el botón, el cargador y el mensaje quedan siempre a
	// 30 px. El propio sí la aplica: lo convertido la descarta para verse igual (sin valor, el propio deja 30 px).
	'drop'    => array(
		'load_more_spacing' => 'PRO no aplicaba la separación de «Cargar más»: su hoja lee otra variable (la raya mal codificada), así que el botón, el cargador y el mensaje quedaban siempre a 30 px; el propio deja los mismos 30 px.',
	),
	'ui'      => array(
		'pagination_individual_divider',
		'pagination_individual_handle_message',
		'heading_load_more_button',
		'heading_load_more_no_posts_message',
		'heading_load_more_style_button',
		'heading_load_more_on_click_no_posts_message',
		'heading_load_more_on_click_infinity_scroll_no_posts_message',
		'pagination_color_heading',
		// `Posts_Base` los quita tras registrarlos (`remove_control()`): nunca se guardan.
		'button_type',
		'link',
		'size',
	),
	'classes' => array(
		'.elementor-pagination' => '.digi-loop__pagination',
		'.e-load-more-anchor'   => '.digi-loop__anchor',
		'.e-load-more-message'  => '.digi-loop__message',
		'.e-load-more-spinner'  => '.digi-loop__spinner',
	),
);
