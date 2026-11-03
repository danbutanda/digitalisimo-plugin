<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Widget «Plantilla» de la barra lateral de Element Pack (`elementortemplate_widget`) → widget Biblioteca del
 * constructor (`elementor-library`), con las mismas claves (`title`, `template_id`), §3.D.9 y V.11 (C2-23).
 *
 * La sustitución se hace en `deactivate`, en la misma petición e inmediatamente antes de desactivar Element Pack, y
 * sólo con Element Pack cargado (`before_deactivate()`): mientras lo está, los dos widgets pintan la misma plantilla,
 * así que ninguna petición se queda sin ella. Con Element Pack inactivo al empezar no se sustituye (no se veía) y se
 * informa. El respaldo `digitalisimo_migration_backup_sidebars` se escribe antes de cambiar nada; las instancias
 * nuevas llevan números libres y las originales (`widget_elementortemplate_widget`) no se tocan.
 */
final class Sidebar {
	const OLD    = 'elementortemplate_widget';
	const NEW    = 'elementor-library';
	const BACKUP = 'digitalisimo_migration_backup_sidebars';
	const KIND   = 'sidebar';

	/** Instancias del widget de origen colocadas en alguna barra (también las inactivas): `id => [title, template_id]`. */
	public static function placed(): array {
		$sidebars  = get_option( 'sidebars_widgets', array() );
		$instances = get_option( 'widget_' . self::OLD, array() );
		$out       = array();
		foreach ( is_array( $sidebars ) ? $sidebars : array() as $sidebar => $widgets ) {
			if ( 'array_version' === $sidebar || ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $widget ) {
				if ( preg_match( '/^' . self::OLD . '-(\d+)$/', (string) $widget, $m ) && is_array( $instances[ (int) $m[1] ] ?? null ) ) {
					$out[ (string) $widget ] = $instances[ (int) $m[1] ];
				}
			}
		}
		return $out;
	}

	public static function analyze(): array {
		$count = count( self::placed() );
		if ( ! $count ) {
			return array();
		}
		return array( array( Inventory::ep_was_active() ? 'plat.sidebar' : 'plat.sidebar.inactive', array( 'count' => $count ) ) );
	}

	/** Etapa `platform_prepare`: sólo el respaldo (antes de que nada cambie). */
	public static function prepare( array &$cursor, float $deadline ): bool {
		if ( self::placed() ) {
			self::backup();
		}
		return true;
	}

	/** Respaldo de las barras y de las instancias del destino, sólo la primera vez. */
	private static function backup(): void {
		if ( false !== get_option( self::BACKUP, false ) ) {
			return;
		}
		$new = get_option( 'widget_' . self::NEW, null );
		update_option(
			self::BACKUP,
			array(
				'time'     => time(),
				'sidebars' => get_option( 'sidebars_widgets', array() ),
				'new'      => is_array( $new ) ? $new : null,
				'map'      => array(),
			),
			false
		);
	}

