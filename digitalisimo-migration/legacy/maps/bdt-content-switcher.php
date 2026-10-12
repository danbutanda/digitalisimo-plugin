<?php
/**
 * Element Pack Pro 9.9.1 `bdt-content-switcher` → `digitalisimo-content-switcher`.
 * Texto, plantillas y secciones enlazadas tienen equivalente propio; la tarjeta de precio se
 * traduce a HTML con los mismos datos. Enlazar a un widget por ID usa la misma alternancia.
 */
defined( 'ABSPATH' ) || exit;

$symbols = array( 'dollar' => '$', 'euro' => '€', 'baht' => '฿', 'franc' => '₣', 'guilder' => 'ƒ', 'krona' => 'kr', 'lira' => '₤', 'peseta' => '₧', 'peso' => '₱', 'pound' => '£', 'real' => 'R$', 'ruble' => '₽', 'rupee' => '₨', 'indian_rupee' => '₹', 'shekel' => '₪', 'yen' => '¥', 'won' => '₩' );

return array(
	'target'    => 'digitalisimo-content-switcher',
	'classes'   => array(
		'.bdt-content-switcher-tab.bdt-active' => '.digi-content-switcher__tab[aria-selected="true"]',
		'.bdt-content-switcher-tab'            => '.digi-content-switcher__tab',
		'.bdt-switcher-content'                => '.digi-content-switcher__panel',
		'.bdt-switch-container-wrap'           => '.digi-content-switcher__tabs',
		'.bdt-content-switcher'                => '.digi-content-switcher',
	),
	'defaults'  => array( 'switcher_style' => '1' ),
	'repeaters' => array(
		'switcher_items' => array(
			'defaults'     => array( 'title' => 'Switcher Title', 'content_type' => 'content', 'price_card_content' => 'Starting From', 'currency_symbol' => 'dollar', 'price' => '49.99', 'original_price' => '79', 'period' => 'Monthly', 'price_card_additional_text' => 'Enjoy our special offer!', 'button_text' => 'Select Plan', 'link' => array( 'url' => '#' ), 'content' => 'Switcher Content' ),
			'default_rows' => array(
				array( 'content_type' => 'content', 'title' => 'Primary', 'content' => 'Switcher Content Primary', 'switcher_active' => 'yes' ),
				array( 'content_type' => 'content', 'title' => 'Secondary', 'content' => 'Switcher Content Secondary' ),
				array( 'content_type' => 'content', 'title' => 'Others', 'content' => 'Switcher Content Others' ),
			),
		),
	),
	'filter'    => static function ( array $out ) use ( $symbols ) {
		// Con estilo de interruptor Element Pack sólo mostraba la opción primaria y la secundaria.
		if ( 'button' !== ( $out['switcher_style'] ?? '1' ) ) {
			$out['switcher_items'] = array_slice( $out['switcher_items'], 0, 2 );
		}
		foreach ( $out['switcher_items'] as $index => $item ) {
			$type = $item['content_type'] ?? 'content';
			if ( 'template' === $type ) {
				$item['template_id'] = $item['saved_templates'] ?? '';
			} elseif ( 'link_section' === $type || 'link_widget' === $type ) {
				$item['content_type'] = 'link_section';
				$item['link_target']  = 'link_section' === $type ? ( $item['link_section_id'] ?? '' ) : ( $item['link_widget_id'] ?? '' );
			} elseif ( 'price_card' === $type ) {
				$symbol = 'custom' === ( $item['currency_symbol'] ?? '' ) ? (string) ( $item['currency_symbol_custom'] ?? '' ) : ( $symbols[ $item['currency_symbol'] ?? '' ] ?? '' );
				$html   = '<p class="digi-switcher-price"><span>' . esc_html( $item['price_card_content'] ?? '' ) . '</span> ';
				if ( 'yes' === ( $item['sale'] ?? '' ) && '' !== (string) ( $item['original_price'] ?? '' ) ) {
					$html .= '<del>' . esc_html( $symbol . $item['original_price'] ) . '</del> ';
				}
				$html .= '<strong>' . esc_html( $symbol . ( $item['price'] ?? '' ) ) . '</strong> <span>' . esc_html( $item['period'] ?? '' ) . '</span></p>';
				$html .= wp_kses_post( $item['price_card_additional_text'] ?? '' );
				if ( ! empty( $item['link']['url'] ) && '' !== (string) ( $item['button_text'] ?? '' ) ) {
					$rel   = trim( ( ! empty( $item['link']['is_external'] ) ? 'noopener noreferrer ' : '' ) . ( ! empty( $item['link']['nofollow'] ) ? 'nofollow' : '' ) );
					$html .= '<p><a class="digi-switcher-price__button" href="' . esc_url( $item['link']['url'] ) . '"' . ( ! empty( $item['link']['is_external'] ) ? ' target="_blank"' : '' ) . ( $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . '>' . esc_html( $item['button_text'] ) . '</a></p>';
				}
				$item['content_type'] = 'content';
				$item['content']      = $html;
			}
			$out['switcher_items'][ $index ] = $item;
		}
		return $out;
	},
);
