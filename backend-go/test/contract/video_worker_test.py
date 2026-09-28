#!/usr/bin/env python3
"""
Teste do worker de vídeo Go (FFmpeg) — contra o STAGING.

Cria um job real (com uma imagem existente de uploads), roda o worker, confere o
vídeo gerado e limpa tudo. NUNCA apontar para produção.

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
UPLOAD_DIR = os.environ.get("UPLOAD_DIR", "/var/www/washiviana.com/uploads")


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
    out = subprocess.run(["psql", "-h", HOST, "-p", PORT, "-U", USER, "-d", STAGING_DB, "-tA", "-c", sql],
                         capture_output=True, text=True, env=env, timeout=30)
    if out.returncode != 0:
        raise RuntimeError("psql falhou: " + out.stderr.strip())
    text = out.stdout.strip()
    return text.splitlines()[0] if text else ""


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)) if extra and not cond else ""))
    return bool(cond)


def pick_image():
    for name in sorted(os.listdir(UPLOAD_DIR)):
        if name.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
            return name
    return None


def main():
    ok = True
    env = load_worker_env()
    img = pick_image()
    if not img:
        print("SKIP: nenhuma imagem em uploads/")
        return 0

    artigo_id = int(psql("SELECT id FROM artigos ORDER BY id LIMIT 1;"))
    variant_id = None
    job_id = None
    out_file = None
    try:
        variant_id = int(psql(
            "INSERT INTO artigos_social_variants (artigo_id, rede, caption, status, image_9x16) "
            f"VALUES ({artigo_id}, 'zzvideo', 'ZZ Video Teste', 'rascunho', '{img}') RETURNING id;"))
        job_id = int(psql(
            "INSERT INTO video_jobs (variant_id, status, attempts, params) "
            f"VALUES ({variant_id}, 'pending', 0, '{{\"duration_per_image\":1,\"resolution\":\"1080x1920\"}}') RETURNING id;"))

        out = subprocess.run([WORKER, "video"], capture_output=True, text=True, env=env, timeout=300)
        line = out.stdout.strip().splitlines()[-1] if out.stdout.strip() else "{}"
        r = json.loads(line)
        ok &= expect("worker processou o job", r.get("job_id") == job_id and r.get("status") == "success", r)

        stt = psql(f"SELECT status FROM video_jobs WHERE id={job_id};")
        ok &= expect("job status=success", stt == "success", stt)
        out_file = psql(f"SELECT output_file FROM video_jobs WHERE id={job_id};")
        ok &= expect("output_file gravado", bool(out_file) and out_file.endswith(".mp4"), out_file)
        ok &= expect("arquivo de vídeo existe", bool(out_file) and os.path.isfile(os.path.join(UPLOAD_DIR, out_file)))
        vf = psql(f"SELECT video_file FROM artigos_social_variants WHERE id={variant_id};")
        ok &= expect("variante com video_file", vf == out_file, vf)
    finally:
        if out_file:
            p = os.path.join(UPLOAD_DIR, out_file)
            if os.path.isfile(p):
                os.remove(p)
        if job_id:
            psql(f"DELETE FROM video_jobs WHERE id={job_id};")
        if variant_id:
            psql(f"DELETE FROM artigos_social_variants WHERE id={variant_id};")

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
