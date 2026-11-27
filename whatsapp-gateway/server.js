/**
 * BaskaDrive — Gateway WhatsApp internal
 * ============================================
 * Provider:
 *  - baileys  (https://baileys.wiki)  → UTAMA    (WebSocket, tanpa browser)
 *  - wwebjs   (https://wwebjs.dev)    → CADANGAN (Puppeteer/Chrome)
 *
 * Kedua provider berjalan MULTI-DEVICE (auth state tersimpan di ./sessions),
 * masing-masing punya saklar on/off yang dikendalikan dari ERP (Laravel) via
 * /enable + /sessions/:provider/start|stop|logout.
 *
 * Jalankan: node server.js  (variabel lingkungan: GATEWAY_PORT, GATEWAY_TOKEN)
 */

const express = require('express');
const fs = require('fs');
const path = require('path');

// Ambil konfigurasi dari .env Laravel (../.env) bila variabel lingkungan
// belum diset — memudahkan menjalankan `node server.js` tanpa export manual.
(function loadLaravelEnv() {
  if (process.env.GATEWAY_TOKEN) return;
  try {
    const envPath = path.join(__dirname, '..', '.env');
    const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
    for (const line of lines) {
      const m = line.match(/^\s*WHATSAPP_GATEWAY_TOKEN\s*=\s*(.+)\s*$/);
      if (m) {
        process.env.GATEWAY_TOKEN = m[1].trim().replace(/^"|"$/g, '');
        return;
      }
    }
  } catch (_) { /* .env tidak ada — jalankan tanpa token */ }
})();

const PORT = Number(process.env.GATEWAY_PORT || 3001);
const TOKEN = process.env.GATEWAY_TOKEN || '';
const SESSIONS_DIR = path.join(__dirname, 'sessions');
const SEND_TIMEOUT_MS = Number(process.env.SEND_TIMEOUT_MS || 25000);

if (!fs.existsSync(SESSIONS_DIR)) fs.mkdirSync(SESSIONS_DIR, { recursive: true });

// ============================================================
// Util
// ============================================================
const log = (...args) => console.log(new Date().toISOString(), '[gateway]', ...args);

function normalizeNumber(input) {
  let num = String(input || '').replace(/[^0-9]/g, '');
  if (num.startsWith('0')) num = `62${num.slice(1)}`;
  return `${num}@s.whatsapp.net`;
}

/** Jid khusus Baileys: grup @g.us dilewatkan apa adanya, selain itu user jid. */
function toBaileysJid(input) {
  const raw = String(input || '');
  if (raw.includes('@')) return raw; // sudah jid (@g.us / @s.whatsapp.net)
  return normalizeNumber(raw);
}

// ============================================================
// Registry sesi per provider (multi-instance: banyak nomor per provider)
// ============================================================

/** Baileys */
const baileysSessions = new Map(); // key → { sock, connected, qr, user, error, sent, starting }

/** whatsapp-web.js */
const wwebjsSessions = new Map(); // key → { client, connected, qr, user, error, sent, starting }

/** Default: satu sesi "default" per provider (cukup untuk ERP). */
const DEFAULT_KEY = 'default';

const stats = {
  startedAt: new Date().toISOString(),
  sent: { baileys: 0, wwebjs: 0 },
};

// ------------------------------------------------------------
// Provider: BAILEYS (https://baileys.wiki) — lazy require
// ------------------------------------------------------------
async function baileysStart(key = DEFAULT_KEY) {
  if (baileysSessions.has(key)) {
    const s = baileysSessions.get(key);
    if (s.connected || s.starting) return { ok: true, already: true };
  }

  const entry = baileysSessions.get(key) || {};
  entry.starting = true;
  entry.error = null;
  entry.qr = null;
  baileysSessions.set(key, entry);

  try {
    // Lazy load agar gateway tetap bisa jalan walau salah satu dep bermasalah.
    const {
      default: makeWASocket,
      useMultiFileAuthState,
      fetchLatestBaileysVersion,
      DisconnectReason,
    } = require('@whiskeysockets/baileys');

    const { state, saveCreds } = await useMultiFileAuthState(path.join(SESSIONS_DIR, 'baileys', key));
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
      version,
      auth: state,
      printQRInTerminal: false,
      browser: ['BaskaDrive ERP', 'Chrome', '1.0.0'],
      markOnlineOnConnect: false,
      syncFullHistory: false,
    });

    entry.sock = sock;
    entry.saveCreds = saveCreds;
    entry.disconnectReasons = DisconnectReason;

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        entry.qr = qr;
        log(`baileys[${key}] QR siap — scan dari WhatsApp (Perangkat Tertaut)`);
      }

      if (connection === 'open') {
        entry.connected = true;
        entry.qr = null;
        entry.starting = false;
        entry.error = null;
        entry.user = sock.user ? { id: sock.user.id, name: sock.user.name || null } : null;
        log(`baileys[${key}] terhubung sebagai`, entry.user && entry.user.id);
      }

      if (connection === 'close') {
        entry.connected = false;
        entry.user = null;
        const code = lastDisconnect?.error?.output?.statusCode;
        const relogin = code !== DisconnectReason.loggedOut;
        log(`baileys[${key}] terputus (code=${code}) — ${relogin ? 'reconnect otomatis' : 'logged out'}`);
        if (relogin) {
          setTimeout(() => baileysStart(key).catch(() => {}), 3000);
        } else {
          entry.error = 'Sesi logout dari perangkat. Logout lalu pair ulang.';
        }
      }
    });

    return { ok: true };
  } catch (err) {
    entry.starting = false;
    entry.connected = false;
    entry.error = `Gagal memulai Baileys: ${err.message}`;
    log('baileys start error:', err.message);
    return { ok: false, error: entry.error };
  }
}

