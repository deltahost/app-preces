import { createServer } from 'node:http';
import { mkdir, readFile, readdir, stat, unlink, writeFile } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';
import { join } from 'node:path';

const port = Number(process.env.TTS_PORT || 8787);
const speechKey = process.env.AZURE_SPEECH_KEY;
const speechRegion = process.env.AZURE_SPEECH_REGION;
const cacheDirectory = join(import.meta.dirname, '.cache');
const ttlMs = 60 * 60 * 1000;
const profiles = {
  male: { voice: 'fr-FR-RemyMultilingualNeural', rate: '-10%' },
  female: { voice: 'pt-BR-ThalitaMultilingualNeural', rate: '-10%' },
};

await mkdir(cacheDirectory, { recursive: true });

function cors(response) {
  response.setHeader('Access-Control-Allow-Origin', '*');
  response.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  response.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
}

function json(response, status, body) {
  cors(response);
  response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
  response.end(JSON.stringify(body));
}

function escapeXml(value) {
  return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&apos;');
}

async function readJson(request) {
  const chunks = [];
  let size = 0;
  for await (const chunk of request) {
    size += chunk.length;
    if (size > 64_000) throw new Error('PAYLOAD_TOO_LARGE');
    chunks.push(chunk);
  }
  return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

async function clearExpiredAudio() {
  const now = Date.now();
  for (const file of await readdir(cacheDirectory)) {
    const path = join(cacheDirectory, file);
    if (now - (await stat(path)).mtimeMs > ttlMs) await unlink(path).catch(() => {});
  }
}

async function synthesize(text, gender) {
  if (!speechKey || !speechRegion) throw new Error('AZURE_NOT_CONFIGURED');
  const profile = profiles[gender] || profiles.female;
  const ssml = `<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="pt-BR"><voice name="${profile.voice}"><lang xml:lang="pt-BR"><prosody rate="${profile.rate}">${escapeXml(text)}</prosody></lang></voice></speak>`;
  const response = await fetch(`https://${speechRegion}.tts.speech.microsoft.com/cognitiveservices/v1`, {
    method: 'POST',
    headers: {
      'Ocp-Apim-Subscription-Key': speechKey,
      'Content-Type': 'application/ssml+xml',
      'X-Microsoft-OutputFormat': 'audio-24khz-48kbitrate-mono-mp3',
      'User-Agent': 'PrecesMobileApp',
    },
    body: ssml,
  });
  if (!response.ok) throw new Error(`AZURE_${response.status}`);
  return Buffer.from(await response.arrayBuffer());
}

createServer(async (request, response) => {
  cors(response);
  if (request.method === 'OPTIONS') return response.writeHead(204).end();
  const url = new URL(request.url || '/', `http://${request.headers.host}`);

  if (request.method === 'GET' && url.pathname === '/health') {
    return json(response, 200, { ok: true, azureConfigured: Boolean(speechKey && speechRegion) });
  }

  if (request.method === 'GET' && /^\/audio\/[a-f0-9-]+\.mp3$/.test(url.pathname)) {
    try {
      const audio = await readFile(join(cacheDirectory, url.pathname.split('/').pop()));
      response.writeHead(200, { 'Content-Type': 'audio/mpeg', 'Cache-Control': 'private, max-age=3600' });
      return response.end(audio);
    } catch {
      return json(response, 404, { error: 'Áudio expirado ou inexistente.' });
    }
  }

  if (request.method === 'POST' && url.pathname === '/v1/tts') {
    try {
      const body = await readJson(request);
      const text = typeof body.text === 'string' ? body.text.trim() : '';
      const gender = body.gender === 'male' ? 'male' : 'female';
      if (!text || text.length > 5000) return json(response, 400, { error: 'O texto deve ter entre 1 e 5000 caracteres.' });
      const audio = await synthesize(text, gender);
      const id = randomUUID();
      await writeFile(join(cacheDirectory, `${id}.mp3`), audio);
      void clearExpiredAudio();
      const origin = `http://${request.headers.host}`;
      return json(response, 200, { audioUrl: `${origin}/audio/${id}.mp3`, expiresAt: new Date(Date.now() + ttlMs).toISOString() });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'TTS_ERROR';
      return json(response, message === 'AZURE_NOT_CONFIGURED' ? 503 : 502, { error: message });
    }
  }

  return json(response, 404, { error: 'Rota não encontrada.' });
}).listen(port, '0.0.0.0', () => {
  console.log(`TTS de Preces disponível em http://0.0.0.0:${port}`);
  console.log(`Azure configurado: ${Boolean(speechKey && speechRegion)}`);
});
