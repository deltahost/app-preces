import asyncio
import hashlib
import hmac
import json
import mimetypes
import os
import re
import threading
import time
import uuid
from collections import defaultdict, deque
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

import edge_tts

HOST = os.getenv("HOST", "0.0.0.0")
PORT = int(os.getenv("PORT", os.getenv("TTS_PORT", "8787")))
PUBLIC_BASE_URL = os.getenv("PUBLIC_BASE_URL", "").rstrip("/")
ACCESS_TOKEN = os.getenv("TTS_API_TOKEN", "")
CACHE_DIR = Path(__file__).parent / ".cache"
CACHE_TTL_SECONDS = int(os.getenv("TTS_CACHE_TTL_SECONDS", str(60 * 60)))
MAX_TEXT_LENGTH = int(os.getenv("TTS_MAX_TEXT_LENGTH", "5000"))
RATE_LIMIT = int(os.getenv("TTS_REQUEST_LIMIT", "20"))
RATE_WINDOW_SECONDS = int(os.getenv("TTS_RATE_WINDOW_SECONDS", "600"))
PROFILES = {
    "male": {"voice": "fr-FR-RemyMultilingualNeural", "rate": "-10%"},
    "female": {"voice": "pt-BR-ThalitaMultilingualNeural", "rate": "-10%"},
}

CACHE_DIR.mkdir(parents=True, exist_ok=True)
GENERATION_LOCK = threading.Lock()
RATE_LOCK = threading.Lock()
REQUEST_TIMES = defaultdict(deque)


def cleanup_cache():
    now = time.time()
    for pattern in ("*.mp3", "*.tmp"):
        for file in CACHE_DIR.glob(pattern):
            try:
                if now - file.stat().st_mtime > CACHE_TTL_SECONDS:
                    file.unlink(missing_ok=True)
            except OSError:
                pass


def cache_key(text, gender):
    payload = json.dumps({"text": text, "gender": gender, **PROFILES[gender]}, ensure_ascii=False, sort_keys=True)
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def is_rate_limited(client_ip):
    now = time.monotonic()
    with RATE_LOCK:
        requests = REQUEST_TIMES[client_ip]
        while requests and now - requests[0] >= RATE_WINDOW_SECONDS:
            requests.popleft()
        if len(requests) >= RATE_LIMIT:
            return True
        requests.append(now)
        return False


class TTSHandler(BaseHTTPRequestHandler):
    def end_headers(self):
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        super().end_headers()

    def send_json(self, status, body):
        payload = json.dumps(body, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def client_ip(self):
        forwarded = self.headers.get("X-Forwarded-For", "")
        return forwarded.split(",", 1)[0].strip() or self.client_address[0]

    def public_origin(self):
        if PUBLIC_BASE_URL:
            return PUBLIC_BASE_URL
        forwarded_proto = self.headers.get("X-Forwarded-Proto", "").split(",", 1)[0].strip()
        host = self.headers.get("X-Forwarded-Host") or self.headers.get("Host", f"127.0.0.1:{PORT}")
        scheme = forwarded_proto or ("https" if host.endswith(".onrender.com") else "http")
        return f"{scheme}://{host}"

    def is_authorized(self):
        if not ACCESS_TOKEN:
            return True
        authorization = self.headers.get("Authorization", "")
        supplied = authorization.removeprefix("Bearer ").strip()
        return hmac.compare_digest(supplied, ACCESS_TOKEN)

    def do_OPTIONS(self):
        self.send_response(204)
        self.end_headers()

    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/health":
            return self.send_json(200, {
                "ok": True,
                "provider": "edge-tts",
                "requiresKey": False,
                "authenticationEnabled": bool(ACCESS_TOKEN),
            })
        if path.startswith("/audio/") and path.endswith(".mp3"):
            filename = Path(path).name
            if not re.fullmatch(r"[0-9a-f]{64}\.mp3", filename):
                return self.send_json(400, {"error": "Arquivo inválido."})
            audio_path = CACHE_DIR / filename
            if not audio_path.exists():
                return self.send_json(404, {"error": "Áudio expirado ou inexistente."})
            payload = audio_path.read_bytes()
            self.send_response(200)
            self.send_header("Content-Type", mimetypes.guess_type(filename)[0] or "audio/mpeg")
            self.send_header("Cache-Control", "private, max-age=3600")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            return self.wfile.write(payload)
        return self.send_json(404, {"error": "Rota não encontrada."})

    def do_POST(self):
        if urlparse(self.path).path != "/v1/tts":
            return self.send_json(404, {"error": "Rota não encontrada."})
        if not self.is_authorized():
            return self.send_json(401, {"error": "Acesso não autorizado."})
        if is_rate_limited(self.client_ip()):
            return self.send_json(429, {"error": "Limite temporário de narrações atingido."})
        try:
            size = int(self.headers.get("Content-Length", "0"))
            if size <= 0 or size > 64_000:
                return self.send_json(400, {"error": "Requisição inválida."})
            body = json.loads(self.rfile.read(size))
            text = str(body.get("text", "")).strip()
            gender = "male" if body.get("gender") == "male" else "female"
            if not text or len(text) > MAX_TEXT_LENGTH:
                return self.send_json(400, {"error": f"O texto deve ter entre 1 e {MAX_TEXT_LENGTH} caracteres."})

            profile = PROFILES[gender]
            audio_id = cache_key(text, gender)
            audio_path = CACHE_DIR / f"{audio_id}.mp3"
            if not audio_path.exists():
                with GENERATION_LOCK:
                    if not audio_path.exists():
                        temporary_path = CACHE_DIR / f"{audio_id}-{uuid.uuid4().hex}.tmp"
                        try:
                            communicate = edge_tts.Communicate(text, profile["voice"], rate=profile["rate"])
                            asyncio.run(communicate.save(str(temporary_path)))
                            temporary_path.replace(audio_path)
                        finally:
                            temporary_path.unlink(missing_ok=True)
            cleanup_cache()

            return self.send_json(200, {
                "audioUrl": f"{self.public_origin()}/audio/{audio_id}.mp3",
                "expiresAt": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(time.time() + CACHE_TTL_SECONDS)),
                "voice": profile["voice"],
                "rate": profile["rate"],
            })
        except Exception as error:
            print(f"Falha no provedor Edge TTS: {type(error).__name__}: {error}")
            return self.send_json(502, {
                "error": "O provedor de narração está temporariamente indisponível.",
                "code": "provider_unavailable",
            })

    def log_message(self, format, *args):
        print(f"[{self.log_date_time_string()}] {format % args}")


if __name__ == "__main__":
    print(f"Edge TTS de Preces disponível em http://{HOST}:{PORT}")
    print("Vozes: Remy e Thalita, narrativa em -10%, sem chave")
    ThreadingHTTPServer((HOST, PORT), TTSHandler).serve_forever()
