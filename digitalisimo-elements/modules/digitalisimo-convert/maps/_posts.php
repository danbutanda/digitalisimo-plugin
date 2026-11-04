<?php
/**
 * Lo común a los mapas de Entradas y Entradas del archivo (`posts.php`, `archive-posts.php`): no es un mapa (`Registry`
 * ignora `maps/_*.php`). Devuelve una función `( array $skins ) => array` con las claves `keep`, `groups`, `defaults`,
 * `drop` y `classes` de los controles de las pieles (prefijados por la piel, como en PRO), de la paginación y del botón
 * «Cargar más» (`Posts_Base`), y las clases de PRO → las propias (`digi-posts__*`, `digi-loop__*`) para el CSS
 * personalizado del elemento.
 */
defined( 'ABSPATH' ) || exit;

return static function ( array $skins ): array {
	$t = static function ( $text ) {
		// Los textos por defecto que PRO pinta en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
		return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
	};
	// Controles de cada piel (sin prefijo): los de `skin-base.php`, `skin-classic.php`, `skin-cards.php` y
	// `skin-content-base.php` de PRO. Los de una piel que no los tiene no llegan a guardarse.
	$controls = array( 'columns', 'posts_per_page', 'thumbnail', 'masonry', 'item_ratio', 'image_width', 'show_title', 'title_tag', 'show_excerpt', 'excerpt_length', 'apply_to_custom_excerpt', 'meta_data', 'meta_separator', 'show_read_more', 'read_more_text', 'read_more_alignment', 'open_new_tab', 'show_badge', 'badge_taxonomy', 'show_avatar', 'row_gap', 'column_gap', 'alignment', 'box_border_width', 'box_border_radius', 'box_padding', 'content_padding', 'box_bg_color', 'box_border_color', 'box_bg_color_hover', 'box_border_color_hover', 'img_border_radius', 'image_spacing', 'heading_title_style', 'title_color', 'title_spacing', 'heading_meta_style', 'meta_color', 'meta_separator_color', 'meta_spacing', 'heading_excerpt_style', 'excerpt_color', 'excerpt_spacing', 'heading_readmore_style', 'read_more_color', 'read_more_spacing', 'card_bg_color', 'card_border_color', 'card_border_width', 'card_border_radius', 'card_padding', 'card_vertical_padding', 'box_shadow_box_shadow_type', 'hover_effect', 'meta_border_color', 'heading_badge_style', 'badge_position', 'badge_bg_color', 'badge_color', 'badge_radius', 'badge_size', 'badge_margin', 'heading_avatar_style', 'avatar_size' );
	$groups   = array( 'thumbnail_size', 'box_shadow', 'box_shadow_hover', 'thumbnail_filters', 'thumbnail_hover_filters', 'title_typography', 'text_stroke', 'meta_typography', 'excerpt_typography', 'read_more_typography', 'badge_typography' );
	$out      = array(
		// Paginación y botón «Cargar más» (`posts-base.php` y `button-widget-trait.php` de PRO).
		'keep'     => array( 'pagination_type', 'pagination_page_limit', 'pagination_numbers_shorten', 'pagination_prev_label', 'pagination_next_label', 'pagination_align', 'pagination_individual_divider', 'pagination_individual_handle', 'pagination_individual_handle_message', 'load_more_spinner', 'heading_load_more_button', 'text', 'selected_icon', 'icon', 'icon_align', 'icon_indent', 'button_css_id', 'heading_load_more_no_posts_message', 'load_more_no_posts_message_align', 'load_more_no_posts_message_switcher', 'load_more_no_posts_custom_message', 'pagination_color_heading', 'pagination_color', 'pagination_hover_color', 'pagination_active_color', 'pagination_spacing', 'pagination_spacing_top', 'heading_load_more_style_button', 'align', 'content_align', 'button_text_color', 'hover_color', 'button_hover_border_color', 'button_hover_transition_duration', 'hover_animation', 'border_radius', 'text_padding', 'heading_load_more_on_click_no_posts_message', 'heading_load_more_on_click_infinity_scroll_no_posts_message', 'load_more_no_posts_message_color', 'load_more_spinner_color', 'load_more_spacing' ),
		'groups'   => array(),
		'defaults' => array(
			'pagination_prev_label'             => $t( '&laquo; Previous' ),
			'pagination_next_label'             => $t( 'Next &raquo;' ),
			'text'                              => $t( 'Load More' ),
			'load_more_no_posts_custom_message' => $t( 'No more posts to show' ),
		),
		// PRO los quita del botón «Cargar más» (`remove_control()`): no se pintan ni se guardan desde el editor.
		'drop'     => array(
			'button_type' => 'PRO quita el tipo del botón «Cargar más»: no tenía efecto.',
			'link'        => 'PRO quita el enlace del botón «Cargar más» (pinta «#»): el propio es un botón sin enlace.',
			'size'        => 'PRO quita el tamaño del botón «Cargar más»: siempre pequeño.',
		),
	);
	foreach ( array( 'pagination_typography', 'typography', 'text_shadow', 'background', 'button_box_shadow', 'button_background_hover', 'button_hover_box_shadow', 'border', 'load_more_no_posts_message' ) as $group ) {
		$out['groups'][ $group ] = $group;
	}
	foreach ( $skins as $skin ) {
		foreach ( $controls as $control ) {
			$out['keep'][] = $skin . '_' . $control;
		}
		foreach ( $groups as $group ) {
			$out['groups'][ $skin . '_' . $group ] = $skin . '_' . $group;
		}
		if ( false === strpos( $skin, 'full_content' ) ) { // Contenido completo no tiene «Leer más».
			$out['defaults'][ $skin . '_read_more_text' ] = $t( 'Read More »' );
		}
	}
	$out['classes'] = array(
		'.elementor-posts-container'        => '.digi-posts',
		'.elementor-posts'                  => '.digi-posts',
		'.elementor-post'                   => '.digi-posts__item',
		'.elementor-post__card'             => '.digi-posts__card',
		'.elementor-post__thumbnail__link'  => '.digi-posts__thumb-link',
		'.elementor-post__thumbnail'        => '.digi-posts__thumb',
		'.elementor-post__text'             => '.digi-posts__text',
		'.elementor-post__title'            => '.digi-posts__title',
		'.elementor-post__excerpt'          => '.digi-posts__excerpt',
		'.elementor-post__meta-data'        => '.digi-posts__meta',
		'.elementor-post-author'            => '.digi-posts__author',
		'.elementor-post-date'              => '.digi-posts__date',
		'.elementor-post-time'              => '.digi-posts__time',
		'.elementor-post-avatar'            => '.digi-posts__comments',
		'.elementor-post__read-more'        => '.digi-posts__more',
		'.elementor-post__read-more-wrapper' => '.digi-posts__more-wrap',
		'.elementor-post__badge'            => '.digi-posts__badge',
		'.elementor-post__avatar'           => '.digi-posts__avatar',
		'.elementor-post__content'          => '.digi-posts__content',
		'.elementor-post__taxonomies'       => '.digi-posts__taxonomies',
		'.elementor-post__terms'            => '.digi-posts__terms',
		'.elementor-post__terms--category'  => '.digi-posts__terms--category',
		'.elementor-post__terms--post_tag'  => '.digi-posts__terms--post_tag',
		'.elementor-posts-nothing-found'    => '.digi-posts__nothing',
		'.elementor-posts-masonry'          => '.digi-loop--masonry',
		'.elementor-card-shadow-yes'        => '.digi-posts--shadow-yes',
		'.elementor-posts__hover-gradient'  => '.digi-posts--hover-gradient',
		'.elementor-posts__hover-none'      => '.digi-posts--hover-none',
		'.elementor-posts--show-avatar'     => '.digi-posts--show-avatar',
		'.elementor-posts--show-thumbnail'  => '.digi-posts--show-thumbnail',
		'.elementor-pagination'             => '.digi-loop__pagination',
		'.e-load-more-anchor'               => '.digi-loop__anchor',
		'.e-load-more-spinner'              => '.digi-loop__spinner',
		'.e-load-more-message'              => '.digi-loop__message',
		'.e-load-more-pagination-end'       => '.digi-loop--end',
		'.e-load-more-pagination-loading'   => '.digi-loop--loading',
	);
	foreach ( array( 'classic', 'cards', 'full_content', 'archive_full_content' ) as $skin ) {
		$out['classes'][ '.elementor-posts--skin-' . $skin ] = '.digi-posts--skin-' . $skin;
	}
	foreach ( array( 'top', 'left', 'right', 'none' ) as $position ) {
		$out['classes'][ '.elementor-posts--thumbnail-' . $position ] = '.digi-posts--thumbnail-' . $position;
	}
	foreach ( array( 'left', 'center', 'right' ) as $align ) {
		$out['classes'][ '.elementor-posts--align-' . $align ] = '.digi-posts--align-' . $align;
	}
	foreach ( array( 'left', 'center', 'right', 'justify', 'start', 'end' ) as $align ) {
		$out['classes'][ '.load-more-align-' . $align ] = '.digi-loop--more-align-' . $align;
	}
	// Sin `_skin` guardado, la piel que el widget de PRO pinta hoy en este sitio (su primera piel registrada): así el
	// widget propio se ve igual. Elementor no guarda la piel cuando coincide con la de por defecto.
	$out['skin_filter'] = static function ( string $widget, string $fallback ) {
		return static function ( array $own, array $source ) use ( $widget, $fallback ) {
			if ( isset( $source['_skin'] ) && '' !== $source['_skin'] ) {
				return $own;
			}
			$skin = $fallback;
			if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) && is_object( \Elementor\Plugin::$instance->widgets_manager ) ) {
				$type  = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget );
				$skins = is_object( $type ) && method_exists( $type, 'get_skins' ) ? array_keys( (array) $type->get_skins() ) : array();
				$skin  = $skins ? (string) $skins[0] : $fallback;
			}
			$own['_skin'] = $skin;
			return $own;
		};
	};
	return $out;
};
