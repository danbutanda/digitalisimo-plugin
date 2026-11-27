#!/usr/bin/env bash
# Salud de plugins y caché de la API pública (DIGITALÍSIMO Tools) en WordPress Playground.
#  1. Plugins de prueba activos: uno que un mu-plugin quita de la lista (siempre), otro que quita sólo al arrancar, uno
#     cuyo archivo se borró y otro que no registra el tipo de contenido que declara su marca. En el Escritorio, la salud
#     de plugins dice cada uno con su motivo (filtered, not_loaded, missing, marker), pinta el aviso de error con la X
#     (sin el que quita un filtro: es a propósito, sólo una nota), «Salud del sitio» da critical y la función del MCP
#     (Abilities API) lo devuelve sin guardar nada.
#  2. Una petición REST sin sesión: /wp/v2/posts y el 404 de una ruta que no existe salen como siempre (los renueva la
#     purga REST); el índice / sale sin caché (cabeceras de WordPress, DONOTCACHEPAGE, litespeed_control_set_nocache).
#  3. Activar y desactivar un plugin pide a LiteSpeed Cache purgar la etiqueta REST.
# Uso: bash tests/playground/tools-plugin-health.sh  (PHPV=8.2|7.4)  → sale 0 si todo cuadra.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
PHPV="${PHPV:-8.2}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
RUN="$(mktemp -d)"; cp "$ROOT/tests/playground/tools-plugin-health/"* "$RUN/"
cat > "$RUN/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHPV","wp":"latest"},"steps":[
{"step":"activatePlugin","pluginPath":"digitalisimo-tools/digitalisimo-tools.php"},
{"step":"runPHP","code":"<?php require '/digi-ph/setup.php';"},
{"step":"runPHP","code":"<?php require '/digi-ph/check.php';"},
{"step":"runPHP","code":"<?php require '/digi-ph/rest.php';"},
{"step":"runPHP","code":"<?php require '/digi-ph/purge.php';"}]}
JSON
$CLI run-blueprint --php="$PHPV" --blueprint="$RUN/blueprint.json" --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" --mount="$RUN:/digi-ph" > "$RUN/cli.log" 2>&1 || { tail -30 "$RUN/cli.log"; exit 1; }
php -r '
$d = $argv[1]; $p = array();
$c = json_decode((string) @file_get_contents("$d/check.json"), true); $r = json_decode((string) @file_get_contents("$d/rest.json"), true); $g = json_decode((string) @file_get_contents("$d/purge.json"), true);
if (!$c || !$r || !$g) { echo "FALLA: faltan resultados\n"; exit(1); }
$why = array(); foreach ((array) ($c["check"] ?? array()) as $x) $why[$x["plugin"]] = $x["reason"];
ksort($why);
$want = array("digi-borrado/digi-borrado.php" => "missing", "digi-filtrado/digi-filtrado.php" => "filtered", "digi-marca/digi-marca.php" => "marker", "digi-tarde/digi-tarde.php" => "not_loaded");
if ($why !== $want) $p[] = "1: motivos " . json_encode($why);
foreach ((array) ($c["check"] ?? array()) as $x) if ("filtered" === $x["reason"] && false === strpos($x["detail"], "mu-plugins/digi-ph.php:")) $p[] = "1: el filtro se nombra con su archivo: " . $x["detail"];
if (false === strpos((string) $c["notice"], "notice-error") || false === strpos((string) $c["notice"], "is-dismissible") || false === strpos((string) $c["notice"], "Prueba digi-tarde") || false !== strpos((string) $c["notice"], "Prueba digi-filtrado")) $p[] = "1: aviso de error con la X, sin el que quita un filtro (es a propósito): " . $c["notice"];
foreach ((array) ($c["check"] ?? array()) as $x) if (("filtered" === $x["reason"]) !== !empty($x["intentional"])) $p[] = "1: sólo lo que quita un filtro es intentional: " . json_encode($x);
if ("critical" !== $c["health"]) $p[] = "1: Salud del sitio critical: " . $c["health"];
if (null === $c["ability"]) echo "AVISO: este WordPress no trae la Abilities API; la función del MCP no se prueba\n";
elseif (!is_array($c["ability"]) || false !== ($c["ability"]["ok"] ?? null) || 4 !== count($c["ability"]["problems"] ?? array()) || !empty($c["option"])) $p[] = "1: función del MCP (sólo lee, no guarda nada): " . json_encode($c["ability"]) . " " . json_encode($c["option"]);
$cc = function ($h) { foreach ((array) $h as $k => $v) if ("cache-control" === strtolower($k)) return (string) $v; return ""; };
if (false !== strpos($cc($r["posts"]["headers"]), "no-store") || $r["posts"]["donotcache"] || $r["posts"]["nocache"]) $p[] = "2: /wp/v2/posts sin tocar: " . json_encode($r["posts"]);
if (404 !== $r["noroute"]["status"] || false !== strpos($cc($r["noroute"]["headers"]), "no-store") || $r["noroute"]["donotcache"] || $r["noroute"]["nocache"]) $p[] = "2: el 404 rest_no_route se sigue guardando (lo renueva la purga REST): " . json_encode($r["noroute"]);
if (200 !== $r["index"]["status"] || false === strpos($cc($r["index"]["headers"]), "no-store") || !$r["index"]["donotcache"] || !$r["index"]["nocache"]) $p[] = "2: el índice sin caché: " . json_encode($r["index"]);
if (array("REST") !== $g["activate"] || array("REST", "REST") !== $g["all"]) $p[] = "3: purga REST al activar y desactivar: " . json_encode($g);
foreach ($p as $x) echo "FALLA $x\n";
echo $p ? "" : "OK tools-plugin-health (Playground PHP " . ($argv[2]) . "): filtered, not_loaded, missing y marker; aviso, Salud del sitio y MCP; API sin caché y purga REST\n";
exit($p ? 1 : 0);' "$RUN" "$PHPV"
rm -rf "$RUN"
