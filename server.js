// Word cloud in stile Mentimeter — server senza dipendenze (solo Node.js)
// Avvio:  node server.js        (porta di default 3000, oppure PORT=8080 node server.js)

const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');

const PORT = Number(process.env.PORT) || 3000;
const DATA_FILE = path.join(__dirname, 'risposte.json');
const PUBLIC_DIR = path.join(__dirname, 'public');

const DOMANDA = 'In una parola, come ti senti un attimo prima di entrare?';
const MAX_LEN = 25;

// ---------- Archivio su file ----------
function nuovaSessione() {
  return { sessione: crypto.randomBytes(4).toString('hex'), risposte: [] };
}

let dati;
try {
  dati = JSON.parse(fs.readFileSync(DATA_FILE, 'utf8'));
  if (!Array.isArray(dati.risposte)) throw new Error('formato non valido');
} catch {
  dati = nuovaSessione();
}

let salvataggioInCorso = false;
let salvataggioPendente = false;
function salva() {
  if (salvataggioInCorso) { salvataggioPendente = true; return; }
  salvataggioInCorso = true;
  const tmp = DATA_FILE + '.tmp';
  fs.writeFile(tmp, JSON.stringify(dati, null, 2), (err) => {
    if (err) console.error('Errore di salvataggio:', err);
    else fs.renameSync(tmp, DATA_FILE);
    salvataggioInCorso = false;
    if (salvataggioPendente) { salvataggioPendente = false; salva(); }
  });
}
salva();

function conteggi() {
  const mappa = new Map();
  for (const r of dati.risposte) mappa.set(r.parola, (mappa.get(r.parola) || 0) + 1);
  return [...mappa.entries()]
    .map(([parola, n]) => ({ parola, n }))
    .sort((a, b) => b.n - a.n || a.parola.localeCompare(b.parola, 'it'));
}

// ---------- Indirizzo in rete locale (per il QR) ----------
function indirizziLocali() {
  const out = [];
  for (const [nome, lista] of Object.entries(os.networkInterfaces())) {
    for (const i of lista || []) {
      if (i.family === 'IPv4' && !i.internal) out.push({ nome, ip: i.address });
    }
  }
  // Preferisce le reti private tipiche (192.168.x, 10.x, 172.16-31.x) ed evita adattatori virtuali
  const punteggio = ({ nome, ip }) =>
    (/^192\.168\./.test(ip) ? 3 : /^10\./.test(ip) ? 2 : /^172\.(1[6-9]|2\d|3[01])\./.test(ip) ? 1 : 0) -
    (/virtual|vmware|vbox|hyper-v|wsl|docker|vethernet/i.test(nome) ? 10 : 0);
  return out.sort((a, b) => punteggio(b) - punteggio(a)).map((x) => x.ip);
}

// ---------- Utilità HTTP ----------
const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.ico': 'image/x-icon',
};

function json(res, status, body) {
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(body));
}

function leggiCorpo(req) {
  return new Promise((resolve, reject) => {
    let s = '';
    req.on('data', (c) => { s += c; if (s.length > 2000) { reject(new Error('troppo grande')); req.destroy(); } });
    req.on('end', () => { try { resolve(JSON.parse(s || '{}')); } catch (e) { reject(e); } });
    req.on('error', reject);
  });
}

function normalizza(testo) {
  return String(testo || '')
    .normalize('NFC')
    .trim()
    .replace(/^[\s.,;:!?"'«»()\-]+|[\s.,;:!?"'«»()\-]+$/g, '')
    .toLocaleLowerCase('it');
}

function servistatico(req, res, pathname) {
  if (pathname === '/') pathname = '/index.html';
  if (pathname === '/rispondi' || pathname === '/r') pathname = '/rispondi.html';
  const file = path.normalize(path.join(PUBLIC_DIR, pathname));
  if (!file.startsWith(PUBLIC_DIR)) return json(res, 403, { errore: 'vietato' });
  fs.readFile(file, (err, buf) => {
    if (err) { res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' }); return res.end('Pagina non trovata'); }
    res.writeHead(200, { 'Content-Type': MIME[path.extname(file)] || 'application/octet-stream', 'Cache-Control': 'no-cache' });
    res.end(buf);
  });
}

// ---------- Server ----------
const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const p = url.pathname;

  try {
    if (p === '/api/info' && req.method === 'GET') {
      const ips = indirizziLocali();
      return json(res, 200, {
        domanda: DOMANDA,
        sessione: dati.sessione,
        maxLen: MAX_LEN,
        indirizzi: ips.map((ip) => `http://${ip}:${PORT}/rispondi`),
      });
    }

    if (p === '/api/risultati' && req.method === 'GET') {
      return json(res, 200, { sessione: dati.sessione, totale: dati.risposte.length, parole: conteggi() });
    }

    if (p === '/api/risposta' && req.method === 'POST') {
      const corpo = await leggiCorpo(req);
      const parola = normalizza(corpo.parola);
      if (!parola) return json(res, 400, { errore: 'Scrivi una parola prima di inviare.' });
      if (/\s/.test(parola)) return json(res, 400, { errore: 'Una sola parola, senza spazi 🙂' });
      if (parola.length > MAX_LEN) return json(res, 400, { errore: `Massimo ${MAX_LEN} caratteri.` });
      dati.risposte.push({ parola, ora: new Date().toISOString() });
      salva();
      return json(res, 200, { ok: true, sessione: dati.sessione });
    }

    if (p === '/api/azzera' && req.method === 'POST') {
      // Archivia le risposte precedenti prima di azzerare
      if (dati.risposte.length) {
        const archivio = path.join(__dirname, `risposte-${new Date().toISOString().replace(/[:.]/g, '-')}.json`);
        fs.writeFileSync(archivio, JSON.stringify(dati, null, 2));
      }
      dati = nuovaSessione();
      salva();
      return json(res, 200, { ok: true, sessione: dati.sessione });
    }

    if (req.method === 'GET') return servistatico(req, res, decodeURIComponent(p));
    json(res, 404, { errore: 'non trovato' });
  } catch (e) {
    json(res, 400, { errore: 'Richiesta non valida.' });
  }
});

server.listen(PORT, '0.0.0.0', () => {
  const ips = indirizziLocali();
  console.log('\n  Word cloud avviato!\n');
  console.log(`  Schermo docente:   http://localhost:${PORT}`);
  for (const ip of ips) console.log(`  Studenti (stessa rete): http://${ip}:${PORT}/rispondi`);
  console.log(`\n  Risposte salvate in: ${DATA_FILE}\n`);
});
