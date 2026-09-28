#!/usr/bin/env python3
"""
Teste do worker Go `metrics` (Fase 3d) — contra o STAGING.

Valida a seleção de publicações elegíveis (dry-run) e a falha graciosa para rede
sem coletor (sem chamadas externas). Cleanup garantido. NUNCA apontar p/ produção.

Uso: python3 backend-go/test/contract/metrics_worker_test.py
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
REDE = "zztest"


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


def run_worker(env, *args):
    out = subprocess.run([WORKER, "metrics", *args], capture_output=True, text=True, env=env, timeout=60)
    line = out.stdout.strip().splitlines()[-1] if out.stdout.strip() else "{}"
    return json.loads(line), out


def main():
    ok = True

    preprint = int(psql("SELECT count(*) FROM publicacoes_redes WHERE rede='" + REDE + "';"))
    if preprint != 0:
        print("ABORTADO: já existem publicações de teste no staging.")
        return 1

    artigo_id = int(psql("SELECT id FROM artigos ORDER BY id LIMIT 1;"))
    env = load_worker_env()
    pub_id = None

    try:
        pub_id = int(psql(
            "INSERT INTO publicacoes_redes (artigo_id, rede, post_id, status, publicado_em) "
            f"VALUES ({artigo_id}, '{REDE}', 'ZZTEST', 'publicado', now()) RETURNING id;"))

        # dry-run: deve listar apenas a publicação de teste
        r, _ = run_worker(env, "--rede=" + REDE, "--dry-run")
        ok &= expect("dry-run lista a publicação elegível",
                     r.get("event") == "eligible" and r.get("ids") == [pub_id],
                     r)

        # execução real: rede sem coletor -> falha graciosa, sem chamadas externas, sem snapshot
        r, _ = run_worker(env, "--rede=" + REDE)
        ok &= expect("rede sem coletor -> falha graciosa",
                     r.get("event") == "metrics" and r.get("ok") == 0 and r.get("fail") == 1,
                     r)
        snaps = int(psql(f"SELECT count(*) FROM metricas_publicacoes WHERE publicacao_id = {pub_id};"))
        ok &= expect("nenhum snapshot gravado em falha", snaps == 0, snaps)
    finally:
        psql("DELETE FROM publicacoes_redes WHERE rede='" + REDE + "';")

    left = int(psql("SELECT count(*) FROM publicacoes_redes WHERE rede='" + REDE + "';"))
    ok &= expect("cleanup limpo", left == 0, left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
