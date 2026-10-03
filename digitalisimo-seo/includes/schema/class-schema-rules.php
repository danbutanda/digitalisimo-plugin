<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reglas puras del auditor de Schema: reciben el HTML final (o un grafo) y
 * devuelven qué existe, qué falla y qué falta. No dependen de WordPress, así
 * que valen para cualquier sitio y se prueban sin servidor.
 *
 * Los tipos y propiedades se validan contra el vocabulario oficial de
 * schema.org; las propiedades obligatorias y recomendadas siguen la
 * documentación de resultados enriquecidos de Google Search Central.
 */
class Digitalisimo_Integrations_Schema_Rules {
	const OK      = 'OK';
	const WARNING = 'ADVERTENCIA';
	const ERROR   = 'ERROR';
	const NA      = 'NO APLICABLE';

	/**
	 * Requisitos de Google por tipo. El orden importa: gana la primera regla
	 * cuyo tipo coincide (LocalBusiness antes que Organization).
	 * rich = false: Google lo usa para entender entidades, no muestra un
	 * resultado enriquecido propio.
	 */
	const GOOGLE = array(
		'FAQPage'             => array( 'feature' => 'Preguntas frecuentes', 'rich' => false, 'required' => array( 'mainEntity' ), 'recommended' => array(), 'note' => 'Google retiró el resultado enriquecido FAQ; este marcado sólo aporta contexto semántico.' ),
		'ProfilePage'         => array( 'feature' => 'Página de perfil', 'rich' => true, 'required' => array( 'mainEntity' ), 'recommended' => array() ),
		'BreadcrumbList'      => array( 'feature' => 'Ruta de navegación', 'rich' => true, 'required' => array( 'itemListElement' ), 'recommended' => array() ),
		'Article'             => array( 'feature' => 'Artículo', 'rich' => true, 'required' => array(), 'recommended' => array( 'headline', 'image', 'datePublished', 'dateModified', 'author' ) ),
		'Product'             => array( 'feature' => 'Fragmento de producto', 'rich' => true, 'required' => array( 'name' ), 'one_of' => array( 'offers', 'review', 'aggregateRating' ), 'recommended' => array( 'image', 'description', 'brand' ) ),
		'SoftwareApplication' => array( 'feature' => 'Aplicación de software', 'rich' => true, 'required' => array( 'name' ), 'one_of' => array( 'aggregateRating', 'review' ), 'recommended' => array( 'offers', 'applicationCategory', 'operatingSystem' ) ),
		'Event'               => array( 'feature' => 'Evento', 'rich' => true, 'required' => array( 'name', 'startDate', 'location' ), 'recommended' => array( 'description', 'endDate', 'image', 'offers', 'organizer', 'eventStatus' ) ),
		'Recipe'              => array( 'feature' => 'Receta', 'rich' => true, 'required' => array( 'name', 'image' ), 'recommended' => array( 'author', 'datePublished', 'description', 'recipeIngredient', 'recipeInstructions' ) ),
		'VideoObject'         => array( 'feature' => 'Video', 'rich' => true, 'required' => array( 'name', 'thumbnailUrl', 'uploadDate' ), 'recommended' => array( 'description', 'contentUrl', 'duration' ) ),
		'JobPosting'          => array( 'feature' => 'Oferta de empleo', 'rich' => true, 'required' => array( 'title', 'description', 'datePosted', 'hiringOrganization', 'jobLocation' ), 'recommended' => array( 'validThrough', 'employmentType', 'baseSalary' ) ),
		'Course'              => array( 'feature' => 'Curso', 'rich' => true, 'required' => array( 'name', 'description' ), 'recommended' => array( 'provider' ) ),
		'Review'              => array( 'feature' => 'Reseña', 'rich' => true, 'required' => array( 'itemReviewed', 'author', 'reviewRating' ), 'recommended' => array() ),
		'LocalBusiness'       => array( 'feature' => 'Negocio local', 'rich' => true, 'required' => array( 'name', 'address' ), 'recommended' => array( 'telephone', 'url', 'geo', 'openingHoursSpecification', 'image', 'priceRange' ) ),
		'Organization'        => array( 'feature' => 'Organización (logo y datos)', 'rich' => false, 'required' => array( 'name' ), 'recommended' => array( 'url', 'logo', 'sameAs' ) ),
		'WebSite'             => array( 'feature' => 'Nombre del sitio', 'rich' => false, 'required' => array( 'name', 'url' ), 'recommended' => array() ),
		'WebPage'             => array( 'feature' => 'Página', 'rich' => false, 'required' => array( 'url' ), 'recommended' => array( 'name', 'isPartOf' ) ),
		'Service'             => array( 'feature' => 'Servicio', 'rich' => false, 'required' => array( 'name' ), 'recommended' => array( 'provider', 'description' ) ),
		'Person'              => array( 'feature' => 'Persona', 'rich' => false, 'required' => array( 'name' ), 'recommended' => array() ),
	);
	/** Propiedades que deben ser URL absolutas cuando son texto. */
	const URL_PROPS  = array( 'url', 'item', 'logo', 'image', 'sameAs', 'thumbnailUrl', 'contentUrl', 'embedUrl', 'hasMap', 'mainEntityOfPage' );
	const DATE_PROPS = array( 'datePublished', 'dateModified', 'dateCreated', 'uploadDate', 'startDate', 'endDate', 'datePosted', 'validThrough', 'priceValidUntil' );

