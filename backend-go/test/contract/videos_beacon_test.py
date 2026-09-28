#!/usr/bin/env python3
"""
Teste de vídeos (fila) e do beacon de métricas no backend Go — contra o STAGING.

Cleanup garantido. NUNCA apontar para produção.

Uso: python3 backend-go/test/contract/videos_beacon_test.py
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
BEACON_PATH = "/zz-beacon-test"

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


def api(method, path, payload=None, csrf=None, extra_headers=None):
    req = urllib.request.Request(GO_BASE + path, method=method)
    if payload is not None:
        req.data = json.dumps(payload).encode()
        req.add_header("Content-Type", "application/json")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    for k, v in (extra_headers or {}).items():
        req.add_header(k, v)
    try:
        with opener.open(req, timeout=20) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode() or "{}")
        except json.JSONDecodeError:
            return e.code, {}


def beacon(payload, origin):
    req = urllib.request.Request(GO_BASE + "/api/v1/metrics/beacon", method="POST",
                                 data=json.dumps(payload).encode())
    req.add_header("Content-Type", "application/json")
    if origin is not None:
        req.add_header("Origin", origin)
    try:
        with opener.open(req, timeout=10) as r:
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

    st, body = api("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    if st != 200 or not body.get("csrf_token"):
        print("FATAL login:", st, body)
        return 1
    csrf = body["csrf_token"]

    artigo_id = int(psql("SELECT id FROM artigos ORDER BY id LIMIT 1;"))
    variant_com_imagem = None
    variant_sem_imagem = None
    job_id = None
    try:
        variant_com_imagem = int(psql(
            "INSERT INTO artigos_social_variants (artigo_id, rede, caption, status, image_9x16) "
            f"VALUES ({artigo_id}, 'zztest', 'ZZVIDEO', 'rascunho', 'zz9x16.png') RETURNING id;"))
        variant_sem_imagem = int(psql(
            "INSERT INTO artigos_social_variants (artigo_id, rede, caption, status) "
            f"VALUES ({artigo_id}, 'zztest', 'ZZVIDEO2', 'rascunho') RETURNING id;"))

        # enqueue com imagem
        st, body = api("POST", "/api/v1/videos/enqueue", {"id": variant_com_imagem, "duration_per_image": 3}, csrf)
        job_id = body.get("job_id")
        ok &= expect("videos enqueue 200", st == 200 and body.get("success") and job_id, body)

        # job_status
        st, body = api("GET", f"/api/v1/videos/jobs/{job_id}")
        job = body.get("job", {})
        ok &= expect("videos job_status 200", st == 200 and body.get("success") and job.get("id") == job_id, body)
        ok &= expect("job engine=auto", job.get("engine") == "auto", job)

        # enqueue sem imagem -> success false
        st, body = api("POST", "/api/v1/videos/enqueue", {"id": variant_sem_imagem}, csrf)
        ok &= expect("videos enqueue sem imagem -> falha clara", st == 200 and body.get("success") is False, body)

        # job_id inexistente
        st, body = api("GET", "/api/v1/videos/jobs/99999999")
        ok &= expect("job inexistente -> Job não encontrado", st == 200 and body.get("message") == "Job não encontrado", body)

        # beacon same-origin
        st, body = beacon({"path": BEACON_PATH, "ua": "pytest"}, "https://washiviana.com")
        ok &= expect("beacon same-origin 200", st == 200 and body.get("success"), body)
        st, body = beacon({"path": BEACON_PATH, "ua": "pytest"}, "https://washiviana.com")
        ok &= expect("beacon rate-limit skipped", st == 200 and body.get("skipped") is True, body)
        n = int(psql(f"SELECT count(*) FROM site_accesses WHERE path='{BEACON_PATH}';"))
        ok &= expect("beacon gravou 1 acesso", n == 1, n)

        st, body = beacon({"path": BEACON_PATH}, "https://evil.example")
        ok &= expect("beacon origem externa -> 403", st == 403, body)
    finally:
        psql(f"DELETE FROM site_accesses WHERE path='{BEACON_PATH}';")
        if job_id:
            psql(f"DELETE FROM video_jobs WHERE id={job_id};")
        if variant_com_imagem:
            psql(f"DELETE FROM video_jobs WHERE variant_id={variant_com_imagem};")
        if variant_sem_imagem:
            psql(f"DELETE FROM video_jobs WHERE variant_id={variant_sem_imagem};")
        psql("DELETE FROM artigos_social_variants WHERE caption IN ('ZZVIDEO','ZZVIDEO2');")

    left = int(psql("SELECT count(*) FROM artigos_social_variants WHERE caption IN ('ZZVIDEO','ZZVIDEO2');"))
    left += int(psql(f"SELECT count(*) FROM site_accesses WHERE path='{BEACON_PATH}';"))
    ok &= expect("cleanup limpo", left == 0, left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
