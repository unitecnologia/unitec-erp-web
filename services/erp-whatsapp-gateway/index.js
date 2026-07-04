import express from 'express';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import QRCode from 'qrcode';
import pino from 'pino';
import makeWASocket, {
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
  delay,
  jidEncode,
  jidNormalizedUser,
} from '@whiskeysockets/baileys';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const configPath = process.env.ERP_WHATSAPP_CONFIG
  ?? path.resolve(__dirname, '../../storage/app/whatsapp/gateway-config.json');

function loadConfig() {
  if (!fs.existsSync(configPath)) {
    throw new Error(`Arquivo de configuração não encontrado: ${configPath}`);
  }

  const raw = fs.readFileSync(configPath, 'utf8');
  const parsed = JSON.parse(raw);

  return {
    port: Number(parsed.port ?? 8091),
    key: String(parsed.key ?? ''),
    sessionsPath: String(parsed.sessionsPath ?? path.resolve(__dirname, '../../storage/app/whatsapp/sessions')),
    host: String(parsed.host ?? '127.0.0.1'),
  };
}

const runtimeConfig = loadConfig();
const logger = pino({ level: process.env.LOG_LEVEL ?? 'warn' });
const BAILEYS_VERSION_CACHE = path.join(runtimeConfig.sessionsPath, '.baileys-version.json');

function currentConfig() {
  try {
    return loadConfig();
  } catch {
    return runtimeConfig;
  }
}

/** @type {Map<string, SessionState>} */
const sessions = new Map();

/**
 * @typedef {Object} SessionState
 * @property {string} status
 * @property {string} number
 * @property {string|null} qr
 * @property {import('@whiskeysockets/baileys').WASocket|null} sock
 * @property {boolean} starting
 */

function createSessionState() {
  return {
    status: 'desconectado',
    number: '',
    qr: null,
    sock: null,
    starting: false,
  };
}

function sessionDir(empresaId) {
  return path.join(runtimeConfig.sessionsPath, String(empresaId));
}

function digitsOnly(value) {
  return String(value ?? '').replace(/\D/g, '');
}

function stripCountryCode(digits) {
  if (digits.startsWith('55') && digits.length > 11) {
    return digits.slice(2);
  }

  return digits;
}

function isBrazilianMobileSubscriber(subscriber) {
  return /^[6-9]/.test(subscriber);
}

function withMobileNine(local) {
  if (local.length === 10 && isBrazilianMobileSubscriber(local.slice(2))) {
    return `${local.slice(0, 2)}9${local.slice(2)}`;
  }

  if (local.length === 11 && local[2] === '9' && isBrazilianMobileSubscriber(local.slice(3))) {
    return local;
  }

  return local;
}

function withoutMobileNine(local) {
  if (local.length === 11 && local[2] === '9' && isBrazilianMobileSubscriber(local.slice(3))) {
    return `${local.slice(0, 2)}${local.slice(3)}`;
  }

  if (local.length === 10 && isBrazilianMobileSubscriber(local.slice(2))) {
    return local;
  }

  return null;
}

function normalizeNumber(value) {
  const digits = digitsOnly(value);

  if (!digits) {
    return null;
  }

  let local = stripCountryCode(digits);

  if (local.length === 10 || local.length === 11) {
    return `55${withMobileNine(local)}`;
  }

  if (digits.length >= 12 && digits.startsWith('55')) {
    local = digits.slice(2);

    if (local.length <= 11) {
      return `55${withMobileNine(local)}`;
    }

    return digits;
  }

  return digits.length >= 12 ? digits : null;
}

function buildLookupCandidates(value) {
  const normalized = normalizeNumber(value);

  if (!normalized) {
    return [];
  }

  const local = normalized.slice(2);
  const candidates = [normalized];
  const legacy = withoutMobileNine(local);

  if (legacy) {
    const legacyFull = `55${legacy}`;

    if (!candidates.includes(legacyFull)) {
      candidates.push(legacyFull);
    }
  }

  return candidates;
}