	/* ------------------------------------------------------------------ *
	 * Extracción
	 * ------------------------------------------------------------------ */

	/** Cada <script type="application/ld+json"> con su JSON decodificado o su error. */
	public static function blocks( $html ) {
		$out = array();
		if ( ! preg_match_all( '#<script\b([^>]*)>(.*?)</script>#is', (string) $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) return $out;
		foreach ( $matches as $m ) {
			if ( ! preg_match( '#\btype\s*=\s*(["\']?)application/ld\+json\1#i', $m[1][0] ) ) continue;
			$raw  = trim( preg_replace( '#^\s*(?:<!--|<!\[CDATA\[)|(?:-->|\]\]>)\s*$#', '', $m[2][0] ) );
			$data = json_decode( $raw, true );
			$out[] = array(
				'tag'    => $m[0][0],
				'offset' => $m[0][1],
				'attrs'  => $m[1][0],
				'raw'    => $raw,
				'data'   => is_array( $data ) ? $data : null,
				'error'  => is_array( $data ) ? '' : ( '' === $raw ? 'Bloque vacío' : 'JSON inválido: ' . json_last_error_msg() ),
				'source' => preg_match( '#\b(?:class|id)\s*=\s*["\']([^"\']+)#i', $m[1][0], $s ) ? $s[1] : '',
			);
		}
		return $out;
	}

	/** Texto que ve el visitante: sin scripts, estilos, plantillas ni comentarios. */
	public static function visible_text( $html ) {
		$html = (string) $html;
		if ( preg_match( '#<body\b[^>]*>(.*)</body>#is', $html, $m ) ) $html = $m[1];
		$html = preg_replace( '#<(script|style|noscript|template|svg)\b.*?</\1>#is', ' ', $html );
		$html = preg_replace( '#<!--.*?-->#s', ' ', $html );
		return self::normalize( strip_tags( $html ) );
	}

	/** Minúsculas, sin acentos, espacios simples: para comparar textos. */
	public static function normalize( $text ) {
		$text = html_entity_decode( strip_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = strtr( $text, array( 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', '¿' => '', '¡' => '', "\xc2\xa0" => ' ' ) );
		return trim( preg_replace( '/\s+/', ' ', strtolower( $text ) ) );
	}

	/** La pregunta aparece en el texto visible de la página. */
	public static function visible( $text, $visible ) {
		$needle = self::normalize( $text );
		return '' !== $needle && false !== strpos( (string) $visible, $needle );
	}

	/**
	 * Nodos de primer nivel de todos los bloques (@graph, listas y objetos
	 * sueltos), con el bloque del que salen.
	 */
	public static function nodes( $blocks ) {
		$out = array();
		foreach ( $blocks as $index => $block ) {
			$data = $block['data'] ?? null;
			if ( ! is_array( $data ) ) continue;
			$items = isset( $data['@graph'] ) && is_array( $data['@graph'] ) ? $data['@graph'] : ( self::is_list( $data ) ? $data : array( $data ) );
			foreach ( $items as $node ) if ( is_array( $node ) && ! self::is_list( $node ) ) $out[] = array( 'node' => $node, 'block' => $index );
		}
		return $out;
	}

	public static function is_list( $value ) {
		return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	public static function types( $node ) {
		if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) return array();
		return array_values( array_filter( array_map( array( 'Digitalisimo_Integrations_Schema_Vocabulary', 'short' ), (array) $node['@type'] ), 'strlen' ) );
	}

	/** Etiqueta legible de un nodo para los informes. */
	public static function label( $node ) {
		$types = self::types( $node );
		$label = $types ? implode( ' + ', $types ) : '(sin @type)';
		return isset( $node['@id'] ) && is_string( $node['@id'] ) ? $label . ' · ' . $node['@id'] : $label;
	}

	/* ------------------------------------------------------------------ *
	 * Análisis
	 * ------------------------------------------------------------------ */

	/**
	 * $ctx: url, home (bool), expected (lista de array( type, why )).
	 * Devuelve blocks, entities, issues, missing, rich y status.
	 */
	public static function analyze( $html, $ctx = array() ) {
		return self::analyze_blocks( self::blocks( $html ), self::visible_text( $html ), $ctx );
	}

	public static function analyze_blocks( $blocks, $visible, $ctx = array() ) {
		$issues = array();
		$add    = function( $status, $where, $message ) use ( &$issues ) { $issues[] = array( 'status' => $status, 'where' => $where, 'message' => $message ); };
		foreach ( $blocks as $i => $block ) {
			$where = 'Bloque ' . ( $i + 1 ) . ( $block['source'] ? ' (' . $block['source'] . ')' : '' );
			if ( $block['error'] ) { $add( self::ERROR, $where, $block['error'] . '. Google ignora el bloque completo.' ); continue; }
			$context = $block['data']['@context'] ?? null;
			if ( ! $context || ! preg_match( '#schema\.org#i', is_array( $context ) ? json_encode( $context ) : (string) $context ) ) $add( self::ERROR, $where, 'Falta "@context": "https://schema.org".' );
		}
		if ( count( $blocks ) > 1 ) $add( self::WARNING, 'Página', count( $blocks ) . ' bloques JSON-LD separados. Lo recomendable es un solo @graph con las entidades relacionadas por @id.' );

		$nodes    = self::nodes( $blocks );
		$entities = array();
		foreach ( $nodes as $entry ) {
			$node = $entry['node'];
			$entities[] = array( 'types' => self::types( $node ), 'id' => is_string( $node['@id'] ?? null ) ? $node['@id'] : '', 'block' => $entry['block'], 'name' => self::text( $node['name'] ?? ( $node['headline'] ?? '' ) ) );
			self::check_node( $node, self::label( $node ), $add, $visible, 0 );
		}
		self::check_ids( $nodes, $add );
		self::check_duplicates( $nodes, $ctx, $add );

		$missing = array();
		foreach ( (array) ( $ctx['expected'] ?? array() ) as $expected ) {
			$found = false;
			foreach ( $nodes as $entry ) if ( Digitalisimo_Integrations_Schema_Vocabulary::is_a( self::types( $entry['node'] ), $expected['type'] ) ) { $found = true; break; }
			if ( ! $found ) $missing[] = array( 'type' => $expected['type'], 'why' => $expected['why'], 'status' => ! empty( $expected['base'] ) ? self::ERROR : self::WARNING );
		}

		$rich = array();
		foreach ( $nodes as $entry ) {
			$rule = self::rule( self::types( $entry['node'] ) );
			$label  = self::label( $entry['node'] );
			$errors = 0;
			foreach ( $issues as $issue ) if ( self::ERROR === $issue['status'] && 0 === strpos( $issue['where'], $label ) ) $errors++;
			if ( ! $rule ) { $rich[] = array( 'type' => implode( ' + ', self::types( $entry['node'] ) ), 'feature' => 'Entidad semántica', 'status' => $errors ? self::ERROR : self::NA, 'detail' => $errors ? $errors . ' error(es) de marcado.' : 'Tipo de Schema.org sin resultado enriquecido propio de Google.', 'category' => 'Semántico' ); continue; }
			$rich[] = array(
				'type'    => implode( ' + ', self::types( $entry['node'] ) ),
				'feature' => $rule['feature'],
				'category' => $rule['rich'] ? 'Resultado enriquecido potencial' : 'Semántico',
				// Con restricciones de Google (FAQ) el marcado es válido pero no se promete el resultado.
				'status'  => $errors ? self::ERROR : ( $rule['rich'] && empty( $rule['note'] ) ? self::OK : self::NA ),
				'detail'  => $errors ? $errors . ' error(es) que impiden usarlo.' : ( $rule['rich'] ? ( $rule['note'] ?? 'Cumple los requisitos de marcado conocidos; Google no garantiza mostrarlo.' ) : ( $rule['note'] ?? 'Marcado semántico; no tiene resultado enriquecido propio.' ) ),
			);
		}

		$status = self::OK;
		foreach ( array_merge( $issues, $missing ) as $item ) {
			if ( self::ERROR === $item['status'] ) { $status = self::ERROR; break; }
			if ( self::WARNING === $item['status'] ) $status = self::WARNING;
		}
		if ( ! $blocks ) $status = self::ERROR;
		return array( 'blocks' => count( $blocks ), 'entities' => $entities, 'issues' => $issues, 'missing' => $missing, 'rich' => $rich, 'status' => $status );
	}

	/** Primera regla de Google que corresponde a los tipos. */
	public static function rule( $types ) {
		foreach ( self::GOOGLE as $type => $rule ) if ( Digitalisimo_Integrations_Schema_Vocabulary::is_a( $types, $type ) ) return $rule + array( 'type' => $type );
		return null;
	}

	private static function text( $value ) {
		return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
	}

	private static function present( $node, $prop ) {
		if ( ! array_key_exists( $prop, $node ) ) return false;
		$value = $node[ $prop ];
		return ! ( null === $value || '' === $value || array() === $value );
	}

	/** Tipos, propiedades y requisitos de un nodo y de sus nodos anidados. */
	private static function check_node( $node, $where, $add, $visible, $depth ) {
		if ( $depth > 6 || ! is_array( $node ) ) return;
		$types = self::types( $node );
		$only_ref = isset( $node['@id'] ) && 1 === count( $node );
		if ( ! $types && ! $only_ref && 0 === $depth ) $add( self::ERROR, $where, 'El nodo no tiene @type.' );
		$valid = array();
		foreach ( $types as $type ) {
			$status = Digitalisimo_Integrations_Schema_Vocabulary::status( $type );
			if ( 'official' === $status ) { $valid[] = $type; continue; }
			if ( 'pending' === $status ) { $valid[] = $type; $add( self::WARNING, $where, '«' . $type . '» todavía está en revisión en Schema.org (pending); Google puede ignorarlo.' ); continue; }
			if ( 'superseded' === $status ) { $valid[] = $type; $add( self::WARNING, $where, '«' . $type . '» fue reemplazado por «' . Digitalisimo_Integrations_Schema_Vocabulary::replacement( $type ) . '».' ); continue; }
			$suggest = Digitalisimo_Integrations_Schema_Vocabulary::suggest( $type );
			$add( self::ERROR, $where, '«' . $type . '» no es un tipo de Schema.org.' . ( $suggest ? ' Usa «' . $suggest . '».' : ' Usa un tipo oficial (por ejemplo LocalBusiness o uno de sus subtipos para un negocio).' ) );
		}
		if ( $valid ) {
			foreach ( $node as $prop => $value ) {
				if ( '@' === $prop[0] ) continue;
				$status = Digitalisimo_Integrations_Schema_Vocabulary::property_status( $prop );
				if ( 'unknown' === $status ) { $add( self::WARNING, $where, '«' . $prop . '» no es una propiedad de Schema.org; se ignora.' ); continue; }
				if ( 'superseded' === $status ) $add( self::WARNING, $where, '«' . $prop . '» está obsoleta; usa «' . Digitalisimo_Integrations_Schema_Vocabulary::property_replacement( $prop ) . '».' );
				elseif ( ! Digitalisimo_Integrations_Schema_Vocabulary::property_fits( $valid, $prop ) ) $add( self::WARNING, $where, '«' . $prop . '» no corresponde a ' . implode( '/', $valid ) . ' según Schema.org.' );
				self::check_value( $prop, $value, $where, $add );
			}
			self::check_google( $node, $valid, $where, $add, $visible );
		}
		foreach ( $node as $prop => $value ) {
			if ( '@' === $prop[0] || ! is_array( $value ) ) continue;
			foreach ( self::is_list( $value ) ? $value : array( $value ) as $child ) if ( is_array( $child ) && isset( $child['@type'] ) ) self::check_node( $child, $where . ' › ' . $prop, $add, $visible, $depth + 1 );
		}
	}

	/** URLs absolutas, fechas ISO 8601, país ISO y teléfono internacional. */
	private static function check_value( $prop, $value, $where, $add ) {
		foreach ( self::is_list( $value ) ? $value : array( $value ) as $item ) {
			if ( ! is_string( $item ) ) continue;
			if ( in_array( $prop, self::URL_PROPS, true ) && ! preg_match( '#^https?://[^\s/]+#i', $item ) ) $add( self::ERROR, $where, '«' . $prop . '» debe ser una URL absoluta (https://…): «' . $item . '».' );
			if ( in_array( $prop, self::DATE_PROPS, true ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/', $item ) ) $add( self::ERROR, $where, '«' . $prop . '» debe usar fecha ISO 8601 (AAAA-MM-DD o con hora): «' . $item . '».' );
			if ( 'addressCountry' === $prop && ! preg_match( '/^[A-Z]{2}$/', $item ) ) $add( self::WARNING, $where, 'addressCountry debería ser el código ISO 3166 de dos letras (por ejemplo MX), no «' . $item . '».' );
			if ( 'telephone' === $prop && '+' !== substr( ltrim( $item ), 0, 1 ) ) $add( self::WARNING, $where, 'El teléfono debería incluir el código de país (+52…).' );
		}
	}

	/** Requisitos de Google y reglas propias de BreadcrumbList y FAQPage. */
	private static function check_google( $node, $types, $where, $add, $visible ) {
		$rule = self::rule( $types );
		if ( ! $rule ) return;
		foreach ( $rule['required'] as $prop ) if ( ! self::present( $node, $prop ) ) $add( self::ERROR, $where, 'Falta «' . $prop . '», obligatorio para ' . $rule['feature'] . '.' );
		if ( ! empty( $rule['one_of'] ) ) {
			$has = false;
			foreach ( $rule['one_of'] as $prop ) if ( self::present( $node, $prop ) ) $has = true;
			if ( ! $has ) $add( self::ERROR, $where, $rule['feature'] . ' necesita al menos una de: ' . implode( ', ', $rule['one_of'] ) . '.' );
		}
		$recommended = array();
		foreach ( $rule['recommended'] as $prop ) if ( ! self::present( $node, $prop ) ) $recommended[] = $prop;
		if ( $recommended ) $add( self::WARNING, $where, 'Recomendado por Google: ' . implode( ', ', $recommended ) . '.' );
		if ( 'BreadcrumbList' === $rule['type'] ) self::check_breadcrumbs( $node, $where, $add );
		if ( 'FAQPage' === $rule['type'] || ( is_array( $node['mainEntity'] ?? null ) && in_array( 'FAQPage', $types, true ) ) ) self::check_faq( $node, $where, $add, $visible );
	}

	private static function check_breadcrumbs( $node, $where, $add ) {
		$items = $node['itemListElement'] ?? array();
		if ( ! is_array( $items ) || ! self::is_list( $items ) ) return;
		foreach ( $items as $i => $item ) {
			if ( (int) ( $item['position'] ?? 0 ) !== $i + 1 ) $add( self::ERROR, $where, 'Las posiciones deben ser 1, 2, 3… en orden; el elemento ' . ( $i + 1 ) . ' tiene «' . ( $item['position'] ?? 'sin posición' ) . '».' );
			if ( '' === self::text( $item['name'] ?? ( $item['item']['name'] ?? '' ) ) ) $add( self::ERROR, $where, 'El elemento ' . ( $i + 1 ) . ' no tiene «name».' );
			if ( $i < count( $items ) - 1 && empty( $item['item'] ) ) $add( self::ERROR, $where, 'El elemento ' . ( $i + 1 ) . ' necesita «item» con su URL (sólo el último puede omitirla).' );
		}
		if ( count( $items ) < 2 ) $add( self::WARNING, $where, 'Una ruta de navegación necesita al menos dos niveles.' );
	}

	private static function check_faq( $node, $where, $add, $visible ) {
		$questions = $node['mainEntity'] ?? array();
		$questions = self::is_list( $questions ) ? $questions : array( $questions );
		$hidden    = array();
		foreach ( $questions as $i => $q ) {
			if ( ! is_array( $q ) ) continue;
			$name = self::text( $q['name'] ?? '' );
			if ( '' === $name ) $add( self::ERROR, $where, 'La pregunta ' . ( $i + 1 ) . ' no tiene «name».' );
			if ( '' === self::text( $q['acceptedAnswer']['text'] ?? '' ) ) $add( self::ERROR, $where, 'La pregunta ' . ( $i + 1 ) . ' no tiene «acceptedAnswer» con «text».' );
			if ( null !== $visible && '' !== $name && ( ! self::visible( $name, $visible ) || ! self::visible( $q['acceptedAnswer']['text'] ?? '', $visible ) ) ) $hidden[] = $name;
		}
		if ( $hidden ) $add( self::ERROR, $where, count( $hidden ) . ' pregunta(s) o respuestas no aparecen en el contenido visible: «' . implode( '», «', array_slice( $hidden, 0, 3 ) ) . '»' . ( count( $hidden ) > 3 ? '…' : '' ) . '. El marcado debe coincidir con lo que ve el visitante.' );
	}

	/** El mismo @id definido varias veces: separado, con tipos o valores distintos. */
	private static function check_ids( $nodes, $add ) {
		$groups = array();
		foreach ( $nodes as $entry ) {
			$id = $entry['node']['@id'] ?? null;
			if ( ! is_string( $id ) || count( $entry['node'] ) <= 1 ) continue;
			$groups[ $id ][] = $entry;
		}
		$defined = array_keys( $groups );
		foreach ( $groups as $id => $entries ) {
			if ( count( $entries ) < 2 ) continue;
			$types = array();
			foreach ( $entries as $entry ) $types[ implode( '+', self::types( $entry['node'] ) ) ] = true;
			if ( count( $types ) > 1 ) $add( self::ERROR, '@id ' . $id, 'Se declara con tipos distintos (' . implode( ', ', array_keys( $types ) ) . '). Un @id identifica una sola entidad.' );
			$blocks = array_unique( array_column( $entries, 'block' ) );
			$add( self::WARNING, '@id ' . $id, 'Se define ' . count( $entries ) . ' veces' . ( count( $blocks ) > 1 ? ' en ' . count( $blocks ) . ' bloques separados' : '' ) . '. Debe definirse una vez y referenciarse con {"@id": …}.' );
			$seen = array();
			foreach ( $entries as $entry ) foreach ( $entry['node'] as $prop => $value ) {
				if ( '@' === $prop[0] ) continue;
				$json = json_encode( $value );
				if ( isset( $seen[ $prop ] ) && $seen[ $prop ] !== $json ) $add( self::ERROR, '@id ' . $id, 'Valores contradictorios en «' . $prop . '».' );
				$seen[ $prop ] = $json;
			}
		}
		// Referencias a entidades de este mismo sitio que no se definen en la página.
		$refs = array();
		foreach ( $nodes as $entry ) self::collect_refs( $entry['node'], $refs, 0 );
		foreach ( array_unique( $refs ) as $ref ) {
			if ( in_array( $ref, $defined, true ) ) continue;
			$add( self::WARNING, '@id ' . $ref, 'Se referencia pero no se define en la página.' );
		}
	}

	private static function collect_refs( $value, &$refs, $depth ) {
		if ( ! is_array( $value ) || $depth > 6 ) return;
		if ( isset( $value['@id'] ) && is_string( $value['@id'] ) && 1 === count( $value ) && $depth > 0 ) { $refs[] = $value['@id']; return; }
		foreach ( $value as $key => $child ) if ( '@id' !== $key ) self::collect_refs( $child, $refs, $depth + 1 );
	}

	/** Entidades repetidas o fuera de lugar en la URL. */
	private static function check_duplicates( $nodes, $ctx, $add ) {
		$count = array( 'WebSite' => array(), 'Organization' => array(), 'WebPage' => array(), 'BreadcrumbList' => array(), 'FAQPage' => array() );
		foreach ( $nodes as $n => $entry ) {
			$types = self::types( $entry['node'] );
			$key   = is_string( $entry['node']['@id'] ?? null ) ? $entry['node']['@id'] : 'nodo-' . $n;
			foreach ( array_keys( $count ) as $family ) if ( Digitalisimo_Integrations_Schema_Vocabulary::is_a( $types, $family ) ) $count[ $family ][ $key ] = true;
		}
		$labels = array( 'WebSite' => 'sitios web (WebSite)', 'Organization' => 'organizaciones', 'WebPage' => 'páginas (WebPage, FAQPage, …) para la misma URL', 'BreadcrumbList' => 'rutas de navegación', 'FAQPage' => 'bloques de preguntas frecuentes' );
		foreach ( $count as $family => $ids ) if ( count( $ids ) > 1 ) $add( self::WARNING, $family, 'Hay ' . count( $ids ) . ' ' . $labels[ $family ] . ' distintas. Intégralas en una sola entidad o relaciónalas con @id.' );
		if ( ! empty( $ctx['home'] ) && $count['BreadcrumbList'] ) $add( self::WARNING, 'BreadcrumbList', 'La portada no necesita BreadcrumbList: sólo tiene sentido en páginas internas.' );
	}

	/** Pasa un grafo ya construido por las mismas reglas (prueba antes de publicar). */
	public static function analyze_graph( $graph, $ctx = array(), $visible = null ) {
		$raw = json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return self::analyze_blocks( array( array( 'tag' => '', 'offset' => 0, 'attrs' => '', 'raw' => $raw, 'data' => $graph, 'error' => '', 'source' => 'Digitalísimo' ) ), $visible, $ctx );
	}
}

