#!/usr/bin/env python3
"""
Teste de geração de imagens em múltiplos formatos (1:1 e 9:16) — backend Go, STAGING.

Usa mock OpenAI-compatível (PNG 64x64). Cleanup dos arquivos. NUNCA em produção.

Uso: python3 backend-go/test/contract/images_multi_test.py
"""
import http.cookiejar
import json
import os
import re
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
REPO_ROOT = os.path.abspath(os.path.join(HERE, "..", "..", ".."))
GO_BASE = os.environ.get("GO_BASE", "http://127.0.0.1:8081")
UPLOAD_DIR = os.environ.get("UPLOAD_DIR", "/var/www/washiviana.com/uploads")
EMAIL = os.environ.get("GO_TEST_EMAIL", "contract-test@staging.local")
PASSWORD = os.environ.get("GO_TEST_PASSWORD", "test-12345678")
STAGING_DB = os.environ.get("GO_DB_NAME", "washiviana_staging")
KEYS = ["llm_image_provider", "llm_image_model", "openai_api_key", "openai_base_url"]

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def load_db_creds():
    text = open(os.path.join(REPO_ROOT, "api", "config.local.php")).read()

    def g(name):
        m = re.search(r"define\('" + name + r"',\s*'([^']*)'\)", text)
        return m.group(1) if m else ""

    return g("DB_HOST"), g("DB_PORT"), g("DB_USER"), g("DB_PASS")


HOST, PORT, USER, PASSWORD_DB = load_db_creds()


def psql(sql):
    env = dict(os.environ)
    env["PGPASSWORD"] = PASSWORD_DB
    out = subprocess.run(["psql", "-h", HOST, "-p", PORT, "-U", USER, "-d", STAGING_DB, "-tA", "-c", sql],
                         capture_output=True, text=True, env=env, timeout=30)
    if out.returncode != 0:
        raise RuntimeError("psql falhou: " + out.stderr.strip())
    text = out.stdout.strip()
    return text.splitlines()[0] if text else ""


def sqlq(v):
    return "'" + str(v).replace("'", "''") + "'"


def set_conf(key, value):
    psql("INSERT INTO configuracoes (chave, valor) VALUES (" + sqlq(key) + ", " + sqlq(value) +
         ") ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = CURRENT_TIMESTAMP;")


def api(method, path, payload=None, csrf=None):
    req = urllib.request.Request(GO_BASE + path, method=method)
    if payload is not None:
        req.data = json.dumps(payload).encode()
        req.add_header("Content-Type", "application/json")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    try:
        with opener.open(req, timeout=240) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode() or "{}")
        except json.JSONDecodeError:
            return e.code, {}


def wait_port(host, port, timeout=8):
    end = time.time() + timeout
    while time.time() < end:
        try:
            with socket.create_connection((host, port), timeout=1):
                return True
        except OSError:
            time.sleep(0.2)
    return False


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)) if extra and not cond else ""))
    return bool(cond)


def main():
    ok = True
    snaps = {k: {"exists": psql(f"SELECT count(*) FROM configuracoes WHERE chave={sqlq(k)};") != "0",
                 "value": psql(f"SELECT valor FROM configuracoes WHERE chave={sqlq(k)};")} for k in KEYS}
    mock = subprocess.Popen([sys.executable, os.path.join(HERE, "mock_ai_server.py")],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    created = []
    try:
        if not wait_port("127.0.0.1", 8091):
            print("FATAL: mock server não subiu")
            return 1
        set_conf("llm_image_provider", "openai")
        set_conf("llm_image_model", "mock-image")
        set_conf("openai_api_key", "test-key")
        set_conf("openai_base_url", "http://127.0.0.1:8091/v1")

        st, body = api("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
        if st != 200 or not body.get("csrf_token"):
            print("FATAL login:", st, body)
            return 1
        csrf = body["csrf_token"]

        st, body = api("POST", "/api/v1/ai/images-multi", {"prompt": "um gato"}, csrf)
        f1 = body.get("imagem_1x1")
        f2 = body.get("imagem_9x16")
        if f1:
            created.append(f1)
        if f2:
            created.append(f2)
        ok &= expect("images-multi 200", st == 200 and body.get("success"), body)
        ok &= expect("1x1 nome ai_1x1_*.jpg", isinstance(f1, str) and f1.startswith("ai_1x1_") and f1.endswith(".jpg"), f1)
        ok &= expect("9x16 nome ai_9x16_*.jpg", isinstance(f2, str) and f2.startswith("ai_9x16_") and f2.endswith(".jpg"), f2)
        ok &= expect("arquivos gravados", all(os.path.isfile(os.path.join(UPLOAD_DIR, x)) for x in (f1, f2)))
        ok &= expect("urls retornadas", isinstance(body.get("imagem_1x1_url"), str) and body["imagem_1x1_url"].endswith(f1))

        st, body = api("POST", "/api/v1/ai/images-multi", {"prompt": ""}, csrf)
        ok &= expect("images-multi sem prompt -> mensagem", st == 200 and body.get("message") == "Prompt não fornecido", body)
    finally:
        for name in created:
            p = os.path.join(UPLOAD_DIR, name)
            if os.path.isfile(p):
                os.remove(p)
        for k, snap in snaps.items():
            if snap["exists"]:
                set_conf(k, snap["value"])
        mock.terminate()

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