async function resolveRecipientJid(sock, rawNumber, extraCandidates = []) {
  const candidates = [...new Set([
    ...buildLookupCandidates(rawNumber),
    ...extraCandidates.map((value) => normalizeNumber(value)).filter(Boolean),
  ])];

  if (candidates.length === 0) {
    return null;
  }

  for (const candidate of candidates) {
    try {
      const [check] = await sock.onWhatsApp(candidate);

      if (!check?.exists || !check?.jid) {
        continue;
      }

      const bare = String(check.jid).split('@')[0].split(':')[0];

      if (bare !== candidate && !bare.endsWith(candidate.slice(-10))) {
        continue;
      }

      return jidNormalizedUser(check.jid);
    } catch (error) {
      logger.warn({ err: error, candidate }, 'Falha ao consultar número no WhatsApp');
    }
  }

  return null;
}

function isSocketLive(sock) {
  if (!sock?.user?.id) {
    return false;
  }

  const ws = sock.ws?.socket ?? sock.ws?.conn ?? sock.ws;

  if (ws && typeof ws.readyState === 'number') {
    return ws.readyState === 1;
  }

  return true;
}

function sessionHasCredentials(empresaId) {
  const dir = sessionDir(empresaId);

  return fs.existsSync(path.join(dir, 'creds.json'));
}

async function ensureConnectedSession(empresaId) {
  const key = String(empresaId);
  let state = sessions.get(key);

  if (state?.status === 'conectado' && state?.sock?.user?.id && isSocketLive(state.sock)) {
    return state;
  }

  if (!sessionHasCredentials(empresaId)) {
    return state ?? createSessionState();
  }

  if (!state?.starting) {
    await startSession(empresaId);
  }

  for (let attempt = 0; attempt < 24; attempt += 1) {
    state = sessions.get(key);

    if (state?.status === 'conectado' && state?.sock?.user?.id) {
      return state;
    }

    if (state?.status === 'aguardando_qr') {
      break;
    }

    await delay(500);
  }

  return sessions.get(key) ?? createSessionState();
}

async function prepareRecipient(sock, jid) {
  try {
    await sock.presenceSubscribe(jid);
  } catch (error) {
    logger.warn({ err: error, jid }, 'presenceSubscribe falhou');
  }

  await delay(250);
}

async function resolveBaileysVersion() {
  try {
    if (fs.existsSync(BAILEYS_VERSION_CACHE)) {
      const cached = JSON.parse(fs.readFileSync(BAILEYS_VERSION_CACHE, 'utf8'));

      if (Array.isArray(cached?.version) && cached.version.length === 3) {
        const ageMs = Date.now() - Number(cached.cachedAt ?? 0);

        if (ageMs >= 0 && ageMs < 86_400_000) {
          return { version: cached.version, isLatest: Boolean(cached.isLatest) };
        }
      }
    }
  } catch (error) {
    logger.warn({ err: error }, 'Falha ao ler cache de versão do Baileys');
  }

  try {
    const resolved = await Promise.race([
      fetchLatestBaileysVersion(),
      new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Timeout ao consultar versão do WhatsApp.')), 8000);
      }),
    ]);

    fs.mkdirSync(runtimeConfig.sessionsPath, { recursive: true });
    fs.writeFileSync(BAILEYS_VERSION_CACHE, JSON.stringify({
      version: resolved.version,
      isLatest: resolved.isLatest,
      cachedAt: Date.now(),
    }));

    return resolved;
  } catch (error) {
    logger.warn({ err: error }, 'Usando versão em cache/fallback do Baileys');

    if (fs.existsSync(BAILEYS_VERSION_CACHE)) {
      const cached = JSON.parse(fs.readFileSync(BAILEYS_VERSION_CACHE, 'utf8'));

      if (Array.isArray(cached?.version) && cached.version.length === 3) {
        return { version: cached.version, isLatest: Boolean(cached.isLatest) };
      }
    }

    return { version: [2, 3000, 1023223821], isLatest: false };
  }
}

async function sendTextMessage(sock, jid, text) {
  await prepareRecipient(sock, jid);

  const sent = await sock.sendMessage(jid, { text });

  if (!sent?.key?.id) {
    throw new Error('WhatsApp não aceitou a mensagem.');
  }

  return {
    key: sent.key,
  };
}

function toJid(number) {
  const normalized = normalizeNumber(number);

  if (!normalized) {
    return null;
  }

  return jidEncode(normalized, 's.whatsapp.net');
}

