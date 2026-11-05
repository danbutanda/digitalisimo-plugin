<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Migas (Yoast o DIGITALÍSIMO SEO) (`digitalisimo-yoast-breadcrumbs`): sustituye al widget «Breadcrumbs» de PRO
 * (`modules/theme-elements/widgets/breadcrumbs.php`, mapa `modules/digitalisimo-convert/maps/breadcrumbs.php`). Repite
 * sus controles con el mismo nombre, valor por defecto y condiciones; la hoja es propia y no lleva script.
 *
 * Qué pinta, en este orden:
 * 1. Con Yoast SEO activo, sus migas, como PRO: `WPSEO_Breadcrumbs::breadcrumb()` (o `yoast_breadcrumb()`) dentro de
 *    la etiqueta elegida con `id="breadcrumbs"` (`p` por defecto). Los ajustes de la ruta son los del panel de Yoast.
 * 2. Sin Yoast, la ruta de DIGITALÍSIMO SEO: la misma fuente que el widget «Ruta de navegación»
 *    (`Digitalisimo_Integrations_Breadcrumbs`, con sus ajustes de SEO → Migas: separador, prefijo, inicio y página
 *    actual), dentro de un `div` (su marcado es un `nav`, que no cabe en `p` ni `span`). Con otro plugin SEO activo,
 *    nada, como el widget «Ruta de navegación».
 * 3. Si no hay ninguno de los dos, nada (en el editor, un aviso).
 *
 * A diferencia de PRO, se registra siempre (sin Yoast también), así la conversión no depende de Yoast y un sitio que pasa
 * de Yoast a DIGITALÍSIMO SEO conserva las migas. Ningún JSON-LD propio: el `@graph` es de DIGITALÍSIMO SEO.
 */
class Yoast_Breadcrumbs extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-yoast-breadcrumbs';
	}

	public function get_title() {
		return esc_html__( 'Migas (Yoast o DIGITALÍSIMO SEO)', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-yoast';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'migas', 'ruta', 'navegación', 'yoast', 'seo', 'breadcrumbs', 'internal links' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/** ¿Están activas las migas de Yoast SEO? */
	public static function yoast(): bool {
		return class_exists( '\WPSEO_Breadcrumbs' ) || function_exists( 'yoast_breadcrumb' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_breadcrumbs_content',
			array(
				'label' => esc_html__( 'Migas', 'pro-elements' ),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'        => esc_html__( 'Alineación', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'   => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'prefix_class' => 'digi-yoast-breadcrumbs%s-align-',
				// PRO alineaba con la clase `elementor-align-*` y la hoja del núcleo; aquí, la propiedad misma.
				'selectors'    => array(
					'{{WRAPPER}}' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'html_tag',
			array(
				'label'   => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''     => esc_html__( 'Predeterminada', 'pro-elements' ),
					'p'    => 'p',
					'div'  => 'div',
					'nav'  => 'nav',
					'span' => 'span',
				),
				'default' => '',
			)
		);

		$this->add_control(
			'html_description',
			array(
				'raw'             => self::description(),
				'type'            => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Migas', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}}',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}' => 'color: {{VALUE}};',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_breadcrumbs_style' );

		$this->start_controls_tab(
			'tab_color_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => esc_html__( 'Color de los enlaces', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_color_hover',
			array(
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			)
		);

		$this->add_control(
			'link_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} a:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/** Ayuda del panel: de dónde salen las migas en este sitio. */
	private static function description(): string {
		if ( self::yoast() ) {
			return sprintf(
				/* translators: 1: apertura del enlace, 2: cierre del enlace. */
				esc_html__( 'Yoast SEO está activo: estas son sus migas. Los demás ajustes están en su %1$spanel de migas%2$s.', 'pro-elements' ),
				sprintf( '<a href="%s" target="_blank">', esc_url( admin_url( 'admin.php?page=wpseo_titles#top#breadcrumbs' ) ) ),
				'</a>'
			);
		}
		return esc_html__( 'Sin Yoast SEO, el widget muestra la ruta de DIGITALÍSIMO SEO con sus ajustes de migas; sin ninguno de los dos, no muestra nada.', 'pro-elements' );
	}

	/** Etiqueta de la envoltura de Yoast: la elegida o `p`, como PRO. */
	private function get_html_tag(): string {
		$html_tag = $this->get_settings( 'html_tag' );
		if ( empty( $html_tag ) ) {
			$html_tag = 'p';
		}
		return Utils::validate_html_tag( $html_tag );
	}

	/**
	 * Migas de DIGITALÍSIMO SEO (la misma fuente que «Ruta de navegación»), o '' si no está, si las desactivó o si otro
	 * plugin SEO publica las señales.
	 */
	public static function seo_html(): string {
		if ( ! class_exists( '\Digitalisimo_Integrations_Breadcrumbs' ) || ! method_exists( '\Digitalisimo_Integrations_Breadcrumbs', 'html' ) ) {
			return '';
		}
		if ( class_exists( '\Digitalisimo_Integrations_SEO_Resolver' ) && method_exists( '\Digitalisimo_Integrations_SEO_Resolver', 'foreign_seo' ) && \Digitalisimo_Integrations_SEO_Resolver::foreign_seo() ) {
			return '';
		}
		return (string) \Digitalisimo_Integrations_Breadcrumbs::html();
	}

	protected function render() {
		if ( self::yoast() ) {
			$html_tag = $this->get_html_tag();
			if ( class_exists( '\WPSEO_Breadcrumbs' ) ) {
				\WPSEO_Breadcrumbs::breadcrumb( '<' . $html_tag . ' id="breadcrumbs">', '</' . $html_tag . '>' );
			} else {
				yoast_breadcrumb( '<' . $html_tag . ' id="breadcrumbs">', '</' . $html_tag . '>' );
			}
			return;
		}
		$html = self::seo_html();
		if ( '' !== $html ) {
			echo '<div class="digi-yoast-breadcrumbs">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado escapado por DIGITALÍSIMO SEO.
			return;
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<p class="digi-yoast-breadcrumbs__notice">' . esc_html__( 'Las migas aparecen con Yoast SEO o con DIGITALÍSIMO SEO activos.', 'pro-elements' ) . '</p>';
		}
	}

	public function render_plain_content() {}
}
