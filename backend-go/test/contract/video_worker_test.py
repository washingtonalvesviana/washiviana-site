#!/usr/bin/env python3
"""
Teste do worker Go `video` (Fase 3d) — contra o STAGING.

Valida a listagem de jobs pendentes (--dry-run) e que a execução real é BLOQUEADA
(o executor de vídeo ainda não foi portado). Cleanup garantido.

Uso: python3 backend-go/test/contract/video_worker_test.py
"""
import json
import os
import re
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO_ROOT = os.path.abspath(os.path.join(HERE, "..", "..", ".."))
WORKER = os.environ.get("WORKER_BIN", "/home/washi/washiviana-go/washiviana-worker")
ENV_FILE = os.environ.get("GO_ENV_FILE", "/home/washi/washiviana-go/api.env")
STAGING_DB = os.environ.get("GO_DB_NAME", "washiviana_staging")


def load_db_creds():
    text = open(os.path.join(REPO_ROOT, "api", "config.local.php")).read()

    def g(name):
        m = re.search(r"define\('" + name + r"',\s*'([^']*)'\)", text)
        return m.group(1) if m else ""

    return g("DB_HOST"), g("DB_PORT"), g("DB_USER"), g("DB_PASS")


def load_worker_env():
    env = dict(os.environ)
    try:
        with open(ENV_FILE) as fh:
            for line in fh:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    env[k] = v
    except OSError:
        pass
    return env


HOST, PORT, USER, PASSWORD = load_db_creds()


def psql(sql):
    env = dict(os.environ)
    env["PGPASSWORD"] = PASSWORD
    out = subprocess.run(
        ["psql", "-h", HOST, "-p", PORT, "-U", USER, "-d", STAGING_DB, "-tA", "-c", sql],
        capture_output=True, text=True, env=env, timeout=30,
    )
    if out.returncode != 0:
        raise RuntimeError("psql falhou: " + out.stderr.strip())
    text = out.stdout.strip()
    return text.splitlines()[0] if text else ""


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)) if extra and not cond else ""))
    return bool(cond)


def main():
    ok = True
    env = load_worker_env()
    variant_id = int(psql("SELECT id FROM artigos_social_variants ORDER BY id LIMIT 1;"))
    job_id = None
    try:
        job_id = int(psql(
            f"INSERT INTO video_jobs (variant_id, status, attempts) VALUES ({variant_id}, 'pending', 0) RETURNING id;"))

        out = subprocess.run([WORKER, "video", "--dry-run"], capture_output=True, text=True, env=env, timeout=60)
        line = out.stdout.strip().splitlines()[-1]
        r = json.loads(line)
        ok &= expect("video dry-run lista o job pendente",
                     r.get("event") == "video_pending" and job_id in r.get("ids", []), r)

        out = subprocess.run([WORKER, "video"], capture_output=True, text=True, env=env, timeout=60)
        ok &= expect("execução real de vídeo bloqueada (exit != 0)", out.returncode != 0, out.returncode)
    finally:
        if job_id:
            psql(f"DELETE FROM video_jobs WHERE id = {job_id};")

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
