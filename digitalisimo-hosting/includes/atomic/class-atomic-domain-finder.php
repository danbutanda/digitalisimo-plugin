<?php
namespace Digitalisimo\Hosting;

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Elements\Base\Has_Template;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;

defined( 'ABSPATH' ) || exit;

/**
 * «Buscador de dominios» del editor atómico (y del servidor MCP, que sólo acepta elementos atómicos): el mismo marcado,
 * hoja y script que [digitalisimo_buscador_dominios] (Digitalisimo_Hosting_Finder::html()). Lo registra DIGITALÍSIMO
 * Hosting sólo con el núcleo de DIGITALÍSIMO Elements y los elementos atómicos activos.
 */
class Atomic_Domain_Finder extends Atomic_Widget_Base {
	use Has_Template;

	public static $widget_description = 'Domain search like a registrar: the visitor types a name (or name.ext) and every extension sold in the store is checked at once, with a main result, one row per extension (first-year price, renewal, requirements, Add button), transfer offer for taken names, "bought together" plans and a sticky cart bar. Optional texts: "title", "placeholder", "button". Style it with the element CSS; colors and fonts come from the site kit.';

	public static function get_element_type(): string {
		return 'e-digitalisimo-domain-finder';
	}

	public function get_title() {
		return 'Buscador de dominios';
	}

	public function get_icon() {
		return 'eicon-search';
	}

	public function get_keywords() {
		return array( 'dominio', 'dominios', 'domain', 'buscador', 'registrar', 'hosting', 'atomic' );
	}

	public static function get_computed_html_tag( array $settings ): string {
		return 'div';
	}

	public function get_style_depends() {
		return array_merge( parent::get_style_depends(), array( \Digitalisimo_Hosting_Finder::HANDLE ) );
	}

	public function get_script_depends() {
		return array_merge( parent::get_script_depends(), array( \Digitalisimo_Hosting_Finder::HANDLE ) );
	}

	protected static function define_props_schema(): array {
		return array(
			'classes'     => Classes_Prop_Type::make()->default( array() ),
			'title'       => String_Prop_Type::make()->default( 'Busca tu dominio' )->description( 'Visible label of the search field.' ),
			'placeholder' => String_Prop_Type::make()->default( 'minegocio' )->description( 'Example text inside the field.' ),
			'button'      => String_Prop_Type::make()->default( 'Buscar' )->description( 'Text of the search button.' ),
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
						Text_Control::bind_to( 'title' )->set_label( 'Título del campo' ),
						Text_Control::bind_to( 'placeholder' )->set_label( 'Texto de ejemplo' ),
						Text_Control::bind_to( 'button' )->set_label( 'Texto del botón' ),
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
			'digitalisimo/hosting/domain-finder' => __DIR__ . '/atomic-domain-finder.html.twig',
		);
	}
}
