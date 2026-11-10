// Comprobaciones HTTP del ensayo E2FIX (backups-e2fix.sh). Fase «origin»: un respaldo por REST (POST /backups y
// POST /jobs/tick) en el almacén del origen. Fase «dest», con 30 s, 64 MB y el búfer de salida activo:
//   (h16) «Descargar» de un respaldo de más de 64 MB desde la pantalla, con el enlace y el nonce que pinta: 200,
//         Content-Length y SHA-256 exactos;
//   (h9)  la sonda copia ese respaldo a SFTP en una petición web (put(): subida, verificación y publicación);
//   (h10) el respaldo del origen, suelto en el almacén, se restaura por REST (otro dominio); después, en otra
//         petición, el almacén sigue siendo el de este servidor y la copia previa sale disponible y restaurable.
// Escribe cada resultado en <dir>/result.txt («ok · …» o «FALLA · …») y termina con TERMINADO.
// Uso: node check.mjs origin <puerto> <host> <dir> | node check.mjs dest <puerto> <host> <dir> <dir-origen> <sftp>
import http from 'node:http';
import { createHash } from 'node:crypto';
import { appendFileSync, createReadStream, existsSync, readFileSync, writeFileSync } from 'node:fs';

const [phase, port, host, dir, originDir, sftpDir] = process.argv.slice(2);
const setup = JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 900)}` : ''}`); }
  return ok;
};
// Petición HTTP; con `stream`, el cuerpo no se guarda: se cuenta y se resume con SHA-256 (un respaldo de 100 MiB).
function request(method, path, { headers = {}, body, stream = false } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : (typeof body === 'string' ? body : JSON.stringify(body));
    const all = { Host: host, ...headers };
    if (data !== undefined) all['Content-Length'] = Buffer.byteLength(data);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 3_600_000 }, (res) => {
      const chunks = [];
      const hash = createHash('sha256');
      let length = 0;
      let head = Buffer.alloc(0);
      res.on('data', (chunk) => {
        length += chunk.length;
        if (stream) { hash.update(chunk); if (head.length < 400) head = Buffer.concat([head, chunk.subarray(0, 400)]); } else chunks.push(chunk);
      });
      res.on('end', () => {
        const text = stream ? head.toString('latin1') : Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json, length, sha256: stream ? hash.digest('hex') : '' });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const basic = (password) => ({ Authorization: `Basic ${Buffer.from(`admin:${password}`).toString('base64')}` });
const api = (method, route, { headers = {}, body } = {}) => request(method, `/?rest_route=${encodeURIComponent(`/digitalisimo-backups/v1${route}`)}`, {
  headers: body === undefined ? headers : { 'Content-Type': 'application/json', ...headers },
  body,
});
const probe = (what, extra = '') => request('GET', `/?digi_e2=${what}&token=${token}${extra}`);
const fileSha = (path) => new Promise((resolve, reject) => {
  const hash = createHash('sha256');
  createReadStream(path).on('data', (chunk) => hash.update(chunk)).on('end', () => resolve(hash.digest('hex'))).on('error', reject);
});
const requests = () => (existsSync(`${dir}/probe/requests.jsonl`) ? readFileSync(`${dir}/probe/requests.jsonl`, 'utf8').trim().split('\n').filter(Boolean).map((line) => JSON.parse(line)) : []);

try {
  log(`· ${phase}: ${setup.versions}`);
  if (phase === 'origin') {
    // H19: las páginas de Elements se visitan antes del respaldo (su CSS generado viaja en él) y se mide su memoria.
    for (const path of setup.pages || []) {
      const view = await request('GET', path);
      const row = requests().filter((r) => r.uri === path).pop() || {};
      log(`· origen (h19): ${path} → ${view.status}, pico ${row.peak_mb ?? '?'} MiB`);
    }
    let res = await api('POST', '/backups', { headers: basic(setup.password), body: {} });
    const id = res.json?.id || res.json?.job?.id;
    check(res.status === 202 && id, 'origen: POST /backups encola el respaldo (202)', `${res.status} ${res.text}`);
    for (let i = 0; i < 300 && id; i++) {
      const job = await api('GET', `/jobs/${id}`, { headers: basic(setup.password) });
      if (job.status !== 200 || job.json?.terminal) { res = job; break; }
      const tick = await api('POST', '/jobs/tick', { headers: basic(setup.password), body: {} });
      if (tick.json?.result !== 'worked') await new Promise((done) => setTimeout(done, 3000));
    }
    check(res.json?.status === 'success', 'origen: el respaldo termina verificado', res.text.slice(0, 600));
    const list = await api('GET', '/backups', { headers: basic(setup.password) });
    const file = list.json?.[0]?.file;
    check(/^digitalisimo-.*\.zip$/.test(file || '') && existsSync(`${dir}/store/${file}`), 'origen: el respaldo está en el historial y en su almacén (/origen-store)', list.text.slice(0, 300));
    writeFileSync(`${dir}/origin.json`, JSON.stringify({ file }));
  } else {
    const { big } = setup;
    // (h16) «Descargar» desde la pantalla: el enlace con su nonce, como lo pulsa el propietario.
    const login = await request('POST', '/wp-login.php', {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
      body: `log=admin&pwd=password&wp-submit=Log+In&testcookie=1&redirect_to=${encodeURIComponent('/wp-admin/')}`,
    });
    const cookie = [].concat(login.headers['set-cookie'] || []).map((value) => value.split(';')[0]).join('; ');
    check(login.status === 302 && /wordpress_logged_in_/.test(cookie), 'destino: inicio de sesión del administrador', `${login.status}`);
    const screen = await request('GET', '/wp-admin/admin.php?page=digitalisimo-backups', { headers: { Cookie: cookie } });
    const link = (screen.text.match(new RegExp(`href="([^"]*action=digitalisimo_backups_download[^"]*file=${big.file.replace(/[.]/g, '\\.')}[^"]*)"`)) || [])[1];
    check(screen.status === 200 && link, 'destino: la pantalla ofrece «Descargar» del respaldo grande', `${screen.status} ${screen.text.slice(0, 300)}`);
    if (link) {
      const url = new URL(link.replace(/&amp;/g, '&').replace(/&#038;/g, '&'), `http://${host}`);
      const started = Date.now();
      const down = await request('GET', url.pathname + url.search, { headers: { Cookie: cookie }, stream: true });
      const seconds = Math.round((Date.now() - started) / 100) / 10;
      const last = requests().filter((row) => row.uri.includes('digitalisimo_backups_download')).pop() || {};
      check(down.status === 200 && Number(down.headers['content-length']) === big.size && down.length === big.size && down.sha256 === big.sha256 && down.headers['content-type'] === 'application/zip',
        `destino (h16): «Descargar» de ${Math.round(big.size / 1048576)} MiB con memory_limit=${last.memory_limit || '?'} y el búfer de salida activo: 200, Content-Length y SHA-256 exactos (${seconds} s, pico ${last.peak_mb ?? '?'} MiB)`,
        `${down.status} · Content-Length ${down.headers['content-length']} · ${down.length} bytes · ${down.sha256 === big.sha256 ? 'mismo SHA-256' : 'otro SHA-256'} · ${down.headers['content-type']} · ${JSON.stringify(last)} · ${down.text.slice(0, 300)}`);
    }
    // (h9) Copia a SFTP de ese mismo respaldo en una petición web.
    let res = await probe('sftp', `&file=${encodeURIComponent(big.file)}`);
    const copy = `${sftpDir}/respaldos/${big.file}`;
    const remote = existsSync(copy) ? await fileSha(copy) : '';
    check(res.status === 200 && res.json?.ok === true && remote === big.sha256 && res.json.largest_write <= 1048576,
      `destino (h9): SFTP copia, verifica y publica ${Math.round(big.size / 1048576)} MiB en una petición con memory_limit=${res.json?.memory_limit} (pico ${res.json?.peak_mb} MiB, escrituras de ${Math.round((res.json?.largest_write || 0) / 1024)} KiB, ${res.json?.seconds} s)`,
      `${res.status} ${res.text.slice(0, 600)} · copia ${remote ? (remote === big.sha256 ? 'íntegra' : 'distinta') : 'ausente'}`);
    // (h10) Restaurar el respaldo del origen (otro servidor y otro dominio, suelto en el almacén).
    const { file } = JSON.parse(readFileSync(`${originDir}/origin.json`, 'utf8'));
    const origin = JSON.parse(readFileSync(`${originDir}/probe/setup.json`, 'utf8'));
    res = await probe('storage');
    check(res.json?.record?.directory === setup.store && res.json?.directory === setup.store, `destino: antes de restaurar, el almacén es ${setup.store}`, res.text.slice(0, 300));
    const started = Date.now();
    res = await api('POST', `/backups/${file}/restore`, { headers: basic(setup.password), body: { confirm: 'RESTAURAR SIN FIRMA', addresses: 'current' } });
    check(res.status === 200 && res.json?.restored === true, `destino (h10): el respaldo de ${origin.home} se restaura por REST en http://${host} (${Math.round((Date.now() - started) / 1000)} s)`, res.text.slice(0, 900));
    // Otra petición (lo que vería el propietario al volver a la pantalla): el almacén y la copia previa.
    res = await probe('storage');
    check(res.status === 200 && res.json?.record?.directory === setup.store && res.json?.directory === setup.store, `destino (h10): tras restaurar, el almacén sigue siendo ${setup.store} (no ${origin.store}, el del origen)`, res.text.slice(0, 400));
    const status = await api('GET', '/status', { headers: basic(origin.password) });
    check(status.status === 200 && !(status.json?.problems || []).some((problem) => /carpeta privada|almacén/i.test(problem)), 'destino (h10): status no informa problemas del almacén', status.text.slice(0, 500));
    const list = await api('GET', '/backups', { headers: basic(origin.password) });
    const safety = (list.json || []).find((row) => row.policy === 'pre-restore');
    check(safety && safety.available === true && safety.restorable === true && existsSync(`${dir}/store/${safety.file}`), 'destino (h10): la copia previa sale disponible y restaurable, con su ZIP en el almacén de este servidor', JSON.stringify(safety || list.text.slice(0, 400)));
    const grande = (list.json || []).find((row) => row.file === big.file);
    check(grande && grande.available === true, 'destino (h10): el historial de este servidor sigue con sus respaldos disponibles', JSON.stringify(grande || null));
    // H19: dos visitas a cada página de Elements y a /wp-json/ tras restaurar en otro dominio, con 64 MB.
    if ((origin.pages || []).length) {
      const paths = [...origin.pages, '/?rest_route=%2F'];
      const rounds = [[], []];
      for (const round of [0, 1]) {
        for (const path of paths) {
          const view = await request('GET', path);
          const row = requests().filter((r) => r.uri === path).pop() || {};
          rounds[round].push({ path, status: view.status, peak: row.peak_mb, error: row.error || '' });
        }
      }
      const fails = rounds[0].filter((r) => r.status >= 500);
      const first = rounds[0].map((r) => r.peak || 0);
      const second = rounds[1].map((r) => r.peak || 0);
      log(`· destino (h19): primera visita ${rounds[0].map((r) => r.status).join(',')} · picos ${first.join('/')} MiB; segunda ${rounds[1].map((r) => r.status).join(',')} · picos ${second.join('/')} MiB`);
      check(fails.length === 0, `destino (h19): tras restaurar en otro dominio con 64 MB, la primera visita a ${paths.length} URL (páginas de Elements y /wp-json/) no da 500`, JSON.stringify(fails.slice(0, 3)));
    }
    const peaks = requests().filter((row) => row.peak_mb > 64 || /Allowed memory size|Maximum execution time/.test(row.error || ''));
    check(peaks.length === 0, 'destino: ninguna petición pasó de 64 MB ni agotó la memoria o el tiempo', JSON.stringify(peaks.slice(0, 3)));
  }
} catch (error) {
  check(false, 'el ensayo terminó con un error', error.stack || error.message);
}
log(failures ? `TERMINADO con ${failures} fallos` : 'TERMINADO');
process.exit(failures ? 1 : 0);
