#!/usr/bin/env python3
"""
Teste de upload de mídia no backend Go (Fase 2c) — contra o STAGING.

Faz login, envia um PNG pequeno (multipart), confere o arquivo em uploads/ e o
remove. Cleanup garantido. NUNCA apontar para produção.

Uso: python3 backend-go/test/contract/upload_test.py
"""
import http.cookiejar
import json
import os
import sys
import urllib.error
import urllib.request

GO_BASE = os.environ.get("GO_BASE", "http://127.0.0.1:8081")
UPLOAD_DIR = os.environ.get("UPLOAD_DIR", "/var/www/washiviana.com/uploads")
EMAIL = os.environ.get("GO_TEST_EMAIL", "contract-test@staging.local")
PASSWORD = os.environ.get("GO_TEST_PASSWORD", "test-12345678")

# PNG 1x1 transparente
PNG = bytes.fromhex(
    "89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c489"
    "0000000a49444154789c6300010000050001a5f645400000000049454e44ae426082"
)

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def json_call(method, path, payload=None, csrf=None):
    req = urllib.request.Request(GO_BASE + path, method=method)
    if payload is not None:
        req.data = json.dumps(payload).encode()
        req.add_header("Content-Type", "application/json")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    try:
        with opener.open(req, timeout=15) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode() or "{}")
        except json.JSONDecodeError:
            return e.code, {}


def multipart_call(path, fields, files, csrf):
    boundary = "----wvboundary7f3a"
    parts = []
    for k, v in fields.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    for k, (filename, content, ctype) in files.items():
        parts.append(
            f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"; filename="{filename}"\r\n'
            f"Content-Type: {ctype}\r\n\r\n".encode() + content + b"\r\n")
    parts.append(f"--{boundary}--\r\n".encode())
    body = b"".join(parts)

    req = urllib.request.Request(GO_BASE + path, data=body, method="POST")
    req.add_header("Content-Type", f"multipart/form-data; boundary={boundary}")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    try:
        with opener.open(req, timeout=30) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode() or "{}")
        except json.JSONDecodeError:
            return e.code, {}


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)) if extra and not cond else ""))
    return bool(cond)


def main():
    ok = True
    filename = None

    st, body = json_call("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    if st != 200 or not body.get("csrf_token"):
        print("FATAL login:", st, body)
        return 1
    csrf = body["csrf_token"]

    try:
        # upload válido
        st, body = multipart_call("/api/v1/upload", {"prefix": "zztest"},
                                  {"file": ("foto.png", PNG, "image/png")}, csrf)
        filename = body.get("filename")
        ok &= expect("upload 200", st == 200 and body.get("success"), body)
        ok &= expect("filename com prefixo correto", isinstance(filename, str) and filename.startswith("zztest_") and filename.endswith(".png"),
                     filename)
        ok &= expect("arquivo gravado em uploads/", filename is not None and os.path.isfile(os.path.join(UPLOAD_DIR, filename)))
        ok &= expect("url retornada", isinstance(body.get("url"), str) and body["url"].endswith(filename))

        # extensão inválida -> 400
        st, body = multipart_call("/api/v1/upload", {"prefix": "zztest"},
                                  {"file": ("malicioso.txt", b"hello", "text/plain")}, csrf)
        ok &= expect("extensão inválida -> 400", st == 400 and body.get("message") == "Tipo de arquivo não permitido.", body)

        # delete
        st, body = json_call("POST", "/api/v1/upload/delete", {"filename": filename}, csrf)
        ok &= expect("delete 200", st == 200 and body.get("message") == "Arquivo deletado com sucesso!", body)
        ok &= expect("arquivo removido", not os.path.isfile(os.path.join(UPLOAD_DIR, filename)))
        filename = None
    finally:
        if filename:
            p = os.path.join(UPLOAD_DIR, filename)
            if os.path.isfile(p):
                os.remove(p)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