async function baileysStop(key = DEFAULT_KEY) {
  const entry = baileysSessions.get(key);
  if (entry && entry.sock) {
    try { entry.sock.ev.removeAllListeners('connection.update'); } catch (_) {}
    try { entry.sock.end(undefined); } catch (_) {}
  }
  if (entry) {
    entry.connected = false;
    entry.sock = null;
    entry.starting = false;
  }
  return { ok: true };
}

async function baileysLogout(key = DEFAULT_KEY) {
  const entry = baileysSessions.get(key);
  if (entry && entry.sock) {
    try { await entry.sock.logout(); } catch (_) { try { entry.sock.end(undefined); } catch (_) {} }
  }
  baileysSessions.delete(key);
  fs.rmSync(path.join(SESSIONS_DIR, 'baileys', key), { recursive: true, force: true });
  return { ok: true };
}

async function baileysSend(key, jid, text) {
  const entry = baileysSessions.get(key);
  if (!entry || !entry.connected || !entry.sock) {
    return { ok: false, code: 'PROVIDER_NOT_CONNECTED' };
  }

  const timeout = new Promise((_, reject) =>
    setTimeout(() => reject(new Error('timeout')), SEND_TIMEOUT_MS));

  try {
    await Promise.race([
      entry.sock.sendMessage(jid, { text }),
      timeout,
    ]);
    stats.sent.baileys += 1;
    entry.sent = (entry.sent || 0) + 1;
    return { ok: true };
  } catch (err) {
    return { ok: false, error: err.message };
  }
}

// ------------------------------------------------------------
// Provider: WHATSAPP-WEB.JS (https://wwebjs.dev) — lazy require
// ------------------------------------------------------------
async function wwebjsStart(key = DEFAULT_KEY) {
  if (wwebjsSessions.has(key)) {
    const s = wwebjsSessions.get(key);
    if (s.connected || s.starting) return { ok: true, already: true };
  }

  const entry = wwebjsSessions.get(key) || {};
  entry.starting = true;
  entry.error = null;
  entry.qr = null;
  wwebjsSessions.set(key, entry);

  try {
    const { Client, LocalAuth } = require('whatsapp-web.js');
    const QRCode = require('qrcode');

    const client = new Client({
      authStrategy: new LocalAuth({ dataPath: path.join(SESSIONS_DIR, 'wwebjs'), clientId: key }),
      puppeteer: {
        headless: true,
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-gpu',
        ],
      },
      // Multi-device: WhatsApp Web JS memakai sesi multi-device sejak 2022.
      webVersionCache: { type: 'remote', remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.3000.1023204471-alpha.html' },
    });

    entry.client = client;

    client.on('qr', (qr) => {
      QRCode.toDataURL(qr, { width: 360, margin: 1 })
        .then((dataUrl) => {
          entry.qr = dataUrl; // wwebjs: QR langsung sebagai data URL gambar
          log(`wwebjs[${key}] QR siap`);
        })
        .catch(() => { entry.qr = qr; });
    });

    client.on('ready', () => {
      entry.connected = true;
      entry.qr = null;
      entry.starting = false;
      entry.error = null;
      entry.user = client.info
        ? { id: client.info.wid && client.info.wid.user, name: client.info.pushname || null }
        : null;
      log(`wwebjs[${key}] siap sebagai`, entry.user && entry.user.id);
    });

    client.on('disconnected', (reason) => {
      entry.connected = false;
      entry.user = null;
      entry.error = `Client terputus: ${reason}`;
      log(`wwebjs[${key}] terputus:`, reason);
    });

    await client.initialize();
    return { ok: true };
  } catch (err) {
    entry.starting = false;
    entry.connected = false;
    entry.error = `Gagal memulai whatsapp-web.js: ${err.message}`;
    log('wwebjs start error:', err.message);
    return { ok: false, error: entry.error };
  }
}

