#!/usr/bin/env python3
"""
Teste do Radar (temas/fontes/itens/ideias — CRUD/listas) no backend Go — STAGING.

Cleanup garantido. NUNCA apontar para produção.

Uso: python3 backend-go/test/contract/radar_test.py
"""
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
REPO_ROOT = os.path.abspath(os.path.join(HERE, "..", "..", ".."))
GO_BASE = os.environ.get("GO_BASE", "http://127.0.0.1:8081")
EMAIL = os.environ.get("GO_TEST_EMAIL", "contract-test@staging.local")
PASSWORD = os.environ.get("GO_TEST_PASSWORD", "test-12345678")
STAGING_DB = os.environ.get("GO_DB_NAME", "washiviana_staging")
MARK = "ZZ Rad"

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


def api(method, path, payload=None, csrf=None):
    req = urllib.request.Request(GO_BASE + path, method=method)
    if payload is not None:
        req.data = json.dumps(payload).encode()
        req.add_header("Content-Type", "application/json")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    try:
        with opener.open(req, timeout=20) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode() or "{}")
        except json.JSONDecodeError:
            return e.code, {}


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)) if extra and not cond else ""))
    return bool(cond)


def cleanup():
    psql(f"DELETE FROM radar_topic_sources WHERE topic_id IN (SELECT id FROM radar_topics WHERE nome LIKE '{MARK}%');")
    psql(f"DELETE FROM radar_topics WHERE nome LIKE '{MARK}%';")
    psql(f"DELETE FROM radar_sources WHERE nome LIKE '{MARK}%';")


def main():
    ok = True
    cleanup()

    st, body = api("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    if st != 200 or not body.get("csrf_token"):
        print("FATAL login:", st, body)
        return 1
    csrf = body["csrf_token"]

    topic_id = None
    source_id = None
    try:
        # tema
        st, body = api("POST", "/api/v1/radar/topics", {"nome": MARK + " Tema", "descricao": "d", "keywords": "ia"}, csrf)
        topic_id = body.get("id")
        ok &= expect("radar topic save (criar)", st == 200 and body.get("message") == "Tema criado" and topic_id, body)

        st, body = api("POST", "/api/v1/radar/topics", {"id": topic_id, "nome": MARK + " Tema Edit"}, csrf)
        ok &= expect("radar topic save (atualizar)", st == 200 and body.get("message") == "Tema atualizado", body)

        st, body = api("GET", "/api/v1/radar/topics")
        ok &= expect("radar topics list", st == 200 and any(t["nome"] == MARK + " Tema Edit" for t in body.get("topics", [])))

        st, body = api("POST", "/api/v1/radar/topics", {"nome": ""}, csrf)
        ok &= expect("radar topic sem nome -> 400", st == 400, body)

        # fonte
        st, body = api("POST", "/api/v1/radar/sources", {"nome": MARK + " Fonte", "tipo": "rss", "url": "https://example.test/feed"}, csrf)
        source_id = body.get("id")
        ok &= expect("radar source save (criar)", st == 200 and body.get("message") == "Fonte criada" and source_id, body)
        st, body = api("POST", "/api/v1/radar/sources", {"nome": MARK + " F", "tipo": "xpto"}, csrf)
        ok &= expect("radar source tipo inválido -> 400", st == 400, body)
        st, body = api("POST", "/api/v1/radar/sources", {"nome": MARK + " F2", "tipo": "rss", "url": ""}, csrf)
        ok &= expect("radar source rss sem url -> 400", st == 400, body)

        # vínculo tema-fonte
        st, body = api("POST", f"/api/v1/radar/topics/{topic_id}/sources", {"source_ids": [source_id]}, csrf)
        ok &= expect("radar topic sources set", st == 200 and body.get("success"), body)
        st, body = api("GET", f"/api/v1/radar/topics/{topic_id}/sources")
        ok &= expect("radar topic sources get", st == 200 and body.get("source_ids") == [source_id], body)

        # listas
        st, body = api("GET", "/api/v1/radar/items?limit=5")
        ok &= expect("radar items list", st == 200 and body.get("success"), body)
        st, body = api("GET", "/api/v1/radar/ideas?limit=5")
        ok &= expect("radar ideas list", st == 200 and body.get("success"), body)

        # items delete (sem itens)
        st, body = api("POST", "/api/v1/radar/items/delete", {"ids": []}, csrf)
        ok &= expect("radar items delete sem parâmetros -> 400", st == 400, body)
        st, body = api("POST", "/api/v1/radar/items/delete", {"url_like": "zz-nao-existe-xyz"}, csrf)
        ok &= expect("radar items delete sem itens -> 404", st == 404, body)

        # remover fonte e tema
        st, body = api("DELETE", f"/api/v1/radar/sources/{source_id}", csrf=csrf)
        ok &= expect("radar source delete", st == 200 and body.get("message") == "Fonte removida", body)
        source_id = None
        st, body = api("DELETE", f"/api/v1/radar/topics/{topic_id}", csrf=csrf)
        ok &= expect("radar topic delete", st == 200 and body.get("message") == "Tema removido", body)
        topic_id = None
    finally:
        if source_id:
            psql(f"DELETE FROM radar_topic_sources WHERE source_id={source_id};")
        cleanup()

    left = int(psql(f"SELECT count(*) FROM radar_topics WHERE nome LIKE '{MARK}%';"))
    left += int(psql(f"SELECT count(*) FROM radar_sources WHERE nome LIKE '{MARK}%';"))
    ok &= expect("cleanup limpo", left == 0, left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
