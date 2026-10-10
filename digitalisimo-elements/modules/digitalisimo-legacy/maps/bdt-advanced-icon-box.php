<?php
/** Element Pack Pro 9.9.1 `bdt-advanced-icon-box` → `digitalisimo-advanced-icon-box`. */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'digitalisimo-advanced-icon-box',
	'classes'  => array(
		'.bdt-ep-advanced-icon-box-icon-wrap' => '.digi-advanced-icon-box__visual',
		'.bdt-ep-advanced-icon-box-icon'      => '.digi-advanced-icon-box__visual',
		'.bdt-ep-advanced-icon-box-readmore'  => '.digi-advanced-icon-box__more',
		'.bdt-ep-advanced-icon-box-badge span' => '.digi-advanced-icon-box__badge',
		'.bdt-ep-advanced-icon-box-badge'     => '.digi-advanced-icon-box__badge',
		'.bdt-ep-advanced-icon-box-separator' => '.digi-advanced-icon-box__separator',
		'.bdt-ep-advanced-icon-box-title'     => '.digi-advanced-icon-box__title',
		'.bdt-ep-advanced-icon-box-sub-title' => '.digi-advanced-icon-box__subtitle',
		'.bdt-ep-advanced-icon-box-description' => '.digi-advanced-icon-box__description',
		'.bdt-ep-advanced-icon-box-content'   => '.digi-advanced-icon-box__content',
		'.bdt-ep-advanced-icon-box'           => '.digi-advanced-icon-box',
	),
	'defaults' => array(
		'icon_type'        => 'icon',
		'selected_icon'    => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ),
		'image'            => array( 'url' => class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '' ),
		'title_text'       => 'Icon Box Heading',
		'sub_title_text'   => 'Icon Box Sub Heading',
		'description_text' => 'Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.',
		'position'         => 'top',
		'title_size'       => 'h3',
		'readmore'         => 'yes',
		'readmore_text'    => 'Read More',
		'readmore_link'    => array( 'url' => '#' ),
		'badge_text'       => 'POPULAR',
	),
	'values'   => array( 'title_size' => array( 'h1' => 'h2', 'p' => 'div', 'span' => 'div' ) ),
	'drop'     => array( 'icon_vertical_alignment', 'indicator', 'advanced_readmore_icon', 'readmore_icon_align', 'readmore_on_hover', 'badge_position' ),
);
