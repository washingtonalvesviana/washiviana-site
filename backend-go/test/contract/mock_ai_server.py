#!/usr/bin/env python3
"""Mock de provedor OpenAI-compatível para testes de IA/i18n (sem chamadas externas)."""
import base64
import json
import struct
import zlib
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

HOST = "127.0.0.1"
PORT = 8091


def _png(w, h, rgb):
    def chunk(tag, data):
        c = tag + data
        return struct.pack(">I", len(data)) + c + struct.pack(">I", zlib.crc32(c) & 0xFFFFFFFF)

    raw = b"".join(b"\x00" + bytes(rgb) * w for _ in range(h))
    return (b"\x89PNG\r\n\x1a\n"
            + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0))
            + chunk(b"IDAT", zlib.compress(raw))
            + chunk(b"IEND", b""))


# PNG 64x64 RGB (para recortes 1:1 e 9:16 terem dimensões válidas)
PNG_B64 = base64.b64encode(_png(64, 64, (200, 100, 50))).decode()


def i18n_payload():
    def entry(tag):
        return {
            "titulo": "Titulo " + tag,
            "slug": "titulo-" + tag,
            "resumo": "resumo " + tag,
            "conteudo": "<p>conteudo " + tag + "</p>",
            "descricao": "descricao " + tag,
            "meta_title": "meta " + tag,
            "meta_description": "desc meta " + tag,
            "og_title": "og " + tag,
            "og_description": "og desc " + tag,
            "keywords": ["k1", "k2", "k3"],
        }
    return {"pt": entry("pt"), "en": entry("en"), "es": entry("es")}


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        length = int(self.headers.get("Content-Length", "0") or 0)
        raw = self.rfile.read(length) if length else b""

        if "images/generations" in self.path:
            body = json.dumps({"data": [{"b64_json": PNG_B64}]}).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return

        prompt = ""
        try:
            req = json.loads(raw or b"{}")
            for msg in reversed(req.get("messages", []) or []):
                if msg.get("role") == "user":
                    prompt = msg.get("content", "")
                    break
        except json.JSONDecodeError:
            pass

        import re as _re
        lang_match = _re.search(r"para (pt|en|es)", prompt)
        lang = lang_match.group(1) if lang_match else "en"

        if "Escreva um artigo ORIGINAL baseado nesta ideia" in prompt:
            reply = ("Título: Mock Draft Artigo\n"
                     "Slug: mock-draft-artigo\n"
                     "Categoria: IA\n"
                     "Resumo: resumo mock do artigo\n"
                     "Conteúdo: <p>corpo mock</p>")
        elif "sugerir ideias de artigos" in prompt:
            reply = json.dumps({"ideas": [
                {"title": "Mock Idea 1", "angle": "angulo", "summary": "resumo",
                 "outline": ["p1", "p2"], "tags": ["t1", "t2"], "priority": "hype"},
                {"title": "Mock Idea 2", "angle": "angulo2", "summary": "resumo2",
                 "outline": ["p3"], "tags": ["t3"], "priority": "medium"},
            ]})
        elif "UI (PT-BR):" in prompt:
            reply = json.dumps({"ui": {lang: {"nav.home": "Home", "nav.contents": "Contents"}}})
        elif "CONFIG (PT-BR):" in prompt:
            reply = json.dumps({"config": {lang: {"site_subtitulo": "Subtitle " + lang}}})
        elif "categorias_projetos" in prompt:
            reply = json.dumps({
                "categorias_projetos": {"1": {"nome": "Proj Cat " + lang}},
                "categorias_artigos": {"1": {"nome": "Art Cat " + lang, "descricao": "desc " + lang}},
            })
        elif "SEO e tradução" in prompt or "gere versões em" in prompt:
            reply = json.dumps(i18n_payload())
        else:
            reply = "TEXTO_MOCK_OK"

        body = json.dumps({"choices": [{"message": {"role": "assistant", "content": reply}}], "usage": {}}).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass


if __name__ == "__main__":
    ThreadingHTTPServer((HOST, PORT), Handler).serve_forever()
