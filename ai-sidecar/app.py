#!/usr/bin/env python3
"""
PoC do sidecar de IA da Washiviana (Fase 3e).

NÃO é implantado em produção. Serve para validar a interface HTTP que o backend
Go (`internal/ai`) usaria caso precisemos de Python para tarefas de IA pesadas
(inferência local, embeddings/vector, RAG, imagem).

Uso:
    SIDECAR_HOST=127.0.0.1 SIDECAR_PORT=8090 python3 ai-sidecar/app.py

Endpoints:
    GET  /health      -> {"status":"ok",...}
    POST /v1/embed    -> {"embedding":[...], "dim":16, "model":"poc-hash-16"}   (determinístico)
"""
import hashlib
import json
import os
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

HOST = os.environ.get("SIDECAR_HOST", "127.0.0.1")
PORT = int(os.environ.get("SIDECAR_PORT", "8090"))
VERSION = "0.1.0-poc"


class Handler(BaseHTTPRequestHandler):
    server_version = "WashivianaAISidecar/" + VERSION

    def _json(self, code, obj):
        payload = json.dumps(obj).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def do_GET(self):
        if self.path == "/health":
            return self._json(200, {"status": "ok", "service": "ai-sidecar", "version": VERSION})
        self._json(404, {"error": "not found"})

    def do_POST(self):
        length = int(self.headers.get("Content-Length", "0") or 0)
        raw = self.rfile.read(length) if length else b""
        try:
            body = json.loads(raw or b"{}")
        except json.JSONDecodeError:
            return self._json(400, {"error": "invalid json"})

        if self.path == "/v1/embed":
            text = str(body.get("text", "")).strip()
            if not text:
                return self._json(400, {"error": "text required"})
            # Embedding determinístico apenas para a PoC (não é um modelo real).
            digest = hashlib.sha256(text.encode("utf-8")).digest()
            vec = [round((b / 255.0) * 2 - 1, 6) for b in digest[:16]]
            return self._json(200, {"embedding": vec, "dim": len(vec), "model": "poc-hash-16"})

        self._json(404, {"error": "not found"})

    def log_message(self, *args):  # silencioso
        pass


if __name__ == "__main__":
    srv = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"ai-sidecar PoC em http://{HOST}:{PORT} (Ctrl+C para parar)")
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        pass
