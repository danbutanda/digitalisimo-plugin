<?php
/** Element Pack Pro 9.9.1 `bdt-tabs` → `digitalisimo-content-switcher`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-content-switcher',
	// Clases de Element Pack más usadas en sus selectores: .bdt-tab, .bdt-tabs-item-title, .bdt-tabs-item, .bdt-tabs, .bdt-active, .bdt-tab-title-icon, .bdt-switcher-wrapper, .bdt-switcher-item-content, .bdt-grid-stack, .bdt-tab-wrapper, .bdt-tab-sub-title, .bdt-tabs-wrap-inside, .bdt-grid, .bdt-tab-title-icon-wrapper
	'classes'   => array(
		'.bdt-tabs .bdt-tabs-item-title' => '.digi-content-switcher__tab',
		'.bdt-tabs-item-title'           => '.digi-content-switcher__tab',
		'.bdt-tab-content'               => '.digi-content-switcher__panel',
		'.bdt-tabs'                      => '.digi-content-switcher',
	),
	'defaults'  => array(
		'tab_layout' => 'default',
		'content_spacing' => array(
			'size' => 20,
		),
		'tab_transition' => '',
		'duration' => array(
			'size' => 200,
		),
		'media' => 960,
		'nav_sticky_offset' => array(
			'size' => 1,
			'unit' => 'px',
		),
		'swiping_on_mobile' => 'yes',
		'active_hash' => 'no',
		'hash_top_offset' => array(
			'unit' => 'px',
			'size' => 70,
		),
		'hash_scrollspy_time' => array(
			'unit' => 'px',
			'size' => 1500,
		),
		'tabs_match_height' => 'yes',
		'section_bg_anim' => 'none',
		'icon_space' => array(
			'size' => 8,
		),
	),
	'repeaters' => array(
		'tabs' => array(
			'defaults'     => array(
				'tab_title' => 'Tab Title',
				'source' => 'custom',
				'tab_content' => 'Tab Content',
				'external_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'tab_title' => 'Tab #1',
					'tab_content' => 'I am tab #1 content. Click edit button to change this text. One morning, when Gregor Samsa woke from troubled dreams, he found himself transformed in his bed into a horrible vermin.',
				), array(
					'tab_title' => 'Tab #2',
					'tab_content' => 'I am tab #2 content. Click edit button to change this text. A collection of textile samples lay spread out on the table - Samsa was a travelling salesman.',
				), array(
					'tab_title' => 'Tab #3',
					'tab_content' => 'I am tab #3 content. Click edit button to change this text. Drops of rain could be heard hitting the pane, which made him feel quite sad. How about if I sleep a little bit longer and forget all this nonsense.',
				) ),
		),
		'section_bg_list' => array(
			'defaults'     => array(
				'section_bg' => array(
					'url' => $placeholder_url,
				),
			),
			'default_rows' => array(),
		),
	),
	// Las pestañas de Element Pack admiten contenido, plantilla o sección enlazada, como el alternador propio.
	'filter'    => static function ( array $out ) {
		$item = static function ( $source, $title, $icon, $content, $template, $section ) {
			$row = array( 'title' => $title, 'switcher_icon' => $icon );
			if ( 'elementor' === $source ) {
				$row += array( 'content_type' => 'template', 'template_id' => $template );
			} elseif ( in_array( $source, array( 'custom_section', 'link_section', 'link_widget' ), true ) ) {
				$row += array( 'content_type' => 'link_section', 'link_target' => $section );
			} else {
				$row += array( 'content_type' => 'content', 'content' => 'anywhere' === $source ? '' : $content );
			}
			return $row;
		};
		$items  = array();
		$active = max( 1, (int) ( $out['active_item'] ?? 1 ) );
		foreach ( (array) ( $out['tabs'] ?? array() ) as $index => $tab ) {
			$row = $item( (string) ( $tab['source'] ?? 'custom' ), (string) ( $tab['tab_title'] ?? '' ), $tab['tab_select_icon'] ?? array(), (string) ( $tab['tab_content'] ?? '' ), $tab['template_id'] ?? '', (string) ( 'link_widget' === ( $tab['source'] ?? '' ) ? ( $tab['source_link_widget'] ?? '' ) : ( $tab['source_link_section'] ?? '' ) ) );
			if ( 'external_link' === ( $tab['source'] ?? '' ) && ! empty( $tab['external_link']['url'] ) ) {
				$row = array( 'title' => $row['title'], 'switcher_icon' => $row['switcher_icon'], 'content_type' => 'content', 'content' => '<p><a href="' . esc_url( $tab['external_link']['url'] ) . '">' . esc_html( $row['title'] ) . '</a></p>' );
			}
			if ( $index + 1 === $active ) {
				$row['switcher_active'] = 'yes';
			}
			$items[] = $row;
		}
		$out['switcher_items'] = $items;
		unset( $out['tabs'], $out['section_bg_list'] );
		return $out;
	},
);
