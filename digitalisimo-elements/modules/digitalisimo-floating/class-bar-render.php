<?php
namespace Digitalisimo\Elements\Floating;

use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Marcado de la Barra flotante (`digitalisimo-floating-bar`). Repite la estructura de las barras de PRO «Ticker» y
 * «Coupon» (código GPL de PRO Elements, `modules/floating-buttons/classes/render/floating-bars-var-{2,3}-render.php`,
 * y de la base del núcleo, `floating-bars-core-render.php`) con clases propias (`digi-fbar`, `digi-fbar__*`) y estas
 * diferencias de accesibilidad:
 *
 * - la barra es una región con nombre (`role="region"`, `aria-label` con el nombre accesible), no un diálogo de alerta,
 *   y no se lleva el foco al cargar;
 * - botón de cierre con su nombre, `aria-controls` hacia la barra y un icono SVG propio (sin la fuente de iconos);
 * - pausar y reanudar la cinta son botones reales y sólo se ve el que sirve (`hidden`);
 * - el cupón es un botón con el código en su nombre y una región de estado que anuncia «Copiado»;
 * - un titular sin enlace se pinta como texto, no como un enlace sin destino.
 */
class Bar_Render {
	/** @var \Elementor\Widget_Base */
	private $widget;

	/** @var array */
	private $settings;

	/** @var string */
	private $presentation;

	public function __construct( $widget ) {
		$this->widget       = $widget;
		$this->settings     = (array) $widget->get_settings_for_display();
		$this->presentation = \Digitalisimo\Elements\Floating\Widgets\Floating_Bar::presentation_of( $this->settings );
	}

