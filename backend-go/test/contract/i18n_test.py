#!/usr/bin/env python3
"""
Teste de SEO/traduções (escrita) no backend Go (Fase 2c) — contra o STAGING.

Usa mock OpenAI-compatível local. Cria um artigo temporário, gera i18n (pt/en/es),
confere no banco e remove o artigo (cascade). Configs sobrescritas e RESTAURADAS.

Uso: python3 backend-go/test/contract/i18n_test.py
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
    snaps = {k: capture(k) for k in KEYS}
    mock = subprocess.Popen([sys.executable, os.path.join(HERE, "mock_ai_server.py")],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    artigo_id = None
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

        st, body = api("POST", "/api/v1/artigos", {"titulo": "ZZ I18N Teste", "conteudo": "<p>base</p>"}, csrf)
        artigo_id = body.get("artigo_id")
        if not artigo_id:
            print("FATAL criar artigo:", st, body)
            return 1

        st, body = api("POST", "/api/v1/i18n/generate",
                       {"entity": "artigo", "id": artigo_id, "langs": ["pt", "en", "es"]}, csrf)
        ok &= expect("i18n generate 200", st == 200 and body.get("success"), body)
        ok &= expect("i18n saved pt/en/es", sorted(body.get("saved", [])) == ["en", "es", "pt"], body.get("saved"))

        rows = psql(f"SELECT count(*) FROM artigos_i18n WHERE artigo_id={artigo_id};")
        ok &= expect("3 linhas i18n no banco", rows == "3", rows)
        mt = psql(f"SELECT meta_title FROM artigos_i18n WHERE artigo_id={artigo_id} AND lang='en';")
        ok &= expect("meta_title gravado (en)", mt == "meta en", mt)

        st, body = api("POST", "/api/v1/i18n/generate", {"entity": "x", "id": 0, "langs": []}, csrf)
        ok &= expect("i18n parâmetros inválidos -> 400", st == 400 and body.get("message") == "Parâmetros inválidos.", body)

        # status de tradução
        st, body = api("POST", "/api/v1/i18n/status",
                       {"entity": "artigo", "id": artigo_id, "lang": "en", "status": "reviewed"}, csrf)
        ok &= expect("i18n status reviewed 200", st == 200 and body.get("status") == "reviewed", body)
        stt = psql(f"SELECT status_traducao FROM artigos_i18n WHERE artigo_id={artigo_id} AND lang='en';")
        ok &= expect("status gravado no banco", stt == "reviewed", stt)

        st, _ = api("POST", "/api/v1/i18n/status",
                    {"entity": "artigo", "id": artigo_id, "lang": "xx", "status": "reviewed"}, csrf)
        ok &= expect("i18n status idioma inválido -> 400", st == 400)
        st, _ = api("POST", "/api/v1/i18n/status",
                    {"entity": "artigo", "id": artigo_id, "lang": "pt", "status": "nope"}, csrf)
        ok &= expect("i18n status inválido -> 400", st == 400)
    finally:
        if artigo_id:
            api("DELETE", f"/api/v1/artigos/{artigo_id}", csrf=locals().get("csrf", ""))
        for k in KEYS:
            restore(k, snaps[k])
        mock.terminate()

    left = psql(f"SELECT count(*) FROM artigos_i18n WHERE artigo_id={artigo_id};") if artigo_id else "0"
    ok &= expect("cleanup (i18n removido por cascade)", left == "0", left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