async function wwebjsStop(key = DEFAULT_KEY) {
  const entry = wwebjsSessions.get(key);
  if (entry && entry.client) {
    try { await entry.client.destroy(); } catch (_) {}
  }
  if (entry) {
    entry.connected = false;
    entry.client = null;
    entry.starting = false;
  }
  return { ok: true };
}

async function wwebjsLogout(key = DEFAULT_KEY) {
  const entry = wwebjsSessions.get(key);
  if (entry && entry.client) {
    try { await entry.client.logout(); } catch (_) {}
    try { await entry.client.destroy(); } catch (_) {}
  }
  wwebjsSessions.delete(key);
  fs.rmSync(path.join(SESSIONS_DIR, 'wwebjs', `session-${key}`), { recursive: true, force: true });
  return { ok: true };
}

async function wwebjsSend(key, chatId, text) {
  const entry = wwebjsSessions.get(key);
  if (!entry || !entry.connected || !entry.client) {
    return { ok: false, code: 'PROVIDER_NOT_CONNECTED' };
  }

  try {
    const chat = await entry.client.getChatById(chatId);
    await chat.sendMessage(text);
    stats.sent.wwebjs += 1;
    entry.sent = (entry.sent || 0) + 1;
    return { ok: true };
  } catch (err) {
    return { ok: false, error: err.message };
  }
}

// ============================================================
// Saklar on/off per provider (diset dari ERP, dipersist ke file)
// ============================================================
const enabledPath = path.join(SESSIONS_DIR, 'enabled.json');

function readEnabled() {
  try {
    return JSON.parse(fs.readFileSync(enabledPath, 'utf8'));
  } catch (_) {
    return { baileys: true, wwebjs: false };
  }
}

function writeEnabled(state) {
  fs.writeFileSync(enabledPath, JSON.stringify(state, null, 2));
}

let enabled = readEnabled();

// ============================================================
// HTTP API
// ============================================================
const app = express();
app.use(express.json({ limit: '2mb' }));

// Auth sederhana via shared token (opsional tapi disarankan).
app.use((req, res, next) => {
  if (!TOKEN) return next();
  if (req.get('X-Gateway-Token') === TOKEN) return next();
  return res.status(401).json({ ok: false, error: 'Token gateway tidak valid.' });
});

function providerEntry(provider, key) {
  if (provider === 'baileys') return baileysSessions.get(key);
  if (provider === 'wwebjs') return wwebjsSessions.get(key);
  return undefined;
}

function providerSnapshot(provider, key = DEFAULT_KEY) {
  const entry = providerEntry(provider, key) || {};
  return {
    enabled: !!enabled[provider],
    connected: !!entry.connected,
    qr_ready: !!entry.qr,
    // Baileys: string qr mentah → dikonversi ke data URL oleh qrcode saat diminta.
    qr: entry.qr || null,
    user: entry.user || null,
    error: entry.error || null,
    messages_sent: entry.sent || 0,
  };
}

async function qrToDataUrl(provider) {
  const entry = providerEntry(provider);
  if (!entry || !entry.qr) return null;
  if (String(entry.qr).startsWith('data:image')) return entry.qr; // wwebjs
  try {
    const QRCode = require('qrcode');
    return await QRCode.toDataURL(entry.qr, { width: 360, margin: 1 });
  } catch (_) {
    return null;
  }
}

// ---- Status semua provider -----------------------------------------------
app.get('/status', async (req, res) => {
  const out = {};
  for (const p of ['baileys', 'wwebjs']) {
    out[p] = providerSnapshot(p);
    const dataUrl = await qrToDataUrl(p);
    if (dataUrl) out[p].qr = dataUrl;
  }
  out.uptime = (Date.now() - Date.parse(stats.startedAt)) / 1000;
  res.json(out);
});

// ---- On/off per provider (dari ERP) --------------------------------------
app.post('/enable', (req, res) => {
  const { provider, on } = req.body || {};
  if (!['baileys', 'wwebjs'].includes(provider) || typeof on !== 'boolean') {
    return res.status(422).json({ ok: false, error: 'provider & (on) boolean wajib.' });
  }
  enabled[provider] = on;
  writeEnabled(enabled);
  log(`${provider} ${on ? 'DINYALAKAN' : 'DIMATIKAN'} dari ERP`);

  if (!on) {
    // Matikan → hentikan sesi agar resource (Chrome dsb.) ikut lepas.
    (provider === 'baileys' ? baileysStop : wwebjsStop)().catch(() => {});
  } else {
    // Nyalakan → mulai sesi (QR akan tersedia bila perlu pairing).
    (provider === 'baileys' ? baileysStart : wwebjsStart)().catch(() => {});
  }

  return res.json({ ok: true, provider, enabled: enabled[provider] });
});

