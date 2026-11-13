// Servidor local del ensayo del instalador (elements-installer.sh): sirve una carpeta con la forma de las Releases
// (releases/latest/download/digitalisimo-plugins.json y releases/download/<etiqueta>/<ZIP>) sólo en 127.0.0.1 y anota
// cada petición (método, ruta, código y bytes) en una línea JSON. Si existe el archivo <fallo>, el índice responde 503
// (caso «índice inaccesible»).
// Uso: node server.cjs <carpeta> <puerto> <registro> <fallo>
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');

const [root, port, logFile, failFile] = process.argv.slice(2);
const base = fs.realpathSync(root);
const log = (entry) => fs.appendFileSync(logFile, JSON.stringify({ at: new Date().toISOString(), ...entry }) + '\n');
http.createServer((req, res) => {
  const route = decodeURIComponent((req.url || '/').split('?')[0]);
  const done = (code, bytes = 0) => log({ method: req.method, route, code, bytes });
  if (route.endsWith('/digitalisimo-plugins.json') && fs.existsSync(failFile)) {
    res.writeHead(503, { 'Content-Type': 'text/plain' });
    res.end('Servicio no disponible (ensayo)');
    return done(503);
  }
  const file = path.resolve(base, '.' + route);
  if (!file.startsWith(base + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('No existe');
    return done(404);
  }
  const size = fs.statSync(file).size;
  res.writeHead(200, { 'Content-Type': file.endsWith('.json') ? 'application/json' : 'application/zip', 'Content-Length': size });
  if (req.method === 'HEAD') { res.end(); return done(200); }
  fs.createReadStream(file).on('end', () => done(200, size)).pipe(res);
}).listen(Number(port), '127.0.0.1', () => log({ ready: true, port: Number(port) }));
