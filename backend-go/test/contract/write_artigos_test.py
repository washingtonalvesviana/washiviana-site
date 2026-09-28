#!/usr/bin/env python3
"""
Testes de escrita de ARTIGOS no backend Go (Fase 2b) — contra o STAGING.

Cobre create/update/delete, validações, slug (gerarSlug) e data_publicacao,
sempre com cleanup. Nunca apontar para produção.

Uso: python3 backend-go/test/contract/write_artigos_test.py
"""
import http.cookiejar
import json
import os
import random
import string
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
    created = []

    st, body = call("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    if st != 200 or not body.get("csrf_token"):
        print("FATAL login:", st, body)
        return 1
    csrf = body["csrf_token"]

    suffix = "".join(random.choices(string.ascii_lowercase + string.digits, k=6))
    titulo = f"ZZ Contract Artigo {suffix} Ação"
    base_slug = f"zz-contract-artigo-{suffix}-acao"

    try:
        # validações
        st, body = call("POST", "/api/v1/artigos", {"conteudo": "<p>x</p>"}, csrf)
        ok &= expect("create sem título -> Título é obrigatório", st == 200 and body.get("message") == "Título é obrigatório", body)
        st, body = call("POST", "/api/v1/artigos", {"titulo": titulo}, csrf)
        ok &= expect("create sem conteúdo -> Conteúdo é obrigatório", st == 200 and body.get("message") == "Conteúdo é obrigatório", body)

        # create
        st, body = call("POST", "/api/v1/artigos", {
            "titulo": titulo,
            "conteudo": "<p>Conteúdo de teste</p>",
            "resumo": "resumo de teste",
            "autor": "Contract Test",
            "status_publicacao": "rascunho",
            "redes_destino": [{"rede": "linkedin", "formato": "1:1"}],
        }, csrf)
        aid = body.get("artigo_id")
        if aid:
            created.append(aid)
        ok &= expect("artigo create 200", st == 200 and body.get("success") and aid)

        st, body = call("GET", f"/api/v1/artigos/{aid}")
        slug_created = body.get("artigo", {}).get("slug")
        ok &= expect("artigo slug == esperado", slug_created == base_slug, f"go={slug_created} esperado={base_slug}")

        # slug duplicado recebe sufixo
        st, body2 = call("POST", "/api/v1/artigos", {
            "titulo": titulo, "conteudo": "<p>outro</p>",
        }, csrf)
        aid2 = body2.get("artigo_id")
        if aid2:
            created.append(aid2)
        st, body3 = call("GET", f"/api/v1/artigos/{aid2}")
        slug2 = body3.get("artigo", {}).get("slug")
        ok &= expect("artigo slug duplicado ganha sufixo",
                     isinstance(slug2, str) and slug2.startswith(base_slug + "-") and slug2 != base_slug,
                     slug2)

        # buscar por id
        st, body = call("GET", f"/api/v1/artigos/{aid}")
        art = body.get("artigo", {})
        ok &= expect("artigo buscar", st == 200 and art.get("titulo") == titulo)
        rd = art.get("redes_destino")
        ok &= expect("redes_destino jsonb preservado", isinstance(rd, str) and "linkedin" in rd, rd)

        # update -> publicado
        st, body = call("PUT", f"/api/v1/artigos/{aid}", {
            "titulo": titulo + " Editado",
            "conteudo": "<p>Conteúdo editado</p>",
            "autor": "Contract Test",
            "status_publicacao": "publicado",
            "redes_destino": [],
        }, csrf)
        ok &= expect("artigo update 200", st == 200 and body.get("message") == "Conteúdo atualizado com sucesso!", body)

        st, body = call("GET", f"/api/v1/artigos/{aid}")
        art = body.get("artigo", {})
        ok &= expect("artigo update refletido", art.get("titulo") == titulo + " Editado" and art.get("status_publicacao") == "publicado")
        ok &= expect("artigo data_publicacao preenchida", art.get("data_publicacao") is not None, art.get("data_publicacao"))

        # delete
        st, body = call("DELETE", f"/api/v1/artigos/{aid}", csrf=csrf)
        ok &= expect("artigo delete 200", st == 200 and body.get("message") == "Conteúdo excluído com sucesso!", body)
        created.remove(aid)

        st, body = call("GET", f"/api/v1/artigos/{aid}")
        ok &= expect("artigo removido", body.get("success") is False, body)
    finally:
        for cid in created:
            call("DELETE", f"/api/v1/artigos/{cid}", csrf=csrf)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
