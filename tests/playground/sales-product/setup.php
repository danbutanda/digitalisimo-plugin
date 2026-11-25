<?php
/**
 * Preparación del ensayo de la landing de producto (paso runPHP del blueprint de sales-product.sh), después de activar
 * la tienda: instala la sonda, activa DIGITALÍSIMO Elements, enciende el MCP del sitio y arma una tienda como la del
 * caso real (plomerito, 2026-11-23):
 * - pesos mexicanos, hora de la Ciudad de México, transferencia y pago contra entrega activos, una zona de envío;
 * - el logo del sitio, el aviso de privacidad y los términos publicados, la página de contacto; la política de
 *   reembolso de la tienda se queda en borrador (la landing no debe enlazarla);
 * - un pie del constructor con un enlace wa.me (el WhatsApp que el sitio ya publica);
 * - el producto: «Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS», $10,000, con descripción con lista,
 *   garantía, atributo, peso, medidas y tres imágenes.
 * La contraseña de aplicación del administrador va a /digi-probe/secrets.json (el arnés la borra). Deja
 * /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-probe';

/** Una imagen JPEG o PNG con un color y un texto, como adjunto de la biblioteca. */
function digi_sp_image( string $name, int $w, int $h, array $rgb, string $label, string $type = 'jpg' ): int {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		throw new RuntimeException( 'Playground sin GD: no se pueden crear las imágenes del producto.' );
	}
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
	$white = imagecolorallocate( $im, 255, 255, 255 );
	imagefilledrectangle( $im, (int) ( $w * 0.3 ), (int) ( $h * 0.15 ), (int) ( $w * 0.7 ), (int) ( $h * 0.85 ), imagecolorallocate( $im, 30, 30, 30 ) );
	imagestring( $im, 5, 20, 20, $label, $white );
	ob_start();
	'png' === $type ? imagepng( $im ) : imagejpeg( $im, null, 85 );
	$bytes = (string) ob_get_clean();
	$up    = wp_upload_bits( $name . '.' . $type, null, $bytes );
	if ( ! empty( $up['error'] ) ) {
		throw new RuntimeException( 'No se subió ' . $name . ': ' . $up['error'] );
	}
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'png' === $type ? 'image/png' : 'image/jpeg',
			'post_title'     => $label,
			'post_status'    => 'inherit',
		),
		$up['file']
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $label );
	return (int) $id;
}

/** Una página publicada (o la que ya existe con esa dirección, publicada). */
function digi_sp_page( string $title, string $slug, string $content = '' ): int {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish', 'post_title' => $title ) );
		return (int) $page->ID;
	}
	return (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => '' !== $content ? $content : '<!-- wp:paragraph --><p>' . esc_html( $title ) . ' del sitio de ensayo.</p><!-- /wp:paragraph -->',
		)
	);
}

