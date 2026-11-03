<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * background-expand → acción con destino de F7b más `custom_css` (F8, §3.C.2).
 *
 * Element Pack ensanchaba la sección o el contenedor de 1300 px (o el ancho guardado) al 100 % con GSAP cuando su
 * parte superior llegaba al centro de la pantalla. Aquí: una acción `scroll_in` → `toggle_class` sobre el propio
 * elemento con la clase `bx-active` (`ELE/modules/digitalisimo-f7b/actions/class-controls.php`; la clase no lleva los
 * prefijos reservados) y el CSS del ancho, el radio y la transición, sin movimiento con `prefers-reduced-motion`.
 * Las acciones existentes se conservan: la fila nueva se añade al final.
 */
final class Expand {
	const CLASS_NAME = 'bx-active';

	public static function convert( Out $out ): bool {
		$s     = $out->orig;
		$on    = $out->s['_digitalisimo_actions_on'] ?? '';
		$rows  = is_array( $out->s['_digitalisimo_actions'] ?? null ) ? $out->s['_digitalisimo_actions'] : array();
		if ( 'yes' !== $on && $rows ) {
			// Acciones guardadas pero apagadas: encenderlas las activaría todas.
			$out->note( 'ext.conflict', array( 'type' => Dispatcher::label( 'background-expand' ), 'name' => '_digitalisimo_actions_on' ) );
			return false;
		}
		$rows[] = array(
			'_id'         => substr( md5( $out->id() . '|bx' ), 0, 7 ),
			'trigger'     => 'scroll_in',
			'action'      => 'toggle_class',
			'target_kind' => 'self',
			'class_name'  => self::CLASS_NAME,
		);
		$out->s['_digitalisimo_actions_on'] = 'yes';
		$out->s['_digitalisimo_actions']    = $rows;
		$width = Css_Value::size( $out->get( 'ep_bg_expand_container_width' ), array( 'px' ) ) ?? '1300px';
		$speed = Css_Value::size( $out->get( 'ep_bg_expand_anim_speed' ), array( 'ms' ), 'ms', 0, 10000 ) ?? '350ms';
		$base  = array( '' => array( 'margin-inline:auto', 'max-width:' . $width, 'transition:border-radius .2s,max-width ' . $speed . ' ease' ) );
		$radius = static function ( $v ) {
			$d = Css_Value::dimensions( $v, array( 'px', 'em', '%' ) );
			return null === $d ? null : 'border-radius:' . $d;
		};
		$base   = $out->by_device( 'ep_bg_expand_border_radius', $radius, $base );
		$active = $out->by_device( 'ep_bg_expand_border_radius_active', $radius, array( '' => array( 'max-width:100%' ) ) );
		$out->css(
			Out::responsive( 'selector', $base )
			. Out::responsive( 'selector.' . self::CLASS_NAME, $active )
			. '@media (prefers-reduced-motion:reduce){selector{transition:none}}'
		);
		$out->loss( __( 'volver a encogerse al subir; arranca en el primer píxel visible', 'digitalisimo-migration' ) );
		return true;
	}
}
