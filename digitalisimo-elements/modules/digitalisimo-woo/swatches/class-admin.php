<?php
namespace Digitalisimo\Elements\Woo\Swatches;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-data.php';

/**
 * Pantallas de WooCommerce para las muestras (V.6.2): Productos → Atributos (añadir y editar) y los términos de cada
 * atributo global. No son pantallas propias de `digitalisimo-ui`: usan los estilos de formulario del núcleo y una hoja
 * pequeña con respaldo en cada valor (`var(--digi-*, …)`), porque ahí no está la clase `.digitalisimo-admin`.
 *
 * - **Guardado:** sólo con el nonce propio presente y válido (`digitalisimo_swatch_attribute`,
 *   `digitalisimo_swatch_term`) y `manage_product_terms`. Sin él no se toca nada: REST, CSV, WP-CLI y K no reescriben
 *   ninguna muestra.
 * - **Renombrar** el *slug* mueve la entrada al nuevo (también sin el formulario); **borrar** el atributo borra la suya.
 * - **Atributo `wc-visual`:** el campo «Tipo de muestra» propone «Muestra visual», pero no la elige solo; en sus
 *   términos no se pinta ningún campo propio: los de WooCommerce escriben las mismas metas.
 * - **Imagen del término:** excepción declarada a la regla del campo de archivo de `AGENTS.md`: se guarda como ID de
 *   adjunto en la meta `image` (el formato nativo de WooCommerce, que K traduce). La interfaz es el modal de Medios: el
 *   archivo se elige o se arrastra y la dirección no se escribe.
 */
final class Admin {
	const ATTRIBUTE_NONCE = 'digitalisimo_swatch_attribute';
	const TERM_NONCE      = 'digitalisimo_swatch_term';
	const CAPABILITY      = 'manage_product_terms';
	const SCRIPT          = 'digitalisimo-woo-swatches-admin';
	const COLUMN          = 'digitalisimo_swatch';

