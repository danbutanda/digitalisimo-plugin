<?php
/** Element Pack Pro 9.9.1 `bdt-switcher` → `digitalisimo-content-switcher`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-content-switcher',
	// Clases de Element Pack más usadas en sus selectores: .bdt-tab, .bdt-tabs-item, .bdt-tabs-container, .bdt-badge, .bdt-tabs-item-a-title, .bdt-tabs-item-b-title, .bdt-switchers, .bdt-active, .bdt-a-badge, .bdt-b-badge, .bdt-button-icon-align-right, .bdt-button-icon-align-left, .bdt-switcher-item-content-inner, .bdt-tab-bottom
	'classes'   => array(
		'.bdt-switcher-item-content-inner' => '.digi-content-switcher__panel',
		'.bdt-switcher .bdt-tab .bdt-tabs-item-title' => '.digi-content-switcher__tab',
		'.bdt-tab .bdt-tabs-item-title' => '.digi-content-switcher__tab',
		'.bdt-switcher'                    => '.digi-content-switcher',
	),
	'defaults'  => array(
		'switch_a_title' => 'Switch A',
		'source_a' => 'custom',
		'switch_a_content' => 'Switch Content A',
		'switch_a_badge' => 'Hot',
		'switch_b_title' => 'Switch B',
		'source_b' => 'custom',
		'switch_b_content' => 'Switch Content B',
		'switch_b_badge' => 'Update',
		'tab_layout' => 'default',
		'tab_transition' => '',
		'duration' => array(
			'size' => 200,
		),
		'default_active' => 'a',
		'icon_space' => array(
			'size' => 8,
		),
		'align' => 'center',
		'content_spacing' => array(
			'size' => 20,
		),
	),
	// Las dos opciones de Element Pack pasan a dos elementos del alternador propio.
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
		$items = array();
		foreach ( array( 'a', 'b' ) as $key ) {
			$items[] = $item(
				(string) ( $out[ 'source_' . $key ] ?? 'custom' ),
				(string) ( $out[ 'switch_' . $key . '_title' ] ?? '' ),
				$out[ 'switch_' . $key . '_select_icon' ] ?? array(),
				(string) ( $out[ 'switch_' . $key . '_content' ] ?? '' ),
				$out[ 'template_id_' . $key ] ?? '',
				(string) ( $out[ 'switch_' . $key . '_custom_section_id' ] ?? ( $out[ 'source_' . $key . '_link_widget' ] ?? '' ) )
			);
		}
		if ( 'b' === ( $out['default_active'] ?? 'a' ) ) {
			$items[1]['switcher_active'] = 'yes';
		}
		$out['switcher_items'] = $items;
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(switch_[ab]_|source_[ab]|template_id_[ab]|anywhere_id_[ab]|show_switch_[ab]_badge|default_active)/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
