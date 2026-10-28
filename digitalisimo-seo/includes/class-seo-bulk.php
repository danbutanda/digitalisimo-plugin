<?php
defined( 'ABSPATH' ) || exit;

/**
 * Edición rápida, edición masiva, filtros y CSV de los metadatos SEO (RM14 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Todo escribe las mismas metas que el editor (`digitalisimo_seo_*`) con el mismo saneado. La
 * importación CSV corre en cola por lotes, comprueba `edit_post` fila a fila con el usuario que la
 * pidió y una celda vacía conserva el valor guardado: nunca borra.
 */
class Digitalisimo_Integrations_SEO_Bulk {
	const P       = 'digitalisimo_seo_';
	const JOB     = 'digitalisimo_seo_csv_job';
	const HOOK    = 'digitalisimo_seo_csv_batch';
	const BATCH   = 200;
	const MAX     = 10485760;
	const COLUMNS = array( 'id', 'type', 'status', 'url', 'seo_title', 'seo_description', 'keywords', 'canonical', 'noindex', 'nofollow', 'social_title', 'social_description', 'breadcrumb_title' );

	public static function init() {
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_box' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( __CLASS__, 'bulk_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter_select' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		add_action( 'admin_post_digitalisimo_seo_csv_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_digitalisimo_seo_csv_import', array( __CLASS__, 'upload' ) );
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
	}

	private static function types() { return Digitalisimo_Integrations_Editorial::content_types(); }

	/* ------------------------------------------------------------------ */
	/* Saneado común (puro).                                                */
	/* ------------------------------------------------------------------ */

