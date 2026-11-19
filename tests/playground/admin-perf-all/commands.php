<?php
/**
 * Órdenes de admin-perf-all.sh (?digi_ap=<orden>, sólo administradores). Devuelven JSON.
 *  - state: opciones con carga automática y su tamaño, opciones y sitemeta de los plugins medidos y eventos de WP-Cron.
 *  - expire: borra las cachés de las Releases y del índice compartido (lo que pasa a los 10 minutos).
 *  - cron_due: adelanta a ahora los eventos periódicos de los plugins medidos, salvo el respaldo programado (para medir
 *    una vuelta de WP-Cron de mantenimiento: actualizadores y vigilante).
 *  - posts: ids de una entrada, una página y un adjunto para las pantallas de edición.
 */
defined( 'ABSPATH' ) || exit;

function digi_ap_ours( $name ) {
	return (bool) preg_match( '/digitalisimo|^digi_|_digi_|elementor|pro_elements|^_elementor/i', (string) $name );
}

function digi_ap_command( $cmd ) {
	global $wpdb;
	switch ( $cmd ) {
		case 'state':
			$autoload = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes' );
			$in = "'" . implode( "','", array_map( 'esc_sql', $autoload ) ) . "'";
			$rows = $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS size, autoload FROM {$wpdb->options}", ARRAY_A );
			$options = array();
			$total = 0;
			$count = 0;
			foreach ( $rows as $row ) {
				$auto = in_array( $row['autoload'], $autoload, true );
				if ( $auto ) { $total += (int) $row['size']; $count++; }
				$options[] = array( 'name' => $row['option_name'], 'size' => (int) $row['size'], 'autoload' => $row['autoload'], 'auto' => $auto, 'ours' => digi_ap_ours( $row['option_name'] ) );
			}
			$sitemeta = array();
			if ( is_multisite() ) {
				foreach ( $wpdb->get_results( "SELECT meta_key, LENGTH(meta_value) AS size FROM {$wpdb->sitemeta}", ARRAY_A ) as $row ) $sitemeta[] = array( 'name' => $row['meta_key'], 'size' => (int) $row['size'], 'ours' => digi_ap_ours( $row['meta_key'] ) );
			}
			$cron = array();
			$schedules = wp_get_schedules();
			foreach ( (array) _get_cron_array() as $time => $hooks ) {
				foreach ( (array) $hooks as $hook => $events ) {
					foreach ( (array) $events as $event ) {
						$cron[] = array( 'hook' => $hook, 'next' => (int) $time - time(), 'schedule' => $event['schedule'] ? $event['schedule'] : 'single', 'interval' => isset( $event['interval'] ) ? (int) $event['interval'] : ( isset( $schedules[ $event['schedule'] ]['interval'] ) ? (int) $schedules[ $event['schedule'] ]['interval'] : 0 ), 'args' => $event['args'], 'ours' => digi_ap_ours( $hook ) );
					}
				}
			}
			return array( 'blog' => get_current_blog_id(), 'autoload_total' => $total, 'autoload_count' => $count, 'options' => $options, 'sitemeta' => $sitemeta, 'cron' => $cron, 'php' => PHP_VERSION, 'wp' => get_bloginfo( 'version' ) );
		case 'expire':
			$deleted = 0;
			foreach ( array( $wpdb->options => 'option_name' ) + ( is_multisite() ? array( $wpdb->sitemeta => 'meta_key' ) : array() ) as $table => $column ) {
				$names = $wpdb->get_col( "SELECT {$column} FROM {$table} WHERE {$column} LIKE '%transient%digitalisimo%'" );
				foreach ( $names as $name ) {
					$wpdb->delete( $table, array( $column => $name ) );
					$deleted++;
				}
			}
			wp_cache_flush();
			return array( 'deleted' => $deleted );
		case 'cron_due':
			$crons = _get_cron_array();
			$moved = array();
			foreach ( (array) $crons as $time => $hooks ) {
				foreach ( (array) $hooks as $hook => $events ) {
					// El respaldo programado (la política mensual) no es una tarea del escritorio: su costo es el del respaldo.
					if ( ! digi_ap_ours( $hook ) || 'digitalisimo_backups_policy' === $hook ) continue;
					foreach ( (array) $events as $key => $event ) {
						if ( empty( $event['schedule'] ) ) continue;
						unset( $crons[ $time ][ $hook ][ $key ] );
						$crons[ time() - 5 ][ $hook ][ $key ] = $event;
						$moved[] = $hook;
					}
					if ( empty( $crons[ $time ][ $hook ] ) ) unset( $crons[ $time ][ $hook ] );
				}
				if ( empty( $crons[ $time ] ) ) unset( $crons[ $time ] );
			}
			ksort( $crons );
			_set_cron_array( $crons );
			delete_transient( 'doing_cron' );
			return array( 'moved' => $moved );
		case 'posts':
			$post = get_posts( array( 'post_type' => 'post', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) );
			$page = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) );
			$media = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
			return array( 'post' => (int) ( $post[0] ?? 0 ), 'page' => (int) ( $page[0] ?? 0 ), 'attachment' => (int) ( $media[0] ?? 0 ), 'counts' => array( 'post' => (int) wp_count_posts( 'post' )->publish, 'page' => (int) wp_count_posts( 'page' )->publish, 'attachment' => (int) array_sum( (array) wp_count_attachments() ), 'users' => (int) count_users()['total_users'] ) );
	}
	return array( 'error' => 'orden desconocida' );
}
