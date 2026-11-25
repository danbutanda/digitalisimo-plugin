<?php
/**
 * Preparación del ensayo «primero hacer y mostrar» (ia-assistant-mostrar.sh, caso plomerito 2 del 2026-11-24): una
 * tienda INVENTADA (ningún dato de un sitio real: el repositorio es público) parecida a la del caso:
 *   - el sitio en UTC (sin ciudad elegida), como el del caso, y la tienda de WooCommerce en México (Coahuila);
 *   - el producto «Purificador Alcalino de Agua bajo tarja AguaClara Negro» a $10,000, con descripción, beneficios
 *     e imágenes, y otros dos productos que no son el de la promoción;
 *   - un pie de Elementor con el WhatsApp de la tienda (enlace wa.me), su teléfono y su correo;
 *   - páginas reales de privacidad, términos y devoluciones (enlazadas en WordPress y WooCommerce);
 *   - transferencia bancaria y envío a domicilio activos;
 *   - Anthropic con la clave que el arnés deja en /digi-ias/k (se lee, se guarda en la opción y el archivo se borra).
 * Deja /digi-ias/ready.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-ias';
try {
	$mode = trim( (string) @file_get_contents( $dir . '/mode.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$key  = trim( (string) @file_get_contents( $dir . '/k' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink( $dir . '/k' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( 'real' !== $mode || strlen( $key ) < 20 ) {
		throw new RuntimeException( 'El ensayo «mostrar» sólo corre con la API real y su clave.' );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ias-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
	// Como el sitio del caso: WordPress en UTC, sin ciudad.
	update_option( 'timezone_string', '' );
	update_option( 'gmt_offset', 0 );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'AguaClara Laguna' );
	update_option( 'blogdescription', 'Purificadores y filtros de agua para tu casa' );
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php' ) as $plugin ) {
		$done = activate_plugin( $plugin );
		if ( is_wp_error( $done ) ) {
			throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $done->get_error_message() );
		}
	}
	if ( class_exists( 'WC_Install' ) ) {
		WC_Install::install();
		WC_Install::create_pages();
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_onboarded', true );
	update_option( 'digitalisimo_elements_woo_mcp', array( 'mode' => 'write', 'own' => array( 'mode' ) ), false );
	update_option( 'woocommerce_currency', 'MXN' );
	update_option( 'woocommerce_store_address', 'Calle Ficticia 123' );
	update_option( 'woocommerce_store_city', 'Gómez Palacio' );
	update_option( 'woocommerce_store_postcode', '35000' );
	update_option( 'woocommerce_default_country', 'MX:COA' );
	update_option( 'woocommerce_email_from_address', 'hola@aguaclara.test' );
	update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Transferencia bancaria', 'description' => 'Paga por transferencia.' ) );
	if ( class_exists( 'WC_Shipping_Zone' ) ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'México' );
		$zone->add_location( 'MX', 'country' );
		$zone->save();
		$zone->add_shipping_method( 'flat_rate' );
	}

	// Imágenes de los productos (generadas aquí: nada se descarga).
	$image = function ( $name, array $rgb ) {
		$upload = wp_upload_dir();
		$file   = trailingslashit( $upload['path'] ) . $name . '.png';
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$im = imagecreatetruecolor( 800, 800 );
			imagefill( $im, 0, 0, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
			imagefilledrectangle( $im, 300, 150, 500, 650, imagecolorallocate( $im, 230, 230, 230 ) );
			imagepng( $im, $file );
			imagedestroy( $im );
		} else {
			file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );
		}
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Purificador AguaClara', 'post_status' => 'inherit' ), $file );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Purificador Alcalino de Agua bajo tarja AguaClara Negro' );
		return (int) $id;
	};
	$images = array( $image( 'purificador-aguaclara-negro', array( 25, 25, 25 ) ), $image( 'purificador-aguaclara-tarja', array( 20, 110, 170 ) ) );
	$product = new WC_Product_Simple();
	$product->set_name( 'Purificador Alcalino de Agua bajo tarja AguaClara Negro' );
	$product->set_slug( 'purificador-alcalino-aguaclara-negro' );
	$product->set_regular_price( '10000' );
	$product->set_sku( 'AC-PA-NEGRO' );
	$product->set_short_description( 'Agua purificada y alcalina en tu cocina, sin garrafones.' );
	$product->set_description( "<p>El Purificador Alcalino AguaClara se instala bajo la tarja y te da agua purificada y alcalina al instante.</p>\n<h3>Beneficios</h3>\n<ul>\n<li>Quita cloro, sedimentos, olores y sabores.</li>\n<li>Alcaliniza el agua y le agrega minerales.</li>\n<li>Se instala en una hora, sin obra.</li>\n<li>Ahorras frente a comprar garrafones.</li>\n<li>Cartucho de larga duración: hasta 6 meses.</li>\n</ul>\n<p>Incluye llave de acero inoxidable y cartucho. Garantía de 1 año.</p>" );
	$product->set_image_id( $images[0] );
	$product->set_gallery_image_ids( array( $images[1] ) );
	$product->set_weight( '4' );
	$product->set_status( 'publish' );
	$product_id = $product->save();
	foreach ( array( array( 'Filtro de regadera AguaClara', '899' ), array( 'Cartucho de repuesto AguaClara', '1200' ) ) as $other ) {
		$p = new WC_Product_Simple();
		$p->set_name( $other[0] );
		$p->set_regular_price( $other[1] );
		$p->set_status( 'publish' );
		$p->save();
	}

	// Páginas reales de políticas y contacto.
	$page = function ( $title, $slug, $content ) {
		$found = get_page_by_path( $slug );
		if ( $found ) {
			wp_update_post( array( 'ID' => $found->ID, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ) );
			return (int) $found->ID;
		}
		return (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ) );
	};
	$privacy = $page( 'Aviso de privacidad', 'aviso-de-privacidad', '<p>AguaClara Laguna protege tus datos personales.</p>' );
	$terms   = $page( 'Términos y condiciones', 'terminos-y-condiciones', '<p>Condiciones de venta de AguaClara Laguna.</p>' );
	$refund  = $page( 'Política de devoluciones', 'devoluciones', '<p>Tienes 30 días para devolver tu producto sin uso. Los purificadores tienen 1 año de garantía.</p>' );
	update_option( 'wp_page_for_privacy_policy', $privacy );
	update_option( 'woocommerce_terms_page_id', $terms );
	update_option( 'woocommerce_refund_returns_page_id', $refund );

	// Pie de Elementor con el WhatsApp, el teléfono y el correo de la tienda (números inventados).
	$footer = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Pie del sitio' ) );
	update_post_meta( $footer, '_elementor_template_type', 'footer' );
	update_post_meta( $footer, '_elementor_edit_mode', 'builder' );
	update_post_meta( $footer, '_elementor_conditions', array( 'include/general' ) );
	$data = array(
		array( 'id' => 'f1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'f2', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'AguaClara Laguna' ), 'elements' => array() ),
			array( 'id' => 'f3', 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array(
				array( '_id' => 'a', 'text' => 'WhatsApp 871 555 0199', 'link' => array( 'url' => 'https://wa.me/5218715550199' ) ),
				array( '_id' => 'b', 'text' => 'Llámanos', 'link' => array( 'url' => 'tel:+528715550199' ) ),
				array( '_id' => 'c', 'text' => 'hola@aguaclara.test', 'link' => array( 'url' => 'mailto:hola@aguaclara.test' ) ),
				array( '_id' => 'd', 'text' => 'Aviso de privacidad', 'link' => array( 'url' => get_permalink( $privacy ) ) ),
			) ), 'elements' => array() ),
		) ),
	);
	update_post_meta( $footer, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

	$ai = array(
		'providers'             => array( 'anthropic' => array( 'enabled' => 1, 'key' => $key, 'url' => '' ) ),
		'assistant_rate_minute' => 0,
		'assistant_daily_calls' => 0,
		'assistant_concurrency' => 1,
	);
	update_option( 'digitalisimo_ai_options', $ai, false );
	$key   = '';
	$admin = get_user_by( 'login', 'admin' );
	file_put_contents(
		$dir . '/ready.json',
		wp_json_encode(
			array(
				'mode'      => $mode,
				'scenario'  => 'mostrar',
				'product'   => (int) $product_id,
				'pages'     => array( 'privacidad' => $privacy, 'terminos' => $terms, 'reembolso' => $refund ),
				'footer'    => (int) $footer,
				'admin'     => (int) $admin->ID,
				'whatsapp'  => '5218715550199',
				'last_id'   => (int) $GLOBALS['wpdb']->get_var( "SELECT MAX(ID) FROM {$GLOBALS['wpdb']->posts}" ),
				'wp'        => $GLOBALS['wp_version'],
				'php'       => PHP_VERSION,
				'woo'       => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'site_tz'   => wp_timezone_string(),
				'abilities' => function_exists( 'wp_register_ability' ),
			)
		)
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
