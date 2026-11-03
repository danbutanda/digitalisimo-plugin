<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * section-sticky → «Fijo» de PRO (`ELE/modules/sticky/module.php`) más `custom_css` sobre la clase que pone el
 * núcleo mientras el elemento está fijo (`.elementor-sticky--active`) (F8, §3.C.2).
 *
 * - `section_sticky_position`: `start`/`auto` → `sticky = top`; `end` → `bottom`.
 * - `section_sticky_off_media` (ancho mínimo de UIkit): sin corte, todos los dispositivos (el valor por defecto de
 *   `sticky_on`, que no se escribe); 768 → todos menos `mobile`; 960 → sin `mobile`, `mobile_extra` ni `tablet`.
 * - `section_sticky_offset` → `sticky_offset`; `sticky_effects_offset` queda en 0.
 * - «Hasta» la columna padre (`bottom: true` o `!` hacia la columna o el contenedor) → `sticky_parent`.
 * - Fondo, relleno, borde, radio, sombra e índice z del estado fijo → `custom_css`, con `@media` en lo responsivo y,
 *   en secciones, la transición del ayudante de Element Pack.
 */
final class Sticky {
	public static function convert( Out $out ): bool {
		$s        = $out->orig;
		$position = (string) $out->get( 'section_sticky_position' );
		$set      = array( 'sticky' => 'end' === $position ? 'bottom' : 'top' );
		$media    = (string) Values::get( $s, 'section_sticky_off_media', '' );
		if ( in_array( $media, array( '768', '960' ), true ) ) {
			$skip            = '768' === $media ? array( 'mobile' ) : array( 'mobile', 'mobile_extra', 'tablet' );
			$set['sticky_on'] = array_values( array_diff( Values::devices(), $skip ) );
		}
		$offset = Css_Value::number( $out->get( 'section_sticky_offset' ), -10000, 10000 );
		if ( null !== $offset && '0' !== $offset ) {
			$set['sticky_offset'] = (int) round( (float) $offset );
		}
		$bottom = trim( (string) Values::get( $s, 'section_sticky_bottom', '' ) );
		if ( '' !== $bottom ) {
			if ( in_array( $bottom, array( 'true', '!.elementor-column', '!.elementor-widget-wrap', '!.e-con', '!.elementor-container' ), true ) && in_array( $out->el_type(), array( 'widget', 'section', 'container' ), true ) ) {
				$set['sticky_parent'] = 'yes';
			} else {
				$out->loss( __( '«hasta» un selector distinto de la columna padre', 'digitalisimo-migration' ) );
			}
		}
		if ( ! $out->set_all( $set ) ) {
			return false;
		}
		$by = array();
		$bg = Values::color( $s, 'section_sticky_active_bg' );
		if ( null !== $bg ) {
			$by[''][] = 'background-color:' . $bg;
		}
		$by = $out->by_device( 'section_sticky_active_padding', static function ( $v ) {
			$d = Css_Value::dimensions( $v );
			return null === $d ? null : 'padding:' . $d;
		}, $by );
		$border = Css_Value::enum( Values::get( $s, 'section_sticky_border_border', '' ), array( 'solid', 'double', 'dotted', 'dashed', 'groove' ) );
		if ( null !== $border ) {
			$by[''][] = 'border-style:' . $border;
			$by       = $out->by_device( 'section_sticky_border_width', static function ( $v ) {
				$d = Css_Value::dimensions( $v );
				return null === $d ? null : 'border-width:' . $d;
			}, $by );
			$color = Values::color( $s, 'section_sticky_border_color' );
			if ( null !== $color ) {
				$by[''][] = 'border-color:' . $color;
			}
		}
		$by = $out->by_device( 'section_sticky_border_radius', static function ( $v ) {
			$d = Css_Value::dimensions( $v, array( 'px', 'em', '%' ) );
			return null === $d ? null : 'border-radius:' . $d;
		}, $by );
		if ( '' !== (string) Values::get( $s, 'section_sticky_active_shadow_box_shadow_type', '' ) ) {
			$shadow = Css_Value::shadow( Values::get( $s, 'section_sticky_active_shadow_box_shadow', array() ), (string) Values::get( $s, 'section_sticky_active_shadow_box_shadow_position', '' ) );
			if ( null !== $shadow ) {
				$by[''][] = 'box-shadow:' . $shadow;
			}
		}
		$z = Css_Value::number( Values::get( $s, 'section_sticky_zindex', '' ), -1000, 9999 );
		if ( null !== $z ) {
			$by[''][] = 'z-index:' . (int) $z;
		}
		if ( $by ) {
			$css = Out::responsive( 'selector.elementor-sticky--active', $by );
			if ( 'section' === $out->el_type() ) {
				$css = 'selector{transition:background-color 250ms ease-out,padding 300ms ease-out}' . $css;
			}
			$out->css( $css );
		}
		if ( 'yes' === Values::get( $s, 'section_sticky_on_scroll_up' ) ) {
			$out->loss( __( 'mostrar sólo al subir', 'digitalisimo-migration' ) );
		}
		if ( '' !== (string) Values::get( $s, 'section_sticky_animation', '' ) ) {
			$out->loss( __( 'animación al fijarse', 'digitalisimo-migration' ) );
		}
		if ( 'auto' === $position ) {
			$out->loss( __( 'posición automática (se fija arriba)', 'digitalisimo-migration' ) );
		}
		if ( '960' === $media ) {
			$out->loss( __( 'corte de 960 px aproximado al punto de corte de la tableta', 'digitalisimo-migration' ) );
		}
		return true;
	}
}
