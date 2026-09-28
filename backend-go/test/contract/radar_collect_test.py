#!/usr/bin/env python3
"""
Teste do radar collect (RSS) no backend Go — STAGING.

Usa um servidor RSS local (mock_rss_server.py). Requer ALLOW_LOOPBACK_FETCH=1 no
env do serviço de staging. Cleanup garantido. NUNCA apontar para produção.

Uso: python3 backend-go/test/contract/radar_collect_test.py
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
FEED = "http://127.0.0.1:8092/feed"
MARK = "ZZ Collect"

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


MAXRUN = [0]


def cleanup():
    psql("DELETE FROM radar_item_topics WHERE item_id IN (SELECT id FROM radar_items WHERE url LIKE '%127.0.0.1:8092%');")
    psql("DELETE FROM radar_items WHERE url LIKE '%127.0.0.1:8092%';")
    psql(f"DELETE FROM radar_topic_sources WHERE topic_id IN (SELECT id FROM radar_topics WHERE nome LIKE '{MARK}%');")
    psql(f"DELETE FROM radar_topics WHERE nome LIKE '{MARK}%';")
    psql(f"DELETE FROM radar_sources WHERE nome LIKE '{MARK}%';")
    if MAXRUN[0]:
        psql(f"DELETE FROM radar_runs WHERE id > {MAXRUN[0]};")


def main():
    ok = True
    cleanup()
    MAXRUN[0] = int(psql("SELECT COALESCE(MAX(id),0) FROM radar_runs;"))

    mock = subprocess.Popen([sys.executable, os.path.join(HERE, "mock_rss_server.py")],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    topic_id = None
    try:
        if not wait_port("127.0.0.1", 8092):
            print("FATAL: mock RSS não subiu")
            return 1

        st, body = api("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
        if st != 200 or not body.get("csrf_token"):
            print("FATAL login:", st, body)
            return 1
        csrf = body["csrf_token"]

        topic_id = int(psql(f"INSERT INTO radar_topics (nome, keywords, idiomas, regioes, ativo) VALUES ('{MARK} Tema','x','pt','br',true) RETURNING id;"))
        source_id = int(psql(f"INSERT INTO radar_sources (nome, tipo, url, ativo) VALUES ('{MARK} Fonte','rss','{FEED}',true) RETURNING id;"))
        psql(f"INSERT INTO radar_topic_sources (topic_id, source_id) VALUES ({topic_id},{source_id}) ON CONFLICT DO NOTHING;")

        st, body = api("POST", "/api/v1/radar/collect", {"topic_id": topic_id}, csrf)
        ok &= expect("radar collect 200", st == 200 and body.get("success"), body)
        ok &= expect("saved_total >= 2", body.get("saved_total", 0) >= 2, body)

        n = int(psql("SELECT count(*) FROM radar_items WHERE url LIKE '%127.0.0.1:8092%';"))
        ok &= expect("itens RSS gravados", n >= 2, n)
        linked = int(psql(f"SELECT count(*) FROM radar_item_topics WHERE topic_id={topic_id};"))
        ok &= expect("itens vinculados ao tema", linked >= 2, linked)
        run = int(psql("SELECT count(*) FROM radar_runs WHERE id = %d;" % body.get("run_id", 0)))
        ok &= expect("run registrado", run == 1, run)
        # dedupe/tracking removido: url_norm não deve conter utm_source
        has_tracking = int(psql("SELECT count(*) FROM radar_items WHERE url_norm LIKE '%utm_source%';"))
        ok &= expect("tracking removido do url_norm", has_tracking == 0, has_tracking)
    finally:
        cleanup()
        mock.terminate()

    left = int(psql("SELECT count(*) FROM radar_items WHERE url LIKE '%127.0.0.1:8092%';"))
    ok &= expect("cleanup limpo", left == 0, left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