	/** Valor de un ajuste (cadena). */
	private function value( string $key ): string {
		$value = $this->settings[ $key ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Id del contenedor de la barra: el del ajuste «ID CSS» o uno propio por elemento. */
	public function root_id(): string {
		$custom = $this->value( 'advanced_custom_css_id' );
		return '' !== $custom ? $custom : 'digi-fbar-' . $this->widget->get_id();
	}

	/** Clases del contenedor, como las de PRO con el bloque propio. */
	public function root_classes(): string {
		$classes = 'digi-fbar digi-fbar--p' . $this->presentation . ' has-vertical-position-' . ( '' !== $this->value( 'advanced_vertical_position' ) ? $this->value( 'advanced_vertical_position' ) : 'top' );
		if ( 'yes' === $this->value( 'floating_bar_close_switch' ) ) {
			$classes .= ' has-close-button';
		}
		if ( 'yes' === $this->value( 'advanced_toggle_sticky' ) ) {
			$classes .= ' is-sticky';
		}
		return $classes;
	}

	/** Nombre accesible de la barra (el del ajuste o el de la presentación). */
	private function name(): string {
		$name = trim( $this->value( 'accessible_name' ) );
		if ( '' !== $name ) {
			return $name;
		}
		return '3' === $this->presentation ? __( 'Banner del cupón', 'pro-elements' ) : __( 'Banner', 'pro-elements' );
	}

	public function render(): void {
		$this->widget->add_render_attribute(
			'digi-fbar',
			array(
				'class'            => $this->root_classes(),
				'id'               => $this->root_id(),
				'data-document-id' => function_exists( 'get_the_ID' ) ? (string) get_the_ID() : '',
				'role'             => 'region',
				'aria-label'       => $this->name(),
			)
		);
		echo '<div ' . $this->widget->get_render_attribute_string( 'digi-fbar' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '3' === $this->presentation ) {
			$this->announcement_text();
			$this->announcement_icon();
			$this->coupon();
			if ( 'yes' === $this->value( 'floating_bar_close_switch' ) ) {
				$this->close_button();
			}
			echo '<div class="digi-fbar__overlay"></div>';
		} else {
			$this->headlines();
			$pause = 'yes' === $this->value( 'floating_bar_pause_switch' );
			$close = 'yes' === $this->value( 'floating_bar_close_switch' );
			if ( $pause || $close ) {
				echo '<div class="digi-fbar__ticker-controls">';
				if ( $pause ) {
					$this->pause_play();
				}
				if ( $close ) {
					$this->close_button();
				}
				echo '</div>';
			}
		}
		echo '</div>';
	}

	/** Icono de la biblioteca de iconos (SVG o fuente, según el sitio), oculto a los lectores de pantalla. */
	private function icon( $icon ): string {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
			return '';
		}
		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	/** Aspa propia (sin la fuente de iconos del núcleo). */
	public static function close_svg(): string {
		return '<svg class="digi-fbar__close-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="1em" height="1em"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';
	}

	private function close_button(): void {
		printf(
			'<button type="button" class="digi-fbar__close-button" aria-controls="%1$s" aria-label="%2$s">%3$s</button>',
			esc_attr( $this->root_id() ),
			/* translators: %s: nombre accesible de la barra. */
			esc_attr( sprintf( __( 'Cerrar %s', 'pro-elements' ), $this->name() ) ),
			self::close_svg() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	private function headlines(): void {
		$classes = 'digi-fbar__headlines';
		if ( 'yes' === $this->value( 'style_ticker_pause_hover' ) ) {
			$classes .= ' has-pause-hover';
		}
		echo '<div class="' . esc_attr( $classes ) . '"><div class="digi-fbar__headlines-inner">';
		foreach ( (array) ( $this->settings['headlines_repeater'] ?? array() ) as $key => $headline ) {
			$text = isset( $headline['headlines_text'] ) && is_scalar( $headline['headlines_text'] ) ? (string) $headline['headlines_text'] : '';
			if ( '' === $text ) {
				continue;
			}
			$link = isset( $headline['headlines_url'] ) && is_array( $headline['headlines_url'] ) ? $headline['headlines_url'] : array();
			$attr = 'digi-fbar-headline-' . $key;
			$this->widget->add_render_attribute( $attr, 'class', 'digi-fbar__headline' );
			$tag = 'span';
			if ( ! empty( $link['url'] ) ) {
				$this->widget->add_link_attributes( $attr, $link );
				$tag = 'a';
			}
			$icon = $this->icon( $headline['headlines_icon'] ?? array() );
			echo '<' . $tag . ' ' . $this->widget->get_render_attribute_string( $attr ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( '' !== $icon ) {
				echo '<span class="digi-fbar__headline-icon">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<span class="digi-fbar__headline-text">' . esc_html( $text ) . '</span></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><div class="digi-fbar__overlay"></div></div>';
	}

	private function pause_play(): void {
		$name = $this->name();
		echo '<div class="digi-fbar__play-pause-controls">';
		printf(
			'<button type="button" class="digi-fbar__pause-button" aria-label="%1$s">%2$s</button>',
			/* translators: %s: nombre accesible de la barra. */
			esc_attr( sprintf( __( 'Pausar %s', 'pro-elements' ), $name ) ),
			$this->icon( $this->settings['floating_bar_pause_icon'] ?? array() ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		printf(
			'<button type="button" class="digi-fbar__play-button" aria-label="%1$s" hidden>%2$s</button>',
			/* translators: %s: nombre accesible de la barra. */
			esc_attr( sprintf( __( 'Reanudar %s', 'pro-elements' ), $name ) ),
			$this->icon( $this->settings['floating_bar_play_icon'] ?? array() ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '</div>';
	}

	private function announcement_text(): void {
		$text = $this->value( 'announcement_text' );
		if ( '' === $text ) {
			return;
		}
		$this->widget->add_render_attribute( 'announcement_text', 'class', 'digi-fbar__announcement-text' );
		echo '<p ' . $this->widget->get_render_attribute_string( 'announcement_text' ) . '>' . esc_html( $text ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function announcement_icon(): void {
		$icon = $this->icon( $this->settings['announcement_icon'] ?? array() );
		if ( '' !== $icon ) {
			echo '<span class="digi-fbar__announcement-icon">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** Forma del cupón (las de PRO, dibujadas con su mismo trazado). */
	private function shape( string $type ): string {
		$shapes = array(
			'price-tag'     => array( '0 0 130.67 42', 'M124.67 1H21.37c-1.3 0-2.56.51-3.49 1.42l-15.37 15a5.005 5.005 0 0 0 0 7.16l15.38 15c.93.91 2.19 1.42 3.49 1.42h103.29c2.76 0 5-2.24 5-5V6c0-2.76-2.24-5-5-5ZM15.34 25c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4Z' ),
			'gift-tag'      => array( '0 0 111 40', 'M1 20C1 9.50229 9.50229 1 20 1H110V39H20C9.50229 39 1 30.4977 1 20ZM11 20C11 22.7623 13.2377 25 16 25C18.7623 25 21 22.7623 21 20C21 17.2377 18.7623 15 16 15C13.2377 15 11 17.2377 11 20Z' ),
			'round-ticket'  => array( '0 0 104 40', 'M96.5 8.51v.522l.428.298A12.972 12.972 0 0 1 102.5 20c0 4.42-2.199 8.319-5.572 10.67l-.428.298V39h-89v-8.032l-.428-.298A12.972 12.972 0 0 1 1.5 20c0-4.42 2.199-8.319 5.572-10.67l.428-.298V1h89v7.51Z' ),
			'square-ticket' => array( '0 0 106 40', 'M7.429 1H99.57a7.003 7.003 0 0 0 4.929 5.712v26.576A7.015 7.015 0 0 0 99.57 39H7.43A7.011 7.011 0 0 0 1.5 33.071V6.93A7.012 7.012 0 0 0 7.429 1Z' ),
		);
		if ( ! isset( $shapes[ $type ] ) ) {
			return '';
		}
		return '<svg class="digi-fbar__shape digi-fbar__' . $type . '" aria-hidden="true" focusable="false" fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="' . $shapes[ $type ][0] . '"><path class="shape path" d="' . $shapes[ $type ][1] . '"/></svg>';
	}

	private function coupon(): void {
		$code = $this->value( 'coupon_code' );
		if ( '' === $code ) {
			return;
		}
		$type    = $this->value( 'style_coupon_type' );
		$classes = 'digi-fbar__coupon-button';
		$entry   = $this->value( 'style_coupon_animation' );
		if ( '' !== $entry && 'none' !== $entry ) {
			$classes .= ' has-entrance-animation';
		}
		if ( '' !== $type ) {
			$classes .= ' is-type-' . $type;
		}
		if ( 'yes' === $this->value( 'style_coupon_show_border' ) ) {
			$classes .= ' has-border';
		}
		$copy    = $this->icon( $this->settings['coupon_copy_icon'] ?? array() );
		$success = $this->icon( $this->settings['coupon_success_icon'] ?? array() );
		$message = $this->value( 'coupon_success_message' );
		printf(
			'<button type="button" class="%1$s" aria-label="%2$s">%3$s<span class="digi-fbar__coupon-text-group digi-fbar__coupon-code">%4$s<span class="digi-fbar__coupon-text">%5$s</span></span><span class="digi-fbar__coupon-text-group digi-fbar__coupon-success is-hidden" aria-hidden="true">%6$s<span class="digi-fbar__coupon-text digi-fbar__coupon-success-message">%7$s</span></span></button><span class="digi-fbar__status" role="status" data-message="%8$s"></span>',
			esc_attr( $classes ),
			/* translators: %s: código del cupón. */
			esc_attr( sprintf( __( 'Copiar el código del cupón %s', 'pro-elements' ), $code ) ),
			$this->shape( $type ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'' !== $copy ? '<span class="digi-fbar__coupon-icon">' . $copy . '</span>' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $code ),
			'' !== $success ? '<span class="digi-fbar__coupon-icon digi-fbar__coupon-success-icon">' . $success . '</span>' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $message ),
			esc_attr( '' !== $message ? $message : __( 'Copiado', 'pro-elements' ) )
		);
	}
}
