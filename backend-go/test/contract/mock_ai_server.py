#!/usr/bin/env python3
"""Mock de provedor OpenAI-compatível para testes de IA/i18n (sem chamadas externas)."""
import base64
import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

HOST = "127.0.0.1"
PORT = 8091

PNG_B64 = base64.b64encode(bytes.fromhex(
    "89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c489"
    "0000000a49444154789c6300010000050001a5f645400000000049454e44ae426082"
)).decode()


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

        if "UI (PT-BR):" in prompt:
            reply = json.dumps({"ui": {lang: {"nav.home": "Home", "nav.contents": "Contents"}}})
        elif "CONFIG (PT-BR):" in prompt:
            reply = json.dumps({"config": {lang: {"site_subtitulo": "Subtitle " + lang}}})
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
