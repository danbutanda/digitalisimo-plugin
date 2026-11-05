<?php
namespace Digitalisimo\Elements\Import\Kits\Export;

use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Elementor\App\Modules\ImportExportCustomization\Runners\Export\Wp_Content as Core_Wp_Content;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `wp-content` de exportación de K (§8.2 y §8.3). Sustituye al del núcleo por su nombre.
 *
 * Exporta lo mismo que el núcleo, sin su tope de 20 entradas por tipo (CC-13), y deja el WXR sin datos personales
 * ni secretos (CR-14, §4.1.11):
 * - autores: `author_login` → `autor-<n>` (y su `dc:creator`), nombre visible «Autor <n>», correo y nombres vacíos;
 * - sin comentarios ni sus metas;
 * - sin la lista negra de metas (§8.2), también como segunda pasada sobre el XML;
 * - sólo lo publicado y los adjuntos: el núcleo exporta los borradores, privados y papelera de los tipos propios;
 *   aquí se quitan y se cuentan en `not_exported`;
 * - sin entradas protegidas con contraseña: la contraseña no viaja y la copia importada sería pública (A14).
 *
 * Las variaciones de producto (`product_variation`) no son públicas y no se exportan: se cuentan (B21).
 */
final class Wp_Content extends Core_Wp_Content {
	const QUERY_FILTER = 'elementor/import-export-customization/export/wp-content/query-args/customization';
	const TYPES_FILTER = 'elementor/import-export-customization/wp-content/post-types/customization';
	const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

	/** Estados que viajan: lo publicado y los adjuntos. */
	const KEEP_STATUSES = array( 'publish', 'inherit' );

	/** @var Export_Context|null */
	private $kit_context;

	public function __construct( ?Export_Context $context = null ) {
		$this->kit_context = $context;
	}

	public function export( array $data ) {
		$this->kit_context = Documents::context( $this->kit_context );
		$limit = array( __CLASS__, 'unlimited' );
		$skip  = array( Meta_Blacklist::class, 'skip' );
		add_filter( self::QUERY_FILTER, $limit, 20 );
		add_filter( 'wxr_export_skip_postmeta', $skip, 10, 2 );
		add_filter( 'wxr_export_skip_termmeta', $skip, 10, 2 );
		add_filter( 'wxr_export_skip_commentmeta', '__return_true', 10 );
		try {
			$result = parent::export( $data );
		} finally {
			remove_filter( self::QUERY_FILTER, $limit, 20 );
			remove_filter( 'wxr_export_skip_postmeta', $skip, 10 );
			remove_filter( 'wxr_export_skip_termmeta', $skip, 10 );
			remove_filter( 'wxr_export_skip_commentmeta', '__return_true', 10 );
		}
		return $this->clean( is_array( $result ) ? $result : array() );
	}

	/** @internal Sin el tope de 20 entradas por tipo (CC-13; `IEC:runners/export/wp-content.php:92`). */
	public static function unlimited( $args ) {
		if ( is_array( $args ) ) {
			$args['limit'] = -1;
		}
		return $args;
	}

