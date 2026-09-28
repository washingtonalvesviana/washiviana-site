#!/usr/bin/env python3
"""
Testes de escrita de PROJETOS no backend Go (Fase 2b) — contra o STAGING.

Cobre create/update/delete, toggles, ordenar, post LinkedIn e delete de mídia,
sempre com cleanup. Nunca apontar para produção.

Uso: python3 backend-go/test/contract/write_projetos_test.py
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
    pid = None

    st, body = call("POST", "/api/v1/auth/login", {"email": EMAIL, "password": PASSWORD})
    if st != 200 or not body.get("csrf_token"):
        print("FATAL login:", st, body)
        return 1
    csrf = body["csrf_token"]

    suffix = "".join(random.choices(string.ascii_lowercase + string.digits, k=6))
    titulo = f"ZZ Contract Projeto {suffix} Ação"
    titulo2 = f"ZZ Contract Projeto {suffix} Alterado"
    slug1 = f"zz-contract-projeto-{suffix}-acao"
    slug2 = f"zz-contract-projeto-{suffix}-alterado"

    try:
        # validação
        st, body = call("POST", "/api/v1/projetos", {"titulo": titulo}, csrf)
        ok &= expect("projeto create sem categoria -> 400", st == 400 and body.get("message") == "Título e categoria são obrigatórios.", body)

        # create
        st, body = call("POST", "/api/v1/projetos", {
            "titulo": titulo, "categoria_id": 1, "descricao": "desc de teste",
            "tecnologias": "Go, PostgreSQL", "url_projeto": "https://exemplo.test",
            "destaque": False, "ativo": True,
        }, csrf)
        pid = body.get("projeto_id")
        ok &= expect("projeto create 200", st == 200 and body.get("success") and body.get("slug") == slug1,
                     f"st={st} slug={body.get('slug')} esperado={slug1}")

        st, body = call("GET", f"/api/v1/projetos/{pid}")
        p = body.get("projeto", {})
        ok &= expect("projeto criar refletido", p.get("titulo") == titulo and p.get("slug") == slug1)
        ok &= expect("projeto galeria vazia como lista", p.get("imagens_galeria") == [], p.get("imagens_galeria"))
        ok &= expect("projeto categoria_nome presente", bool(p.get("categoria_nome")))

        # update (muda titulo -> regenera slug)
        st, body = call("PUT", f"/api/v1/projetos/{pid}", {
            "titulo": titulo2, "categoria_id": 1, "descricao": "desc editada",
            "tecnologias": "Go", "url_projeto": "https://exemplo.test", "destaque": True, "ativo": False,
        }, csrf)
        ok &= expect("projeto update 200 e slug regerado", st == 200 and body.get("slug") == slug2,
                     f"st={st} slug={body.get('slug')} esperado={slug2}")

        st, body = call("GET", f"/api/v1/projetos/{pid}")
        p = body.get("projeto", {})
        ok &= expect("projeto update refletido", p.get("titulo") == titulo2 and p.get("destaque") is True and p.get("ativo") is False)

        # toggles
        st, body = call("POST", f"/api/v1/projetos/{pid}/toggle-status", {}, csrf)
        ok &= expect("projeto toggle-status 200", st == 200 and body.get("message") == "Status atualizado com sucesso!")
        st, body = call("GET", f"/api/v1/projetos/{pid}")
        ok &= expect("projeto ativo voltou a true", body.get("projeto", {}).get("ativo") is True)

        st, body = call("POST", f"/api/v1/projetos/{pid}/toggle-destaque", {}, csrf)
        ok &= expect("projeto toggle-destaque 200", st == 200 and body.get("message") == "Destaque atualizado com sucesso!")

        # ordenar
        st, body = call("POST", "/api/v1/projetos/ordenar", {"ordem": [pid]}, csrf)
        ok &= expect("projeto ordenar 200", st == 200 and body.get("message") == "Ordem atualizada com sucesso!")

        # post linkedin
        st, body = call("POST", f"/api/v1/projetos/{pid}/linkedin-post", {"conteudo": "post de teste", "prompt": "p"}, csrf)
        post_id = body.get("post_id")
        ok &= expect("projeto linkedin-post salvo", st == 200 and post_id)
        st, body = call("GET", f"/api/v1/linkedin-posts/{post_id}")
        ok &= expect("projeto linkedin-post buscado", st == 200 and body.get("post", {}).get("conteudo") == "post de teste")

        # media delete de arquivo inexistente -> 400
        st, body = call("POST", f"/api/v1/projetos/{pid}/media/delete", {"filename": "nao_existe.png"}, csrf)
        ok &= expect("projeto media delete arquivo inexistente -> 400", st == 400 and body.get("message") == "Arquivo não pertence a este projeto.")

        # delete
        deleted_id = pid
        st, body = call("DELETE", f"/api/v1/projetos/{deleted_id}", csrf=csrf)
        ok &= expect("projeto delete 200", st == 200 and body.get("message") == "Projeto deletado com sucesso!")
        pid = None
        st, body = call("GET", f"/api/v1/projetos/{deleted_id}")
        ok &= expect("projeto removido -> 404", st == 404, f"st={st}")
    finally:
        if pid:
            call("DELETE", f"/api/v1/projetos/{pid}", csrf=csrf)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
