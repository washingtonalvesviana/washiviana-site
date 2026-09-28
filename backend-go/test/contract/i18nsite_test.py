#!/usr/bin/env python3
"""
Teste de i18n do site (UI strings + configs) no backend Go — contra o STAGING.

Usa mock OpenAI-compatível. Salva e RESTAURA as chaves afetadas. Nunca apontar p/ produção.

Uso: python3 backend-go/test/contract/i18nsite_test.py
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
AFFECTED_UI = [("nav.home", "en"), ("nav.home", "es"), ("nav.contents", "en"), ("nav.contents", "es")]
AFFECTED_CFG = [("site_subtitulo", "en"), ("site_subtitulo", "es")]

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


def snap_ui(chave, lang):
    exists = psql(f"SELECT count(*) FROM ui_strings WHERE chave={sqlq(chave)} AND lang={sqlq(lang)};") != "0"
    return {"exists": exists, "value": psql(f"SELECT texto FROM ui_strings WHERE chave={sqlq(chave)} AND lang={sqlq(lang)};")}


def snap_cfg(chave, lang):
    exists = psql(f"SELECT count(*) FROM configuracoes_i18n WHERE chave={sqlq(chave)} AND lang={sqlq(lang)};") != "0"
    return {"exists": exists, "value": psql(f"SELECT valor FROM configuracoes_i18n WHERE chave={sqlq(chave)} AND lang={sqlq(lang)};")}


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
    cfg_snaps = {k: {"exists": psql(f"SELECT count(*) FROM configuracoes WHERE chave={sqlq(k)};") != "0",
                     "value": psql(f"SELECT valor FROM configuracoes WHERE chave={sqlq(k)};")} for k in KEYS}
    ui_snaps = {(c, l): snap_ui(c, l) for c, l in AFFECTED_UI}
    cfg2_snaps = {(c, l): snap_cfg(c, l) for c, l in AFFECTED_CFG}

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

        st, body = api("POST", "/api/v1/i18n/site", {"langs": ["en", "es"]}, csrf)
        ok &= expect("i18n site 200", st == 200 and body.get("success"), body)
        ok &= expect("saved_ui contém en/es", "en" in body.get("saved_ui", {}) and "es" in body.get("saved_ui", {}), body.get("saved_ui"))

        v = psql("SELECT texto FROM ui_strings WHERE chave='nav.home' AND lang='en';")
        ok &= expect("ui_strings nav.home(en) gravado", v == "Home", v)
        c = psql("SELECT valor FROM configuracoes_i18n WHERE chave='site_subtitulo' AND lang='en';")
        ok &= expect("configuracoes_i18n site_subtitulo(en) gravado", c == "Subtitle en", c)
    finally:
        # restaurar UI
        for (chave, lang), snap in ui_snaps.items():
            if snap["exists"]:
                psql("INSERT INTO ui_strings (chave, lang, texto) VALUES (" + sqlq(chave) + "," + sqlq(lang) + "," + sqlq(snap["value"]) +
                     ") ON CONFLICT (chave, lang) DO UPDATE SET texto = EXCLUDED.texto;")
            else:
                psql(f"DELETE FROM ui_strings WHERE chave={sqlq(chave)} AND lang={sqlq(lang)};")
        for (chave, lang), snap in cfg2_snaps.items():
            if snap["exists"]:
                psql("INSERT INTO configuracoes_i18n (chave, lang, valor) VALUES (" + sqlq(chave) + "," + sqlq(lang) + "," + sqlq(snap["value"]) +
                     ") ON CONFLICT (chave, lang) DO UPDATE SET valor = EXCLUDED.valor;")
            else:
                psql(f"DELETE FROM configuracoes_i18n WHERE chave={sqlq(chave)} AND lang={sqlq(lang)};")
        for k, snap in cfg_snaps.items():
            if snap["exists"]:
                set_conf(k, snap["value"])
        mock.terminate()

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