	/** Anonimiza cada WXR del resultado y quita del manifiesto lo que no viaja. */
	private function clean( array $result ): array {
		$files = isset( $result['files'] ) && is_array( $result['files'] ) ? $result['files'] : array();
		if ( isset( $files['path'] ) ) {
			$files = array( $files );
		}
		$removed = array();
		$stats   = array();
		foreach ( $files as $index => $file ) {
			if ( ! is_array( $file ) || ! isset( $file['path'], $file['data'] ) || ! is_string( $file['data'] ) || '.xml' !== substr( (string) $file['path'], -4 ) ) {
				continue;
			}
			$clean = self::anonymize( $file['data'], $stats );
			if ( null === $clean ) {
				unset( $files[ $index ] );
				if ( $this->kit_context ) {
					$this->kit_context->report( 'wxr.invalid', array( 'path' => (string) $file['path'] ), Part_Context::ERROR );
				}
				continue;
			}
			$files[ $index ]['data'] = $clean;
		}
		$result['files'] = array_values( $files );
		$removed         = isset( $stats['removed'] ) ? $stats['removed'] : array();
		$kept            = isset( $stats['kept'] ) ? $stats['kept'] : array();

		foreach ( isset( $result['manifest'] ) && is_array( $result['manifest'] ) ? $result['manifest'] : array() as $position => $entry ) {
			if ( ! isset( $entry['wp-content'] ) || ! is_array( $entry['wp-content'] ) ) {
				continue;
			}
			foreach ( $entry['wp-content'] as $type => $posts ) {
				if ( is_array( $posts ) ) {
					$result['manifest'][ $position ]['wp-content'][ $type ] = array_diff_key( $posts, $removed );
				}
			}
		}

		if ( $this->kit_context ) {
			foreach ( $kept as $kind => $ids ) {
				$this->kit_context->add_exported( $kind, $ids );
			}
			foreach ( isset( $stats['reasons'] ) ? $stats['reasons'] : array() as $reason => $count ) {
				$this->kit_context->not_exported( $reason, $count );
			}
			if ( isset( $stats['comments'] ) && $stats['comments'] > 0 ) {
				$this->kit_context->not_exported( 'comments', $stats['comments'] );
			}
			if ( function_exists( 'post_type_exists' ) && post_type_exists( 'product_variation' ) ) {
				$variations = Documents::count( array( 'product_variation' ), array( 'publish', 'private' ) );
				if ( $variations > 0 ) {
					$this->kit_context->not_exported( 'variations', $variations );
				}
			}
		}
		return $result;
	}

	/**
	 * WXR sin datos personales, sin comentarios, sin la lista negra y sólo con lo que viaja; null si no es XML válido.
	 *
	 * @param string $xml   WXR del núcleo.
	 * @param array  $stats Acumula `removed` (ID => true), `kept` (`post` o `attachment` => IDs), `reasons` (motivo => n)
	 *                      y `comments`.
	 */
	public static function anonymize( string $xml, array &$stats = array() ): ?string {
		if ( '' === trim( $xml ) || ! class_exists( '\DOMDocument' ) || false !== stripos( $xml, '<!DOCTYPE' ) || false !== stripos( $xml, '<!ENTITY' ) ) {
			return null;
		}
		$stats += array(
			'removed'  => array(),
			'kept'     => array(),
			'reasons'  => array(),
			'comments' => 0,
			'authors'  => array(),
		);
		$doc      = new \DOMDocument();
		$internal = libxml_use_internal_errors( true );
		$loaded   = $doc->loadXML( $xml, LIBXML_NONET | LIBXML_PARSEHUGE );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );
		if ( ! $loaded || ! $doc->documentElement ) {
			return null;
		}
		$wp = $doc->documentElement->lookupNamespaceURI( 'wp' );
		if ( ! $wp ) {
			return null;
		}

		// Autores: `autor-<n>` en el orden del archivo, sin correo ni nombres.
		$logins = array();
		foreach ( self::children_list( $doc->getElementsByTagNameNS( $wp, 'author' ) ) as $author ) {
			$login = self::child( $author, $wp, 'author_login' );
			$old   = $login ? trim( $login->textContent ) : '';
			if ( ! isset( $logins[ $old ] ) ) {
				$logins[ $old ] = count( $logins ) + 1;
			}
			$n = $logins[ $old ];
			self::set( $author, $wp, 'author_login', 'autor-' . $n );
			self::set( $author, $wp, 'author_email', '' );
			self::set( $author, $wp, 'author_display_name', 'Autor ' . $n );
			self::set( $author, $wp, 'author_first_name', '' );
			self::set( $author, $wp, 'author_last_name', '' );
		}

