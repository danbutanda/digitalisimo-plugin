#!/usr/bin/env bash
# Sal de la caché de objetos en WordPress Playground (Digitalisimo_Cache_Salt, copia idéntica en Tools y Migración), con
# un drop-in de prueba que guarda la caché en /digi-cache con la clave `<sal><$table_prefix><grupo>:<clave>` (como
# Memcached o Redis Object Cache): dos sitios con `wp_` y sin sal comparten las claves. Antes de empezar, la clave sin sal
# de `alloptions` tiene las opciones de «otro sitio» (otra dirección): este WordPress lee la dirección del otro.
#  1. Tools, en el escritorio de un administrador: la primera carga agrega la sal a wp-config.php (copia, PHP válido); la
#     segunda ya la tiene en vigor, lee su propia dirección y la clave del otro sitio sigue intacta (sin vaciar la
#     caché global); la tercera no escribe nada.
#  2. Migrar, sin Tools, con la misma caché que ya da los datos del otro sitio: migration-full.sh SCENARIO=salt pulsa
#     Migrar y termina solo (su copia de la clase pone la sal antes de nada), sin tocar lo del otro sitio.
# Uso: bash tests/playground/tools-cache-salt.sh  (PHPV=8.2|7.4; SOLO_TOOLS=1 omite el paso 2)  → sale 0 si todo cuadra.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
PHPV="${PHPV:-8.2}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
RUN="$(mktemp -d)"; mkdir -p "$RUN/cache"; cp "$ROOT/tests/playground/tools-cache-salt/"*.php "$RUN/"
cat > "$RUN/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHPV","wp":"latest"},"steps":[
{"step":"activatePlugin","pluginPath":"digitalisimo-tools/digitalisimo-tools.php"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require '/digi-cs/seed-neighbor.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CS_STEP=uno'); require '/digi-cs/step.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CS_STEP=dos'); require '/digi-cs/step.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CS_STEP=tres'); require '/digi-cs/step.php';"}]}
JSON
$CLI run-blueprint --php="$PHPV" --blueprint="$RUN/blueprint.json" --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" --mount="$RUN:/digi-cs" --mount="$RUN/cache:/digi-cache" > "$RUN/cli.log" 2>&1 || { tail -30 "$RUN/cli.log"; exit 1; }
php -r '
$d = $argv[1]; $p = array();
$u = json_decode((string) @file_get_contents("$d/uno.json"), true); $s = json_decode((string) @file_get_contents("$d/dos.json"), true); $t = json_decode((string) @file_get_contents("$d/tres.json"), true);
if (!$u || !$s || !$t) { echo "FALLA: faltan resultados\n"; exit(1); }
if (!$u["ext"] || null !== $u["salt"] || "http://otro.test" !== $u["home"]) $p[] = "1: la caché compartida debe dar la dirección del otro sitio y no tener sal: " . json_encode($u);
if ("written" !== ($u["result"]["state"] ?? "") || 1 !== $u["marks"] || "ok" !== $u["lint"] || 1 !== count($u["copies"])) $p[] = "1: debe escribir la sal con copia y PHP válido: " . json_encode($u);
if ("salted" !== ($u["notice"]["state"] ?? "")) $p[] = "1: el aviso dice que ya se agregó: " . json_encode($u["notice"]);
if (!is_string($s["salt"]) || $s["salt"] !== ($u["result"]["salt"] ?? "x") || 1 !== $s["marks"]) $p[] = "2: en la carga siguiente la constante está en vigor y el bloque no se duplica: " . json_encode($s);
if ($s["home"] !== $s["db_home"] || "active" !== ($s["result"]["state"] ?? "")) $p[] = "2: con la sal, este sitio lee su propia dirección (" . $s["home"] . " / " . $s["db_home"] . ") y la sal queda comprobada: " . json_encode($s["result"]);
if ("http://otro.test" !== $s["neighbor"] || $s["flushed"] || $t["flushed"]) $p[] = "2: la clave del otro sitio sigue intacta y no se vació la caché global";
if ("salted" !== ($s["notice"]["state"] ?? "") || 1 !== $t["marks"] || "active" !== ($t["result"]["state"] ?? "")) $p[] = "3: aviso de éxito en vigor, sin otra escritura: " . json_encode($t);
foreach ($p as $x) echo "FALLA $x\n";
echo $p ? "" : "OK tools-cache-salt (Tools, Playground PHP " . PHP_VERSION . " lado CLI): sal " . $s["salt"] . "\n";
exit($p ? 1 : 0);' "$RUN"
sed -n '/DIGITALÍSIMO: sal/,/Fin de DIGITALÍSIMO/p' "$RUN/wp-config-despues.php"
rm -rf "$RUN"
[ "${SOLO_TOOLS:-0}" = 1 ] && exit 0
SCENARIO=salt bash "$ROOT/tests/playground/migration-full.sh"
