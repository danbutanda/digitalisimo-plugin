<?php
/**
 * Portfolio de PRO → Portafolio (`digitalisimo-portfolio`, modules/digitalisimo-loop/widgets/class-portfolio.php).
 *
 * El Portafolio repite los controles de PRO (incluido `item_gap`, oculto, para los sitios antiguos, y el grupo de
 * consulta `posts_*`) con el mismo nombre y valores: `keep` y `groups`. El filtro son botones con `aria-pressed` (la
 * clase `elementor-active` del filtro pasa a ese atributo en el CSS personalizado). PRO pone también `elementor-active`
 * en los elementos que el filtro deja a la vista: junto a la clase del elemento pasa a `:not([hidden])`, que es lo que
 * marca el propio (`rewrite_classes()` aplica antes la clave más larga). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'portfolio',
	'target'  => 'digitalisimo-portfolio',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => array( 'columns', 'posts_per_page', 'masonry', 'item_ratio', 'show_title', 'title_tag', 'show_filter_bar', 'taxonomy', 'item_gap', 'column_gap', 'row_gap', 'border_radius', 'color_background', 'color_title', 'color_filter', 'color_filter_active', 'filter_item_spacing', 'filter_spacing' ),
	'groups'  => array(
		'posts'            => 'posts',
		'thumbnail_size'   => 'thumbnail_size',
		'typography_title' => 'typography_title',
		'typography_filter' => 'typography_filter',
	),
	'classes' => array(
		'.elementor-widget-portfolio'       => '.elementor-widget-digitalisimo-portfolio',
		'.elementor-portfolio'              => '.digi-portfolio',
		'.elementor-posts-container'        => '.digi-portfolio',
		'.elementor-portfolio-item'         => '.digi-portfolio__item',
		'.elementor-post'                   => '.digi-portfolio__item',
		'.elementor-post__thumbnail__link'  => '.digi-portfolio__link',
		'.elementor-portfolio-item__img'    => '.digi-portfolio__img',
		'.elementor-post__thumbnail'        => '.digi-portfolio__img',
		'.elementor-portfolio-item__overlay' => '.digi-portfolio__overlay',
		'.elementor-portfolio-item__title'  => '.digi-portfolio__title',
		'.elementor-portfolio__filters'     => '.digi-portfolio__filters',
		'.elementor-portfolio__filter'      => '.digi-portfolio__filter',
		'.elementor-portfolio-item.elementor-active' => '.digi-portfolio__item:not([hidden])',
		'.elementor-post.elementor-active'  => '.digi-portfolio__item:not([hidden])',
		'.elementor-active'                 => '[aria-pressed="true"]',
		'.elementor-posts-masonry'          => '.digi-loop--masonry',
	),
);
