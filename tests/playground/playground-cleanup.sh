# Limpieza común de los arneses: Playground deja en el directorio temporal una copia del sitio
# (node-playground-cli-site-*, unos 400 MB) cuando su proceso termina por la fuerza, como al cerrar
# el servidor de las pruebas en navegador. Al salir se borran las creadas durante esta prueba cuyo
# proceso ya terminó; las de otra prueba que siga en marcha se respetan.
PLAYGROUND_MARK="$(mktemp)"
playground_cleanup() {
  local dir pid
  for dir in "${TMPDIR:-/tmp}"/node-playground-cli-site-*; do
    [ -d "$dir" ] && [ "$dir" -nt "$PLAYGROUND_MARK" ] || continue
    pid="$(basename "$dir" | sed -E 's/^node-playground-cli-site-([0-9]+)-.*/\1/')"
    kill -0 "$pid" 2>/dev/null || rm -rf "$dir"
  done
  rm -f "$PLAYGROUND_MARK"
}
