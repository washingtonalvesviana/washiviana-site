#!/usr/bin/env python3
"""
Testes de escrita de CONFIGURAÇÕES no backend Go (Fase 2b) — contra o STAGING.

Salva e RESTAURA os valores originais (rollback). Nunca apontar para produção.

Uso: python3 backend-go/test/contract/write_config_test.py
"""
import http.cookiejar
import json
import os
import sys
import urllib.error
import urllib.request

GO_BASE = os.environ.get("GO_BASE", "http://127.0.0.1:8081")
EMAIL = os.environ.get("GO_TEST_EMAIL", "contract-test@staging.local")
PASSWORD = os.environ.get("GO_TEST_PASSWORD", "test-12345678")

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


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)) if extra and not cond else ""))
    return bool(cond)


def main():
    ok = True

    st, body = call("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    if st != 200 or not body.get("csrf_token"):
        print("FATAL login:", st, body)
        return 1
    csrf = body["csrf_token"]

    # originais
    st, body = call("GET", "/api/v1/configuracoes")
    conf = body.get("configuracoes", {})
    ok &= expect("config list 200", st == 200 and body.get("success") and "site_titulo" in conf)
    orig_titulo = conf.get("site_titulo", "")
    orig_sub = conf.get("site_subtitulo", "")

    try:
        # single save
        st, body = call("POST", "/api/v1/configuracoes/item", {"chave": "site_titulo", "valor": "ZZ Contract Titulo"}, csrf)
        ok &= expect("config item save 200", st == 200 and body.get("message") == "Configuração salva.", body)
        st, body = call("GET", "/api/v1/configuracoes")
        ok &= expect("config item refletido", body.get("configuracoes", {}).get("site_titulo") == "ZZ Contract Titulo")

        # chave vazia -> 400
        st, body = call("POST", "/api/v1/configuracoes/item", {"chave": "", "valor": "x"}, csrf)
        ok &= expect("config item sem chave -> 400", st == 400 and body.get("message") == "Chave é obrigatória.")

        # bulk save
        st, body = call("POST", "/api/v1/configuracoes", {
            "site_titulo": "ZZ Bulk Titulo", "site_subtitulo": "ZZ Bulk Sub",
        }, csrf)
        ok &= expect("config bulk 200", st == 200 and body.get("message") == "Configurações salvas com sucesso! (2 campos atualizados)", body)
        st, body = call("GET", "/api/v1/configuracoes")
        c = body.get("configuracoes", {})
        ok &= expect("config bulk refletido", c.get("site_titulo") == "ZZ Bulk Titulo" and c.get("site_subtitulo") == "ZZ Bulk Sub")

        # sem CSRF -> 403
        st, _ = call("POST", "/api/v1/configuracoes", {"site_titulo": "x"})
        ok &= expect("config bulk sem CSRF -> 403", st == 403)
    finally:
        # restaura originais
        call("POST", "/api/v1/configuracoes", {"site_titulo": orig_titulo, "site_subtitulo": orig_sub}, csrf)

    st, body = call("GET", "/api/v1/configuracoes")
    c = body.get("configuracoes", {})
    ok &= expect("config restaurado", c.get("site_titulo") == orig_titulo and c.get("site_subtitulo") == orig_sub,
                 f"titulo={c.get('site_titulo')!r} esperado={orig_titulo!r}")

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
