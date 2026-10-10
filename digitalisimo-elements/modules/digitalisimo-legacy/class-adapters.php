<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';

/**
 * Registra un ID `bdt-*` sobre el widget propio equivalente. Al construir cada elemento traduce sus
 * ajustes, de modo que el render, el CSS que genera Elementor y el editor trabajan ya con los
 * nombres propios. No aparece en el panel: el contenido nuevo usa el widget `digitalisimo-*`.
 */
trait Legacy_Adapter {
	public function __construct( $data = array(), $args = null ) {
		if ( is_array( $data ) && ! empty( $data['id'] ) ) {
			$data['settings'] = Translator::translate( static::LEGACY_ID, is_array( $data['settings'] ?? null ) ? $data['settings'] : array() );
		}
		if ( method_exists( (string) get_parent_class( $this ), '__construct' ) ) {
			parent::__construct( $data, $args );
		}
	}

	public function get_name() {
		return static::LEGACY_ID;
	}

	public function get_title() {
		return parent::get_title() . ' (Element Pack)';
	}

	public function show_in_panel() {
		return false;
	}

	/**
	 * Añade los controles de estilo de Element Pack que el widget propio no tiene, con sus mismos
	 * nombres y plantillas CSS: Elementor vuelve a generar los colores, tipografías y espacios que
	 * el usuario ya había elegido, ahora sobre el marcado propio.
	 */
	protected function register_controls() {
		parent::register_controls();
		$styles = Translator::styles( static::LEGACY_ID );
		if ( ! $styles ) {
			return;
		}
		$existing = array_keys( (array) $this->get_controls() );
		$this->start_controls_section( 'digitalisimo_legacy_style', array(
			'label' => 'Estilo de Element Pack',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		foreach ( $styles as $control ) {
			$name = (string) ( $control['name'] ?? '' );
			if ( '' === $name || ( ! isset( $control['group'] ) && in_array( $name, $existing, true ) ) ) {
				continue;
			}
			$label = ucfirst( str_replace( '_', ' ', $name ) );
			if ( isset( $control['group'] ) ) {
				// Un grupo sólo choca con controles que comparten su prefijo, no con uno de igual nombre.
				foreach ( $existing as $key ) {
					if ( 0 === strpos( $key, $name . '_' ) ) {
						continue 2;
					}
				}
				$args = array( 'name' => $name, 'label' => $label, 'selector' => $control['selector'] );
				foreach ( array( 'types', 'exclude' ) as $key ) {
					if ( isset( $control[ $key ] ) ) {
						$args[ $key ] = $control[ $key ];
					}
				}
				$this->add_group_control( $control['group'], $args );
				continue;
			}
			$args = array( 'label' => $label, 'type' => $control['type'], 'selectors' => $control['selectors'] );
			foreach ( array( 'default', 'desktop_default', 'tablet_default', 'mobile_default', 'selectors_dictionary', 'condition', 'size_units', 'options' ) as $key ) {
				if ( isset( $control[ $key ] ) ) {
					$args[ $key ] = $control[ $key ];
				}
			}
			if ( ! empty( $control['responsive'] ) ) {
				$this->add_responsive_control( $name, $args );
			} else {
				$this->add_control( $name, $args );
			}
		}
		$this->end_controls_section();
	}
}

final class Adapters {
	/** ID heredado => clase adaptadora; la clase base debe estar cargada por el registro. */
	const CLASSES = array(
		'bdt-accordion' => Bdt_Accordion::class,
		'bdt-advanced-button' => Bdt_AdvancedButton::class,
		'bdt-advanced-divider' => Bdt_AdvancedDivider::class,
		'bdt-advanced-heading' => Bdt_AdvancedHeading::class,
		'bdt-advanced-icon-box' => Bdt_AdvancedIconBox::class,
		'bdt-animated-heading' => Bdt_AnimatedHeading::class,
		'bdt-brand-grid' => Bdt_BrandGrid::class,
		'bdt-brand-carousel' => Bdt_BrandCarousel::class,
		'bdt-breadcrumbs' => Bdt_Breadcrumbs::class,
		'bdt-dual-button' => Bdt_DualButton::class,
		'bdt-call-out' => Bdt_CallOut::class,
	);

	public static function element_pack_active() {
		return Translator::element_pack_active();
	}

	public static function register( $manager ) {
		if ( self::element_pack_active() ) {
			return;
		}
		foreach ( self::CLASSES as $id => $class ) {
			if ( ! Translator::map( $id ) || ! class_exists( $class ) ) {
				continue;
			}
			if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( $id ) ) {
				continue;
			}
			$manager->register( new $class() );
		}
	}
}

if ( class_exists( '\\Digitalisimo\\Elements\\Accordion_Widget' ) ) {
	final class Bdt_Accordion extends \Digitalisimo\Elements\Accordion_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-accordion'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Button_Widget' ) ) {
	final class Bdt_AdvancedButton extends \Digitalisimo\Elements\Advanced_Button_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-button'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Divider_Widget' ) ) {
	final class Bdt_AdvancedDivider extends \Digitalisimo\Elements\Advanced_Divider_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-divider'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Heading_Widget' ) ) {
	final class Bdt_AdvancedHeading extends \Digitalisimo\Elements\Advanced_Heading_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-heading'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Advanced_Icon_Box_Widget' ) ) {
	final class Bdt_AdvancedIconBox extends \Digitalisimo\Elements\Advanced_Icon_Box_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-advanced-icon-box'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Animated_Heading_Widget' ) ) {
	final class Bdt_AnimatedHeading extends \Digitalisimo\Elements\Animated_Heading_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-animated-heading'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Brand_Grid_Widget' ) ) {
	final class Bdt_BrandGrid extends \Digitalisimo\Elements\Brand_Grid_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-brand-grid'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Brand_Carousel_Widget' ) ) {
	final class Bdt_BrandCarousel extends \Digitalisimo\Elements\Brand_Carousel_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-brand-carousel'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Breadcrumbs_Widget' ) ) {
	final class Bdt_Breadcrumbs extends \Digitalisimo\Elements\Breadcrumbs_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-breadcrumbs'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Dual_Button_Widget' ) ) {
	final class Bdt_DualButton extends \Digitalisimo\Elements\Dual_Button_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-dual-button'; }
}
if ( class_exists( '\\Digitalisimo\\Elements\\Call_Out_Widget' ) ) {
	final class Bdt_CallOut extends \Digitalisimo\Elements\Call_Out_Widget { use Legacy_Adapter; const LEGACY_ID = 'bdt-call-out'; }
}
