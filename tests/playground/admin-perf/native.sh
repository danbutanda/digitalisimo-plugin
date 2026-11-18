#!/usr/bin/env bash
# Modo nativo de admin-perf.sh: el mismo sitio con PHP nativo (php$PHP), OPcache y SQLite, servido con `php -S`, para
# medir con el código PHP ya compilado como en un servidor real (Playground no tiene OPcache y su tiempo absoluto
# incluye compilar cada archivo en cada petición). Necesita la copia de WordPress y la integración de SQLite que trae
# Playground (~/.wordpress-playground/*.zip y @wp-playground/cli/sqlite-database-integration.zip); sin red ni idioma
# (inglés). Lo carga admin-perf.sh con `.`; deja el sitio en $WP y el servidor en $SERVER.
WPZIP="${WPZIP:-$(ls -t "$HOME"/.wordpress-playground/*.zip 2>/dev/null | head -1)}"
SQLZIP="${SQLZIP:-$(ls -t "$HOME"/.npm/_npx/*/node_modules/@wp-playground/cli/sqlite-database-integration.zip 2>/dev/null | head -1)}"
[ -f "$WPZIP" ] && [ -f "$SQLZIP" ] || { echo "Modo nativo: falta la copia de WordPress o de SQLite de Playground"; exit 1; }
PHPBIN="php$PHP"; command -v "$PHPBIN" >/dev/null || { echo "Modo nativo: no hay $PHPBIN"; exit 1; }
WP="$TMPDIR/wp-native-$PORT"; rm -rf "$WP"; mkdir -p "$WP"
unzip -q "$WPZIP" -d "$WP.tmp" && mv "$WP.tmp/wordpress/"* "$WP/" && rm -rf "$WP.tmp"
mkdir -p "$WP/wp-content/mu-plugins" "$WP/wp-content/database"
unzip -q "$SQLZIP" -d "$WP/wp-content/mu-plugins" && mv "$WP/wp-content/mu-plugins/"plugin-sqlite-database-integration "$WP/wp-content/mu-plugins/sqlite-database-integration"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP/wp-content/mu-plugins/sqlite-database-integration#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$WP/wp-content/mu-plugins/sqlite-database-integration/db.copy" > "$WP/wp-content/db.php"
cat > "$WP/wp-config.php" <<CFG
<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', 'wp' ); define( 'DB_PASSWORD', 'wp' ); define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' ); define( 'DB_DIR', '$WP/wp-content/database/' ); define( 'DB_FILE', '.ht.sqlite' );
define( 'WP_HOME', 'http://127.0.0.1:$PORT' ); define( 'WP_SITEURL', 'http://127.0.0.1:$PORT' );
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_DEBUG_LOG', '$OUT/debug.log' );
define( 'SAVEQUERIES', true ); define( 'DISABLE_WP_CRON', true );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) { define( \$k, 'clave-de-prueba-' . \$k ); }
\$table_prefix = 'wp_';
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
CFG
ln -s "$OUT" "$WP/wp-content/probe"
ln -s "${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}" "$WP/wp-content/plugins/digitalisimo-elements"
ln -s "${MIGRATION_DIR:-$ROOT/digitalisimo-migration}" "$WP/wp-content/plugins/digitalisimo-migration"
[ -n "${ANTES_ELEMENTS:-}" ] && ln -s "$ANTES_ELEMENTS" "$WP/wp-content/plugins/digitalisimo-elements-antes"
[ -n "${ANTES_MIGRATION:-}" ] && ln -s "$ANTES_MIGRATION" "$WP/wp-content/plugins/digitalisimo-migration-antes"
OPC=(-d opcache.enable=1 -d opcache.enable_cli=1 -d opcache.memory_consumption=256 -d opcache.max_accelerated_files=30000 -d opcache.interned_strings_buffer=32 -d memory_limit=512M -d date.timezone=UTC)
DIGI_AP_WP="$WP" $PHPBIN "${OPC[@]}" -r 'define( "WP_INSTALLING", true ); $_SERVER["HTTP_HOST"] = "127.0.0.1"; require getenv( "DIGI_AP_WP" ) . "/wp-load.php"; require_once ABSPATH . "wp-admin/includes/upgrade.php"; $r = wp_install( "Auditoría del escritorio", "admin", "admin@ejemplo.test", true, "", "password" ); echo is_wp_error( $r ) ? $r->get_error_message() : "instalado\n";' 
DIGI_AP_WP="$WP" $PHPBIN -d memory_limit=1024M -d date.timezone=UTC "$OUT/setup.php"
cp "$A/router.php" "$WP/digi-router.php"
$PHPBIN "${OPC[@]}" -S "127.0.0.1:$PORT" -t "$WP" "$WP/digi-router.php" > "$OUT/server.log" 2>&1 &
SERVER=$!
