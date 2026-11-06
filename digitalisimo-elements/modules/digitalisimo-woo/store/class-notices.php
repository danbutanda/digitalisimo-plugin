<?php
namespace Digitalisimo\Elements\Woo\Store;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Avisos de la tienda (`digitalisimo-woo-notices`): sustituye a «WooCommerce Notices» de PRO
 * (`modules/woocommerce/widgets/notices.php` y su manejador del front `modules/woocommerce/assets/js/frontend/handlers/
 * notices.js`, GPL; mapa `maps/woocommerce-notices.php`). Mismos controles. Los avisos los pinta WooCommerce
 * (`woocommerce_output_all_notices()`, con sus plantillas y filtros) dentro de la envoltura del widget, y el script
 * propio (`store/assets/notices.js`, sin jQuery) lleva a ella, como PRO, los avisos que WooCommerce pinte en otro sitio de
 * la página —también los que lleguen después, al actualizar el carrito o el pago— y desplaza la vista hasta ellos.
 *
 * Diferencias declaradas con PRO:
 * - Sin `<style>` en el marcado: la hoja propia oculta los avisos de fuera del widget (como el estilo en línea de PRO).
 * - Los avisos que pinta el servidor se ven sin esperar al script (PRO los ocultaba hasta que corría el suyo).
 * - Los avisos que llegan después se detectan con un `MutationObserver` (PRO escuchaba los eventos de jQuery de
 *   WooCommerce); con movimiento reducido, el desplazamiento no se anima.
 */
class Notices extends Base {

	public function get_name() {
		return 'digitalisimo-woo-notices';
	}

	public function get_title() {
		return esc_html__( 'Avisos de la tienda', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-woocommerce-notices';
	}

	public function get_keywords() {
		return array( 'woocommerce', 'avisos', 'notificaciones', 'mensajes', 'notices', 'notifications' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! self::optimized_markup();
	}

	public function get_help_url() {
		return '';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section',
			array(
				'label' => esc_html__( 'Avisos de la tienda', 'pro-elements' ),
			)
		);

		$this->add_control(
			'where_to_appear_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Coloca este widget en el lugar de la página o de la plantilla donde quieras que aparezcan los avisos.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'site_settings_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: 1: apertura del enlace, 2: cierre del enlace. */
					esc_html__( 'Para cambiar el diseño de los avisos, ve a los %1$sajustes de WooCommerce%2$s del sitio.', 'pro-elements' ),
					'<a href="#" onclick="elementorPro.modules.woocommerce.openSiteSettingsTab( \'settings-woocommerce\', \'section_woocommerce_notices\' );">',
					'</a>'
				),
				'content_classes' => 'elementor-descriptor elementor-descriptor-subtle',
			)
		);

		$this->add_control(
			'one_per_page_notice',
			array(
				'type'       => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'heading'    => esc_html__( 'Nota:', 'pro-elements' ),
				'content'    => esc_html__( 'Sólo puedes añadir el widget de avisos una vez por página.', 'pro-elements' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render_widget() {
		if ( self::is_editor() ) {
			$this->render_demo_notice();
			return;
		}
		echo '<div class="digi-woo-notices" data-digi-woo-notices>';
		woocommerce_output_all_notices();
		echo '</div>';
	}

	/** Aviso de muestra en el editor (y en su vista previa), con la plantilla de avisos de WooCommerce. */
	protected function render_demo_notice() {
		echo '<div class="digi-woo-notices__demo">';
		wc_get_template(
			'notices/notice.php',
			array(
				'notices' => array(
					array(
						'notice' => esc_html__( 'Así se ve un aviso de WooCommerce. (No lo verás al previsualizar el sitio.)', 'pro-elements' ),
						'data'   => array(),
					),
				),
			)
		);
		echo '</div>';
	}
}
