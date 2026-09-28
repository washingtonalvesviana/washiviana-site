#!/usr/bin/env python3
"""
Teste de auth (change-password) e gemini-models no backend Go — contra o STAGING.

A senha do usuário de teste é alterada e RESTAURADA no fim. Nunca apontar p/ produção.

Uso: python3 backend-go/test/contract/auth_models_test.py
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
NEW_PASSWORD = "NovaSenha!2026x"

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


def bcrypt_hash(pw):
    out = subprocess.run(["php", "-r", f'echo password_hash("{pw}", PASSWORD_BCRYPT);'],
                         capture_output=True, text=True, timeout=30)
    return out.stdout.strip()


def call(op, method, path, payload=None, csrf=None):
    req = urllib.request.Request(GO_BASE + path, method=method)
    if payload is not None:
        req.data = json.dumps(payload).encode()
        req.add_header("Content-Type", "application/json")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    try:
        with op.open(req, timeout=30) as r:
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

    # gemini-models sem chave
    key_snap = psql("SELECT count(*) FROM configuracoes WHERE chave='gemini_api_key';") != "0"
    key_val = psql("SELECT valor FROM configuracoes WHERE chave='gemini_api_key';")

    try:
        # login
        st, body = call(opener, "POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
        if st != 200 or not body.get("csrf_token"):
            print("FATAL login:", st, body)
            return 1
        csrf = body["csrf_token"]

        # gemini-models sem chave (autenticado)
        psql("INSERT INTO configuracoes (chave, valor) VALUES ('gemini_api_key','') ON CONFLICT (chave) DO UPDATE SET valor='';")
        st, body = call(opener, "GET", "/api/v1/ai/gemini-models")
        ok &= expect("gemini-models sem chave -> mensagem", st == 200 and body.get("message") == "API Key não configurada", body)

        # validações
        st, body = call(opener, "POST", "/api/v1/auth/change-password",
                        {"current_password": PASSWORD, "new_password": "curta", "confirm_password": "curta"}, csrf)
        ok &= expect("senha curta -> 400", st == 400 and "8 caracteres" in body.get("message", ""), body)
        st, body = call(opener, "POST", "/api/v1/auth/change-password",
                        {"current_password": PASSWORD, "new_password": NEW_PASSWORD, "confirm_password": "x" * 12}, csrf)
        ok &= expect("confirmação divergente -> 400", st == 400, body)

        # troca de senha
        st, body = call(opener, "POST", "/api/v1/auth/change-password",
                        {"current_password": PASSWORD, "new_password": NEW_PASSWORD, "confirm_password": NEW_PASSWORD}, csrf)
        ok &= expect("change-password 200", st == 200 and body.get("success"), body)

        # login com a senha nova
        op2 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        st, body = call(op2, "POST", "/api/v1/auth/login", {"email": EMAIL, "password": NEW_PASSWORD})
        ok &= expect("login com senha nova", st == 200 and body.get("success"), body)

        # restaurar via endpoint (sessão antiga segue válida)
        st, body = call(opener, "POST", "/api/v1/auth/change-password",
                        {"current_password": NEW_PASSWORD, "new_password": PASSWORD, "confirm_password": PASSWORD}, csrf)
        ok &= expect("restaurar senha original via endpoint", st == 200 and body.get("success"), body)
    finally:
        # garantir restauração no banco
        h = bcrypt_hash(PASSWORD)
        psql("UPDATE usuarios SET senha='" + h + "' WHERE email='" + EMAIL + "';")
        if key_snap:
            psql("UPDATE configuracoes SET valor='" + key_val.replace("'", "''") + "' WHERE chave='gemini_api_key';")

    # confirmar login com a senha original
    op3 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    st, body = call(op3, "POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    ok &= expect("login restaurado com senha original", st == 200 and body.get("success"), body)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
