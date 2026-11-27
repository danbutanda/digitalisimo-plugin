<?php
/**
 * Preparación del ensayo «sin tecnicismos» (ia-assistant-simple.sh, casos plomerito 2, 3 y 4 del 2026-11-24 al
 * 2026-11-27): una ferretería INVENTADA (ningún dato de un sitio real: el repositorio es público) parecida a la del caso:
 *   - WooCommerce con transferencia y pago contra entrega, una zona de envío de México y «WooCommerce en el MCP» en
 *     «Sólo lectura» (lo predeterminado en los sitios);
 *   - Skydropx 2.1.5 real (sin vincular: la plataforma no se alcanza en el ensayo) y seis productos, tres SIN peso ni
 *     medidas (uno dice su peso en la descripción) y un purificador de $10,000 para la landing;
 *   - DIGITALÍSIMO SEO activo, sin descripciones para Google; la «Sample Page» de WordPress publicada y una portada con
 *     un párrafo de texto de muestra en latín;
 *   - políticas reales y un pie con el WhatsApp de la tienda;
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
	// MODE=medir (ia-assistant-medir.sh): sin clave ni API; la sonda guarda cada petición y contesta sola.
	if ( 'medir' === $mode ) {
		$key = 'sk-medir-sin-clave-real-0000000000';
	} elseif ( 'real' !== $mode || strlen( $key ) < 20 ) {
		throw new RuntimeException( 'El ensayo «simple» sólo corre con la API real y su clave (o con MODE=medir).' );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ias-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
	update_option( 'timezone_string', 'America/Monterrey' );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'Ferretería El Tornillo' );
	update_option( 'blogdescription', 'Ferretería y plomería en Gómez Palacio' );
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-seo/digitalisimo-integrations.php' ) as $plugin ) {
		$done = activate_plugin( $plugin );
		if ( is_wp_error( $done ) ) {
			throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $done->get_error_message() );
		}
	}
	if ( class_exists( 'WC_Install' ) ) {
		WC_Install::install();
		WC_Install::create_pages();
	}
	// Skydropx se carga desde la siguiente petición (su activación pide WooCommerce ya cargado; aquí basta con cargarlo).
	if ( ! file_exists( WP_PLUGIN_DIR . '/skydropx/skydropx.php' ) ) {
		throw new RuntimeException( 'No está montado skydropx/skydropx.php.' );
	}
	$active = (array) get_option( 'active_plugins', array() );
	if ( ! in_array( 'skydropx/skydropx.php', $active, true ) ) {
		$active[] = 'skydropx/skydropx.php';
		update_option( 'active_plugins', array_values( $active ) );
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_onboarded', true );
	update_option( 'digitalisimo_elements_woo_mcp', array( 'mode' => 'read', 'own' => array( 'mode' ) ), false );
	update_option( 'woocommerce_currency', 'MXN' );
	update_option( 'woocommerce_weight_unit', 'kg' );
	update_option( 'woocommerce_dimension_unit', 'cm' );
	update_option( 'woocommerce_store_address', 'Calle Ficticia 45' );
	update_option( 'woocommerce_store_city', 'Gómez Palacio' );
	update_option( 'woocommerce_store_postcode', '35000' );
	update_option( 'woocommerce_default_country', 'MX:DUR' );
	update_option( 'woocommerce_email_from_address', 'ventas@eltornillo.test' );
	update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Transferencia bancaria', 'description' => 'Paga por transferencia.' ) );
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Pago contra entrega', 'description' => 'Pagas al recibir.' ) );
	if ( class_exists( 'WC_Shipping_Zone' ) ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'México' );
		$zone->add_location( 'MX', 'country' );
		$zone->save();
		$zone->add_shipping_method( 'flat_rate' );
	}

	$image = function ( $name, array $rgb, $alt ) {
		$upload = wp_upload_dir();
		$file   = trailingslashit( $upload['path'] ) . $name . '.png';
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$im = imagecreatetruecolor( 600, 600 );
			imagefill( $im, 0, 0, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
			imagefilledrectangle( $im, 200, 120, 400, 480, imagecolorallocate( $im, 235, 235, 235 ) );
			imagepng( $im, $file );
			imagedestroy( $im );
		} else {
			file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );
		}
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $alt, 'post_status' => 'inherit' ), $file );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		return (int) $id;
	};
	$products = array();
	$make     = function ( $name, $price, $desc, $weight, $dims, $rgb ) use ( $image, &$products ) {
		$p = new WC_Product_Simple();
		$p->set_name( $name );
		$p->set_regular_price( $price );
		$p->set_description( $desc );
		$p->set_short_description( strtok( wp_strip_all_tags( $desc ), '.' ) . '.' );
		$p->set_image_id( $image( sanitize_title( $name ), $rgb, $name ) );
		if ( '' !== $weight ) {
			$p->set_weight( $weight );
		}
		if ( $dims ) {
			$p->set_length( $dims[0] );
			$p->set_width( $dims[1] );
			$p->set_height( $dims[2] );
		}
		$p->set_status( 'publish' );
		$products[ $name ] = (int) $p->save();
		return $products[ $name ];
	};
	$make( 'Bomba periférica 1/2 HP El Tornillo', '2450', '<p>Bomba periférica para subir agua al tinaco. Motor de 1/2 HP, cuerpo de hierro fundido.</p><h3>Características</h3><ul><li>Peso: 6 kg</li><li>Medidas de la caja: 30 x 18 x 20 cm</li><li>Garantía de 1 año</li></ul>', '', array(), array( 30, 60, 160 ) );
	$make( 'Llave mezcladora para lavabo cromada', '890', '<p>Llave mezcladora monomando para lavabo, acabado cromado. Incluye mangueras.</p>', '', array(), array( 150, 150, 160 ) );
	$make( 'Juego de desarmadores 6 piezas', '320', '<p>Seis desarmadores planos y de cruz con mango antiderrapante.</p>', '', array(), array( 200, 40, 40 ) );
	$make( 'Taladro percutor 1/2 pulgada 650 W', '1650', '<p>Taladro percutor de 650 W con broquero de 1/2 pulgada y velocidad variable.</p>', '2.1', array( 32, 10, 26 ), array( 40, 120, 60 ) );
	$make( 'Juego de llaves combinadas 12 piezas', '720', '<p>Doce llaves combinadas de 8 a 19 mm en estuche.</p>', '1.4', array( 30, 12, 4 ), array( 90, 90, 90 ) );
	$purifier = $make( 'Purificador de agua bajo tarja AquaPlus', '10000', '<p>Purificador de agua bajo tarja con cinco etapas de filtrado. Se instala en una hora, sin obra.</p><h3>Beneficios</h3><ul><li>Quita cloro, sedimentos, olores y sabores.</li><li>Ahorras frente a comprar garrafones.</li><li>Cartucho de hasta 6 meses.</li></ul><p>Garantía de 1 año.</p>', '4', array( 40, 25, 45 ), array( 20, 20, 20 ) );

	$page = function ( $title, $slug, $content ) {
		$found = get_page_by_path( $slug );
		if ( $found ) {
			wp_update_post( array( 'ID' => $found->ID, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ) );
			return (int) $found->ID;
		}
		return (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ) );
	};
	// La portada con un párrafo de muestra en latín (como la del caso) y la «Sample Page» de WordPress publicada.
	$home    = $page( 'Inicio', 'inicio', "<!-- wp:heading --><h2>Bienvenido a Ferretería El Tornillo</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p>Herramienta, plomería y material eléctrico con envío a todo México.</p><!-- /wp:paragraph -->" );
	$sample  = $page( 'Sample Page', 'sample-page', '<p>This is an example page. It\'s different from a blog post because it will stay in one place and will show up in your site navigation.</p>' );
	$about   = $page( 'Nosotros', 'nosotros', '<p>Somos una ferretería familiar de Gómez Palacio, Durango, desde 1998.</p>' );
	$privacy = $page( 'Aviso de privacidad', 'aviso-de-privacidad', '<p>Ferretería El Tornillo protege tus datos personales.</p>' );
	$terms   = $page( 'Términos y condiciones', 'terminos-y-condiciones', '<p>Condiciones de venta de Ferretería El Tornillo.</p>' );
	$refund  = $page( 'Política de devoluciones', 'devoluciones', '<p>Tienes 30 días para devolver tu producto sin uso.</p>' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home );
	update_option( 'wp_page_for_privacy_policy', $privacy );
	update_option( 'woocommerce_terms_page_id', $terms );
	update_option( 'woocommerce_refund_returns_page_id', $refund );

	$footer = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Pie del sitio' ) );
	update_post_meta( $footer, '_elementor_template_type', 'footer' );
	update_post_meta( $footer, '_elementor_edit_mode', 'builder' );
	update_post_meta( $footer, '_elementor_conditions', array( 'include/general' ) );
	$data = array(
		array( 'id' => 'f1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'f2', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Ferretería El Tornillo' ), 'elements' => array() ),
			array( 'id' => 'f3', 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array(
				array( '_id' => 'a', 'text' => 'WhatsApp 871 555 0177', 'link' => array( 'url' => 'https://wa.me/5218715550177' ) ),
				array( '_id' => 'b', 'text' => 'ventas@eltornillo.test', 'link' => array( 'url' => 'mailto:ventas@eltornillo.test' ) ),
				array( '_id' => 'c', 'text' => 'Aviso de privacidad', 'link' => array( 'url' => get_permalink( $privacy ) ) ),
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
				'scenario'  => 'simple',
				'products'  => $products,
				'product'   => (int) $purifier,
				'pages'     => array( 'inicio' => $home, 'sample' => $sample, 'nosotros' => $about ),
				'footer'    => (int) $footer,
				'admin'     => (int) $admin->ID,
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
