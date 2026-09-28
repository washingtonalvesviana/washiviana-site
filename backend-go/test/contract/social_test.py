#!/usr/bin/env python3
"""
Teste de redes sociais no backend Go (Fase 2c) — contra o STAGING.

(a) social-agent (IA) com mock OpenAI-compatível (sem chamadas externas).
(b) GET /api/v1/redes-sociais (sem expor segredos) e POST de uma rede de teste
    ('zztest'), com cleanup. Configs de IA sobrescritas e RESTAURADAS.

Uso: python3 backend-go/test/contract/social_test.py
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
EMAIL = os.environ.get("GO_TEST_EMAIL", "contract-test@staging.local")
PASSWORD = os.environ.get("GO_TEST_PASSWORD", "test-12345678")
STAGING_DB = os.environ.get("GO_DB_NAME", "washiviana_staging")
KEYS = ["llm_text_provider", "llm_text_model", "openai_api_key", "openai_base_url"]

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


def capture(key):
    return {"exists": psql(f"SELECT count(*) FROM configuracoes WHERE chave={sqlq(key)};") != "0",
            "value": psql(f"SELECT valor FROM configuracoes WHERE chave={sqlq(key)};")}


def set_conf(key, value):
    psql("INSERT INTO configuracoes (chave, valor) VALUES (" + sqlq(key) + ", " + sqlq(value) +
         ") ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = CURRENT_TIMESTAMP;")


def restore(key, snap):
    if snap["exists"]:
        set_conf(key, snap["value"])
    else:
        psql(f"DELETE FROM configuracoes WHERE chave={sqlq(key)};")


def api(method, path, payload=None, csrf=None):
    req = urllib.request.Request(GO_BASE + path, method=method)
    if payload is not None:
        req.data = json.dumps(payload).encode()
        req.add_header("Content-Type", "application/json")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    try:
        with opener.open(req, timeout=200) as r:
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
    snaps = {k: capture(k) for k in KEYS}
    mock = subprocess.Popen([sys.executable, os.path.join(HERE, "mock_ai_server.py")],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        if not wait_port("127.0.0.1", 8091):
            print("FATAL: mock server não subiu")
            return 1
        set_conf("llm_text_provider", "openai")
        set_conf("llm_text_model", "mock-model")
        set_conf("openai_api_key", "test-key")
        set_conf("openai_base_url", "http://127.0.0.1:8091/v1")

        st, body = api("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
        if st != 200 or not body.get("csrf_token"):
            print("FATAL login:", st, body)
            return 1
        csrf = body["csrf_token"]

        # (a) social-agent
        st, body = api("POST", "/api/v1/ai/social-agent", {"prompt": "novo artigo", "rede": "instagram"}, csrf)
        ok &= expect("social-agent 200", st == 200 and body.get("success") and body.get("text"), body)
        st, body = api("POST", "/api/v1/ai/social-agent", {"prompt": ""}, csrf)
        ok &= expect("social-agent sem prompt -> Prompt não fornecido", st == 200 and body.get("message") == "Prompt não fornecido", body)

        # (b) redes-sociais GET (sem segredos)
        st, body = api("GET", "/api/v1/redes-sociais")
        redes = body.get("redes", [])
        ok &= expect("redes list 200", st == 200 and body.get("success") and any(r["rede"] == "linkedin" for r in redes))
        leak = any(("access_token" in r) or ("client_secret" in r) for r in redes)
        ok &= expect("redes sem segredos expostos", not leak)

        # POST rede de teste
        st, body = api("POST", "/api/v1/redes-sociais", {"rede": "zztest", "ativo": False, "client_id": "abc"}, csrf)
        ok &= expect("rede save 200", st == 200 and body.get("success"), body)
        st, body = api("GET", "/api/v1/redes-sociais")
        entry = next((r for r in body.get("redes", []) if r["rede"] == "zztest"), None)
        ok &= expect("rede zztest listada com ativo=false", entry is not None and entry.get("ativo") is False, entry)
        ok &= expect("zztest sem token", entry is not None and entry.get("has_token") is False)
    finally:
        psql("DELETE FROM redes_sociais_config WHERE rede='zztest';")
        for k in KEYS:
            restore(k, snaps[k])
        mock.terminate()

    left = psql("SELECT count(*) FROM redes_sociais_config WHERE rede='zztest';")
    ok &= expect("cleanup rede de teste", left == "0", left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