	/**
	 * En `deactivate`, antes de desactivar Element Pack: sustituye cada instancia colocada por una del widget
	 * Biblioteca con la misma plantilla. Devuelve los códigos del informe (`[ [code, detail], … ]`).
	 */
	public static function before_deactivate(): array {
		$placed = self::placed();
		if ( ! $placed ) {
			return array();
		}
		if ( ! Inventory::ep_loaded() ) {
			$pair = array( 'plat.sidebar.inactive', array( 'count' => count( $placed ) ) );
			Inventory::site_code( $pair[0], $pair[1] );
			return array( $pair );
		}
		self::backup();
		$backup   = get_option( self::BACKUP, array() );
		$map      = is_array( $backup['map'] ?? null ) ? $backup['map'] : array();
		$new      = get_option( 'widget_' . self::NEW, array() );
		$new      = is_array( $new ) ? $new : array();
		$sidebars = get_option( 'sidebars_widgets', array() );
		$next     = 2;
		foreach ( array_keys( $new ) as $number ) {
			$next = is_int( $number ) ? max( $next, $number + 1 ) : $next;
		}
		$written = array();
		foreach ( $placed as $old_id => $instance ) {
			if ( isset( $map[ $old_id ] ) ) {
				continue;
			}
			$new[ $next ]       = array(
				'title'       => (string) ( $instance['title'] ?? '' ),
				'template_id' => (string) ( $instance['template_id'] ?? '' ),
			);
			$map[ $old_id ]     = self::NEW . '-' . $next;
			$written[ $next ]   = $new[ $next ];
			++$next;
		}
		foreach ( $sidebars as $sidebar => $widgets ) {
			if ( 'array_version' === $sidebar || ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $index => $widget ) {
				if ( isset( $map[ (string) $widget ] ) ) {
					$sidebars[ $sidebar ][ $index ] = $map[ (string) $widget ];
				}
			}
		}
		$new['_multiwidget'] = 1;
		$backup['map']       = $map;
		update_option( self::BACKUP, $backup, false );
		// Primero las instancias y después las barras: ninguna barra nombra una instancia que no existe.
		update_option( 'widget_' . self::NEW, $new );
		update_option( 'sidebars_widgets', $sidebars );
		$previous = Inventory::reg_get( self::KIND, 'sidebars' );
		Inventory::reg_put(
			self::KIND,
			'sidebars',
			array(
				'hash'  => md5( (string) wp_json_encode( $map ) ),
				'state' => 'done',
				'extra' => array( 'written' => (array) ( $previous['extra']['written'] ?? array() ) + $written ),
			)
		);
		$pair = array( 'plat.sidebar', array( 'count' => count( $map ) ) );
		Inventory::site_code( $pair[0], $pair[1] );
		return array( $pair );
	}

	public static function finish( array &$cursor, float $deadline ): bool {
		return true;
	}

	/**
	 * `--revert`: cada instancia nueva vuelve a ser la original si sigue en su sitio y la original no se ha vuelto a
	 * colocar; las instancias nuevas que nadie cambió se retiran. Lo demás se deja como está.
	 */
	public static function revert( bool $force = false ): array {
		$backup = get_option( self::BACKUP, false );
		if ( ! is_array( $backup ) ) {
			Inventory::reg_forget( self::KIND, 'sidebars' );
			return array();
		}
		$map      = is_array( $backup['map'] ?? null ) ? $backup['map'] : array();
		$row      = Inventory::reg_get( self::KIND, 'sidebars' );
		$written  = (array) ( $row['extra']['written'] ?? array() );
		$sidebars = get_option( 'sidebars_widgets', array() );
		$present  = array();
		foreach ( is_array( $sidebars ) ? $sidebars : array() as $sidebar => $widgets ) {
			if ( 'array_version' !== $sidebar && is_array( $widgets ) ) {
				$present = array_merge( $present, array_map( 'strval', $widgets ) );
			}
		}
		$restored = 0;
		$back     = array_flip( $map );
		foreach ( $sidebars as $sidebar => $widgets ) {
			if ( 'array_version' === $sidebar || ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $index => $widget ) {
				$old = $back[ (string) $widget ] ?? null;
				if ( null !== $old && ! in_array( $old, $present, true ) ) {
					$sidebars[ $sidebar ][ $index ] = $old;
					$present[]                      = $old;
					++$restored;
				}
			}
		}
		$new = get_option( 'widget_' . self::NEW, array() );
		$new = is_array( $new ) ? $new : array();
		$in  = array();
		foreach ( $sidebars as $sidebar => $widgets ) {
			if ( 'array_version' !== $sidebar && is_array( $widgets ) ) {
				$in = array_merge( $in, array_map( 'strval', $widgets ) );
			}
		}
		foreach ( $written as $number => $instance ) {
			if ( isset( $new[ $number ] ) && $new[ $number ] == $instance && ! in_array( self::NEW . '-' . $number, $in, true ) ) { // phpcs:ignore Universal.Operators.StrictComparisons -- mismo contenido, aunque cambie el orden de las claves.
				unset( $new[ $number ] );
			}
		}
		if ( $restored ) {
			update_option( 'sidebars_widgets', $sidebars );
		}
		update_option( 'widget_' . self::NEW, $new );
		delete_option( self::BACKUP );
		Inventory::reg_forget( self::KIND, 'sidebars' );
		return array( array( 'plat.sidebar.reverted', array( 'count' => $restored ) ) );
	}
}