try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-sp-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-sp-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'La tienda no está activa.' );
	}
	$activated = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
	if ( is_wp_error( $activated ) ) {
		throw new RuntimeException( 'No se activó Elements: ' . $activated->get_error_message() );
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_onboarded', true );
	update_option( 'timezone_string', 'America/Mexico_City' );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'Plomería de Ensayo' );

	// La tienda.
	update_option( 'woocommerce_currency', 'MXN' );
	update_option( 'woocommerce_default_country', 'MX:COA' );
	update_option( 'woocommerce_weight_unit', 'kg' );
	update_option( 'woocommerce_dimension_unit', 'cm' );
	update_option( 'woocommerce_coming_soon', 'no' );
	update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
	if ( class_exists( 'WC_Install' ) && method_exists( 'WC_Install', 'create_pages' ) ) {
		WC_Install::create_pages();
	}
	update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Transferencia bancaria', 'description' => 'Transferencia a nuestra cuenta.', 'instructions' => '' ) );
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Pago contra entrega', 'description' => 'Paga al recibir.', 'instructions' => '', 'enable_for_methods' => array(), 'enable_for_virtual' => 'yes' ) );
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( 'México' );
	$zone->add_location( 'MX', 'country' );
	$zone->save();
	$instance = $zone->add_shipping_method( 'flat_rate' );
	update_option( 'woocommerce_flat_rate_' . $instance . '_settings', array( 'title' => 'Envío estándar', 'tax_status' => 'none', 'cost' => '150' ) );

	// Las páginas del sitio: privacidad y términos publicados, contacto; la de reembolso de la tienda, en borrador.
	$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $privacy ) {
		wp_update_post( array( 'ID' => $privacy, 'post_status' => 'publish', 'post_title' => 'Aviso de privacidad' ) );
	} else {
		$privacy = digi_sp_page( 'Aviso de privacidad', 'privacidad' );
		update_option( 'wp_page_for_privacy_policy', $privacy );
	}
	update_option( 'woocommerce_terms_page_id', digi_sp_page( 'Términos y condiciones', 'terminos' ) );
	digi_sp_page( 'Contacto', 'contacto' );
	$refund = (int) get_option( 'woocommerce_refund_returns_page_id' );
	if ( $refund ) {
		wp_update_post( array( 'ID' => $refund, 'post_status' => 'draft' ) );
	}

	// El logo del sitio y el pie con el WhatsApp que el sitio ya publica.
	set_theme_mod( 'custom_logo', digi_sp_image( 'logo-plomeria', 480, 140, array( 21, 146, 218 ), 'Plomeria de Ensayo', 'png' ) );
	$footer = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Pie del sitio' ) );
	$data   = array(
		array(
			'id'       => 'f00d001',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(
				array(
					'id'         => 'f00d002',
					'elType'     => 'widget',
					'widgetType' => 'button',
					'settings'   => array(
						'text' => 'Escríbenos',
						'link' => array( 'url' => 'https://wa.me/5218711112222?text=Hola', 'is_external' => 'on' ),
					),
					'elements'   => array(),
				),
			),
		),
	);
	update_post_meta( $footer, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $footer, '_elementor_template_type', 'footer' );
	update_post_meta( $footer, '_elementor_edit_mode', 'builder' );
	update_post_meta( $footer, '_elementor_conditions', array( 'include/general' ) ); // Un pie en uso en todo el sitio.

	// El producto del caso.
	$images  = array(
		digi_sp_image( 'purificador-frente', 1200, 900, array( 15, 20, 26 ), 'Purificador ROTOPLAS - frente' ),
		digi_sp_image( 'purificador-tarja', 1200, 900, array( 11, 42, 69 ), 'Purificador ROTOPLAS - en la tarja' ),
		digi_sp_image( 'purificador-cartucho', 1200, 900, array( 0, 120, 90 ), 'Purificador ROTOPLAS - cartucho' ),
	);
	$product = new WC_Product_Simple();
	$product->set_name( 'Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS' );
	$product->set_regular_price( '10000' );
	$product->set_short_description( '<p>Agua purificada y alcalina directo de tu tarja, sin garrafones. Se instala sobre la tarja en minutos.</p>' );
	$product->set_description( '<p>El purificador elimina el cloro, el sarro y el mal sabor del agua de la llave.</p><ul><li>Agua alcalina: eleva el pH del agua para un mejor sabor</li><li>Filtra el 99 % de bacterias y sedimentos</li><li>Sin garrafones: ahorra dinero y espacio</li><li>Cartucho de larga duración</li></ul><p>Incluye garantía de 1 año contra defectos de fabricación.</p>' );
	$product->set_image_id( $images[0] );
	$product->set_gallery_image_ids( array( $images[1], $images[2] ) );
	$color = new WC_Product_Attribute();
	$color->set_id( 0 );
	$color->set_name( 'Color' );
	$color->set_options( array( 'Negro' ) );
	$color->set_visible( true );
	$color->set_position( 0 );
	$product->set_attributes( array( $color ) );
	$product->set_weight( '3.5' );
	$product->set_length( '30' );
	$product->set_width( '20' );
	$product->set_height( '45' );
	$product->set_stock_status( 'instock' );
	$product->set_status( 'publish' );
	$product_id = (int) $product->save();

	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) {
		throw new RuntimeException( 'Falta el usuario admin.' );
	}
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-sales-product' ) );
	if ( is_wp_error( $created ) ) {
		throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	}
	file_put_contents( $dir . '/secrets.json', wp_json_encode( array( 'password:admin' => $created[0] ) ) );
	flush_rewrite_rules( false );
	file_put_contents(
		$dir . '/setup.json',
		wp_json_encode(
			array(
				'versions'   => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION . ' · tienda ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : '?' ),
				'product_id' => $product_id,
				'images'     => array_map( 'wp_get_attachment_url', $images ),
				'logo'       => wp_get_attachment_url( (int) get_theme_mod( 'custom_logo' ) ),
				'privacy'    => get_permalink( $privacy ),
				'terms'      => get_permalink( (int) get_option( 'woocommerce_terms_page_id' ) ),
				'checkout'   => wc_get_checkout_url(),
				'contact'    => get_permalink( get_page_by_path( 'contacto' ) ),
			)
		)
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