function publicStatus(state) {
  return {
    status: state.status,
    number: state.number,
    qr: state.qr,
  };
}

async function destroySession(empresaId, logout = true) {
  const key = String(empresaId);
  const state = sessions.get(key) ?? createSessionState();

  if (state.sock) {
    try {
      if (logout) {
        await state.sock.logout();
      } else {
        state.sock.end(undefined);
      }
    } catch {
      // ignore
    }
  }

  sessions.delete(key);

  if (logout) {
    const dir = sessionDir(empresaId);

    if (fs.existsSync(dir)) {
      fs.rmSync(dir, { recursive: true, force: true });
    }
  }
}

async function startSession(empresaId) {
  const key = String(empresaId);
  let state = sessions.get(key);

  if (!state) {
    state = createSessionState();
    sessions.set(key, state);
  }

  if (state.status === 'conectado' && state.sock?.user?.id && isSocketLive(state.sock)) {
    return publicStatus(state);
  }

  if (state.starting) {
    return publicStatus(state);
  }

  if (state.sock) {
    try {
      state.sock.end(undefined);
    } catch {
      // ignore
    }

    state.sock = null;
  }

  state.starting = true;
  state.status = 'aguardando_qr';
  state.qr = null;

  const dir = sessionDir(empresaId);
  fs.mkdirSync(dir, { recursive: true });

  const { state: authState, saveCreds } = await useMultiFileAuthState(dir);
  const { version } = await resolveBaileysVersion();

  const sock = makeWASocket({
    version,
    auth: authState,
    logger,
    printQRInTerminal: false,
    syncFullHistory: false,
    markOnlineOnConnect: false,
  });

  state.sock = sock;

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async (update) => {
    const current = sessions.get(key);

    if (!current) {
      return;
    }

    if (update.qr) {
      try {
        current.qr = await QRCode.toDataURL(update.qr);
      } catch (error) {
        current.qr = null;
        logger.error({ err: error }, 'Falha ao gerar QR');
      }

      current.status = 'aguardando_qr';
    }

    if (update.connection === 'open') {
      const me = sock.user?.id ?? '';
      const rawNumber = me.split(':')[0]?.split('@')[0] ?? '';
      current.number = normalizeNumber(rawNumber) ?? rawNumber;
      current.status = 'conectado';
      current.qr = null;
      current.starting = false;
    }

    if (update.connection === 'close') {
      const statusCode = update.lastDisconnect?.error?.output?.statusCode;
      current.starting = false;
      current.qr = null;
      current.sock = null;

      if (statusCode === DisconnectReason.loggedOut) {
        current.status = 'desconectado';
        current.number = '';
        sessions.delete(key);

        const authDir = sessionDir(empresaId);

        if (fs.existsSync(authDir)) {
          fs.rmSync(authDir, { recursive: true, force: true });
        }

        return;
      }

      current.status = 'desconectado';

      if (statusCode !== DisconnectReason.loggedOut) {
        setTimeout(() => {
          startSession(empresaId).catch((error) => {
            logger.error({ err: error }, 'Falha ao reconectar sessão');
          });
        }, 1500);
      }
    }
  });

  state.starting = false;

  return publicStatus(state);
}

async function restoreSessions() {
  if (!fs.existsSync(runtimeConfig.sessionsPath)) {
    return;
  }

  const entries = fs.readdirSync(runtimeConfig.sessionsPath, { withFileTypes: true });

  for (const entry of entries) {
    if (!entry.isDirectory()) {
      continue;
    }

    const empresaId = entry.name;

    if (!/^\d+$/.test(empresaId)) {
      continue;
    }

    try {
      await startSession(empresaId);
    } catch (error) {
      logger.error({ err: error, empresaId }, 'Falha ao restaurar sessão');
    }
  }
}

function requireAuth(req, res, next) {
  const config = currentConfig();
  const provided = req.header('X-Erp-Gateway-Key') ?? '';

  if (! config.key || provided !== config.key) {
    return res.status(401).json({ message: 'Chave interna inválida.' });
  }

  return next();
}

const app = express();
app.use(express.json({ limit: '2mb' }));

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'erp-whatsapp-gateway' });
});

app.get('/sessions/:empresaId/status', requireAuth, (req, res) => {
  const key = String(req.params.empresaId);
  const state = sessions.get(key) ?? createSessionState();

  res.json(publicStatus(state));
});

