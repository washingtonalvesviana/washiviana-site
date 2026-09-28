#!/usr/bin/env python3
"""
Teste do site público em Go (strangler) — home pt/en/es — contra o STAGING.

Uso: python3 backend-go/test/contract/site_test.py
"""
import os
import sys
import urllib.error
import urllib.request

GO_BASE = os.environ.get("GO_BASE", "http://127.0.0.1:8081")


def fetch(path, allow_redirect=False):
    op = urllib.request.build_opener() if allow_redirect else urllib.request.build_opener(NoRedirect)
    try:
        with op.open(GO_BASE + path, timeout=15) as r:
            return r.status, r.read().decode("utf-8", "replace"), dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), dict(e.headers)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def expect(name, cond, extra=""):
    print(("PASS  " if cond else "FAIL  ") + name + (("  -> " + str(extra)[:200]) if extra and not cond else ""))
    return bool(cond)


def main():
    ok = True

    st, body, headers = fetch("/site")
    ok &= expect("/site -> 302 para /site/pt/", st == 302 and headers.get("Location", "").endswith("/site/pt/"),
                 f"{st} {headers.get('Location')}")

    for lang in ("pt", "en", "es"):
        st, body, _ = fetch(f"/site/{lang}/")
        ok &= expect(f"/site/{lang}/ 200 html", st == 200 and "<html" in body.lower())
        ok &= expect(f"/site/{lang}/ canonical+hreflang", "canonical" in body and f'hreflang="{lang}"' in body)
        ok &= expect(f"/site/{lang}/ css tailwind", "/assets/css/tailwind.min.css" in body)
        ok &= expect(f"/site/{lang}/ links de conteúdo", f'/{lang}/conteudos' in body)
    st, body, _ = fetch("/site/pt/")
    ok &= expect("/site/pt/ tem título do site", "Washington Viana" in body)
    ok &= expect("/site/pt/ lista artigos (link /pt/artigo/)", "/pt/artigo/" in body)
    st, body, _ = fetch("/site/en/")
    ok &= expect("/site/en/ usa subtítulo EN", "Tech &amp; AI with a human touch" in body or "Tech & AI with a human touch" in body)

    st, body, _ = fetch("/site/xx/")
    ok &= expect("/site/xx/ cai para pt (200)", st == 200 and "Washington Viana" in body)

    # conteudos
    st, body, _ = fetch("/site/pt/conteudos")
    ok &= expect("/site/pt/conteudos 200", st == 200 and "Conteúdos" in body)
    import re
    m = re.search(r'/pt/artigo/([a-z0-9\-]+)', body)
    if m:
        slug = m.group(1)
        st2, b2, _ = fetch(f"/site/pt/artigo/{slug}")
        ok &= expect(f"/site/pt/artigo/{slug} 200", st2 == 200 and "<article" in b2)
    else:
        print("SKIP  artigo (sem artigos publicados no staging)")

    # projetos
    st, body, _ = fetch("/site/pt/projetos")
    ok &= expect("/site/pt/projetos 200", st == 200)
    m = re.search(r'/pt/projeto/([a-z0-9\-]+)', body)
    if m:
        slug = m.group(1)
        st2, b2, _ = fetch(f"/site/pt/projeto/{slug}")
        ok &= expect(f"/site/pt/projeto/{slug} 200", st2 == 200)
    else:
        print("SKIP  projeto (sem projetos ativos no staging)")

    # sobre
    st, body, _ = fetch("/site/pt/sobre")
    ok &= expect("/site/pt/sobre 200", st == 200 and "Sobre" in body)
    st, body, _ = fetch("/site/en/conteudos")
    ok &= expect("/site/en/conteudos 200", st == 200)

    # 404
    st, body, _ = fetch("/site/pt/artigo/zz-nao-existe-xyz")
    ok &= expect("artigo inexistente -> 404", st == 404)

    # sitemap
    st, body, headers = fetch("/site/sitemap.xml")
    ok &= expect("sitemap 200 xml", st == 200 and "xml" in headers.get("Content-Type", ""))
    ok &= expect("sitemap urlset + hreflang", "<urlset" in body and 'hreflang="pt-BR"' in body)
    ok &= expect("sitemap páginas estáticas", "/pt/conteudos" in body and "/en/projetos" in body and "/es/sobre" in body)
    ok &= expect("sitemap URLs de artigo", "/pt/artigo/" in body)

    print()
    print("RESULTADO:", "TUDO OK" if ok else "FALHAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
