<?php
defined( 'ABSPATH' ) || exit;

/**
 * Editor de producto de WooCommerce (pedido del propietario del 2026-11-27: «En el editor de producto, en los tipos
 * variable y simple, el plugin hosting debe agregar las opciones si es dominio o hosting para que tengan sus propiedades
 * definidas»).
 *
 * Pestaña «DIGITALÍSIMO Hosting» en el producto (simple o variable) y el mismo bloque en cada variación:
 *  - «Este producto es…»: Ninguno, Dominio u Hosting (en una variación, además «Igual que el producto»).
 *  - Dominio: la extensión de un desplegable con buscador (Digitalisimo_Hosting_Lists::tlds(): la de los registradores,
 *    la guardada o la base), «Registro» o «Transferencia» y el aviso de que el dominio siempre es anual.
 *  - Hosting: el paquete de Webuzo (Digitalisimo_Hosting_Lists::plans()) y el periodo de cobro (mensual a trianual).
 * Lo guardado se valida contra esas mismas listas (más el valor que ya tenía el producto): nunca texto libre. Una
 * extensión con la misma acción en dos productos se avisa aquí, en la lista de productos y en los ajustes.
 */
class Digitalisimo_Hosting_Product_Editor {
	const HANDLE = 'digitalisimo-hosting-product-editor';
	const FIELD  = 'digitalisimo_hosting';
	const VFIELD = 'digitalisimo_hosting_var';

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'save_variation' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function tab( $tabs ) {
		$tabs = is_array( $tabs ) ? $tabs : array();
		$tabs['digitalisimo_hosting'] = array( 'label' => 'DIGITALÍSIMO Hosting', 'target' => 'digitalisimo_hosting_data', 'class' => array( 'hide_if_grouped', 'hide_if_external' ), 'priority' => 75 );
		return $tabs;
	}

