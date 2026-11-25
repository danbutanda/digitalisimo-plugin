<?php
/**
 * Preparación del ensayo «el Asistente IA como agente» (paso runPHP del blueprint de ia-assistant-agent.sh y de
 * ia-assistant-real.sh): un sitio como el del caso plomerito (2026-11-23) con WooCommerce, DIGITALÍSIMO Elements (MCP
 * del sitio y de la tienda activos) e IA Tools:
 *   - el producto «Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS» a $10,000 con descripción,
 *     beneficios e imágenes;
 *   - un pie de Elementor con el WhatsApp del sitio (enlace wa.me), su teléfono y su correo;
 *   - páginas reales de privacidad, términos y reembolso (enlazadas en los ajustes de WordPress y WooCommerce);
 *   - Anthropic como proveedor: con una clave simulada (la sonda contesta) o, en el modo real, con la clave que el
 *     arnés deja en /digi-ias/k: se lee, se guarda en la opción del sitio y el archivo se borra en el acto.
 * Deja /digi-ias/ready.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-ias';
try {
	$mode = trim( (string) @file_get_contents( $dir . '/mode.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$key  = '';
	if ( 'real' === $mode ) {
		$key = trim( (string) @file_get_contents( $dir . '/k' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $dir . '/k' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( strlen( $key ) < 20 ) {
			throw new RuntimeException( 'Modo real sin la clave de Anthropic.' );
		}
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ias-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
	update_option( 'timezone_string', 'America/Mexico_City' );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'El Plomerito' );
	update_option( 'blogdescription', 'Purificadores, filtros y plomería en Torreón' );
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
	update_option( 'woocommerce_store_address', 'Blvd. Independencia 1500' );
	update_option( 'woocommerce_store_city', 'Torreón' );
	update_option( 'woocommerce_store_postcode', '27000' );
	update_option( 'woocommerce_default_country', 'MX:COA' );
	update_option( 'woocommerce_email_from_address', 'ventas@elplomerito.test' );

	// Imágenes del producto (generadas aquí: nada se descarga).
	$images = array();
	foreach ( array( 'purificador-rotoplas-negro' => array( 20, 20, 20 ), 'purificador-rotoplas-tarja' => array( 30, 90, 160 ) ) as $name => $rgb ) {
		$upload = wp_upload_dir();
		$file   = trailingslashit( $upload['path'] ) . $name . '.png';
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$im = imagecreatetruecolor( 800, 800 );
			imagefill( $im, 0, 0, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
			imagefilledrectangle( $im, 300, 150, 500, 650, imagecolorallocate( $im, 220, 220, 220 ) );
			imagepng( $im, $file );
			imagedestroy( $im );
		} else {
			file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );
		}
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Purificador ROTOPLAS', 'post_status' => 'inherit' ), $file );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS' );
		$images[] = (int) $id;
	}
	// El producto del caso (ID real en plomerito: 252).
	$product = new WC_Product_Simple();
	$product->set_name( 'Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS' );
	$product->set_slug( 'purificador-alcalinizador-rotoplas-negro' );
	$product->set_regular_price( '10000' );
	$product->set_sku( 'ROTO-PA-NEGRO' );
	$product->set_short_description( 'Agua purificada y alcalina directo de tu tarja, sin garrafones.' );
	$product->set_description( "<p>El Purificador y Alcalinizador de Agua sobre tarja ROTOPLAS en color negro te da agua purificada y alcalina al instante, sin instalaciones complicadas.</p>\n<h3>Beneficios</h3>\n<ul>\n<li>Elimina cloro, sedimentos, malos olores y sabores.</li>\n<li>Alcaliniza el agua (pH 8 a 9.5) y le agrega minerales.</li>\n<li>Se instala sobre la tarja en minutos, sin obra.</li>\n<li>Ahorra hasta 90% frente a comprar garrafones.</li>\n<li>Filtro de larga duración: hasta 6 meses o 3,000 litros.</li>\n</ul>\n<p>Incluye llave cromada y cartucho. Garantía de 1 año del fabricante.</p>" );
	$product->set_image_id( $images[0] );
	$product->set_gallery_image_ids( array( $images[1] ) );
	$product->set_status( 'publish' );
	$product_id = $product->save();

	// Páginas reales de políticas y contacto.
	$page = function ( $title, $slug, $content ) {
		$found = get_page_by_path( $slug );
		if ( $found ) {
			wp_update_post( array( 'ID' => $found->ID, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ) );
			return (int) $found->ID;
		}
		return (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ) );
	};
	$privacy = $page( 'Aviso de privacidad', 'aviso-de-privacidad', '<p>El Plomerito protege tus datos personales conforme a la ley.</p>' );
	$terms   = $page( 'Términos y condiciones', 'terminos-y-condiciones', '<p>Condiciones de venta de El Plomerito.</p>' );
	$refund  = $page( 'Política de reembolso y devoluciones', 'reembolsos-y-devoluciones', '<p>Tienes 30 días para devolver tu producto sin uso. Garantía de 1 año del fabricante en purificadores.</p>' );
	update_option( 'wp_page_for_privacy_policy', $privacy );
	update_option( 'woocommerce_terms_page_id', $terms );
	update_option( 'woocommerce_refund_returns_page_id', $refund );

	// Pie de Elementor con el WhatsApp, el teléfono y el correo del sitio.
	$footer = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Pie del sitio' ) );
	update_post_meta( $footer, '_elementor_template_type', 'footer' );
	update_post_meta( $footer, '_elementor_edit_mode', 'builder' );
	$data = array(
		array( 'id' => 'f1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'f2', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'El Plomerito · Torreón' ), 'elements' => array() ),
			array( 'id' => 'f3', 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array(
				array( '_id' => 'a', 'text' => 'WhatsApp 871 123 4567', 'link' => array( 'url' => 'https://wa.me/5218711234567' ) ),
				array( '_id' => 'b', 'text' => 'Llámanos', 'link' => array( 'url' => 'tel:+528711234567' ) ),
				array( '_id' => 'c', 'text' => 'ventas@elplomerito.test', 'link' => array( 'url' => 'mailto:ventas@elplomerito.test' ) ),
				array( '_id' => 'd', 'text' => 'Aviso de privacidad', 'link' => array( 'url' => get_permalink( $privacy ) ) ),
			) ), 'elements' => array() ),
		) ),
	);
	update_post_meta( $footer, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

	$ai = array(
		'providers'             => array( 'anthropic' => array( 'enabled' => 1, 'key' => 'real' === $mode ? $key : 'sk-ant-simulada-0123456789abcdefABCDEF', 'url' => '' ) ),
		'assistant_rate_minute' => 0,
		'assistant_daily_calls' => 0,
		'assistant_concurrency' => 1,
	);
	update_option( 'digitalisimo_ai_options', $ai, false );
	$key = '';
	$admin = get_user_by( 'login', 'admin' );
	file_put_contents(
		$dir . '/ready.json',
		wp_json_encode(
			array(
				'mode'      => $mode,
				'product'   => (int) $product_id,
				'pages'     => array( 'privacidad' => $privacy, 'terminos' => $terms, 'reembolso' => $refund ),
				'footer'    => (int) $footer,
				'admin'     => (int) $admin->ID,
				'last_id'   => (int) $GLOBALS['wpdb']->get_var( "SELECT MAX(ID) FROM {$GLOBALS['wpdb']->posts}" ),
				'wp'        => $GLOBALS['wp_version'],
				'php'       => PHP_VERSION,
				'woo'       => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'abilities' => function_exists( 'wp_register_ability' ),
			)
		)
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
