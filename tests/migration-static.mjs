/*
 * F8 · Comprobaciones estáticas de DIGITALÍSIMO Migración (V.16, §8.1): cerrojos sólo con INSERT IGNORE y token (nunca
 * add_option), escrituras de la tarea de red sólo bajo su cerrojo, sin register_deactivation_hook, sin borrar plugins ni
 * cambiar el tema, wp_delete_post sólo al revertir, ninguna ruta de disco leída de `_bdt_icon_set_path`, metas nuevas
 * con contenido en Aliases::SKIP_META, un solo botón por pantalla (el análisis es una acción AJAX), etapas, pasos y
 * grupos de V, códigos `review` de C2-27, textos en español con el dominio de Migración y la pantalla con la barrera.
 */
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../digitalisimo-migration/', import.meta.url).pathname;
const legacy = join(root, 'legacy');
let checks = 0;
function check(ok, message) {
	checks++;
	assert.ok(ok, message);
}
function read(file) {
	return readFileSync(file, 'utf8');
}
function walk(dir, out = []) {
	for (const name of readdirSync(dir)) {
		const path = join(dir, name);
		if (statSync(path).isDirectory()) walk(path, out);
		else if (name.endsWith('.php')) out.push(path);
	}
	return out;
}
/** Quita los comentarios de PHP para buscar sólo en el código. */
function code(text) {
	return text.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*(\/\/|#).*$/gm, '').replace(/\s\/\/[^'"\n]*$/gm, '');
}
/** Cuerpo de cada función: nombre => texto. */
function functions(text) {
	const out = {};
	const re = /function\s+([a-z_0-9]+)\s*\([^)]*\)[^{;]*\{/gi;
	let m;
	while ((m = re.exec(text))) {
		let depth = 1;
		let i = re.lastIndex;
		for (; i < text.length && depth; i++) {
			if (text[i] === '{') depth++;
			else if (text[i] === '}') depth--;
		}
		out[m[1]] = text.slice(re.lastIndex, i);
	}
	return out;
}

const files = walk(root).filter((f) => !f.includes('/digitalisimo-ui/'));
const sources = Object.fromEntries(files.map((f) => [f, code(read(f))]));
const runner = read(join(legacy, 'class-runner.php'));
const runnerCode = code(runner);
const runnerFns = functions(runnerCode);
const migration = read(join(legacy, 'class-migration.php'));
const retire = read(join(legacy, 'class-retire.php'));
const native = read(join(legacy, 'class-native.php'));
const aliases = read(join(legacy, 'class-aliases.php'));
const js = read(join(root, 'assets/js/migrate.js'));

/* ---------------------------------------------------------------- Cerrojos y escrituras de red */
for (const [file, text] of Object.entries(sources)) {
	check(!/\badd_option\s*\(/.test(text) || !/lock/i.test(text.match(/add_option\s*\(([^)]*)\)/)?.[1] || ''), 'Ningún cerrojo con add_option: ' + file);
}
check(!Object.entries(sources).some(([, t]) => /\badd_option\s*\(\s*['"][^'"]*lock/i.test(t) || /add_site_option\s*\(\s*['"][^'"]*lock/i.test(t)), 'Ningún cerrojo con add_option ni en sitemeta.');
check((runnerCode.match(/INSERT IGNORE INTO/g) || []).length >= 2, 'Los cerrojos del sitio y de la red son INSERT IGNORE.');
check(/get_blog_prefix\(\s*function_exists\(\s*'get_main_site_id'\s*\)/.test(runnerCode), 'El cerrojo de red vive en `options` del sitio principal.');
check(/NETWORK_LOCK\s*=\s*'digitalisimo_migration_network_lock'/.test(runner) && /NETWORK_STALE\s*=\s*300/.test(runner), 'Cerrojo de red con nombre propio, robado a los 300 s.');
const writers = Object.entries(runnerFns).filter(([, body]) => /update_site_option\(\s*self::NETWORK_JOB/.test(body));
check(writers.length >= 3, 'La tarea de red se escribe en varios sitios: ' + writers.map(([n]) => n).join(', '));
for (const [name, body] of writers) {
	check(/self::network_lock\(\)/.test(body) && /self::network_unlock\(\)/.test(body), 'La escritura de la tarea de red en ' + name + '() va bajo su cerrojo.');
}
check(!/update_site_option\(\s*self::NETWORK_JOB/.test(runnerFns.network_summary || ''), 'network_summary() ya no escribe `done` por su cuenta (lo hace network_finish() bajo el cerrojo).');
check(!/register_deactivation_hook/.test(Object.values(sources).join('\n')), 'Sin register_deactivation_hook: la publicación perezosa no deja nada que limpiar.');

/* ---------------------------------------------------------------- Nada se borra ni cambia de tema */
for (const [file, text] of Object.entries(sources)) {
	check(!/\bdelete_plugins\s*\(/.test(text) && !/\bswitch_theme\s*\(/.test(text), 'Sin delete_plugins ni switch_theme: ' + file.replace(root, ''));
}
const deletes = [];
for (const [file, text] of Object.entries(sources)) {
	const fns = functions(text);
	for (const [name, body] of Object.entries(fns)) {
		if (/\bwp_delete_post\s*\(/.test(body) && !/revert|retire_post|forget/i.test(name)) deletes.push(file.replace(root, '') + ':' + name);
	}
}
check(deletes.length === 0, 'wp_delete_post sólo al revertir: ' + deletes.join(', '));
const iconPath = Object.entries(sources).filter(([, t]) => /_bdt_icon_set_path/.test(t) && /(file_get_contents|copy|opendir|scandir|glob|realpath|fopen)\s*\([^;]*_bdt_icon_set_path/.test(t));
check(iconPath.length === 0, 'Ninguna ruta de disco leída de `_bdt_icon_set_path`: ' + iconPath.map(([f]) => f).join(', '));

/* ---------------------------------------------------------------- Metas con contenido fuera de la búsqueda de alias */
for (const meta of ['_digitalisimo_ep_backup', '_digitalisimo_ep_backup_page', '_digitalisimo_ep_backup_f8', '_digitalisimo_ep_f8']) {
	check(aliases.includes("'" + meta + "'"), 'Aliases::SKIP_META incluye ' + meta);
}
check(/BACKUP_PAGE\s*=\s*'_digitalisimo_ep_backup_page'/.test(migration) && /BACKUP_F8\s*=\s*'_digitalisimo_ep_backup_f8'/.test(migration) && /F8_MARK\s*=\s*'_digitalisimo_ep_f8'/.test(migration), 'Migration declara las tres metas nuevas.');

/* ---------------------------------------------------------------- Un solo botón */
check((migration.match(/<button/g) || []).length === 2, 'Las pantallas de Migración tienen un botón cada una (sitio y red).');
check(!/<button/.test(runner) && !/<button/.test(read(join(legacy, 'class-report.php'))), 'El informe y el avance no añaden botones.');
check(/add_action\(\s*'wp_ajax_'\s*\.\s*self::ANALYZE/.test(migration) && /check_ajax_referer\(\s*Runner::AJAX\s*\.\s*'_'\s*\.\s*get_current_blog_id\(\)\s*\)/.test(migration) && /current_user_can\(\s*'manage_options'\s*\)/.test(functions(code(migration)).analyze_ajax || ''), 'El análisis previo es una acción AJAX con el nonce y la capacidad del paso (no un botón).');
check(!/<button/.test(js) && !/createElement\('button'\)/.test(js), 'migrate.js no crea botones.');

/* ---------------------------------------------------------------- Etapas, pasos y grupos de V */
const stages = ['prepare', 'platform_prepare', 'deactivate', 'platform_convert', 'recover', 'repair', 'convert', 'revisions', 'markers', 'platform', 'references', 'aliases', 'pro', 'retire_late', 'report', 'finish'];
check(runner.includes("const STAGES      = array( '" + stages.join("', '") + "' );"), 'STAGES como V.3.1.');
check(runner.includes("const LOOP_STAGES = array( 'platform_convert', 'recover', 'convert', 'revisions', 'markers', 'aliases', 'pro', 'retire_late', 'report' );"), 'LOOP_STAGES como V.3.1 (con la etapa pro de los widgets de PRO).');
for (const stage of stages) {
	check(new RegExp("private static function stage_" + stage + '\\(').test(runner) && new RegExp("'" + stage + "'\\s*=>\\s*'").test(runner), 'La etapa ' + stage + ' tiene método y rótulo.');
}
check(runner.includes("const PREPARE_STEPS  = array( 'Theme_Builder', 'Fonts', 'Icons', 'Snippets', 'Sidebar' );") && runner.includes("const PLATFORM_STEPS = array( 'Theme_Builder', 'Mega_Menu', 'Kit', 'Snippets', 'Swatches', 'Inventory' );"), 'Pasos de plataforma de V.14 C5.');
check(runner.includes("const REVERT_EXTRA = array( 'Prime_Slider\\\\Loop_Item', 'Prime_Slider\\\\Demo_Media' );"), 'REVERT_EXTRA de V.8.');
check(/const NETWORK_TICK = 'digitalisimo_migration_network_tick'/.test(runner) && /add_action\(\s*self::NETWORK_TICK/.test(runner), 'El evento de red de WP-Cron se registra.');
const order = [...retire.matchAll(/^\s*'([a-z0-9-]+\.php)'\s*=>\s*array\(/gm)].map((m) => m[1]);
check(order.join(',') === 'bdthemes-element-pack-addon.php,bdthemes-element-pack.php,bdthemes-element-pack-lite.php,elementor-pro.php,pro-elements.php,elementor.php,bdthemes-prime-slider.php', 'Retire::PLUGINS en el orden de retirada: ' + order.join(','));
check(/'match' => 'name'/.test(retire) && /'requires' => 'bdthemes-element-pack.php'/.test(retire) && /'late' => true, 'group' => 'prime-slider'/.test(retire), 'El Addon por nombre y dependiente de Pro; Prime Slider tardío.');
check(/const KEPT = array\(\);/.test(retire), 'KEPT vacío: el Addon y Prime Slider se retiran.');
check(/deactivate_plugins\(\s*1 === count\( \$list \) \? \$list\[0\] : \$list/.test(retire), 'Una sola llamada a deactivate_plugins() por grupo.');

/* ---------------------------------------------------------------- Llamadas a otros frentes, protegidas */
for (const [cls, method] of [['Prime_Slider\\\\Converter', 'element'], ['Effects\\\\Dispatcher', 'is_key'], ['Effects\\\\Dispatcher', 'detect'], ['Effects\\\\Dispatcher', 'element'], ['Markers', 'classify'], ['Prime_Slider\\\\Maps', 'get']]) {
	check(native.includes("self::has( '" + cls + "', '" + method + "' )"), 'Native llama a ' + cls.replace(/\\\\/g, '\\') + '::' + method + '() protegida.');
}
check(native.includes("foreach ( array( 'Platform\\\\Icons', 'Platform\\\\Dynamic_Tags' ) as $class )") && native.includes("self::has( $class, 'rewrite' )"), 'Los reescritores de iconos y etiquetas se llaman protegidos.');
check(/self::platform_call\( 'Publisher', 'allow', array\( true \) \)/.test(runner) && /self::has\( 'Markers', 'stage' \)/.test(runner), 'El runner llama protegidos al publicador y a la etapa de marcadores.');

/* ---------------------------------------------------------------- Códigos review de C2-27 y catálogo propio */
const catalogs = Object.fromEntries(['core', 'ps', 'platform', 'tb'].map((n) => [n, join(legacy, 'report', 'codes-' + n + '.php')]).filter(([, f]) => existsSync(f)).map(([n, f]) => [n, read(f)]));
check(Boolean(catalogs.core), 'El catálogo del frente A existe.');
function spec(text, code) {
	const m = text && text.match(new RegExp("'" + code.replace(/\./g, '\\.') + "'\\s*=>\\s*array\\(([\\s\\S]*?)\\)\\s*,\\s*\\n"));
	return m ? m[1] : null;
}
const demo = spec(catalogs.ps, 'ps.demo.skipped');
check(demo === null || /review_if_published'\s*=>\s*true|'section'\s*=>\s*'review'/.test(demo), 'ps.demo.skipped en una página publicada va a «Pendiente de revisar» (C2-27).');
const tags = spec(catalogs.platform, 'plat.tags.dropped');
check(tags === null || /'section'\s*=>\s*'review'|review_if_published'\s*=>\s*true/.test(tags), 'plat.tags.dropped va a «Pendiente de revisar» (C2-27; en una página publicada).');
const css = Object.entries(catalogs.platform ? { x: catalogs.platform } : {}).map(([, t]) => spec(t, 'plat.code.css_draft')).filter(Boolean)[0];
check(css === undefined || /'section'\s*=>\s*'review'/.test(css), 'El CSS global en borrador va a «Pendiente de revisar» (C2-27).');
const core = catalogs.core;
const coreCodes = [...core.matchAll(/'(core\.[a-z0-9_.]+)'\s*=>\s*array\(\s*'section'\s*=>\s*'([a-z]+)'/g)];
check(coreCodes.length >= 15 && coreCodes.every(([, , s]) => ['converted', 'approx', 'created', 'dropped', 'review'].includes(s)), 'Los códigos `core.` tienen sección válida: ' + coreCodes.length);
check(coreCodes.every(([, c]) => core.includes("'" + c + "'") && new RegExp("'" + c.replace(/\./g, '\\.') + "'[^\\n]*__\\( '[^']+', 'digitalisimo-migration' \\)").test(core)), 'Cada texto del catálogo `core.` es traducible con el dominio de Migración.');
const used = new Set();
for (const text of [runnerCode, code(migration), code(native), code(retire)]) {
	for (const m of text.matchAll(/'(core\.[a-z0-9_.]+)'/g)) used.add(m[1]);
}
const undeclared = [...used].filter((c) => !core.includes("'" + c + "'"));
check(undeclared.length === 0, 'Cada código `core.` que se usa está en el catálogo: ' + undeclared.join(', '));

/* ---------------------------------------------------------------- Español y pantalla */
const spanishStrings = [...runner.matchAll(/(?:warn\(\s*\$state,\s*|'warnings'\]\[\]\s*=\s*)'([^']{20,})'/g)].map((m) => m[1]);
check(spanishStrings.length >= 5 && spanishStrings.every((t) => !/\b(the|is|with|without|could)\b/i.test(t)), 'Los avisos del runner están en español.');
check(/data\.wait_network/.test(js) && /POLL = 15000/.test(js) && /MIN_SITE = 5000/.test(js) && /PARALLEL = 2/.test(js), 'La pantalla entiende wait_network, sondea cada 15 s, espera 5 s por sitio y conduce de dos en dos.');
check(/function analyze\(\)/.test(js) && /data-action/.test(js) && /digi-migrate__analysis/.test(js), 'La pantalla conduce el análisis previo por trozos.');
check(/'wait_network'\s*=>\s*! empty\( \$state\['wait_network'\] \)/.test(runner), 'La respuesta del paso dice wait_network.');
check(/Esperando a la migración de la red\./.test(read(join(legacy, 'class-cli.php'))), 'WP-CLI ante wait_network lo dice y sale sin relanzar.');
check(/Requires Plugins: digitalisimo-elements/.test(read(join(root, 'digitalisimo-migration.php'))) && /Prime Slider/.test(read(join(root, 'digitalisimo-migration.php'))), 'La descripción del plugin nombra también Prime Slider (y sigue exigiendo Elements).');
check(/ELEMENTS_MIN = '4\.3\.0\.170'/.test(runner), 'Migrar exige DIGITALÍSIMO Elements 4.3.0.170.');

console.log(`DIGITALÍSIMO Migración: comprobaciones estáticas de F8: ${checks} en verde.`);
if (checks < 35) {
	throw new Error(`Se esperaban al menos 35 comprobaciones (${checks}).`);
}