// ---- Kontrol sesi ----------------------------------------------------------
app.post('/sessions/:provider/start', async (req, res) => {
  const provider = req.params.provider;
  if (!['baileys', 'wwebjs'].includes(provider)) return res.status(404).json({ ok: false });
  if (!enabled[provider]) return res.status(409).json({ ok: false, code: 'PROVIDER_DISABLED', error: 'Provider dimatikan.' });
  const fn = provider === 'baileys' ? baileysStart : wwebjsStart;
  const result = await fn();
  return res.json(result);
});

app.post('/sessions/:provider/stop', async (req, res) => {
  const provider = req.params.provider;
  if (!['baileys', 'wwebjs'].includes(provider)) return res.status(404).json({ ok: false });
  const fn = provider === 'baileys' ? baileysStop : wwebjsStop;
  return res.json(await fn());
});

app.post('/sessions/:provider/logout', async (req, res) => {
  const provider = req.params.provider;
  if (!['baileys', 'wwebjs'].includes(provider)) return res.status(404).json({ ok: false });
  const fn = provider === 'baileys' ? baileysLogout : wwebjsLogout;
  return res.json(await fn());
});

// ---- Kirim pesan -----------------------------------------------------------
app.post('/send', async (req, res) => {
  const { provider, number, message } = req.body || {};
  if (!provider || !number || !message) {
    return res.status(422).json({ ok: false, error: 'provider, number, message wajib.' });
  }
  if (!enabled[provider]) {
    return res.status(409).json({ ok: false, code: 'PROVIDER_DISABLED', error: `${provider} dimatikan.` });
  }

  const jid = provider === 'baileys' ? toBaileysJid(number) : normalizeNumber(number);
  const result = provider === 'baileys'
    ? await baileysSend(DEFAULT_KEY, jid, String(message))
    : await wwebjsSend(DEFAULT_KEY, jid, String(message));

  return res.status(result.ok ? 200 : (result.code === 'PROVIDER_NOT_CONNECTED' ? 409 : 502)).json(result);
});

app.post('/send-file', async (req, res) => {
  const { provider, number, url, caption } = req.body || {};
  if (!provider || !number || !url) {
    return res.status(422).json({ ok: false, error: 'provider, number, url wajib.' });
  }
  if (!enabled[provider]) {
    return res.status(409).json({ ok: false, code: 'PROVIDER_DISABLED', error: `${provider} dimatikan.` });
  }

  const jid = provider === 'baileys' ? toBaileysJid(number) : normalizeNumber(number);
  try {
    if (provider === 'baileys') {
      const entry = baileysSessions.get(DEFAULT_KEY);
      if (!entry || !entry.connected || !entry.sock) {
        return res.status(409).json({ ok: false, code: 'PROVIDER_NOT_CONNECTED' });
      }
      await entry.sock.sendMessage(jid, { document: { url }, caption: caption || undefined, mimetype: 'application/octet-stream' });
    } else {
      const entry = wwebjsSessions.get(DEFAULT_KEY);
      if (!entry || !entry.connected || !entry.client) {
        return res.status(409).json({ ok: false, code: 'PROVIDER_NOT_CONNECTED' });
      }
      const chat = await entry.client.getChatById(jid);
      const { MessageMedia } = require('whatsapp-web.js');
      const media = await MessageMedia.fromUrl(url, { unsafeMime: true });
      await chat.sendMessage(media, { caption: caption || undefined });
    }
    stats.sent[provider] += 1;
    return res.json({ ok: true });
  } catch (err) {
    return res.status(502).json({ ok: false, error: err.message });
  }
});

// ---- Health ----------------------------------------------------------------
app.get('/health', (req, res) => res.json({ ok: true, service: 'baskadrive-whatsapp-gateway', startedAt: stats.startedAt }));

app.listen(PORT, () => {
  log(`Gateway WhatsApp siap di http://127.0.0.1:${PORT}`);
  log(`Provider aktif: baileys=${enabled.baileys ? 'ON' : 'off'} | wwebjs=${enabled.wwebjs ? 'ON' : 'off'}`);
  // Auto-start sesi provider yang menyala saat server naik.
  if (enabled.baileys) baileysStart().catch(() => {});
  if (enabled.wwebjs) wwebjsStart().catch(() => {});
});

// Matikan bersih children Puppeteer saat proses dihentikan.
process.on('SIGINT', async () => {
  await wwebjsStop().catch(() => {});
  await baileysStop().catch(() => {});
  process.exit(0);
});
process.on('SIGTERM', async () => {
  await wwebjsStop().catch(() => {});
  await baileysStop().catch(() => {});
  process.exit(0);
});