		foreach ( self::children_list( $doc->getElementsByTagName( 'item' ) ) as $item ) {
			$id       = self::text( $item, $wp, 'post_id' );
			$status   = self::text( $item, $wp, 'status' );
			$type     = self::text( $item, $wp, 'post_type' );
			$password = self::text( $item, $wp, 'post_password' );
			$reason   = '';
			if ( '' !== $password ) {
				$reason = 'protected';
			} elseif ( ! in_array( $status, self::KEEP_STATUSES, true ) ) {
				$reason = 'private' === $status ? 'private' : ( 'trash' === $status ? 'trash' : 'drafts' );
			}
			if ( '' !== $reason ) {
				$stats['reasons'][ $reason ] = ( isset( $stats['reasons'][ $reason ] ) ? $stats['reasons'][ $reason ] : 0 ) + 1;
				if ( ctype_digit( $id ) ) {
					$stats['removed'][ (int) $id ] = true;
				}
				$item->parentNode->removeChild( $item );
				continue;
			}
			foreach ( self::children_list( $item->getElementsByTagNameNS( self::DC_NAMESPACE, 'creator' ) ) as $creator ) {
				$old = trim( $creator->textContent );
				self::replace_text( $creator, 'autor-' . ( isset( $logins[ $old ] ) ? $logins[ $old ] : 0 ) );
			}
			// El exportador del núcleo escribe el login del autor como `<guid>` (CR-14): pasa a `autor-<n>` también.
			foreach ( self::children_list( $item->getElementsByTagName( 'guid' ) ) as $guid ) {
				$old = trim( $guid->textContent );
				if ( isset( $logins[ $old ] ) ) {
					self::replace_text( $guid, 'autor-' . $logins[ $old ] );
				}
			}
			self::set( $item, $wp, 'post_password', '' );
			foreach ( self::children_list( $item->getElementsByTagNameNS( $wp, 'comment' ) ) as $comment ) {
				$comment->parentNode->removeChild( $comment );
				++$stats['comments'];
			}
			foreach ( self::children_list( $item->getElementsByTagNameNS( $wp, 'postmeta' ) ) as $meta ) {
				if ( Meta_Blacklist::blocks( self::text( $meta, $wp, 'meta_key' ) ) ) {
					$meta->parentNode->removeChild( $meta );
				}
			}
			if ( ctype_digit( $id ) ) {
				$kind                     = 'attachment' === $type ? 'attachment' : 'post';
				$stats['kept'][ $kind ][] = (int) $id;
			}
		}

		foreach ( self::children_list( $doc->getElementsByTagNameNS( $wp, 'termmeta' ) ) as $meta ) {
			if ( Meta_Blacklist::blocks( self::text( $meta, $wp, 'meta_key' ) ) ) {
				$meta->parentNode->removeChild( $meta );
			}
		}
		$stats['authors'] = count( $logins );
		$out              = $doc->saveXML();
		return is_string( $out ) ? $out : null;
	}

	/** Copia de una lista de nodos (quitar mientras se recorre una `DOMNodeList` viva salta nodos). */
	private static function children_list( \DOMNodeList $list ): array {
		$nodes = array();
		foreach ( $list as $node ) {
			$nodes[] = $node;
		}
		return $nodes;
	}

	/** Hijo directo con ese nombre en el espacio de nombres `wp`, o null. */
	private static function child( \DOMElement $parent, string $ns, string $name ): ?\DOMElement {
		foreach ( $parent->childNodes as $node ) {
			if ( $node instanceof \DOMElement && $ns === $node->namespaceURI && $name === $node->localName ) {
				return $node;
			}
		}
		return null;
	}

	private static function text( \DOMElement $parent, string $ns, string $name ): string {
		$node = self::child( $parent, $ns, $name );
		return $node ? trim( $node->textContent ) : '';
	}

	/** Cambia el texto de un hijo directo, si existe. */
	private static function set( \DOMElement $parent, string $ns, string $name, string $value ): void {
		$node = self::child( $parent, $ns, $name );
		if ( $node ) {
			self::replace_text( $node, $value );
		}
	}

	/** Sustituye el contenido de un nodo por un CDATA (como lo escribe el exportador). */
	private static function replace_text( \DOMElement $node, string $value ): void {
		while ( $node->firstChild ) {
			$node->removeChild( $node->firstChild );
		}
		if ( '' !== $value ) {
			$node->appendChild( $node->ownerDocument->createCDATASection( $value ) );
		}
	}
}