	/** Sí/no de una celda o un formulario; null si no dice nada (se conserva). */
	public static function flag( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( '1', 'si', 'sí', 'yes', 'true', 'x' ), true ) ) return '1';
		if ( in_array( $value, array( '0', 'no', 'false' ), true ) ) return '';
		return null;
	}

	/** Celda de CSV segura en una hoja de cálculo: una fórmula se exporta como texto. */
	public static function csv_cell( $value ) { $value = (string) $value; return '' !== $value && false !== strpos( '=+-@', $value[0] ) ? "'" . $value : $value; }
	public static function csv_value( $value ) { $value = (string) $value; return 1 < strlen( $value ) && "'" === $value[0] && false !== strpos( '=+-@', $value[1] ) ? substr( $value, 1 ) : $value; }

	/** Metas a escribir a partir de una fila o un formulario (clave => valor). Las celdas vacías no se tocan. */
	public static function changes( array $row, $max_keywords = 5 ) {
		$out = array();
		foreach ( array( 'seo_title' => 'title', 'social_title' => 'social_title' ) as $from => $to ) if ( isset( $row[ $from ] ) && '' !== trim( (string) $row[ $from ] ) ) $out[ $to ] = Digitalisimo_Integrations_Settings::sanitize_template( self::csv_value( $row[ $from ] ) );
		if ( isset( $row['breadcrumb_title'] ) && '' !== trim( (string) $row['breadcrumb_title'] ) ) $out['breadcrumb_title'] = sanitize_text_field( self::csv_value( $row['breadcrumb_title'] ) );
		foreach ( array( 'seo_description' => 'description', 'social_description' => 'social_description' ) as $from => $to ) if ( isset( $row[ $from ] ) && '' !== trim( (string) $row[ $from ] ) ) $out[ $to ] = Digitalisimo_Integrations_Settings::sanitize_template( self::csv_value( $row[ $from ] ), true );
		if ( isset( $row['keywords'] ) && '' !== trim( (string) $row['keywords'] ) ) {
			$keywords = array_slice( Digitalisimo_Integrations_Keyword_Match::unique( preg_split( '/[,\r\n]+/', self::csv_value( $row['keywords'] ) ) ), 0, max( 1, min( 5, (int) $max_keywords ) ) );
			if ( $keywords ) $out['keywords'] = implode( ', ', $keywords );
		}
		if ( isset( $row['canonical'] ) && '' !== trim( (string) $row['canonical'] ) ) { $url = esc_url_raw( trim( (string) $row['canonical'] ), array( 'http', 'https' ) ); if ( $url ) $out['canonical'] = $url; }
		foreach ( array( 'noindex', 'nofollow' ) as $key ) if ( isset( $row[ $key ] ) && null !== ( $flag = self::flag( $row[ $key ] ) ) ) $out[ $key ] = $flag;
		return $out;
	}

	private static function apply( $post_id, array $changes ) { foreach ( $changes as $key => $value ) update_post_meta( $post_id, self::P . $key, $value ); }

	/* ------------------------------------------------------------------ */
	/* Edición rápida y masiva.                                             */
	/* ------------------------------------------------------------------ */

	public static function assets( $hook ) {
		if ( 'edit.php' !== $hook || ! in_array( get_current_screen()->post_type ?? '', self::types(), true ) ) return;
		wp_enqueue_script( 'digitalisimo-seo-quick-edit', DIGITALISIMO_INTEGRATIONS_URL . 'assets/js/seo-quick-edit.js', array( 'inline-edit-post' ), DIGITALISIMO_INTEGRATIONS_VERSION, true );
	}

	/** Datos de la fila para que la edición rápida rellene sus campos (los lee seo-quick-edit.js). */
	public static function row_data( $post_id ) {
		$get = function( $key ) use ( $post_id ) { return (string) get_post_meta( $post_id, self::P . $key, true ); };
		return '<div hidden class="digitalisimo-seo-inline" data-title="' . esc_attr( $get( 'title' ) ) . '" data-description="' . esc_attr( $get( 'description' ) ) . '" data-keywords="' . esc_attr( $get( 'keywords' ) ) . '" data-noindex="' . esc_attr( $get( 'noindex' ) ) . '" data-nofollow="' . esc_attr( $get( 'nofollow' ) ) . '"></div>';
	}

	public static function quick_box( $column, $type ) {
		if ( 'digitalisimo_score' !== $column || ! in_array( $type, self::types(), true ) ) return;
		echo '<fieldset class="inline-edit-col-right digitalisimo-seo-quick"><div class="inline-edit-col"><span class="title">SEO</span>';
		wp_nonce_field( 'digitalisimo_seo_quick', 'digitalisimo_seo_quick_nonce' );
		echo '<label><span class="title">Título SEO</span><span class="input-text-wrap"><input type="text" name="digitalisimo_seo_quick[seo_title]" value=""></span></label>';
		echo '<label><span class="title">Descripción</span><span class="input-text-wrap"><textarea rows="2" name="digitalisimo_seo_quick[seo_description]"></textarea></span></label>';
		echo '<label><span class="title">Keywords</span><span class="input-text-wrap"><input type="text" name="digitalisimo_seo_quick[keywords]" value="" placeholder="principal, secundaria"></span></label>';
		echo '<input type="hidden" name="digitalisimo_seo_quick[noindex]" value="0"><label class="alignleft"><input type="checkbox" name="digitalisimo_seo_quick[noindex]" value="1"> noindex</label>';
		echo '<input type="hidden" name="digitalisimo_seo_quick[nofollow]" value="0"><label class="alignleft"><input type="checkbox" name="digitalisimo_seo_quick[nofollow]" value="1"> nofollow</label>';
		echo '</div></fieldset>';
	}

	public static function bulk_box( $column, $type ) {
		if ( 'digitalisimo_score' !== $column || ! in_array( $type, self::types(), true ) ) return;
		echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col"><span class="title">SEO</span>';
		wp_nonce_field( 'digitalisimo_seo_bulk', 'digitalisimo_seo_bulk_nonce' );
		foreach ( array( 'noindex' => 'noindex', 'nofollow' => 'nofollow' ) as $key => $label ) echo '<label class="inline-edit-status alignleft"><span class="title">' . esc_html( $label ) . '</span><select name="digitalisimo_seo_bulk[' . esc_attr( $key ) . ']"><option value="">— Sin cambios —</option><option value="1">Activar</option><option value="0">Quitar</option></select></label>';
		echo '</div></fieldset>';
	}

	/** Edición rápida (AJAX inline-save) y masiva (bulk_edit_posts) llegan por save_post, cada una con su nonce. */
	public static function save( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! in_array( get_post_type( $post_id ), self::types(), true ) ) return;
		if ( isset( $_POST['digitalisimo_seo_quick_nonce'], $_POST['digitalisimo_seo_quick'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['digitalisimo_seo_quick_nonce'] ) ), 'digitalisimo_seo_quick' ) ) {
			$row = (array) wp_unslash( $_POST['digitalisimo_seo_quick'] );
			// En la edición rápida vaciar un campo sí lo vacía: es lo que el usuario ve en pantalla.
			foreach ( array( 'seo_title' => 'title', 'seo_description' => 'description', 'keywords' => 'keywords' ) as $from => $to ) if ( isset( $row[ $from ] ) && '' === trim( (string) $row[ $from ] ) ) delete_post_meta( $post_id, self::P . $to );
			self::apply( $post_id, self::changes( $row, (int) Digitalisimo_Integrations_SEO_Resolver::option( 'keyword_max_count', 5 ) ) );
			return;
		}
		if ( isset( $_REQUEST['digitalisimo_seo_bulk_nonce'], $_REQUEST['digitalisimo_seo_bulk'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['digitalisimo_seo_bulk_nonce'] ) ), 'digitalisimo_seo_bulk' ) ) {
			$row = array_intersect_key( (array) wp_unslash( $_REQUEST['digitalisimo_seo_bulk'] ), array_flip( array( 'noindex', 'nofollow' ) ) );
			self::apply( $post_id, self::changes( $row ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Filtro del listado.                                                  */
	/* ------------------------------------------------------------------ */

	public static function filter_choices() { return array( '' => 'SEO: todos', 'no_description' => 'Sin meta descripción propia', 'no_keyword' => 'Sin keyword', 'noindex' => 'Con noindex', 'low_score' => 'Análisis por debajo de 50' ); }

	public static function filter_select() {
		global $typenow;
		if ( ! in_array( $typenow, self::types(), true ) ) return;
		$current = sanitize_key( wp_unslash( $_GET['digitalisimo_seo_filter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="digitalisimo_seo_filter">';
		foreach ( self::filter_choices() as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select>';
	}

	/** Meta query de cada filtro (pura). */
	public static function filter_meta( $filter ) {
		$empty = function( $key ) { return array( 'relation' => 'OR', array( 'key' => $key, 'compare' => 'NOT EXISTS' ), array( 'key' => $key, 'value' => '' ) ); };
		switch ( $filter ) {
			case 'no_description': return $empty( self::P . 'description' );
			case 'no_keyword': return $empty( self::P . 'keywords' );
			case 'noindex': return array( array( 'key' => self::P . 'noindex', 'value' => '1' ) );
			case 'low_score': return array( array( 'key' => Digitalisimo_Integrations_Content_Analysis::SCORE, 'value' => 50, 'compare' => '<', 'type' => 'NUMERIC' ) );
		}
		return array();
	}

	public static function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) return;
		$meta = self::filter_meta( sanitize_key( wp_unslash( $_GET['digitalisimo_seo_filter'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $meta ) return;
		$existing = (array) $query->get( 'meta_query' );
		$query->set( 'meta_query', $existing ? array( 'relation' => 'AND', $existing, $meta ) : $meta );
	}

	/* ------------------------------------------------------------------ */
	/* CSV.                                                                 */
	/* ------------------------------------------------------------------ */

	public static function export() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_csv_export' ) ) wp_die( 'No autorizado.' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="seo-' . sanitize_file_name( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		self::write_csv( $out );
		fclose( $out );
		exit;
	}

	/** Todas las filas del sitio en un flujo abierto. */
	public static function write_csv( $out ) {
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM: las hojas de cálculo leen bien las tildes.
		fputcsv( $out, self::COLUMNS );
		for ( $page = 1; ; $page++ ) {
			$ids = get_posts( array( 'post_type' => self::types(), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 500, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
			if ( ! $ids ) break;
			update_meta_cache( 'post', $ids );
			foreach ( $ids as $id ) {
				$m = function( $key ) use ( $id ) { return (string) get_post_meta( $id, self::P . $key, true ); };
				fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), array( $id, get_post_type( $id ), get_post_status( $id ), get_permalink( $id ), $m( 'title' ), $m( 'description' ), $m( 'keywords' ), $m( 'canonical' ), $m( 'noindex' ), $m( 'nofollow' ), $m( 'social_title' ), $m( 'social_description' ), $m( 'breadcrumb_title' ) ) ) );
			}
			if ( count( $ids ) < 500 ) break;
		}
	}

	/** Carpeta privada de trabajo: nombre aleatorio, sin listado y borrada al terminar. */
	private static function work_dir() {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'digitalisimo-seo/tmp';
		if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); }
		return $dir;
	}

	public static function upload() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_csv_import' ) ) wp_die( 'No autorizado.' );
		$back = admin_url( 'admin.php?page=digitalisimo-seo-import' );
		$file = $_FILES['digitalisimo_seo_csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $file || UPLOAD_ERR_OK !== ( $file['error'] ?? 1 ) || (int) $file['size'] > self::MAX || ! preg_match( '/\.csv$/i', (string) $file['name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { wp_safe_redirect( add_query_arg( 'csv', 'invalid', $back ) ); exit; }
		$handle = fopen( $file['tmp_name'], 'r' );
		$header = $handle ? fgetcsv( $handle ) : false;
		if ( $handle ) fclose( $handle );
		$header = is_array( $header ) ? array_map( function( $h ) { return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ); }, $header ) : array();
		if ( ! in_array( 'id', $header, true ) ) { wp_safe_redirect( add_query_arg( 'csv', 'header', $back ) ); exit; }
		$target = self::work_dir() . '/' . wp_generate_password( 32, false, false ) . '.csv';
		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) { wp_safe_redirect( add_query_arg( 'csv', 'invalid', $back ) ); exit; }
		update_option( self::JOB, array( 'status' => 'running', 'file' => $target, 'offset' => 0, 'user' => get_current_user_id(), 'updated' => 0, 'skipped' => 0, 'errors' => array(), 'started' => time(), 'tick' => time() ), false );
		self::run_batch();
		wp_safe_redirect( add_query_arg( 'csv', 'started', $back ) );
		exit;
	}

	public static function run_batch() {
		$job = get_option( self::JOB, array() );
		if ( ! is_array( $job ) || 'running' !== ( $job['status'] ?? '' ) ) return;
		$file = (string) $job['file'];
		$dir  = realpath( self::work_dir() );
		if ( ! $dir || 0 !== strpos( (string) realpath( $file ), $dir . DIRECTORY_SEPARATOR ) || ! ( $handle = fopen( $file, 'r' ) ) ) { $job['status'] = 'error'; update_option( self::JOB, $job, false ); return; }
		$header = array_map( function( $h ) { return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ); }, (array) fgetcsv( $handle ) );
		if ( $job['offset'] ) fseek( $handle, (int) $job['offset'] );
		$previous = get_current_user_id();
		wp_set_current_user( (int) $job['user'] ); // Los permisos se comprueban con quien pidió la importación.
		$max = (int) Digitalisimo_Integrations_SEO_Resolver::option( 'keyword_max_count', 5 );
		for ( $i = 0; $i < self::BATCH && false !== ( $cells = fgetcsv( $handle ) ); $i++ ) {
			if ( array( null ) === $cells ) continue;
			$row = array_combine( array_slice( $header, 0, count( $cells ) ), array_slice( $cells, 0, count( $header ) ) );
			$id  = absint( $row['id'] ?? 0 );
			$type = $id ? get_post_type( $id ) : '';
			if ( ! $id || ! $type || ! in_array( $type, self::types(), true ) || ! current_user_can( 'edit_post', $id ) ) { $job['skipped']++; if ( count( $job['errors'] ) < 20 ) $job['errors'][] = (string) ( $row['id'] ?? '?' ); continue; }
			$changes = self::changes( $row, $max );
			if ( $changes ) { self::apply( $id, $changes ); $job['updated']++; }
		}
		$job['offset'] = ftell( $handle );
		$done = feof( $handle );
		fclose( $handle );
		wp_set_current_user( $previous );
		$job['tick'] = time();
		if ( $done ) { $job['status'] = 'done'; wp_delete_file( $file ); }
		else wp_schedule_single_event( time() + 5, self::HOOK );
		update_option( self::JOB, $job, false );
	}

	/** Sección de la pestaña «Importar». */
	public static function render() {
		$job = get_option( self::JOB, array() );
		if ( is_array( $job ) && 'running' === ( $job['status'] ?? '' ) && time() - (int) ( $job['tick'] ?? 0 ) > MINUTE_IN_SECONDS ) { self::run_batch(); $job = get_option( self::JOB, array() ); }
		$notices = array( 'invalid' => array( 'error', 'El archivo no es un CSV válido o supera 10 MB.' ), 'header' => array( 'error', 'El CSV necesita una columna «id» en la primera fila.' ), 'started' => array( 'success', 'Importación iniciada.' ) );
		$notice  = sanitize_key( $_GET['csv'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<h2>Metadatos SEO en CSV</h2><p>Exporta los metadatos SEO de los contenidos, edítalos en una hoja de cálculo y vuelve a importarlos. Columnas: <code>' . esc_html( implode( ', ', self::COLUMNS ) ) . '</code>. Se aplican por <code>id</code>; una celda vacía conserva el valor guardado, y noindex y nofollow admiten 1/0 o sí/no.</p>';
		if ( isset( $notices[ $notice ] ) ) echo '<div class="notice notice-' . esc_attr( $notices[ $notice ][0] ) . ' inline"><p>' . esc_html( $notices[ $notice ][1] ) . '</p></div>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_seo_csv_export' ), 'digitalisimo_seo_csv_export' ) ) . '">Exportar CSV</a></p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_csv_import">';
		wp_nonce_field( 'digitalisimo_seo_csv_import' );
		echo '<input type="file" name="digitalisimo_seo_csv" accept=".csv,text/csv" required> ';
		submit_button( 'Importar CSV', 'secondary', 'submit', false );
		echo '</form>';
		if ( is_array( $job ) && $job ) echo '<p class="description">Última importación: ' . esc_html( 'done' === $job['status'] ? 'terminada' : ( 'running' === $job['status'] ? 'en curso' : 'detenida' ) ) . ' · ' . esc_html( (int) $job['updated'] ) . ' contenidos actualizados · ' . esc_html( (int) $job['skipped'] ) . ' filas omitidas' . ( $job['errors'] ? ' (id: ' . esc_html( implode( ', ', $job['errors'] ) ) . ')' : '' ) . '.</p>';
	}
}