app.post('/sessions/:empresaId/start', requireAuth, async (req, res) => {
  try {
    const payload = await startSession(req.params.empresaId);

    res.json({
      ...payload,
      message: payload.status === 'conectado'
        ? 'WhatsApp já conectado.'
        : 'Leia o QR Code no ERP para conectar.',
    });
  } catch (error) {
    logger.error({ err: error }, 'Erro ao iniciar sessão');
    res.status(500).json({ message: error instanceof Error ? error.message : 'Erro ao iniciar sessão.' });
  }
});

app.delete('/sessions/:empresaId', requireAuth, async (req, res) => {
  try {
    await destroySession(req.params.empresaId, true);
    res.json({ message: 'Sessão desconectada.' });
  } catch (error) {
    logger.error({ err: error }, 'Erro ao desconectar sessão');
    res.status(500).json({ message: error instanceof Error ? error.message : 'Erro ao desconectar.' });
  }
});

app.post('/sessions/:empresaId/send', requireAuth, async (req, res) => {
  const key = String(req.params.empresaId);

  let state;

  try {
    state = await ensureConnectedSession(req.params.empresaId);
  } catch (error) {
    logger.error({ err: error, empresaId: key }, 'Falha ao preparar sessão para envio');

    return res.status(500).json({
      message: error instanceof Error ? error.message : 'Erro ao preparar sessão WhatsApp.',
    });
  }

  if (!state || state.status !== 'conectado' || !state.sock?.user?.id) {
    return res.status(409).json({
      message: state?.status === 'aguardando_qr'
        ? 'WhatsApp aguardando leitura do QR Code. Conecte em Empresa → Parâmetros → WhatsApp.'
        : 'WhatsApp não está conectado. Conecte em Empresa → Parâmetros → WhatsApp.',
    });
  }

  const extraCandidates = Array.isArray(req.body?.candidates)
    ? req.body.candidates.map((value) => String(value))
    : [];

  const jid = await resolveRecipientJid(state.sock, req.body?.number, extraCandidates);

  if (!jid) {
    return res.status(422).json({
      message: 'Número não possui WhatsApp ou está em formato inválido. Confira o celular do destinatário.',
    });
  }

  try {
    const text = String(req.body?.text ?? '').trim();

    if (req.body?.documentPath) {
      const documentPath = String(req.body.documentPath);
      const documentName = String(req.body.documentName ?? path.basename(documentPath));
      const mimetype = String(req.body.mimetype ?? 'application/pdf');

      if (!fs.existsSync(documentPath)) {
        return res.status(422).json({ message: 'Documento não encontrado no servidor.' });
      }

      const buffer = fs.readFileSync(documentPath);

      if (text !== '') {
        await sendTextMessage(state.sock, jid, text);
      }

      await prepareRecipient(state.sock, jid);

      const documentSent = await state.sock.sendMessage(jid, {
        document: buffer,
        mimetype,
        fileName: documentName,
      });

      if (!documentSent?.key?.id) {
        return res.status(500).json({ message: 'WhatsApp não aceitou o envio do documento.' });
      }

      return res.json({
        message: 'Documento enviado.',
        jid,
        messageId: documentSent.key.id,
      });
    }

    if (text === '') {
      return res.status(422).json({ message: 'Informe o texto da mensagem.' });
    }

    const result = await sendTextMessage(state.sock, jid, text);

    return res.json({
      message: 'Mensagem enviada.',
      jid,
      messageId: result.key.id,
    });
  } catch (error) {
    logger.error({ err: error, empresaId: key }, 'Erro ao enviar mensagem');
    return res.status(500).json({ message: error instanceof Error ? error.message : 'Erro ao enviar mensagem.' });
  }
});

const server = app.listen(runtimeConfig.port, runtimeConfig.host, () => {
  logger.info(`Gateway WhatsApp ouvindo em http://${runtimeConfig.host}:${runtimeConfig.port}`);
  restoreSessions().catch((error) => {
    logger.error({ err: error }, 'Falha ao restaurar sessões');
  });
});

process.on('SIGINT', () => {
  server.close(() => process.exit(0));
});

process.on('SIGTERM', () => {
  server.close(() => process.exit(0));
});
