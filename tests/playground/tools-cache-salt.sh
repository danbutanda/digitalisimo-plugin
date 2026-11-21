#!/usr/bin/env bash
# Sal de la caché de objetos en un WordPress de Playground (DIGITALÍSIMO Tools, Cache_Salt): con un drop-in falso que
# hace que la caché cuente como externa y sin WP_CACHE_KEY_SALT, la primera carga del escritorio de un administrador
# agrega la sal a wp-config.php (con copia y PHP válido); la segunda ya la tiene en vigor y no duplica el bloque.
# Uso: bash tests/playground/tools-cache-salt.sh  (PHPV=8.2|7.4)  → sale 0 si todo cuadra.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
PHPV="${PHPV:-8.2}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
RUN="$(mktemp -d)"; cp "$ROOT/tests/playground/tools-cache-salt/"*.php "$RUN/"
cat > "$RUN/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHPV","wp":"latest"},"steps":[
{"step":"activatePlugin","pluginPath":"digitalisimo-tools/digitalisimo-tools.php"},
{"step":"writeFile","path":"/wordpress/wp-content/object-cache.php","data":{"resource":"literal","name":"object-cache.php","contents":$(php -r 'echo json_encode(file_get_contents($argv[1]));' "$RUN/object-cache.php")}},
{"step":"runPHP","code":"<?php putenv('DIGI_CS_STEP=uno'); require '/digi-cs/step.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CS_STEP=dos'); require '/digi-cs/step.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CS_STEP=tres'); require '/digi-cs/step.php';"}]}
JSON
$CLI run-blueprint --php="$PHPV" --blueprint="$RUN/blueprint.json" --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" --mount="$RUN:/digi-cs" > "$RUN/cli.log" 2>&1 || { tail -30 "$RUN/cli.log"; exit 1; }
php -r '
$d = $argv[1]; $p = array();
$u = json_decode((string) @file_get_contents("$d/uno.json"), true); $s = json_decode((string) @file_get_contents("$d/dos.json"), true); $t = json_decode((string) @file_get_contents("$d/tres.json"), true);
if (!$u || !$s || !$t) { echo "FALLA: faltan resultados\n"; exit(1); }
if (!$u["ext"] || null !== $u["salt"]) $p[] = "1: la caché debe contar como externa y sin sal";
if ("written" !== ($u["result"]["state"] ?? "") || 1 !== $u["marks"] || "ok" !== $u["lint"] || 1 !== count($u["copies"])) $p[] = "1: debe escribir la sal con copia y PHP válido: " . json_encode($u);
if ("salted" !== ($u["notice"]["state"] ?? "")) $p[] = "1: el aviso dice que ya se agregó: " . json_encode($u["notice"]);
if (!is_string($s["salt"]) || $s["salt"] !== ($u["result"]["salt"] ?? "x") || 1 !== $s["marks"]) $p[] = "2: en la carga siguiente la constante está en vigor y el bloque no se duplica: " . json_encode($s);
if ("" !== ($s["notice"]["state"] ?? "x") || 1 !== $t["marks"]) $p[] = "3: sin aviso ni otra escritura: " . json_encode($t);
foreach ($p as $x) echo "FALLA $x\n";
echo $p ? "" : "OK tools-cache-salt (Playground PHP " . PHP_VERSION . " lado CLI): sal " . $s["salt"] . "\n";
exit($p ? 1 : 0);' "$RUN"
sed -n '/DIGITALÍSIMO/,/Fin de DIGITALÍSIMO/p' "$RUN/wp-config-despues.php"
rm -rf "$RUN"
