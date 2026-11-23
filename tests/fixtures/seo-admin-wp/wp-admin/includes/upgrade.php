<?php
// dbDelta() para tests/lib/seo-admin-wp.php: las tablas propias de SEO se «crean» sin base de datos. Con PHP 7.4 un
// require_once a un archivo que falta es un error fatal que no se puede atrapar.
if ( ! function_exists( 'dbDelta' ) ) { function dbDelta( $queries = '', $execute = true ) { return array(); } }
