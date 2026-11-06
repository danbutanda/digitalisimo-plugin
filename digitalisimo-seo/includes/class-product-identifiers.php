<?php
defined( 'ABSPATH' ) || exit;

/**
 * MPN, ISBN y estado del producto en el editor de WooCommerce (RM8 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * El GTIN vive en el campo nativo de WooCommerce; aquí sólo se piden los identificadores que WooCommerce no
 * tiene. Se guardan como metas del producto y de cada variación (`digitalisimo_seo_mpn`, `digitalisimo_seo_isbn`)
 * y el estado, sólo del producto (`digitalisimo_seo_condition`). Los publica
 * `Digitalisimo_Integrations_Schema_Graph`, en el único `@graph` y sólo sin otro plugin SEO activo.
 *
 * WooCommerce ya comprueba el nonce del formulario (o el de la petición AJAX de variaciones) y que el usuario puede
 * editar productos antes de los ganchos de guardado que se usan aquí.
 */
class Digitalisimo_Integrations_Product_Identifiers {
	const MPN       = 'digitalisimo_seo_mpn';
	const ISBN      = 'digitalisimo_seo_isbn';
	const CONDITION = 'digitalisimo_seo_condition';

	public static function init() {
		add_action( 'woocommerce_product_options_global_unique_id', array( __CLASS__, 'fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'save_variation' ), 10, 2 );
	}

	/** Estados que puede elegir un producto: el del sitio, ninguno o uno propio. */
	public static function condition_choices() {
		$site  = Digitalisimo_Integrations_Schema_Graph::sanitize_condition( Digitalisimo_Integrations_SEO_Resolver::option( 'seo_product_condition', '' ) );
		$label = '' === $site ? 'no se publica' : Digitalisimo_Integrations_Schema_Graph::CONDITIONS[ $site ];
		return array( '' => 'El del sitio (' . $label . ')', 'none' => 'No publicar' ) + Digitalisimo_Integrations_Schema_Graph::CONDITIONS;
	}

	public static function fields() {
		global $product_object;
		if ( ! is_object( $product_object ) || ! function_exists( 'woocommerce_wp_text_input' ) ) return;
		$id = $product_object->get_id();
		woocommerce_wp_text_input( array( 'id' => self::MPN, 'value' => (string) get_post_meta( $id, self::MPN, true ), 'label' => '<abbr title="Manufacturer Part Number">MPN</abbr>', 'desc_tip' => true, 'description' => 'Código del fabricante, tal como lo da (hasta 70 caracteres). Se publica en el schema del producto.', 'custom_attributes' => array( 'maxlength' => Digitalisimo_Integrations_Schema_Graph::MPN_MAX ) ) );
		woocommerce_wp_text_input( array( 'id' => self::ISBN, 'value' => (string) get_post_meta( $id, self::ISBN, true ), 'label' => '<abbr title="International Standard Book Number">ISBN</abbr>', 'desc_tip' => true, 'description' => 'Sólo para libros: ISBN-10 o ISBN-13, con o sin guiones. Se comprueba el dígito de control y un ISBN equivocado no se guarda. El producto se publica también como Book.', 'custom_attributes' => array( 'pattern' => '[0-9Xx\\- ]{10,17}', 'inputmode' => 'text' ) ) );
		woocommerce_wp_select( array( 'id' => self::CONDITION, 'value' => (string) get_post_meta( $id, self::CONDITION, true ), 'label' => 'Estado', 'desc_tip' => true, 'description' => 'Estado del artículo (itemCondition) en el schema. El del sitio se elige en SEO → Schema.', 'options' => self::condition_choices() ) );
	}

	public static function variation_fields( $loop, $variation_data, $variation ) {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) || ! is_object( $variation ) ) return;
		$loop = (int) $loop;
		echo '<div class="digitalisimo-seo-variation-ids">';
		woocommerce_wp_text_input( array( 'id' => self::MPN . '_variation' . $loop, 'name' => self::MPN . '_variation[' . $loop . ']', 'value' => (string) get_post_meta( $variation->ID, self::MPN, true ), 'label' => 'MPN', 'wrapper_class' => 'form-row form-row-first', 'desc_tip' => true, 'description' => 'Código del fabricante de esta variación. Vacío: la variación no publica ninguno (nunca el del producto).', 'custom_attributes' => array( 'maxlength' => Digitalisimo_Integrations_Schema_Graph::MPN_MAX ) ) );
		woocommerce_wp_text_input( array( 'id' => self::ISBN . '_variation' . $loop, 'name' => self::ISBN . '_variation[' . $loop . ']', 'value' => (string) get_post_meta( $variation->ID, self::ISBN, true ), 'label' => 'ISBN', 'wrapper_class' => 'form-row form-row-last', 'desc_tip' => true, 'description' => 'ISBN-10 o ISBN-13 de esta variación (por ejemplo, otra edición).', 'custom_attributes' => array( 'pattern' => '[0-9Xx\\- ]{10,17}' ) ) );
		echo '</div>';
	}

	public static function save( $product ) {
		if ( ! is_object( $product ) || ! isset( $_POST[ self::MPN ], $_POST[ self::ISBN ] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verificó el nonce.
		self::store( $product, wp_unslash( $_POST[ self::MPN ] ), wp_unslash( $_POST[ self::ISBN ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea en store().
		if ( isset( $_POST[ self::CONDITION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$condition = sanitize_text_field( wp_unslash( $_POST[ self::CONDITION ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$condition = 'none' === $condition ? 'none' : Digitalisimo_Integrations_Schema_Graph::sanitize_condition( $condition );
			if ( '' === $condition ) $product->delete_meta_data( self::CONDITION ); else $product->update_meta_data( self::CONDITION, $condition );
		}
	}

	public static function save_variation( $variation, $i ) {
		$i = (int) $i;
		if ( ! is_object( $variation ) || ! isset( $_POST[ self::MPN . '_variation' ][ $i ], $_POST[ self::ISBN . '_variation' ][ $i ] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::store( $variation, wp_unslash( $_POST[ self::MPN . '_variation' ][ $i ] ), wp_unslash( $_POST[ self::ISBN . '_variation' ][ $i ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Guarda MPN e ISBN en el objeto de WooCommerce (que se guarda después). Un campo vacío borra la meta;
	 * un ISBN que no supera el dígito de control no se guarda: se conserva el anterior y se avisa.
	 */
	private static function store( $object, $mpn, $isbn ) {
		$mpn_raw = trim( (string) $mpn );
		$mpn     = Digitalisimo_Integrations_Schema_Graph::sanitize_mpn( $mpn_raw );
		if ( '' !== $mpn ) $object->update_meta_data( self::MPN, $mpn );
		elseif ( '' === $mpn_raw ) $object->delete_meta_data( self::MPN );
		else self::error( 'El MPN tiene más de ' . Digitalisimo_Integrations_Schema_Graph::MPN_MAX . ' caracteres: no se guardó.' );
		$isbn_raw = trim( (string) $isbn );
		$isbn     = Digitalisimo_Integrations_Schema_Graph::sanitize_isbn( $isbn_raw );
		if ( '' !== $isbn ) $object->update_meta_data( self::ISBN, $isbn );
		elseif ( '' === $isbn_raw ) $object->delete_meta_data( self::ISBN );
		else self::error( 'El ISBN «' . sanitize_text_field( $isbn_raw ) . '» no es válido (revisa el dígito de control): no se guardó.' );
	}

	private static function error( $message ) {
		if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) WC_Admin_Meta_Boxes::add_error( esc_html( $message ) );
	}
}
