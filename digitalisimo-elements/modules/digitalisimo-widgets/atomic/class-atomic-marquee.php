<?php
namespace Digitalisimo\Elements;

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Textarea_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Elements\Base\Has_Template;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Marquesina atómica (editor v4 y servidor MCP): textos que se desplazan en bucle, con el mismo marcado, la misma hoja
 * y el mismo script que la marquesina clásica (`digitalisimo-marquee`, `assets/css/marquee.css`, `assets/js/marquee.js`):
 * botón «Pausar» (WCAG 2.2.2), pausa al enfocar y, si se pide, al pasar el cursor, y sin movimiento con «reducir
 * movimiento» (la copia oculta desaparece y la lista se puede recorrer). Caso digitalisimo.lat (2026-11-24): el MCP
 * descarta `animation` en los estilos de los elementos y sólo acepta widgets atómicos, así que la franja de clientes de
 * la landing tuvo que llevar `@keyframes` en el CSS propio de la página.
 *
 * Es un widget nuevo: ningún widget existente cambia. Sólo se registra con los elementos atómicos activos
 * (`Widget_Registry::atomic_widgets()`).
 */
class Atomic_Marquee extends Atomic_Widget_Base {
	use Has_Template;

	/** Colores que aceptan el acento: hexadecimal, rgb(a)/hsl(a) con números o una variable CSS. */
	const COLOR_PATTERN = '/^(#[0-9A-Fa-f]{3,8}|(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)|var\(--[A-Za-z0-9_-]+\))$/';

	public static $widget_description = 'Marquee (horizontal scrolling text) with a pause button that respects prefers-reduced-motion. Put one item per line in "items"; use "separator" for a mark between items, "accent" (hex color) for the separator and, with "accent-last", the last item. Style the text (font, size, color, background, padding) with the normal style CSS of the element. Use this instead of CSS animation/@keyframes, which element styles do not keep.';

	public static function get_element_type(): string {
		return 'e-digitalisimo-marquee';
	}

	public function get_title() {
		return 'Marquesina';
	}

	public function get_icon() {
		return 'eicon-animation-text';
	}

	public function get_keywords() {
		return array( 'marquesina', 'marquee', 'ticker', 'movimiento', 'desplazamiento', 'clientes', 'logos', 'atomic' );
	}

	public static function get_computed_html_tag( array $settings ): string {
		return 'div';
	}

	public function get_style_depends() {
		return array_merge( parent::get_style_depends(), array( 'digitalisimo-marquee' ) );
	}

	public function get_script_depends() {
		return array_merge( parent::get_script_depends(), array( 'digitalisimo-marquee' ) );
	}

	protected static function define_props_schema(): array {
		return array(
			'classes'     => Classes_Prop_Type::make()->default( array() ),
			'items'       => String_Prop_Type::make()
				->default( "Primer elemento\nSegundo elemento\nTercer elemento" )
				->description( 'Items of the marquee, one per line (plain text).' ),
			'label'       => String_Prop_Type::make()
				->default( '' )
				->description( 'Optional visible label before the moving items; also the accessible name of the region.' ),
			'separator'   => String_Prop_Type::make()
				->default( '' )
				->description( 'Optional mark shown after every item (for example "|" or "·"). Decorative: hidden from screen readers.' ),
			'accent'      => String_Prop_Type::make()
				->regex( self::COLOR_PATTERN )
				->default( '' )
				->description( 'Optional accent color (hex like #00ff99, rgb(), hsl() or var(--name)) for the separator and, with accent-last, the last item.' ),
			'accent-last' => Boolean_Prop_Type::make()
				->default( false )
				->description( 'Paint the last item with the accent color (for example a closing "and many more").' ),
			'duration'    => Number_Prop_Type::make()
				->default( 30 )
				->description( 'Seconds for one full loop (5 to 300).' ),
			'duration-mobile' => Number_Prop_Type::make()
				->default( null )
				->description( 'Optional seconds for one loop on phones (up to 767 px wide). Empty: same as duration.' ),
			'direction'   => String_Prop_Type::make()
				->enum( array( 'left', 'right' ) )
				->default( 'left' )
				->description( 'Direction of the movement: left or right.' ),
			'gap'         => Number_Prop_Type::make()
				->default( null )
				->description( 'Optional space between items in px (0 to 200). Empty: 2rem.' ),
			'pause-on-hover' => Boolean_Prop_Type::make()
				->default( true )
				->description( 'Pause while the pointer is over the marquee. It also pauses with keyboard focus and with its pause button.' ),
			'fade-edges'  => Boolean_Prop_Type::make()
				->default( false )
				->description( 'Fade the items at the left and right edges.' ),
			'attributes'  => Attributes_Prop_Type::make(),
		);
	}

	protected function define_atomic_controls(): array {
		return array(
			Section::make()
				->set_label( 'Contenido' )
				->set_id( 'content' )
				->set_items(
					array(
						Textarea_Control::bind_to( 'items' )
							->set_placeholder( 'Un elemento por renglón' )
							->set_label( 'Elementos (uno por renglón)' ),
						Text_Control::bind_to( 'label' )->set_label( 'Rótulo' ),
						Text_Control::bind_to( 'separator' )->set_label( 'Separador' ),
						Text_Control::bind_to( 'accent' )->set_label( 'Color de acento (#00ff99)' ),
						Switch_Control::bind_to( 'accent-last' )->set_label( 'Último elemento con el acento' ),
					)
				),
			Section::make()
				->set_label( 'Movimiento' )
				->set_id( 'motion' )
				->set_items(
					array(
						Number_Control::bind_to( 'duration' )->set_label( 'Duración de una vuelta (s)' )->set_min( 5 )->set_max( 300 ),
						Number_Control::bind_to( 'duration-mobile' )->set_label( 'Duración en el celular (s)' )->set_min( 5 )->set_max( 300 ),
						Select_Control::bind_to( 'direction' )
							->set_label( 'Dirección' )
							->set_options(
								array(
									array( 'value' => 'left', 'label' => 'Hacia la izquierda' ),
									array( 'value' => 'right', 'label' => 'Hacia la derecha' ),
								)
							),
						Number_Control::bind_to( 'gap' )->set_label( 'Separación (px)' )->set_min( 0 )->set_max( 200 ),
						Switch_Control::bind_to( 'pause-on-hover' )->set_label( 'Pausar al pasar el cursor' ),
						Switch_Control::bind_to( 'fade-edges' )->set_label( 'Desvanecer los bordes' ),
					)
				),
			Section::make()
				->set_label( 'Ajustes' )
				->set_id( 'settings' )
				->set_items(
					array(
						Text_Control::bind_to( '_cssid' )->set_label( 'ID' ),
					)
				),
		);
	}

	protected function get_templates(): array {
		return array(
			'digitalisimo/elements/marquee' => __DIR__ . '/atomic-marquee.html.twig',
		);
	}
}
