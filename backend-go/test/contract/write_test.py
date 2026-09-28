#!/usr/bin/env python3
"""
Testes de escrita do backend Go (Fase 2b) — contra o banco de STAGING.

Valida CRUD de categorias end-to-end com rollback (cleanup) garantido:
login -> create -> listar -> update -> toggle -> ordenar -> delete -> verificar remoção.
Também compara o slug gerado com o do PHP (generateSlug/sanitize).

Uso: python3 backend-go/test/contract/write_test.py
Sai != 0 se algo falhar. NUNCA aponta para produção (usa GO_BASE local).
"""
import http.cookiejar
import json
import os
import random
import string
import subprocess
import sys
import urllib.error
import urllib.request

GO_BASE = os.environ.get("GO_BASE", "http://127.0.0.1:8081")
EMAIL = os.environ.get("GO_TEST_EMAIL", "contract-test@staging.local")
PASSWORD = os.environ.get("GO_TEST_PASSWORD", "test-12345678")
REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def call(method, path, payload=None, csrf=None):
    req = urllib.request.Request(GO_BASE + path, method=method)
    if payload is not None:
        req.data = json.dumps(payload).encode()
        req.add_header("Content-Type", "application/json")
    if csrf:
        req.add_header("X-CSRF-Token", csrf)
    try:
        with opener.open(req, timeout=15) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode() or "{}")
        except json.JSONDecodeError:
            return e.code, {}


def php_slug(name):
    code = ('require "' + REPO_ROOT + '/api/config.php"; echo generateSlug(sanitize($argv[1]));')
    out = subprocess.run(["php", "-r", code, name], capture_output=True, text=True, timeout=30)
    return out.stdout.strip()


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + extra) if extra and not cond else ""))
    return bool(cond)


def main():
    ok = True
    created_id = None

    st, body = call("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    if st != 200 or not body.get("csrf_token"):
        print("FATAL não foi possível autenticar:", st, body)
        return 1
    csrf = body["csrf_token"]

    suffix = "".join(random.choices(string.ascii_lowercase + string.digits, k=6))
    nome = f"ZZ Contract Teste {suffix} Ação"
    nome2 = f"ZZ Contract Teste {suffix} Alterado"

    try:
        # create
        st, body = call("POST", "/api/v1/categorias", {"nome": nome, "target": "projetos"}, csrf)
        expected_slug = php_slug(nome)
        created_id = body.get("categoria_id")
        ok &= expect("categoria create 200", st == 200 and body.get("success"))
        ok &= expect("categoria slug == PHP", body.get("slug") == expected_slug,
                     f"go={body.get('slug')} php={expected_slug}")

        # sem CSRF -> 403
        st, _ = call("POST", "/api/v1/categorias", {"nome": nome + " x", "target": "projetos"})
        ok &= expect("categoria create sem CSRF -> 403", st == 403)

        # duplicado -> 400
        st, _ = call("POST", "/api/v1/categorias", {"nome": nome, "target": "projetos"}, csrf)
        ok &= expect("categoria duplicada -> 400", st == 400)

        # listar contém
        st, body = call("GET", "/api/v1/categorias?target=projetos")
        found = [c for c in body.get("categorias", []) if c.get("id") == created_id]
        ok &= expect("categoria aparece na listagem", st == 200 and len(found) == 1)

        # update
        st, body = call("PUT", f"/api/v1/categorias/{created_id}", {"nome": nome2, "target": "projetos"}, csrf)
        expected_slug2 = php_slug(nome2)
        ok &= expect("categoria update 200", st == 200 and body.get("slug") == expected_slug2,
                     f"go={body.get('slug')} php={expected_slug2}")

        # toggle
        st, _ = call("POST", f"/api/v1/categorias/{created_id}/toggle?target=projetos", {}, csrf)
        ok &= expect("categoria toggle 200", st == 200)

        # ordenar (apenas a categoria temporária)
        st, _ = call("POST", "/api/v1/categorias/ordenar", {"target": "projetos", "ordem": [created_id]}, csrf)
        ok &= expect("categoria ordenar 200", st == 200)

        # delete categoria com itens (id=1 tem projetos) -> 400
        st, _ = call("DELETE", "/api/v1/categorias/1?target=projetos", csrf=csrf)
        ok &= expect("categoria com itens -> 400", st == 400)

        # delete da temporária
        st, _ = call("DELETE", f"/api/v1/categorias/{created_id}?target=projetos", csrf=csrf)
        ok &= expect("categoria delete 200", st == 200)
        created_id = None

        st, body = call("GET", "/api/v1/categorias?target=projetos")
        gone = [c for c in body.get("categorias", []) if c.get("nome") == nome2]
        ok &= expect("categoria removida da listagem", len(gone) == 0)
    finally:
        if created_id:
            call("DELETE", f"/api/v1/categorias/{created_id}?target=projetos", csrf=csrf)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