	public static function init(): void {
		add_action( 'woocommerce_attribute_added', array( __CLASS__, 'attribute_added' ), 10, 2 );
		add_action( 'woocommerce_attribute_updated', array( __CLASS__, 'attribute_updated' ), 10, 3 );
		add_action( 'woocommerce_attribute_deleted', array( __CLASS__, 'attribute_deleted' ), 10, 3 );
		add_action( 'created_term', array( __CLASS__, 'save_term' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'save_term' ), 10, 3 );
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		add_action( 'woocommerce_after_add_attribute_fields', array( __CLASS__, 'add_attribute_fields' ) );
		add_action( 'woocommerce_after_edit_attribute_fields', array( __CLASS__, 'edit_attribute_fields' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * La opción existe siempre (con carga automática), así el front sabe sin consultar que no hay muestras; y los
	 * ganchos de la pantalla de términos y de su lista, sólo para el atributo que se está editando.
	 */
	public static function admin_init(): void {
		if ( function_exists( 'is_network_admin' ) && is_network_admin() ) {
			return; // Los atributos son de cada sitio: la red no tiene esta pantalla.
		}
		if ( function_exists( 'wp_load_alloptions' ) ) {
			$all = wp_load_alloptions();
			if ( is_array( $all ) && ! array_key_exists( Data::OPTION, $all ) ) {
				add_option( Data::OPTION, array( 'v' => 1, 'attributes' => array() ), '', true );
			}
		}
		$taxonomy = self::request_taxonomy();
		if ( '' === $taxonomy ) {
			return;
		}
		add_filter( "manage_edit-{$taxonomy}_columns", array( __CLASS__, 'columns' ), 20 );
		add_filter( "manage_{$taxonomy}_custom_column", array( __CLASS__, 'column' ), 10, 3 );
		if ( self::own_fields( $taxonomy ) ) {
			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'add_term_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'edit_term_fields' ) );
		}
	}

	// ---------------------------------------------------------------- Atributos.

	/** Campos al añadir un atributo (`div.form-field`, como los de WooCommerce). */
	public static function add_attribute_fields(): void {
		self::attribute_fields( Data::defaults(), false, false );
	}

	/** Campos al editar un atributo (filas de `form-table`). */
	public static function edit_attribute_fields(): void {
		$id        = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo lectura de la pantalla.
		$attribute = $id && function_exists( 'wc_get_attribute' ) ? wc_get_attribute( $id ) : null;
		$slug      = is_object( $attribute ) && isset( $attribute->slug ) ? (string) $attribute->slug : '';
		$entry     = '' !== $slug ? Data::attribute( $slug ) : Data::defaults();
		$visual    = is_object( $attribute ) && isset( $attribute->type ) && 'wc-visual' === $attribute->type;
		self::attribute_fields( $entry, true, $visual );
	}

	/**
	 * @param array $entry  `{ type, shape, size }`.
	 * @param bool  $table  Filas de tabla (editar) o bloques (añadir).
	 * @param bool  $visual El atributo es `wc-visual`.
	 */
	private static function attribute_fields( array $entry, bool $table, bool $visual ): void {
		$types  = array( 'select' => 'Desplegable', 'visual' => 'Muestra visual (color o imagen)', 'label' => 'Etiqueta (el nombre del término)' );
		$shapes = array( 'circle' => 'Círculo', 'rounded' => 'Cuadrado redondeado', 'pill' => 'Píldora' );
		$help   = 'Cómo se elige este atributo en el formulario de compra clásico. «Desplegable» lo deja como hoy.';
		if ( $visual ) {
			$help .= ' Este atributo ya guarda colores e imágenes en WooCommerce: elige «Muestra visual» para mostrarlos también en el formulario clásico.';
		}
		$fields = array(
			array( 'digitalisimo_swatch_type', 'Tipo de muestra', self::select( 'digitalisimo_swatch_type', $types, $entry['type'] ), $help ),
			array( 'digitalisimo_swatch_shape', 'Forma de la muestra', self::select( 'digitalisimo_swatch_shape', $shapes, $entry['shape'] ), '' ),
			array( 'digitalisimo_swatch_size', 'Tamaño de la muestra (px)', '<input type="number" name="digitalisimo_swatch_size" id="digitalisimo_swatch_size" min="' . Data::SIZE_MIN . '" max="' . Data::SIZE_MAX . '" step="1" value="' . (int) $entry['size'] . '">', 'Entre ' . Data::SIZE_MIN . ' y ' . Data::SIZE_MAX . ' píxeles.' ),
		);
		foreach ( $fields as $index => $field ) {
			$description = '' !== $field[3] ? '<p class="description">' . esc_html( $field[3] ) . '</p>' : '';
			$nonce       = 2 === $index ? wp_nonce_field( self::ATTRIBUTE_NONCE, self::ATTRIBUTE_NONCE . '_nonce', false, false ) : '';
			if ( $table ) {
				echo '<tr class="form-field"><th scope="row" valign="top"><label for="' . esc_attr( $field[0] ) . '">' . esc_html( $field[1] ) . '</label></th><td>' . $field[2] . $description . $nonce . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campos escapados al construirlos.
			} else {
				echo '<div class="form-field"><label for="' . esc_attr( $field[0] ) . '">' . esc_html( $field[1] ) . '</label>' . $field[2] . $description . $nonce . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campos escapados al construirlos.
			}
		}
	}

	private static function select( string $name, array $options, string $current ): string {
		$html = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . ( $value === $current ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * `woocommerce_attribute_added`: guarda el tipo sólo desde el formulario propio.
	 *
	 * @param int   $id   Atributo.
	 * @param array $data Datos de la tabla de atributos.
	 */
	public static function attribute_added( $id, $data = array() ): void {
		$slug = is_array( $data ) && isset( $data['attribute_name'] ) ? (string) $data['attribute_name'] : '';
		$entry = self::posted_attribute();
		if ( '' !== $slug && null !== $entry ) {
			Data::put( $slug, $entry );
		}
	}

	/**
	 * `woocommerce_attribute_updated`: al renombrar, la entrada pasa al *slug* nuevo (siempre); después, el tipo del
	 * formulario propio, si viene.
	 *
	 * @param int    $id       Atributo.
	 * @param array  $data     Datos de la tabla de atributos.
	 * @param string $old_slug Slug anterior.
	 */
	public static function attribute_updated( $id, $data = array(), $old_slug = '' ): void {
		$slug = is_array( $data ) && isset( $data['attribute_name'] ) ? (string) $data['attribute_name'] : '';
		if ( '' === $slug ) {
			return;
		}
		if ( is_string( $old_slug ) && '' !== $old_slug && $old_slug !== $slug ) {
			Data::move( $old_slug, $slug );
		}
		$entry = self::posted_attribute();
		if ( null !== $entry ) {
			Data::put( $slug, $entry );
		}
	}

	/**
	 * `woocommerce_attribute_deleted`: el atributo ya no existe, su entrada tampoco.
	 *
	 * @param int    $id       Atributo.
	 * @param string $name     Slug.
	 * @param string $taxonomy Taxonomía.
	 */
	public static function attribute_deleted( $id, $name = '', $taxonomy = '' ): void {
		if ( is_string( $name ) && '' !== $name ) {
			Data::remove( $name );
		}
	}

	/** Valores del formulario propio de atributos, o `null` sin nonce válido o sin permiso. */
	private static function posted_attribute(): ?array {
		if ( ! self::verified( self::ATTRIBUTE_NONCE ) ) {
			return null;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado en verified().
		$entry = array(
			'type'  => isset( $_POST['digitalisimo_swatch_type'] ) ? sanitize_key( wp_unslash( $_POST['digitalisimo_swatch_type'] ) ) : '',
			'shape' => isset( $_POST['digitalisimo_swatch_shape'] ) ? sanitize_key( wp_unslash( $_POST['digitalisimo_swatch_shape'] ) ) : '',
			'size'  => isset( $_POST['digitalisimo_swatch_size'] ) ? absint( wp_unslash( $_POST['digitalisimo_swatch_size'] ) ) : Data::SIZE_DEFAULT,
		);
		// phpcs:enable
		return Data::sanitize_entry( $entry );
	}

	/** El nonce propio `$action` viene en el `POST`, es válido y el usuario puede gestionar los términos de producto. */
	private static function verified( string $action ): bool {
		$field = $action . '_nonce';
		if ( empty( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- se verifica aquí.
			return false;
		}
		return function_exists( 'wp_verify_nonce' ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) && current_user_can( self::CAPABILITY ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	// ---------------------------------------------------------------- Términos.

	/** Taxonomía `pa_*` de la petición de administración (pantalla de términos o su AJAX), o ''. */
	private static function request_taxonomy(): string {
		$taxonomy = isset( $_REQUEST['taxonomy'] ) && is_string( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide qué ganchos registrar.
		return '' !== Data::slug( $taxonomy ) && taxonomy_exists( $taxonomy ) ? $taxonomy : '';
	}

	/** El atributo es `wc-visual` de WooCommerce. */
	public static function is_wc_visual( string $taxonomy ): bool {
		$slug = Data::slug( $taxonomy );
		if ( '' === $slug || ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return false;
		}
		foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
			if ( is_object( $attribute ) && isset( $attribute->attribute_name ) && $slug === $attribute->attribute_name ) {
				return isset( $attribute->attribute_type ) && 'wc-visual' === $attribute->attribute_type;
			}
		}
		return false;
	}

	/** Los campos propios de color e imagen: atributo con «Muestra visual» que no es `wc-visual`. */
	public static function own_fields( string $taxonomy ): bool {
		return 'visual' === Data::attribute( $taxonomy )['type'] && ! self::is_wc_visual( $taxonomy );
	}

	/** Campos al añadir un término (`div.form-field`). */
	public static function add_term_fields( $taxonomy = '' ): void {
		self::term_fields( '', 0, false );
	}

	/** Campos al editar un término (filas de `form-table`). */
	public static function edit_term_fields( $term ): void {
		$data = $term instanceof \WP_Term ? Data::term( $term ) : array( 'image' => 0, 'color' => '' );
		self::term_fields( $data['color'], (int) $data['image'], true );
	}

	private static function term_fields( string $color, int $image, bool $table ): void {
		$preview = $image ? Form::image( $image ) : '';
		$color_html = '<span class="digi-swatch-admin__color"><input type="color" data-digi-swatch-picker value="' . esc_attr( '' !== $color ? self::long_hex( $color ) : '#000000' ) . '" aria-label="Elegir el color en la paleta"> '
			. '<input type="text" name="digitalisimo_swatch_color" id="digitalisimo-swatch-color" value="' . esc_attr( $color ) . '" placeholder="#rrggbb" maxlength="7" pattern="#([0-9A-Fa-f]{3}){1,2}" spellcheck="false" autocomplete="off"></span>';
		$image_html = '<div class="digi-swatch-admin__media" data-digi-swatch-media>'
			. '<input type="hidden" name="digitalisimo_swatch_image" value="' . ( $image ? (int) $image : '' ) . '">'
			. '<span class="digi-swatch-admin__preview" data-digi-swatch-preview>' . $preview . '</span> '
			. '<button type="button" class="button" data-digi-swatch-pick aria-describedby="digitalisimo-swatch-image-help">Elegir imagen</button> '
			. '<button type="button" class="button-link button-link-delete" data-digi-swatch-remove' . ( $image ? '' : ' hidden' ) . '>Quitar imagen</button></div>';
		$fields = array(
			array( 'digitalisimo-swatch-color', 'Color de la muestra', $color_html, 'Hexadecimal, por ejemplo #cc0000. Si el término tiene imagen, la imagen gana.', 'digitalisimo-swatch-color-help' ),
			array( '', 'Imagen de la muestra', $image_html, 'Elígela de la biblioteca de medios o arrástrala al abrirla; la dirección no se escribe.', 'digitalisimo-swatch-image-help' ),
		);
		$nonce = wp_nonce_field( self::TERM_NONCE, self::TERM_NONCE . '_nonce', false, false );
		foreach ( $fields as $index => $field ) {
			$label = '' !== $field[0] ? '<label for="' . esc_attr( $field[0] ) . '">' . esc_html( $field[1] ) . '</label>' : '<span class="digi-swatch-admin__label">' . esc_html( $field[1] ) . '</span>';
			$help  = '<p class="description" id="' . esc_attr( $field[4] ) . '">' . esc_html( $field[3] ) . '</p>' . ( 1 === $index ? $nonce : '' );
			if ( $table ) {
				echo '<tr class="form-field digi-swatch-admin"><th scope="row">' . $label . '</th><td>' . $field[2] . $help . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado al construirlo.
			} else {
				echo '<div class="form-field digi-swatch-admin">' . $label . $field[2] . $help . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado al construirlo.
			}
		}
	}

	/** `#abc` → `#aabbcc` para `<input type="color">`, que sólo admite seis cifras. */
	private static function long_hex( string $hex ): string {
		return 4 === strlen( $hex ) ? '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3] : strtolower( $hex );
	}

	/**
	 * `created_term` y `edited_term`: color e imagen con el formato de WooCommerce, sólo desde el formulario propio de
	 * un atributo que no es `wc-visual`. Un valor inválido no se guarda y la pantalla lo dice.
	 *
	 * @param int    $term_id  Término.
	 * @param int    $tt_id    Término de taxonomía.
	 * @param string $taxonomy Taxonomía.
	 */
	public static function save_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( ! is_string( $taxonomy ) || '' === Data::slug( $taxonomy ) || ! self::verified( self::TERM_NONCE ) || self::is_wc_visual( $taxonomy ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verificado en verified().
		$raw_color = isset( $_POST['digitalisimo_swatch_color'] ) && is_string( $_POST['digitalisimo_swatch_color'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['digitalisimo_swatch_color'] ) ) ) : '';
		$raw_image = isset( $_POST['digitalisimo_swatch_image'] ) && is_scalar( $_POST['digitalisimo_swatch_image'] ) ? absint( wp_unslash( $_POST['digitalisimo_swatch_image'] ) ) : 0;
		// phpcs:enable
		$color = Data::color( $raw_color );
		if ( '' !== $raw_color && '' === $color ) {
			self::flash( 'El color de la muestra no se guardó: escribe un hexadecimal como #cc0000.' );
			return;
		}
		if ( $raw_image && ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $raw_image ) ) ) {
			self::flash( 'La imagen de la muestra no se guardó: elige una imagen de la biblioteca de medios.' );
			return;
		}
		Data::save_term( (int) $term_id, $color, $raw_image );
	}

	/** Aviso para la siguiente carga de la pantalla del usuario. */
	private static function flash( string $message ): void {
		if ( function_exists( 'set_transient' ) && function_exists( 'get_current_user_id' ) ) {
			set_transient( 'digitalisimo_swatch_notice_' . get_current_user_id(), $message, 120 );
		}
	}

	/** Muestra (y borra) el aviso pendiente en las pantallas de términos. */
	public static function notice(): void {
		if ( ! function_exists( 'get_transient' ) || '' === self::request_taxonomy() ) {
			return;
		}
		$key     = 'digitalisimo_swatch_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( is_string( $message ) && '' !== $message ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Columna «Muestra» en la lista de términos, también en un atributo `wc-visual`, salvo que WooCommerce ya añada la
	 * suya (`visual`).
	 *
	 * @param array $columns Columnas.
	 */
	public static function columns( $columns ) {
		$taxonomy = self::request_taxonomy();
		if ( ! is_array( $columns ) || isset( $columns['visual'] ) || '' === $taxonomy || 'visual' !== Data::attribute( $taxonomy )['type'] ) {
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'slug' === $key ) {
				$out[ self::COLUMN ] = 'Muestra';
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = 'Muestra';
		}
		return $out;
	}

	/**
	 * Contenido de la columna: la imagen o el color del término, con su nombre para lectores de pantalla.
	 *
	 * @param string $content Contenido.
	 * @param string $column  Columna.
	 * @param int    $term_id Término.
	 */
	public static function column( $content, $column = '', $term_id = 0 ) {
		if ( self::COLUMN !== $column ) {
			return $content;
		}
		$term = get_term( (int) $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return $content;
		}
		$data = Data::term( $term );
		if ( $data['image'] ) {
			return '<span class="digi-swatch-admin__chip">' . Form::image( $data['image'] ) . '</span><span class="screen-reader-text">Imagen</span>';
		}
		if ( '' !== $data['color'] ) {
			return '<span class="digi-swatch-admin__chip" style="background-color:' . esc_attr( $data['color'] ) . '"></span><span class="screen-reader-text">' . esc_html( $data['color'] ) . '</span>';
		}
		return '<span aria-hidden="true">—</span><span class="screen-reader-text">Sin muestra</span>';
	}

	/** Modal de Medios, script y hoja sólo en la pantalla de términos de un atributo con campos propios. */
	public static function enqueue( $hook = '' ): void {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		$taxonomy = self::request_taxonomy();
		if ( '' === $taxonomy || ! self::own_fields( $taxonomy ) ) {
			return;
		}
		$file    = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? DIGITALISIMO_ELEMENTS_FILE : dirname( __DIR__, 3 ) . '/pro-elements.php';
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_style( self::SCRIPT, plugins_url( 'assets/css/swatches-admin.css', $file ), array(), $version );
		wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/js/swatches-admin.js', $file ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
}
