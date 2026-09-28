#!/usr/bin/env python3
"""
Teste de radar idea_to_draft (modos ai e simple) no backend Go — STAGING.

Cria uma ideia temporária, gera rascunhos de artigo (mock p/ IA) e limpa tudo.
NUNCA apontar para produção.

Uso: python3 backend-go/test/contract/radar_draft_test.py
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
    idea_id = None
    artigos = []
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

        topic_id = int(psql("SELECT id FROM radar_topics ORDER BY id LIMIT 1;"))
        idea_id = int(psql(
            "INSERT INTO radar_ideas (topic_id, titulo, angulo, resumo, outline, tags, status) "
            f"VALUES ({topic_id}, 'ZZ Draft Idea', 'angulo', 'resumo ideia', E'- um\\n- dois', 't', 'nova') RETURNING id;"))

        # modo ai
        st, body = api("POST", f"/api/v1/radar/ideas/{idea_id}/to-draft", {"mode": "ai"}, csrf)
        res = body.get("result", {})
        aid = res.get("artigo_id")
        if aid:
            artigos.append(aid)
        ok &= expect("to-draft (ai) 200", st == 200 and body.get("success") and aid, body)
        stt = psql(f"SELECT status FROM radar_ideas WHERE id={idea_id};")
        ok &= expect("ideia marcada virou_artigo", stt == "virou_artigo", stt)
        titulo = psql(f"SELECT titulo FROM artigos WHERE id={aid};")
        ok &= expect("artigo criado a partir do mock", titulo == "Mock Draft Artigo", titulo)
        sp = psql(f"SELECT status_publicacao FROM artigos WHERE id={aid};")
        ok &= expect("artigo como rascunho", sp == "rascunho", sp)

        # modo simple (nova ideia)
        idea2 = int(psql(
            "INSERT INTO radar_ideas (topic_id, titulo, resumo, outline, tags, status) "
            f"VALUES ({topic_id}, 'ZZ Simple Idea', 'resumo s', E'- a\\n- b', 't', 'nova') RETURNING id;"))
        st, body = api("POST", f"/api/v1/radar/ideas/{idea2}/to-draft", {"mode": "simple"}, csrf)
        aid2 = body.get("result", {}).get("artigo_id")
        if aid2:
            artigos.append(aid2)
        ok &= expect("to-draft (simple) 200", st == 200 and body.get("success") and aid2, body)
        conteudo = psql(f"SELECT conteudo FROM artigos WHERE id={aid2};")
        ok &= expect("conteudo simple com <ul>", "<ul>" in conteudo and "<li>" in conteudo, conteudo)
        psql(f"DELETE FROM radar_ideas WHERE id={idea2};")
    finally:
        for aid in artigos:
            psql(f"DELETE FROM artigos WHERE id={aid};")
        psql("DELETE FROM radar_ideas WHERE titulo IN ('ZZ Draft Idea','ZZ Simple Idea');")
        psql("DELETE FROM artigos WHERE titulo = 'Mock Draft Artigo';")
        for k, snap in snaps.items():
            if snap["exists"]:
                set_conf(k, snap["value"])
        mock.terminate()

    left = int(psql("SELECT count(*) FROM artigos WHERE titulo LIKE 'Mock Draft Artigo';"))
    ok &= expect("cleanup limpo", left == 0, left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
