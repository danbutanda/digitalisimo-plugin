#!/usr/bin/env bash
# P7 · marca (f8.sh, escenario delegado en brand-audit.sh, puerto 9517): ONLY=f8 recorre en es_MX y en_US el editor de
# digi-marca-f8 (setup-f8.php) con cada control nuevo seleccionado y los frontales; falla si se ve «Elementor» o algo
# apunta a un proveedor. Lo migrado de P0 y P2 lo auditan esos escenarios (sin rastro del proveedor). Los dos idiomas
# no caben en una llamada de 10 min: cada tanda corre uno (P7_LOCALE, por defecto es_MX) y deja su resultado en
# $OUT_F8/P7-data/<idioma>/ (fuera de $RUN, que f8.sh vacía); f8/p7.cjs suma los dos.
set -uo pipefail
: "${ROOT:?}" "${RUN:?}" "${PORT:?}" "${OUT_F8:?}"
LOC="${P7_LOCALE:-es_MX}"
case "$LOC" in es_MX|en_US) ;; *) echo "P7_LOCALE debe ser es_MX o en_US"; exit 1;; esac
DIR="$OUT_F8/P7-data/$LOC"
rm -rf "$DIR" && mkdir -p "$DIR"
OUT="$DIR" PORT="$PORT" ONLY=f8 LOCALES="$LOC" SCOPE="${SCOPE:-full}" PUPPETEER_PATH="${PUPPETEER_PATH:-}" bash "$ROOT/tests/playground/brand-audit.sh" > "$DIR/out.log" 2>&1
echo $? > "$DIR/exit.txt"
tail -5 "$DIR/out.log"
