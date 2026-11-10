<?php
/** WP-CLI mínimo: registra comandos, guarda la salida y termina con WP_CLI::error() como `exit 1`. */
namespace {
	define( 'WP_CLI', true );
	class Digi_Cli_Exit extends Exception {}
	class WP_CLI {
		public static $commands = array();
		public static $output = array();
		public static function add_command( $name, $callable ) { self::$commands[ $name ] = $callable; return true; }
		public static function success( $message ) { self::$output[] = 'Success: ' . $message; }
		public static function warning( $message ) { self::$output[] = 'Warning: ' . $message; }
		public static function line( $message = '' ) { self::$output[] = (string) $message; }
		public static function log( $message ) { self::$output[] = (string) $message; }
		public static function error( $message ) { self::$output[] = 'Error: ' . $message; throw new Digi_Cli_Exit( (string) $message ); }
		/** Como WP-CLI: con --yes sigue; sin él, en una prueba (sin terminal) se responde «no» y el comando termina. */
		public static function confirm( $question, $assoc_args = array() ) { if ( ! empty( $assoc_args['yes'] ) ) return; self::$output[] = $question . ' [y/n] n'; throw new Digi_Cli_Exit( 'No confirmado.' ); }
	}
	/**
	 * Ejecuta `wp <comando> <subcomando> [args] [--assoc]` sobre las clases registradas y devuelve array( código, salida ).
	 */
	function digi_wp( $command, $subcommand, array $args = array(), array $assoc = array() ) {
		WP_CLI::$output = array();
		$class = WP_CLI::$commands[ $command ] ?? null;
		if ( ! $class ) throw new RuntimeException( 'FALLA: no se registró el comando ' . $command );
		$method = 'list' === $subcommand ? 'list_' : $subcommand;
		$reflection = new ReflectionMethod( $class, $method );
		if ( 'list' === $subcommand && false === strpos( (string) $reflection->getDocComment(), '@subcommand list' ) ) throw new RuntimeException( 'FALLA: list_ no se anuncia como «list».' );
		try {
			$reflection->invoke( new $class(), $args, $assoc );
			return array( 0, WP_CLI::$output );
		} catch ( Digi_Cli_Exit $exit ) {
			return array( 1, WP_CLI::$output );
		}
	}
}
namespace cli {
	/** php-cli-tools: oculta y vuelve a mostrar el eco de la terminal (`stty -echo`); aquí sólo anota cada llamada. */
	class Shell {
		public static $calls = array();
		public static function hide( $hidden = true ) { self::$calls[] = (bool) $hidden; }
	}
}
namespace WP_CLI\Utils {
	function format_items( $format, $items, $fields ) {
		if ( is_string( $fields ) ) $fields = explode( ',', $fields );
		$rows = array();
		foreach ( $items as $item ) $rows[] = array_intersect_key( (array) $item, array_flip( $fields ) );
		\WP_CLI::$output[] = 'json' === $format ? json_encode( $rows ) : $format . ':' . json_encode( $rows, JSON_UNESCAPED_UNICODE );
	}
}
