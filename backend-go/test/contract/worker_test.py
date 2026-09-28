#!/usr/bin/env python3
"""
Teste do worker Go com CLAIM ATÔMICO (Fase 3d) — contra o STAGING.

Valida que dois workers concorrentes nunca processam a mesma variante agendada.
Insere variantes de teste, roda 2 workers em paralelo e confere a partição dos IDs.
Cleanup garantido. NUNCA apontar para produção.

Uso: python3 backend-go/test/contract/worker_test.py
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
MARKER = "ZZWORKER"
N = 20
LIMIT = 10


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
    # Ignora a linha de status do comando (ex.: "INSERT 0 1") e devolve o primeiro valor.
    return text.splitlines()[0] if text else ""


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)) if extra and not cond else ""))
    return bool(cond)


def run_worker(env):
    out = subprocess.run([WORKER, "publish-scheduled", "--limit", str(LIMIT)],
                         capture_output=True, text=True, env=env, timeout=60)
    line = out.stdout.strip().splitlines()[-1] if out.stdout.strip() else "{}"
    return json.loads(line), out


def main():
    ok = True

    # Segurança: staging não deve ter variantes 'pronto' antes do teste.
    pre = int(psql("SELECT count(*) FROM artigos_social_variants WHERE status='pronto';"))
    if pre != 0:
        print(f"ABORTADO: já existem {pre} variantes 'pronto' no staging; não é seguro testar.")
        return 1

    artigo_id = int(psql("SELECT id FROM artigos ORDER BY id LIMIT 1;"))
    env = load_worker_env()

    inserted = []
    try:
        for i in range(N):
            vid = int(psql(
                f"INSERT INTO artigos_social_variants (artigo_id, rede, caption, status, scheduled_at) "
                f"VALUES ({artigo_id}, 'zzworker', '{MARKER} {i}', 'pronto', now() - interval '1 minute') "
                f"RETURNING id;"))
            inserted.append(vid)

        # Dois workers concorrentes
        p1 = subprocess.Popen([WORKER, "publish-scheduled", "--limit", str(LIMIT)],
                              stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, env=env)
        p2 = subprocess.Popen([WORKER, "publish-scheduled", "--limit", str(LIMIT)],
                              stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, env=env)
        o1, _ = p1.communicate(timeout=60)
        o2, _ = p2.communicate(timeout=60)
        r1 = json.loads(o1.strip().splitlines()[-1])
        r2 = json.loads(o2.strip().splitlines()[-1])

        claimed = r1["ids"] + r2["ids"]
        ok &= expect("todos os itens reclamados", sorted(claimed) == sorted(inserted),
                     f"claimed={sorted(claimed)} inserted={sorted(inserted)}")
        ok &= expect("nenhum item reclamado duas vezes", len(claimed) == len(set(claimed)),
                     f"duplicados={[x for x in claimed if claimed.count(x) > 1]}")
        ok &= expect("soma dos claims == total", r1["count"] + r2["count"] == N,
                     f"{r1['count']}+{r2['count']}")

        # Idempotência: rodar de novo não reclama nada
        r3, _ = run_worker(env)
        ok &= expect("segunda execução não reclama nada", r3["count"] == 0, r3)

        # Estado final
        st = psql(f"SELECT count(*) FROM artigos_social_variants WHERE caption LIKE '{MARKER}%' AND status='pronto_para_publicacao';")
        ok &= expect("todas marcadas como pronto_para_publicacao", int(st) == N, st)
    finally:
        psql(f"DELETE FROM artigos_social_variants WHERE caption LIKE '{MARKER}%';")

    left = int(psql(f"SELECT count(*) FROM artigos_social_variants WHERE caption LIKE '{MARKER}%';"))
    ok &= expect("cleanup limpo", left == 0, left)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