	public static function assets( $hook = '' ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! function_exists( 'wp_enqueue_script' ) ) return;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'product' !== ( $screen->post_type ?? '' ) ) return;
		wp_enqueue_script( self::HANDLE, DIGITALISIMO_HOSTING_URL . 'assets/product-editor.js', array(), DIGITALISIMO_HOSTING_VERSION, true );
		wp_enqueue_style( self::HANDLE, DIGITALISIMO_HOSTING_URL . 'assets/product-editor.css', array(), DIGITALISIMO_HOSTING_VERSION );
	}

	/** El panel del producto (simple o variable). */
	public static function panel() {
		global $post;
		$id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
		echo '<div id="digitalisimo_hosting_data" class="panel woocommerce_options_panel hidden">';
		echo self::fields( $id, self::FIELD, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fields() escapa todo.
		echo '</div>';
	}

	/** El bloque de cada variación. */
	public static function variation_fields( $loop, $variation_data, $variation ) {
		$id = is_object( $variation ) && isset( $variation->ID ) ? (int) $variation->ID : 0;
		echo '<div class="digitalisimo-hosting-variation form-row form-row-full">';
		echo self::fields( $id, self::VFIELD . '[' . (int) $loop . ']', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fields() escapa todo.
		echo '</div>';
	}

	private static function select( $name, $id, $label, array $options, $current, $extra = '' ) {
		$h = '<p class="form-field dh-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $extra . '>';
		foreach ( $options as $value => $text ) $h .= '<option value="' . esc_attr( (string) $value ) . '"' . ( (string) $value === (string) $current ? ' selected' : '' ) . '>' . esc_html( $text ) . '</option>';
		return $h . '</select></p>';
	}

	/**
	 * El marcado del bloque (público para las pruebas): todo con su etiqueta, los avisos en role=status y las secciones
	 * de Dominio y de Hosting (el script muestra sólo la que corresponde; sin script se ven las dos).
	 */
	public static function fields( $id, $prefix, $is_variation ) {
		$P = 'Digitalisimo_Hosting_Products';
		$own = $id ? $P::own( $id ) : array( 'type' => '', 'tld' => '', 'kind' => '', 'plan' => '', 'period' => 0 );
		$type = $own['type'];
		if ( '' === $type && ! $is_variation && $id ) { $c = $P::config( $id ); $type = '' === $c['type'] ? 'none' : $c['type']; }
		if ( '' === $type ) $type = $is_variation ? 'inherit' : 'none';
		$uid = 'dh-' . ( $is_variation ? 'v' : 'p' ) . (int) $id;
		$h = '<fieldset class="dh-box" data-dh-box><legend class="dh-legend">DIGITALÍSIMO Hosting: ' . ( $is_variation ? 'esta variación es…' : 'este producto es…' ) . '</legend>';
		$types = $is_variation ? array( 'inherit' => 'Igual que el producto', 'none' => 'Ninguno', 'domain' => 'Dominio', 'hosting' => 'Hosting' ) : array( 'none' => 'Ninguno', 'domain' => 'Dominio', 'hosting' => 'Hosting' );
		$h .= self::select( $prefix . '[type]', $uid . '-type', 'Tipo', $types, $type, ' data-dh-type' );
		// Dominio.
		$tl = class_exists( 'Digitalisimo_Hosting_Lists' ) ? Digitalisimo_Hosting_Lists::tlds() : array( 'list' => array(), 'source' => 'base', 'notice' => '' );
		$cur_tld = '*' === $own['tld'] ? '*' : $P::normalize( $own['tld'] );
		$opts = $is_variation ? array( '' => 'Igual que el producto' ) : array( '' => 'Elige la extensión' );
		$opts['*'] = 'Cualquier extensión (sólo transferencia)';
		foreach ( $tl['list'] as $e ) $opts[ $e ] = $e;
		if ( '' !== $cur_tld && ! isset( $opts[ $cur_tld ] ) ) $opts[ $cur_tld ] = $cur_tld . ' (la que ya tenía)';
		$h .= '<div class="dh-section" data-dh-section="domain">';
		$h .= '<p class="form-field dh-field"><label for="' . esc_attr( $uid . '-find' ) . '">Buscar extensión</label><input type="search" id="' . esc_attr( $uid . '-find' ) . '" class="dh-find" data-dh-find="' . esc_attr( $uid . '-tld' ) . '" placeholder=".mx" autocomplete="off" spellcheck="false" aria-controls="' . esc_attr( $uid . '-tld' ) . '"></p>';
		$h .= self::select( $prefix . '[tld]', $uid . '-tld', 'Extensión', $opts, $cur_tld, ' aria-describedby="' . esc_attr( $uid . '-tld-help' ) . '"' );
		$h .= '<p class="description dh-help" id="' . esc_attr( $uid . '-tld-help' ) . '">La extensión exacta que vende este producto: el buscador y el carrito la comparan tal cual.</p>';
		if ( '' !== $tl['notice'] ) $h .= '<p class="dh-notice" role="status">' . esc_html( $tl['notice'] ) . '</p>';
		$actions = $is_variation ? array( '' => 'Igual que el producto', 'register' => 'Registro', 'transfer' => 'Transferencia' ) : array( 'register' => 'Registro', 'transfer' => 'Transferencia' );
		$h .= self::select( $prefix . '[action]', $uid . '-action', 'Venta', $actions, '' !== $own['kind'] ? $own['kind'] : ( $is_variation ? '' : 'register' ) );
		$h .= '<p class="description dh-help">El dominio se cobra siempre por un año.</p>';
		if ( $id ) foreach ( $P::conflicts_of( $id ) as $c ) $h .= '<p class="dh-error" role="alert">' . esc_html( 'Ya hay otro producto para ' . $c['tld'] . ' (' . ( 'transfer' === $c['action'] ? 'transferencia' : 'registro' ) . '): «' . implode( '», «', array_map( function ( $o ) { $p = function_exists( 'wc_get_product' ) ? wc_get_product( $o ) : null; return $p ? $p->get_name() : '#' . $o; }, $c['others'] ) ) . '». Deja uno solo; mientras tanto se usa el primero.' ) . '</p>';
		$h .= '</div>';
		// Hosting.
		$pl = class_exists( 'Digitalisimo_Hosting_Lists' ) ? Digitalisimo_Hosting_Lists::plans() : array( 'list' => array(), 'source' => 'base', 'notice' => '' );
		$popts = $is_variation ? array( '' => 'Igual que el producto' ) : array( '' => 'Elige el paquete' );
		foreach ( $pl['list'] as $slug => $label ) $popts[ $slug ] = $label;
		if ( '' !== $own['plan'] && ! isset( $popts[ $own['plan'] ] ) ) $popts[ $own['plan'] ] = $own['plan'] . ' (el que ya tenía)';
		$h .= '<div class="dh-section" data-dh-section="hosting">';
		$h .= self::select( $prefix . '[plan]', $uid . '-plan', 'Paquete de Webuzo', $popts, $own['plan'] );
		if ( '' !== $pl['notice'] ) $h .= '<p class="dh-notice" role="status">' . esc_html( $pl['notice'] ) . '</p>';
		$periods = $is_variation ? array( '' => 'Igual que el producto' ) + $P::PERIODS : $P::PERIODS;
		$h .= self::select( $prefix . '[period]', $uid . '-period', 'Periodo de cobro', $periods, $own['period'] ? $own['period'] : ( $is_variation ? '' : 12 ) );
		$h .= '<p class="description dh-help">Cada cuánto se cobra y se renueva este plan. En un producto variable, pon una variación por periodo.</p>';
		$h .= '</div></fieldset>';
		return $h;
	}

	/** `woocommerce_admin_process_product_object`: lo del panel del producto. */
	public static function save_product( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) return;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce ya verificó su nonce antes de este gancho.
		$in = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : null;
		if ( null === $in ) return;
		self::report( self::apply( (int) $product->get_id(), $in, false ) );
	}

	/** `woocommerce_admin_process_variation_object`: lo de cada variación ($i es su índice en el formulario). */
	public static function save_variation( $variation, $i ) {
		if ( ! is_object( $variation ) || ! method_exists( $variation, 'get_id' ) ) return;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce ya verificó el nonce de save_variations.
		$all = isset( $_POST[ self::VFIELD ] ) && is_array( $_POST[ self::VFIELD ] ) ? wp_unslash( $_POST[ self::VFIELD ] ) : array();
		if ( ! isset( $all[ $i ] ) || ! is_array( $all[ $i ] ) ) return;
		self::report( self::apply( (int) $variation->get_id(), $all[ $i ], true ) );
	}

	/** Los errores del guardado, en el aviso de WooCommerce del editor. */
	private static function report( array $errors ) {
		foreach ( $errors as $e ) if ( class_exists( 'WC_Admin_Meta_Boxes' ) && method_exists( 'WC_Admin_Meta_Boxes', 'add_error' ) ) WC_Admin_Meta_Boxes::add_error( 'DIGITALÍSIMO Hosting: ' . $e );
	}

	/**
	 * Valida y guarda lo del bloque en un producto o una variación. Devuelve los errores (lo que no se aceptó no cambia).
	 * Público para las pruebas y para quien guarde por código.
	 */
	public static function apply( $id, array $in, $is_variation ) {
		$P = 'Digitalisimo_Hosting_Products';
		$errors = array();
		$id = (int) $id;
		if ( ! $id ) return array( 'Producto desconocido.' );
		$type = (string) ( $in['type'] ?? '' );
		$allowed = $is_variation ? array( 'inherit', 'none', 'domain', 'hosting' ) : array( 'none', 'domain', 'hosting' );
		if ( ! in_array( $type, $allowed, true ) ) return array( 'El tipo «' . sanitize_text_field( $type ) . '» no existe: elige Ninguno, Dominio u Hosting.' );
		$own = $P::own( $id );
		if ( 'inherit' === $type ) delete_post_meta( $id, $P::META_TYPE ); else update_post_meta( $id, $P::META_TYPE, $type );
		// «Igual que el producto»: sólo cuenta la sección del tipo del producto; las marcas sueltas de la otra se limpian
		// (una extensión que quedó elegida no convierte en dominio la variación de un plan).
		$section = $type;
		if ( 'inherit' === $type ) {
			$parent = $P::parent_of( $id );
			$section = $parent ? $P::config( $parent )['type'] : '';
			if ( 'domain' !== $section ) { delete_post_meta( $id, $P::META_TLD ); delete_post_meta( $id, $P::META_KIND ); }
			if ( 'hosting' !== $section ) { delete_post_meta( $id, $P::META_PLAN ); delete_post_meta( $id, $P::META_PERIOD ); }
		}
		// Guardado en el editor: el periodo ya lo eligió el dueño (se quita el aviso de la migración).
		if ( 'hosting' === $type || 'hosting' === $section ) delete_post_meta( $id, $P::META_PERIOD_MIGRATED );
		if ( 'domain' === $section ) {
			$action = (string) ( $in['action'] ?? '' );
			if ( in_array( $action, array( 'register', 'transfer' ), true ) ) update_post_meta( $id, $P::META_KIND, $action );
			elseif ( '' === $action && $is_variation ) delete_post_meta( $id, $P::META_KIND );
			elseif ( '' !== $action ) $errors[] = 'Elige «Registro» o «Transferencia».';
			$raw = trim( (string) ( $in['tld'] ?? '' ) );
			$cur = '*' === $own['tld'] ? '*' : $P::normalize( $own['tld'] );
			if ( '' === $raw ) { if ( $is_variation ) delete_post_meta( $id, $P::META_TLD ); elseif ( 'domain' === $type ) $errors[] = 'Elige la extensión del dominio.'; }
			elseif ( '*' === $raw ) {
				if ( 'transfer' === $P::config( $id )['action'] ) update_post_meta( $id, $P::META_TLD, '*' );
				else $errors[] = '«Cualquier extensión» sólo vale para un producto de transferencia.';
			} else {
				$ext = $P::normalize( $raw );
				if ( '' !== $ext && ( $ext === $cur || ( class_exists( 'Digitalisimo_Hosting_Lists' ) && Digitalisimo_Hosting_Lists::valid_tld( $ext ) ) ) ) update_post_meta( $id, $P::META_TLD, $ext );
				else $errors[] = 'La extensión «' . sanitize_text_field( $raw ) . '» no está en la lista de los registradores: elígela del desplegable.';
			}
		}
		if ( 'hosting' === $section ) {
			$plan = trim( (string) ( $in['plan'] ?? '' ) );
			$plans = class_exists( 'Digitalisimo_Hosting_Lists' ) ? Digitalisimo_Hosting_Lists::plans()['list'] : array();
			if ( '' === $plan ) { if ( $is_variation ) delete_post_meta( $id, $P::META_PLAN ); elseif ( 'hosting' === $type && '' === $own['plan'] ) $errors[] = 'Elige el paquete de Webuzo: sin él no se puede crear la cuenta al pagarse.'; }
			elseif ( $plan === $own['plan'] || isset( $plans[ $plan ] ) ) update_post_meta( $id, $P::META_PLAN, $plan );
			else $errors[] = 'El paquete «' . sanitize_text_field( $plan ) . '» no está en Webuzo: elígelo del desplegable.';
			$period = (string) ( $in['period'] ?? '' );
			if ( '' === $period && $is_variation ) delete_post_meta( $id, $P::META_PERIOD );
			elseif ( $P::period_months( $period ) ) update_post_meta( $id, $P::META_PERIOD, $P::period_months( $period ) );
			elseif ( '' !== $period ) $errors[] = 'Elige un periodo de cobro: mensual, trimestral, semestral, anual, bianual o trianual.';
		}
		$P::flush();
		// La misma extensión y acción en otro producto: se guarda, pero se avisa (el buscador usa el primero).
		foreach ( $P::conflicts_of( $id ) as $c ) $errors[] = 'Ya hay otro producto para ' . $c['tld'] . ' (' . ( 'transfer' === $c['action'] ? 'transferencia' : 'registro' ) . '). Deja uno solo; mientras tanto se usa el primero.';
		return $errors;
	}
}
