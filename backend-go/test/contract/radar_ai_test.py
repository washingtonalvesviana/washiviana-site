#!/usr/bin/env python3
"""
Teste das partes de IA/clustering do Radar no backend Go — STAGING.

- ideas/generate: com mock OpenAI-compatível (sem chamadas externas).
- radar/hype: clustering local (não usa IA).
Cleanup das ideias criadas. NUNCA apontar para produção.

Uso: python3 backend-go/test/contract/radar_ai_test.py
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
        with opener.open(req, timeout=220) as r:
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
    max_idea_id = None
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

        topic = psql("SELECT topic_id FROM radar_item_topics GROUP BY topic_id HAVING count(*) > 0 LIMIT 1;")
        if not topic:
            print("SKIP: sem tema com itens no staging")
            return 0
        topic_id = int(topic)

        cur = psql("SELECT COALESCE(MAX(id),0) FROM radar_ideas;")
        max_idea_id = int(cur)

        st, body = api("POST", "/api/v1/radar/ideas/generate", {"topic_id": topic_id, "num_ideas": 2}, csrf)
        ok &= expect("radar ideas/generate 200", st == 200 and body.get("success"), body)
        res = body.get("result", {})
        ok &= expect("ideias salvas >= 1", res.get("saved", 0) >= 1, res)

        n = int(psql(f"SELECT count(*) FROM radar_ideas WHERE id > {max_idea_id};"))
        ok &= expect("radar_ideas gravadas no banco", n >= 1, n)

        st, body = api("POST", "/api/v1/radar/hype", {}, csrf)
        ok &= expect("radar/hype 200 com contagens",
                     st == 200 and body.get("success") and isinstance(body.get("clusters_found"), int)
                     and isinstance(body.get("items_updated"), int), body)
    finally:
        if max_idea_id is not None:
            psql(f"DELETE FROM radar_ideas WHERE id > {max_idea_id};")
        for k, snap in snaps.items():
            if snap["exists"]:
                set_conf(k, snap["value"])
        mock.terminate()

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
