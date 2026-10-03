<?php
defined( 'ABSPATH' ) || exit;

/**
 * Un único grafo JSON-LD por URL.
 *
 * build() arma Organization (o LocalBusiness/Person) + WebSite + WebPage y,
 * cuando corresponde, BreadcrumbList, la entidad del contenido y su autor, todo
 * relacionado por @id. merge() incorpora lo que publican el tema y otros
 * plugins: descarta tipos que no existen en Schema.org y duplicados de las
 * entidades base, funde los @id repetidos y sólo conserva las preguntas
 * frecuentes que se ven en la página. build(), merge() y unify() son puros;
 * el resto lee los datos del sitio actual.
 */
class Digitalisimo_Integrations_Schema_Graph {
	const CLASS_NAME = 'digitalisimo-schema-graph';
	const RAW_QUERY  = 'digitalisimo_schema_raw';
	const RAW_TOKEN  = 'digitalisimo_schema_raw_token';
	/** Entidades que pueden ser el tema principal de una página. */
	const MAIN_TYPES = array( 'Article', 'Product', 'Service', 'Event', 'Recipe', 'Course', 'JobPosting', 'SoftwareApplication', 'VideoObject', 'Book', 'Movie' );
	/** Nombres de país frecuentes → ISO 3166-1 alfa-2 (Google pide el código). */
	const COUNTRIES = array( 'mexico' => 'MX', 'estados unidos' => 'US', 'estados unidos de america' => 'US', 'usa' => 'US', 'united states' => 'US', 'espana' => 'ES', 'spain' => 'ES', 'colombia' => 'CO', 'argentina' => 'AR', 'chile' => 'CL', 'peru' => 'PE', 'ecuador' => 'EC', 'venezuela' => 'VE', 'guatemala' => 'GT', 'costa rica' => 'CR', 'panama' => 'PA', 'uruguay' => 'UY', 'paraguay' => 'PY', 'bolivia' => 'BO', 'honduras' => 'HN', 'el salvador' => 'SV', 'nicaragua' => 'NI', 'republica dominicana' => 'DO', 'puerto rico' => 'PR', 'cuba' => 'CU', 'canada' => 'CA', 'brasil' => 'BR', 'brazil' => 'BR' );

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 4 );
	}

	/* ------------------------------------------------------------------ *
	 * Construcción (pura)
	 * ------------------------------------------------------------------ */

	public static function country( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^[A-Za-z]{2}$/', $value ) ) return strtoupper( $value );
		$key = Digitalisimo_Integrations_Schema_Rules::normalize( $value );
		return self::COUNTRIES[ $key ] ?? $value;
	}

	/**
	 * Un negocio local sólo se declara con datos reales: nombre, ciudad,
	 * calle o código postal, y teléfono o coordenadas.
	 */
	public static function local_ready( $local ) {
		if ( ! is_array( $local ) ) return false;
		$a = $local['address'] ?? array();
		return '' !== trim( (string) ( $local['name'] ?? '' ) ) && '' !== trim( (string) ( $a['addressLocality'] ?? '' ) ) && ( '' !== trim( (string) ( $a['streetAddress'] ?? '' ) ) || '' !== trim( (string) ( $a['postalCode'] ?? '' ) ) ) && ( '' !== trim( (string) ( $local['telephone'] ?? '' ) ) || ! empty( $local['geo'] ) );
	}

	/** @id estables de las entidades base. */
	public static function ids( $site, $page ) {
		$home = $site['home'];
		$person = 'Person' === ( $site['org_type'] ?? '' );
		return array(
			'identity'   => $home . ( $person ? '#person' : '#organization' ),
			'local'      => $home . '#localbusiness',
			'website'    => $home . '#website',
			'webpage'    => $page['url'] . '#webpage',
			'breadcrumb' => $page['url'] . '#breadcrumb',
			'image'      => $page['url'] . '#primaryimage',
		);
	}

	/**
	 * $site: home, name, org_name, org_type (Organization|LocalBusiness|Person),
	 * org_url, logo, image, same_as, email, language, local (array|null).
	 * $page: kind (home|singular|archive|term|search), url, title, description,
	 * published, modified, image, breadcrumbs, entity (array|null), about_identity,
	 * spatial.
	 */
	public static function build( $site, $page ) {
		$ids   = self::ids( $site, $page );
		$graph = array();
		$local = self::local_ready( $site['local'] ?? null ) ? $site['local'] : null;
		$type  = $site['org_type'] ?? 'Organization';
		if ( ! in_array( $type, array( 'Organization', 'LocalBusiness', 'Person' ), true ) ) $type = 'Organization';
		$is_person = 'Person' === $type;
		// LocalBusiness sin datos reales se publica como Organization.
		if ( 'LocalBusiness' === $type ) $type = 'Organization';

		$identity = array( '@type' => $is_person ? 'Person' : ( $local ? $local['type'] : $type ), '@id' => $ids['identity'], 'name' => $site['org_name'], 'url' => $site['org_url'] ?: $site['home'] );
		if ( ! $is_person && ! empty( $site['name'] ) && $site['name'] !== $site['org_name'] ) $identity['alternateName'] = $site['name'];
		if ( $site['logo'] ) $identity[ $is_person ? 'image' : 'logo' ] = array( '@type' => 'ImageObject', 'url' => $site['logo'] );
		if ( ! $is_person && ! empty( $site['image'] ) && $site['image'] !== $site['logo'] ) $identity['image'] = $site['image'];
		if ( ! empty( $site['same_as'] ) ) $identity['sameAs'] = array_values( $site['same_as'] );
		if ( ! empty( $site['email'] ) ) $identity['email'] = $site['email'];
		if ( ! $is_person && ! empty( $site['local']['telephone'] ) ) $identity['telephone'] = $site['local']['telephone'];
		$business = null;
		if ( $local ) {
			$business = array_filter( array(
				'alternateName'             => ( $local['name'] !== $site['org_name'] ) ? $local['name'] : '',
				'image'                     => $site['logo'] ?: ( $site['image'] ?? '' ),
				'telephone'                 => $local['telephone'] ?? '',
				'priceRange'                => $local['price'] ?? '',
				'address'                   => array( '@type' => 'PostalAddress' ) + array_filter( array_map( 'trim', array_map( 'strval', (array) $local['address'] ) ), 'strlen' ),
				'geo'                       => $local['geo'] ?? array(),
				'openingHoursSpecification' => $local['hours'] ?? array(),
			) );
			if ( isset( $business['address']['addressCountry'] ) ) $business['address']['addressCountry'] = self::country( $business['address']['addressCountry'] );
			if ( isset( $business['geo'] ) ) {
				$lat = self::coordinate( $business['geo']['latitude'] ); $lng = self::coordinate( $business['geo']['longitude'] );
				$business['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng );
				$business['hasMap'] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $lat . ',' . $lng );
			}
		}
		if ( $business && ! $is_person ) $identity += $business;
		if ( ! $is_person && ! empty( $identity['telephone'] ) && ! empty( $identity['email'] ) ) $identity['contactPoint'] = array( '@type' => 'ContactPoint', 'telephone' => $identity['telephone'], 'email' => $identity['email'] );
		$graph[] = $identity;
		// Una persona con negocio: el negocio es una entidad propia fundada por ella.
		if ( $business && $is_person ) $graph[] = array( '@type' => $local['type'], '@id' => $ids['local'], 'name' => $local['name'], 'url' => $site['home'], 'founder' => array( '@id' => $ids['identity'] ) ) + $business;

		$website = array( '@type' => 'WebSite', '@id' => $ids['website'], 'url' => $site['home'], 'name' => $site['name'], 'publisher' => array( '@id' => $ids['identity'] ) );
		if ( ! empty( $site['language'] ) ) $website['inLanguage'] = $site['language'];
		$graph[] = $website;

		$kind     = $page['kind'];
		$web_type = in_array( $kind, array( 'author', 'profile' ), true ) && ! empty( $page['profile']['name'] ) ? 'ProfilePage' : ( 'search' === $kind ? 'SearchResultsPage' : ( in_array( $kind, array( 'archive', 'term' ), true ) ? 'CollectionPage' : 'WebPage' ) );
		$webpage  = array_filter( array(
			'@type'         => $web_type,
			'@id'           => $ids['webpage'],
			'url'           => $page['url'],
			'name'          => $page['title'],
			'description'   => $page['description'],
			'isPartOf'      => array( '@id' => $ids['website'] ),
			'inLanguage'    => $site['language'] ?? '',
			'datePublished' => $page['published'] ?? '',
			'dateModified'  => $page['modified'] ?? '',
		) );
		if ( ! empty( $page['image'] ) ) $webpage['primaryImageOfPage'] = array( '@type' => 'ImageObject', '@id' => $ids['image'], 'url' => $page['image'] );
		if ( 'home' === $kind || ! empty( $page['about_identity'] ) ) $webpage['about'] = array( '@id' => $ids['identity'] );
		if ( ! empty( $page['spatial'] ) ) $webpage['spatialCoverage'] = $page['spatial'];
		$profile = null;
		if ( 'ProfilePage' === $web_type ) {
			$profile = self::person_node( $site, $page['profile'] );
			$webpage['mainEntity'] = array( '@id' => $profile['@id'] );
		}

		// Breadcrumbs sólo en páginas internas con al menos dos niveles.
		$crumbs = (array) ( $page['breadcrumbs'] ?? array() );
		$breadcrumb = null;
		if ( 'home' !== $kind && count( $crumbs ) >= 2 ) {
			$items = array();
			foreach ( array_values( $crumbs ) as $i => $crumb ) $items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb['name'], 'item' => $crumb['url'] );
			$breadcrumb = array( '@type' => 'BreadcrumbList', '@id' => $ids['breadcrumb'], 'itemListElement' => $items );
			$webpage['breadcrumb'] = array( '@id' => $ids['breadcrumb'] );
		}

		$entity = null;
		$author = null;
		if ( ! empty( $page['entity'] ) ) {
			$e    = $page['entity'];
			$id   = $page['url'] . '#' . strtolower( $e['type'] );
			$base = array( '@type' => $e['type'], '@id' => $id, 'mainEntityOfPage' => array( '@id' => $ids['webpage'] ) );
			if ( Digitalisimo_Integrations_Schema_Vocabulary::is_a( $e['type'], 'Article' ) ) {
				$entity = $base + array_filter( array( 'headline' => self::cut( $page['headline'] ?? $page['title'], 110 ), 'description' => $page['description'], 'datePublished' => $page['published'] ?? '', 'dateModified' => $page['modified'] ?? '', 'inLanguage' => $site['language'] ?? '', 'publisher' => array( '@id' => $ids['identity'] ) ) );
				if ( ! empty( $page['image'] ) ) $entity['image'] = array( '@id' => $ids['image'] );
				if ( ! empty( $page['author']['name'] ) ) {
					$author = self::person_node( $site, $page['author'] );
					$entity['author'] = array( '@id' => $author['@id'] );
				}
			} elseif ( 'Service' === $e['type'] ) {
				$entity = $base + array_filter( array( 'name' => $page['headline'] ?? $page['title'], 'description' => $page['description'], 'url' => $page['url'], 'provider' => array( '@id' => $ids['identity'] ), 'areaServed' => $page['spatial'] ?? array(), 'image' => $page['image'] ?? '' ) );
				if ( isset( $e['offers']['price'], $e['offers']['priceCurrency'] ) && is_numeric( $e['offers']['price'] ) && preg_match( '/^[A-Z]{3}$/', $e['offers']['priceCurrency'] ) ) $entity['offers'] = array( '@type' => 'Offer', 'price' => $e['offers']['price'], 'priceCurrency' => $e['offers']['priceCurrency'], 'url' => $page['url'], 'seller' => array( '@id' => $ids['identity'] ) );
			} elseif ( 'Product' === $e['type'] ) {
				$entity = $base + array_filter( array( 'name' => $page['headline'] ?? $page['title'], 'description' => $page['description'], 'url' => $page['url'], 'image' => $page['image'] ?? '', 'sku' => $e['sku'] ?? '' ) );
				if ( isset( $e['offers']['priceCurrency'], $e['offers']['price'] ) && is_numeric( $e['offers']['price'] ) && preg_match( '/^[A-Z]{3}$/', $e['offers']['priceCurrency'] ) ) {
					$entity['offers'] = array( '@type' => 'Offer', 'price' => $e['offers']['price'], 'priceCurrency' => $e['offers']['priceCurrency'], 'url' => $page['url'], 'seller' => array( '@id' => $ids['identity'] ) );
					if ( ! empty( $e['offers']['availability'] ) ) $entity['offers']['availability'] = $e['offers']['availability'];
				}
			} else {
				$entity = $base + array_filter( array( 'name' => $page['headline'] ?? $page['title'], 'description' => $page['description'], 'url' => $page['url'], 'image' => $page['image'] ?? '' ) );
			}
			$webpage['mainEntity'] = array( '@id' => $id );
		}
		$webpage_index = count( $graph );
		$graph[] = $webpage;
		if ( $breadcrumb ) $graph[] = $breadcrumb;
		if ( $entity ) $graph[] = $entity;
		if ( $author ) $graph[] = $author;
		if ( $profile && $profile['@id'] !== $ids['identity'] ) $graph[] = $profile;
		if ( ! empty( $page['video'] ) ) {
			$video = self::video_node( $page );
			if ( $video ) { $graph[] = $video; $graph[ $webpage_index ]['video'] = array( '@id' => $video['@id'] ); }
		}
		return $graph;
	}

	/** La identidad del autor usa el mismo @id en artículos y en su archivo. */
	private static function person_node( $site, $person ) {
		$slug = trim( (string) ( $person['slug'] ?? '' ) );
		$id = $site['home'] . '#/schema/person/' . rawurlencode( $slug );
		return array_filter( array( '@type' => 'Person', '@id' => $id, 'name' => $person['name'], 'url' => $person['url'] ?? '', 'sameAs' => $person['same_as'] ?? array() ) );
	}

	/** Sólo un video con miniatura, fecha propia y URL de reproducción reales. */
	private static function video_node( $page ) {
		$v = $page['video'];
		if ( empty( $v['name'] ) || empty( $v['thumbnailUrl'] ) || empty( $v['uploadDate'] ) || empty( $v['contentUrl'] ) ) return null;
		return array_filter( array( '@type' => 'VideoObject', '@id' => $page['url'] . '#video', 'name' => $v['name'], 'description' => $v['description'] ?? '', 'thumbnailUrl' => $v['thumbnailUrl'], 'uploadDate' => $v['uploadDate'], 'contentUrl' => $v['contentUrl'], 'isPartOf' => array( '@id' => $page['url'] . '#webpage' ) ) );
	}

	/**
	 * Coordenada con 7 decimales (precisión de ~1 cm). Se publica como texto:
	 * un float pasa por serialize_precision y algunos servidores lo imprimen
	 * con 50 cifras.
	 */
	public static function coordinate( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 7, '.', '' ), '0' ), '.' );
	}

	private static function cut( $text, $max ) {
		$text = trim( (string) $text );
		if ( strlen( $text ) <= $max ) return $text;
		$cut = substr( $text, 0, $max );
		// No dejar un carácter UTF-8 partido.
		$cut = preg_replace( '/[\x80-\xBF]+$/', '', $cut );
		$cut = preg_replace( '/[\xC0-\xFF]$/', '', $cut );
		return rtrim( $cut );
	}

	/* ------------------------------------------------------------------ *
	 * Unificación (pura)
	 * ------------------------------------------------------------------ */

	private static function index_of( $graph, $id ) {
		foreach ( $graph as $i => $node ) if ( ( $node['@id'] ?? null ) === $id ) return $i;
		return null;
	}

	private static function find( $graph, $family ) {
		foreach ( $graph as $i => $node ) if ( Digitalisimo_Integrations_Schema_Vocabulary::is_a( Digitalisimo_Integrations_Schema_Rules::types( $node ), $family ) ) return $i;
		return null;
	}

	/** Completa $ours con lo que sólo trae $theirs; lo propio nunca se pisa. */
	public static function fill( $ours, $theirs ) {
		foreach ( $theirs as $key => $value ) {
			if ( '@context' === $key || '@id' === $key || '@type' === $key ) continue;
			if ( ! array_key_exists( $key, $ours ) || null === $ours[ $key ] || '' === $ours[ $key ] || array() === $ours[ $key ] ) $ours[ $key ] = $value;
		}
		return $ours;
	}

	private static function add_type( $node, $type ) {
		$types = Digitalisimo_Integrations_Schema_Rules::types( $node );
		if ( ! in_array( $type, $types, true ) ) $types[] = $type;
		$node['@type'] = 1 === count( $types ) ? $types[0] : $types;
		return $node;
	}

	private static function remap( $value, $map ) {
		if ( ! is_array( $value ) ) return $value;
		if ( isset( $value['@id'] ) && 1 === count( $value ) && is_string( $value['@id'] ) && isset( $map[ $value['@id'] ] ) ) return array( '@id' => $map[ $value['@id'] ] );
		foreach ( $value as $key => $child ) if ( '@id' !== $key ) $value[ $key ] = self::remap( $child, $map );
		return $value;
	}

	/**
	 * Incorpora nodos ajenos al grafo propio. $visible es el texto visible de
	 * la página (null si no se conoce: entonces las FAQ no se filtran).
	 * Devuelve array( grafo, registro de cambios ).
	 */
	public static function merge( $graph, $foreign, $page, $visible = null ) {
		$log      = array();
		$map      = array();
		$home     = ! empty( $page['home'] );
		$url      = rtrim( (string) $page['url'], '/' );
		$identity = self::find( $graph, 'Organization' );
		if ( null === $identity ) $identity = self::find( $graph, 'Person' );
		$website  = self::find( $graph, 'WebSite' );
		$webpage  = self::find( $graph, 'WebPage' );
		foreach ( $foreign as $node ) {
			if ( ! is_array( $node ) ) continue;
			unset( $node['@context'] );
			$label = Digitalisimo_Integrations_Schema_Rules::label( $node );
			$types = Digitalisimo_Integrations_Schema_Rules::types( $node );
			$valid = array_values( array_filter( $types, function( $t ) { return 'unknown' !== Digitalisimo_Integrations_Schema_Vocabulary::status( $t ); } ) );
			$id    = is_string( $node['@id'] ?? null ) ? $node['@id'] : '';
			if ( ! $valid ) {
				$log[] = 'Retirado «' . $label . '»: no es un tipo de Schema.org.';
				// Un negocio con tipo inventado es la misma organización del sitio: sus referencias pasan a ella.
				if ( $id && null !== $identity && array_intersect( array( 'address', 'telephone', 'geo', 'openingHoursSpecification', 'areaServed' ), array_keys( $node ) ) ) $map[ $id ] = $graph[ $identity ]['@id'];
				continue;
			}
			if ( count( $valid ) < count( $types ) ) { $node['@type'] = 1 === count( $valid ) ? $valid[0] : $valid; $log[] = 'Se quitaron de «' . $label . '» los tipos que no existen en Schema.org.'; }
			if ( $id && null !== ( $i = self::index_of( $graph, $id ) ) ) {
				if ( Digitalisimo_Integrations_Schema_Vocabulary::is_a( $types, 'Product' ) && ! empty( $node['offers'] ) ) $graph[ $i ]['offers'] = $node['offers'];
				$graph[ $i ] = self::fill( $graph[ $i ], $node ); $log[] = 'Fusionado «' . $label . '» con la entidad del mismo @id.'; continue;
			}
			$is = function( $family ) use ( $valid ) { return Digitalisimo_Integrations_Schema_Vocabulary::is_a( $valid, $family ); };
			$node_url = rtrim( (string) ( is_string( $node['url'] ?? null ) ? $node['url'] : '' ), '/' );
			if ( $is( 'WebSite' ) && null !== $website ) { if ( $id ) $map[ $id ] = $graph[ $website ]['@id']; $log[] = 'Retirado «' . $label . '»: el sitio ya está declarado.'; continue; }
			if ( null !== $identity && ( $is( 'Organization' ) || ( $is( 'Person' ) && '' !== $node_url && $node_url === rtrim( (string) ( $graph[ $identity ]['url'] ?? '' ), '/' ) ) ) ) {
				if ( $id ) $map[ $id ] = $graph[ $identity ]['@id'];
				$log[] = 'Retirado «' . $label . '»: duplica la organización del sitio.';
				continue;
			}
			if ( $is( 'BreadcrumbList' ) ) {
				$own = self::find( $graph, 'BreadcrumbList' );
				if ( $home || null !== $own ) { if ( $id && null !== $own ) $map[ $id ] = $graph[ $own ]['@id']; $log[] = 'Retirado «' . $label . '»: ' . ( $home ? 'la portada no lleva BreadcrumbList.' : 'la ruta de navegación ya está declarada.' ); continue; }
				$node['@id'] = $page['url'] . '#breadcrumb';
				if ( null !== $webpage ) $graph[ $webpage ]['breadcrumb'] = array( '@id' => $node['@id'] );
				$graph[] = $node;
				$log[] = 'Integrado «' . $label . '».';
				continue;
			}
			if ( $is( 'FAQPage' ) && null !== $webpage && ! empty( $graph[ $webpage ]['mainEntity'] ) && ( '' === $node_url || $node_url === $url ) ) { $log[] = 'Retirado «' . $label . '»: la página ya tiene una entidad principal distinta de FAQ.'; continue; }
			if ( $is( 'FAQPage' ) && null !== $webpage && empty( $graph[ $webpage ]['mainEntity'] ) && ( '' === $node_url || $node_url === $url ) ) {
				$graph[ $webpage ] = self::add_type( $graph[ $webpage ], 'FAQPage' );
				$graph[ $webpage ]['mainEntity'] = $node['mainEntity'] ?? array();
				if ( $id ) $map[ $id ] = $graph[ $webpage ]['@id'];
				$log[] = 'Las preguntas de «' . $label . '» se integraron en el WebPage de la URL.';
				continue;
			}
			if ( $is( 'WebPage' ) && null !== $webpage && ( '' === $node_url || $node_url === $url ) ) {
				foreach ( $valid as $type ) $graph[ $webpage ] = self::add_type( $graph[ $webpage ], $type );
				$graph[ $webpage ] = self::fill( $graph[ $webpage ], $node );
				if ( $id ) $map[ $id ] = $graph[ $webpage ]['@id'];
				$log[] = 'Fusionado «' . $label . '» con el WebPage de la URL.';
				continue;
			}
			// Contenido: si ya existe una entidad del mismo tipo, se completan entre sí.
			$same = null;
			$main = false;
			foreach ( self::MAIN_TYPES as $type ) if ( $is( $type ) ) $main = true;
			if ( $main ) foreach ( $graph as $i => $own ) if ( array_intersect( $valid, Digitalisimo_Integrations_Schema_Rules::types( $own ) ) ) { $same = $i; break; }
			if ( null !== $same ) {
				// WooCommerce conoce ofertas, variantes e impuestos mejor que el nodo mínimo propio.
				if ( $is( 'Product' ) && ! empty( $node['offers'] ) ) $graph[ $same ]['offers'] = $node['offers'];
				$graph[ $same ] = self::fill( $graph[ $same ], $node );
				if ( $id ) $map[ $id ] = $graph[ $same ]['@id'];
				$log[] = 'Fusionado «' . $label . '» con la entidad del mismo tipo.';
				continue;
			}
			if ( '' === $id ) $node['@id'] = $page['url'] . '#' . strtolower( $valid[0] );
			if ( $main && null !== $webpage && empty( $graph[ $webpage ]['mainEntity'] ) ) {
				$graph[ $webpage ]['mainEntity'] = array( '@id' => $node['@id'] );
				if ( empty( $node['mainEntityOfPage'] ) ) $node['mainEntityOfPage'] = array( '@id' => $graph[ $webpage ]['@id'] );
			}
			$graph[] = $node;
			$log[] = 'Integrado «' . $label . '».';
		}
		if ( $map ) $graph = self::remap( $graph, $map );
		if ( null !== $visible ) list( $graph, $log ) = self::visible_faq( $graph, $visible, $log );
		return array( array_values( $graph ), $log );
	}

	/** Sólo quedan las preguntas que el visitante puede leer en la página. */
	private static function visible_faq( $graph, $visible, $log ) {
		foreach ( $graph as $i => $node ) {
			if ( ! in_array( 'FAQPage', Digitalisimo_Integrations_Schema_Rules::types( $node ), true ) ) continue;
			$questions = $node['mainEntity'] ?? array();
			$questions = Digitalisimo_Integrations_Schema_Rules::is_list( $questions ) ? $questions : array( $questions );
			$kept = array();
			foreach ( $questions as $q ) if ( is_array( $q ) && Digitalisimo_Integrations_Schema_Rules::visible( $q['name'] ?? '', $visible ) && Digitalisimo_Integrations_Schema_Rules::visible( $q['acceptedAnswer']['text'] ?? '', $visible ) ) $kept[] = $q;
			if ( count( $kept ) === count( $questions ) ) continue;
			$log[] = ( count( $questions ) - count( $kept ) ) . ' pregunta(s) de FAQPage retiradas: la pregunta o su respuesta no aparecen en el contenido visible.';
			if ( $kept ) { $graph[ $i ]['mainEntity'] = $kept; continue; }
			$types = array_values( array_diff( Digitalisimo_Integrations_Schema_Rules::types( $node ), array( 'FAQPage' ) ) );
			if ( ! $types ) { unset( $graph[ $i ] ); continue; }
			$graph[ $i ]['@type'] = 1 === count( $types ) ? $types[0] : $types;
			unset( $graph[ $i ]['mainEntity'] );
		}
		return array( array_values( $graph ), $log );
	}

	/** Un VideoObject sólo sale si el video realmente se renderizó en la página. */
	private static function visible_video( $graph, $html, $log, $page ) {
		$body = preg_match( '#<body\\b[^>]*>(.*)</body>#is', $html, $match ) ? $match[1] : $html;
		$sources = array();
		if ( preg_match_all( '#<(?:video|source)\\b[^>]*\\b(?:src|data-src)\\s*=\\s*(["\\\'])(.*?)\\1#is', $body, $matches ) ) foreach ( $matches[2] as $src ) $sources[] = html_entity_decode( $src, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$removed = array();
		foreach ( $graph as $i => $node ) {
			if ( ! Digitalisimo_Integrations_Schema_Vocabulary::is_a( Digitalisimo_Integrations_Schema_Rules::types( $node ), 'VideoObject' ) || ( $node['@id'] ?? '' ) !== $page['url'] . '#video' ) continue;
			if ( in_array( $node['contentUrl'] ?? '', $sources, true ) ) continue;
			$removed[] = $node['@id'] ?? '';
			unset( $graph[ $i ] );
			$log[] = 'Retirado VideoObject: el archivo no aparece en un elemento video del HTML final.';
		}
		if ( $removed ) foreach ( $graph as &$node ) if ( in_array( $node['video']['@id'] ?? '', $removed, true ) ) unset( $node['video'] );
		return array( array_values( $graph ), $log );
	}

	public static function script( $graph ) {
		return '<script type="application/ld+json" class="' . self::CLASS_NAME . '">' . self::json( array( '@context' => 'https://schema.org', '@graph' => array_values( $graph ) ) ) . '</script>';
	}

	public static function json( $data, $pretty = false ) {
		$json = (string) json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ( $pretty ? JSON_PRETTY_PRINT : 0 ) );
		// Un "</script>" dentro de un texto cerraría la etiqueta antes de tiempo.
		return str_replace( '</', '<\/', $json );
	}

	/**
	 * Reúne en el grafo propio todos los JSON-LD de la página. Los bloques que
	 * no se pueden leer quedan intactos; sin grafo propio no se toca nada.
	 * Devuelve array( html, registro ).
	 */
	public static function unify( $html, $page ) {
		$blocks = Digitalisimo_Integrations_Schema_Rules::blocks( $html );
		$own    = null;
		foreach ( $blocks as $i => $block ) if ( false !== strpos( $block['attrs'], self::CLASS_NAME ) && is_array( $block['data'] ) ) { $own = $i; break; }
		if ( null === $own ) return array( $html, array() );
		$foreign = array();
		$remove  = array();
		foreach ( $blocks as $i => $block ) {
			if ( $i === $own || ! is_array( $block['data'] ) ) continue;
			foreach ( Digitalisimo_Integrations_Schema_Rules::nodes( array( $block ) ) as $entry ) $foreign[] = $entry['node'];
			$remove[] = $block['tag'];
		}
		$graph = $blocks[ $own ]['data']['@graph'] ?? array();
		list( $graph, $log ) = self::merge( $graph, $foreign, $page, Digitalisimo_Integrations_Schema_Rules::visible_text( $html ) );
		list( $graph, $log ) = self::visible_video( $graph, $html, $log, $page );
		if ( ! $foreign && ! $log ) return array( $html, array() );
		$html = str_replace( $blocks[ $own ]['tag'], self::script( $graph ), $html );
		foreach ( array_unique( $remove ) as $tag ) $html = str_replace( $tag, '', $html );
		return array( $html, $log );
	}

	/** Filtra sólo las FAQ propias cuando la unificación de otros plugins está apagada. */
	public static function sanitize_own_faq( $html, $page ) {
		foreach ( Digitalisimo_Integrations_Schema_Rules::blocks( $html ) as $block ) {
			if ( false === strpos( $block['attrs'], self::CLASS_NAME ) || ! is_array( $block['data'] ) ) continue;
			$graph = $block['data']['@graph'] ?? array();
			list( $clean, $log ) = self::visible_faq( $graph, Digitalisimo_Integrations_Schema_Rules::visible_text( $html ), array() );
			list( $clean, $log ) = self::visible_video( $clean, $html, $log, $page );
			return $log ? str_replace( $block['tag'], self::script( $clean ), $html ) : $html;
		}
		return $html;
	}

	/* ------------------------------------------------------------------ *
	 * Datos del sitio y de la página (WordPress)
	 * ------------------------------------------------------------------ */

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_Settings::get( $key, $default ); }

	public static function site() {
		$name  = self::o( 'seo_site_name' ) ?: get_bloginfo( 'name' );
		$local = null;
		if ( self::o( 'seo_local_enabled' ) ) {
			$lat = trim( (string) self::o( 'seo_local_latitude' ) );
			$lng = trim( (string) self::o( 'seo_local_longitude' ) );
			$local = array(
				'type'      => Digitalisimo_Integrations_Schema_Vocabulary::local_type( self::o( 'seo_local_type' ) ),
				'name'      => self::o( 'seo_local_name' ) ?: ( self::o( 'seo_organization_name' ) ?: $name ),
				'telephone' => Digitalisimo_Integrations_Local_Business::phone(),
				'price'     => self::o( 'seo_local_price_range' ),
				'address'   => array( 'streetAddress' => self::o( 'seo_local_address' ), 'addressLocality' => self::o( 'seo_local_city' ), 'addressRegion' => self::o( 'seo_local_region' ), 'postalCode' => self::o( 'seo_local_postal' ), 'addressCountry' => self::o( 'seo_local_country' ) ),
				'geo'       => is_numeric( $lat ) && is_numeric( $lng ) ? array( 'latitude' => (float) $lat, 'longitude' => (float) $lng ) : array(),
				'hours'     => Digitalisimo_Integrations_Local_Business::schema_hours(),
			);
		}
		return array(
			'home'     => home_url( '/' ),
			'name'     => $name,
			'org_name' => self::o( 'seo_organization_name' ) ?: $name,
			'org_type' => self::o( 'seo_organization_type' ) ?: 'Organization',
			'org_url'  => self::o( 'seo_organization_url' ),
			'logo'     => self::o( 'seo_logo' ),
			'image'    => self::o( 'seo_default_image' ),
			'same_as'  => Digitalisimo_Integrations_Social_Profiles::all_urls(),
			'email'    => is_email( self::o( 'seo_organization_email' ) ) ? self::o( 'seo_organization_email' ) : '',
			'language' => get_bloginfo( 'language' ),
			'local'    => $local,
		);
	}

	/** Valores antiguos fueron guardados en minúsculas por sanitize_key(). */
	public static function chosen_type( $post_id ) {
		$raw = strtolower( (string) get_post_meta( $post_id, Digitalisimo_Integrations_SEO_Suite::P . 'schema_type', true ) );
		$types = array( 'article' => 'Article', 'webpage' => 'WebPage', 'faqpage' => 'FAQPage', 'service' => 'Service', 'product' => 'Product', 'localbusiness' => 'LocalBusiness', 'person' => 'Person' );
		return $types[ $raw ] ?? '';
	}

	/** Tipo de entidad del contenido según el editor y el tipo de publicación. */
	public static function entity_type( $post ) {
		$chosen = self::chosen_type( $post->ID );
		$woo    = 'product' === $post->post_type && function_exists( 'wc_get_product' );
		// El tipo real de WooCommerce prevalece sobre una selección antigua del editor.
		if ( 'product' === $post->post_type ) return $woo && self::o( 'seo_schema_unify', 1 ) ? 'Product' : '';
		if ( in_array( $chosen, array( 'Article', 'Service' ), true ) ) return $chosen;
		if ( '' === $chosen && 'post' === $post->post_type ) return 'BlogPosting';
		if ( '' === $chosen && preg_match( '/^(?:services?|servicios?)$/i', $post->post_type ) ) return 'Service';
		return '';
	}

	/** Encuentra un único video local relevante en bloques o en un widget Elementor. */
	private static function video_from_post( $post ) {
		$candidates = array();
		if ( function_exists( 'parse_blocks' ) ) {
			$walk = function( $blocks ) use ( &$walk, &$candidates ) {
				foreach ( (array) $blocks as $block ) {
					if ( 'core/video' === ( $block['blockName'] ?? '' ) ) {
						$a = $block['attrs'] ?? array();
						$candidates[] = array( 'id' => $a['id'] ?? 0, 'url' => $a['src'] ?? '', 'poster' => $a['poster'] ?? '' );
					}
					if ( ! empty( $block['innerBlocks'] ) ) $walk( $block['innerBlocks'] );
				}
			};
			$walk( parse_blocks( $post->post_content ) );
		}
		$elementor = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_string( $elementor ) ) $elementor = json_decode( $elementor, true );
		if ( is_array( $elementor ) ) {
			$walk = function( $elements ) use ( &$walk, &$candidates ) {
				foreach ( (array) $elements as $element ) {
					if ( ! is_array( $element ) ) continue;
					if ( 'video' === ( $element['widgetType'] ?? '' ) ) {
						$a = $element['settings'] ?? array();
						$hosted = isset( $a['hosted_url'] ) && is_array( $a['hosted_url'] ) ? $a['hosted_url'] : array();
						$overlay = isset( $a['image_overlay'] ) && is_array( $a['image_overlay'] ) ? $a['image_overlay'] : array();
						if ( 'hosted' === ( $a['video_type'] ?? '' ) && 'yes' === ( $a['show_image_overlay'] ?? '' ) ) $candidates[] = array( 'id' => $hosted['id'] ?? 0, 'url' => $hosted['url'] ?? '', 'poster' => $overlay['url'] ?? '' );
					}
					if ( ! empty( $element['elements'] ) ) $walk( $element['elements'] );
				}
			};
			$walk( $elementor );
		}
		if ( 1 !== count( $candidates ) ) return null;
		$v = $candidates[0];
		$id = absint( $v['id'] ?: ( $v['url'] && function_exists( 'attachment_url_to_postid' ) ? attachment_url_to_postid( $v['url'] ) : 0 ) );
		if ( ! $id || 0 !== strpos( (string) get_post_mime_type( $id ), 'video/' ) ) return null;
		$url = wp_get_attachment_url( $id );
		$poster = is_string( $v['poster'] ) ? esc_url_raw( $v['poster'] ) : '';
		$date = get_post_time( DATE_W3C, false, $id );
		$name = trim( wp_strip_all_tags( get_the_title( $id ) ) );
		if ( ! $url || ! $poster || ! $date || ! $name || ! preg_match( '#^https?://#i', $url ) || ! preg_match( '#^https?://#i', $poster ) ) return null;
		return array( 'name' => $name, 'thumbnailUrl' => $poster, 'uploadDate' => $date, 'contentUrl' => $url );
	}

	/** Contexto de un contenido concreto (vista previa del editor o auditoría). */
	public static function post_page( $post ) {
		$front   = (int) get_option( 'page_on_front' );
		$url     = get_post_meta( $post->ID, Digitalisimo_Integrations_SEO_Suite::P . 'canonical', true ) ?: get_permalink( $post );
		$excerpt = trim( wp_strip_all_tags( $post->post_excerpt ) ) ?: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '' );
		$title   = get_post_meta( $post->ID, Digitalisimo_Integrations_SEO_Suite::P . 'title', true ) ?: get_the_title( $post );
		$desc    = get_post_meta( $post->ID, Digitalisimo_Integrations_SEO_Suite::P . 'description', true ) ?: $excerpt;
		return self::singular_page( $post, $front && $front === (int) $post->ID, $url, $title, $desc );
	}

	private static function singular_page( $post, $home, $url, $title, $desc ) {
		$type   = $home ? '' : self::entity_type( $post );
		$author = get_userdata( $post->post_author );
		$image  = get_post_meta( $post->ID, Digitalisimo_Integrations_SEO_Suite::P . 'social_image', true ) ?: get_the_post_thumbnail_url( $post, 'full' );
		$chosen = self::chosen_type( $post->ID );
		$entity = $type ? array( 'type' => $type ) : null;
		if ( 'Service' === $type ) {
			// Una integración puede aportar una oferta real; nunca se infiere del texto.
			$offer = apply_filters( 'digitalisimo_seo_service_offer', array(), $post );
			if ( is_array( $offer ) && isset( $offer['price'], $offer['priceCurrency'] ) ) $entity['offers'] = $offer;
		}
		$profile = ! $home && 'Person' === $chosen && 'product' !== $post->post_type ? array( 'name' => wp_strip_all_tags( get_the_title( $post ) ), 'slug' => $post->post_name ?: (string) $post->ID, 'url' => $url ) : array();
		if ( 'Product' === $type && function_exists( 'wc_get_product' ) && ( $product = wc_get_product( $post->ID ) ) ) {
			$entity['sku'] = $product->get_sku();
			$price = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : $product->get_price();
			if ( ! $product->is_type( 'variable' ) && is_numeric( $price ) && function_exists( 'get_woocommerce_currency' ) ) {
				$entity['offers'] = array( 'price' => $price, 'priceCurrency' => get_woocommerce_currency(), 'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' );
			}
		}
		return array(
			'kind'           => $home ? 'home' : ( $profile ? 'profile' : 'singular' ),
			'home'           => $home,
			'url'            => $url,
			'title'          => wp_strip_all_tags( (string) $title ),
			'headline'       => wp_strip_all_tags( get_the_title( $post ) ),
			'description'    => wp_strip_all_tags( (string) $desc ),
			'published'      => get_the_date( DATE_W3C, $post ),
			'modified'       => get_the_modified_date( DATE_W3C, $post ),
			'image'          => $image ?: '',
			'breadcrumbs'    => $home || ! self::o( 'seo_breadcrumbs' ) ? array() : Digitalisimo_Integrations_SEO_Suite::breadcrumbs_for( $post ),
			'entity'         => $entity,
			'profile'        => $profile,
			'author'         => $author ? array( 'name' => $author->display_name, 'slug' => $author->user_nicename, 'url' => get_author_posts_url( $author->ID ), 'same_as' => $author->user_url ? array( $author->user_url ) : array() ) : array(),
			'about_identity' => 'LocalBusiness' === $chosen,
			'chosen'         => $chosen,
			'woo_product'    => 'product' === $post->post_type && function_exists( 'wc_get_product' ),
			'video'          => self::video_from_post( $post ),
			'spatial'        => Digitalisimo_Integrations_Editorial::location_schema( $post->ID ),
		);
	}

	/** Contexto de la petición actual. Null cuando la página no lleva schema. */
	public static function current_page() {
		if ( is_404() ) return null;
		$c    = Digitalisimo_Integrations_SEO_Suite::context();
		$url  = ! empty( $c['canonical'] ) && ! is_wp_error( $c['canonical'] ) ? $c['canonical'] : home_url( '/' );
		$home = is_front_page();
		if ( is_singular() && ( $post = get_queried_object() ) instanceof WP_Post ) return self::singular_page( $post, $home, $url, $c['title'] ?? '', $c['description'] ?? '' );
		$author_archive = ! $home && is_author() && ! self::o( 'noindex_authors' );
		$kind = $home ? 'home' : ( $author_archive ? 'author' : ( is_search() ? 'search' : ( is_tax() || is_category() || is_tag() ? 'term' : 'archive' ) ) );
		$profile = array();
		if ( $author_archive && ( $user = get_queried_object() ) instanceof WP_User && $user->display_name ) { $url = get_author_posts_url( $user->ID ); $profile = array( 'name' => $user->display_name, 'slug' => $user->user_nicename, 'url' => $url, 'same_as' => $user->user_url ? array( $user->user_url ) : array() ); }
		$crumbs = array();
		if ( 'term' === $kind && self::o( 'seo_breadcrumbs' ) && ( $term = get_queried_object() ) ) $crumbs = array( array( 'name' => 'Inicio', 'url' => home_url( '/' ) ), array( 'name' => $term->name, 'url' => $url ) );
		return array( 'kind' => $kind, 'home' => $home, 'url' => $home ? home_url( '/' ) : $url, 'title' => wp_strip_all_tags( (string) ( $c['title'] ?? '' ) ), 'description' => wp_strip_all_tags( (string) ( $c['description'] ?? '' ) ), 'breadcrumbs' => $crumbs, 'entity' => null, 'profile' => $profile );
	}

	/** Nodos del JSON-LD adicional escrito en el editor, si su tipo es oficial. */
	public static function custom_nodes( $post_id ) {
		$custom = json_decode( (string) get_post_meta( $post_id, Digitalisimo_Integrations_SEO_Suite::P . 'schema_custom', true ), true );
		if ( ! is_array( $custom ) ) return array();
		$block = array( array( 'data' => $custom ) );
		return array_column( Digitalisimo_Integrations_Schema_Rules::nodes( $block ), 'node' );
	}

	/** Grafo propio de una página, con el JSON-LD adicional del editor integrado. */
	public static function graph_for( $site, $page, $post_id = 0, $visible = null ) {
		$graph = self::build( $site, $page );
		$log   = array();
		if ( $post_id ) list( $graph, $log ) = self::merge( $graph, self::custom_nodes( $post_id ), $page, $visible );
		return array( $graph, $log );
	}

	/** Qué debería tener la URL: lo usa el auditor para señalar faltantes. */
	public static function expected( $site, $page ) {
		$local = self::local_ready( $site['local'] ?? null );
		$type  = 'Person' === ( $site['org_type'] ?? '' ) ? 'Person' : 'Organization';
		$out   = array(
			array( 'type' => $type, 'why' => 'Entidad base del sitio.', 'base' => true ),
			array( 'type' => 'WebSite', 'why' => 'Entidad base del sitio.', 'base' => true ),
			array( 'type' => 'WebPage', 'why' => 'Cada URL se declara como página.', 'base' => true ),
		);
		if ( $local ) $out[] = array( 'type' => 'LocalBusiness', 'why' => 'SEO local está activo con dirección y contacto.' );
		if ( in_array( $page['kind'], array( 'author', 'profile' ), true ) && ! empty( $page['profile']['name'] ) ) {
			$out[] = array( 'type' => 'ProfilePage', 'why' => 'Página pública dedicada a una persona real.' );
			$out[] = array( 'type' => 'Person', 'why' => 'Persona descrita por el perfil.' );
		}
		if ( ! empty( $page['video'] ) ) $out[] = array( 'type' => 'VideoObject', 'why' => 'Video local único con miniatura y fecha reales.' );
		if ( 'home' !== $page['kind'] && count( (array) ( $page['breadcrumbs'] ?? array() ) ) >= 2 ) $out[] = array( 'type' => 'BreadcrumbList', 'why' => 'Página interna con ruta de navegación.' );
		if ( ! empty( $page['entity']['type'] ) ) $out[] = array( 'type' => $page['entity']['type'], 'why' => 'Tipo de contenido de esta URL.' );
		if ( ! empty( $page['woo_product'] ) ) $out[] = array( 'type' => 'Product', 'why' => 'Producto de WooCommerce.' );
		if ( 'FAQPage' === ( $page['chosen'] ?? '' ) ) $out[] = array( 'type' => 'FAQPage', 'why' => 'Elegido en el editor: requiere preguntas visibles en la página.' );
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Salida
	 * ------------------------------------------------------------------ */

	/** Imprime el grafo propio en <head>. */
	public static function head() {
		$page = self::current_page();
		if ( ! $page ) return;
		$post_id = is_singular() ? get_queried_object_id() : 0;
		list( $graph ) = self::graph_for( self::site(), $page, $post_id );
		echo self::script( $graph ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON codificado.
	}

	public static function raw_requested() {
		$token = isset( $_GET[ self::RAW_QUERY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::RAW_QUERY ] ) ) : '';
		return '' !== $token && hash_equals( (string) get_transient( self::RAW_TOKEN ), $token );
	}

	public static function start_buffer() {
		if ( is_admin() || is_feed() || is_robots() || wp_doing_ajax() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) return;
		if ( ! self::o( 'enable_seo' ) || ! self::o( 'seo_schema_enabled' ) ) return;
		if ( self::raw_requested() ) { nocache_headers(); return; }
		$page = self::current_page();
		if ( ! $page ) return;
		$unify = (bool) self::o( 'seo_schema_unify', 1 );
		ob_start( function( $html ) use ( $page, $unify ) {
			if ( false === stripos( $html, 'application/ld+json' ) ) return $html;
			if ( $unify ) list( $html ) = self::unify( $html, $page );
			else $html = self::sanitize_own_faq( $html, $page );
			return $html;
		} );
	}
}
