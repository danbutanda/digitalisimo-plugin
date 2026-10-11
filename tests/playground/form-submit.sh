#!/usr/bin/env bash
# Envío de Contact Form, Webhook Form y Mailchimp heredados por el AJAX de PRO Elements (Playground Multisite, sin Element Pack).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${OUT:-/tmp/digi-form-submit}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
mkdir -p "$OUT" && rm -f "$OUT/result.json"
cp "$ROOT/tests/playground/form-submit/probe.php" "$OUT/probe.php"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[{"step":"enableMultisite"},{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'elementor/elementor.php', '', true ); activate_plugin( 'digitalisimo-elements/pro-elements.php', '', true ); if ( ! get_site( 2 ) ) { wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 ); }"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"}]}
JSON
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
node -e '
const r = require(process.argv[1]);
for (const blog of ["1", "2"]) {
  const s = r[blog] || {};
  if (!s["bdt-contact-form"]?.success || !s["bdt-webhook-form"]?.success) throw new Error("envío sin éxito en el sitio " + blog + ": " + JSON.stringify(s));
  if (!s.mail?.has_message || !/Consulta/.test(s.mail.subject) || !/ana@example.com/.test(s.mail.reply_to)) throw new Error("correo incompleto en el sitio " + blog + ": " + JSON.stringify(s.mail));
  if (s.webhook?.url !== "https://example.com/hook" || !JSON.stringify(s.webhook.body).includes("Ana")) throw new Error("webhook incompleto en el sitio " + blog + ": " + JSON.stringify(s.webhook));
  if (!s["bdt-mailchimp"]?.success) throw new Error("Mailchimp sin éxito en el sitio " + blog + ": " + JSON.stringify(s["bdt-mailchimp"]));
  const calls = s.mailchimp || [];
  const put = calls.find((c) => /us1\.api\.mailchimp\.com\/3\.0\/lists\/L1\/members\//.test(c.url) && /PUT|POST|PATCH/.test(c.method));
  if (!put || !JSON.stringify(put.body).includes("ana@example.com") || !JSON.stringify(put.body).includes("FNAME") || !JSON.stringify(put.body).includes("Ana")) throw new Error("Mailchimp incompleto en el sitio " + blog + ": " + JSON.stringify(calls));
}
console.log("Envío de Contact Form, Webhook Form y Mailchimp heredados validado en los dos sitios.");
' "$OUT/result.json"
