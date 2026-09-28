#!/usr/bin/env python3
"""
Contract tests PHP x Go (Fase 1).

Compara a resposta do backend Go (staging :8081) com a referência PHP:
- endpoints públicos: via HTTP (produção).
- endpoints autenticados: via CLI (backend-go/test/contract/php_ref.php) contra o
  banco de produção (somente leitura), com sessão fake.

Uso: python3 backend-go/test/contract/contract_test.py

Sai com código != 0 se houver divergência.
"""
import http.cookiejar
import json
import os
import ssl
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

GO_BASE = os.environ.get("GO_BASE", "http://127.0.0.1:8081")
PHP_BASE = os.environ.get("PHP_BASE", "https://washiviana.com")
ENV_FILE = os.environ.get("GO_ENV_FILE", "/home/washi/washiviana-go/api.env")
HERE = os.path.dirname(os.path.abspath(__file__))

_CTX = ssl.create_default_context()
_CTX.check_hostname = False
_CTX.verify_mode = ssl.CERT_NONE


def load_token():
    try:
        with open(ENV_FILE) as fh:
            for line in fh:
                if line.startswith("INTERNAL_TOKEN="):
                    return line.split("=", 1)[1].strip()
    except OSError:
        pass
    return os.environ.get("INTERNAL_TOKEN", "")


TOKEN = load_token()


def http_json(url, token=None):
    req = urllib.request.Request(url)
    if token:
        req.add_header("X-Internal-Token", token)
    with urllib.request.urlopen(req, timeout=15, context=_CTX) as resp:
        return json.loads(resp.read().decode())


def php_cli(api_file, query):
    out = subprocess.run(
        ["php", os.path.join(HERE, "php_ref.php"), api_file, query],
        capture_output=True, text=True, timeout=60,
    )
    text = out.stdout.strip()
    if not text:
        raise RuntimeError(f"php_ref sem saída (stderr={out.stderr[:300]})")
    start = text.find("{")
    end = text.rfind("}")
    if start == -1 or end == -1:
        raise RuntimeError(f"php_ref saída inválida: {text[:200]}")
    return json.loads(text[start:end + 1])


def normalize(obj):
    """Ordena objetos recursivamente para comparação independente de ordem de chaves."""
    if isinstance(obj, dict):
        return {k: normalize(obj[k]) for k in sorted(obj)}
    if isinstance(obj, list):
        return [normalize(v) for v in obj]
    return obj


def diff(go, php, path=""):
    """Retorna lista de diferenças legíveis (máx. 10)."""
    out = []
    if isinstance(go, dict) and isinstance(php, dict):
        for key in sorted(set(go) | set(php)):
            out += diff(go.get(key, "<ausente>"), php.get(key, "<ausente>"), f"{path}.{key}")
    elif isinstance(go, list) and isinstance(php, list):
        if len(go) != len(php):
            out.append(f"{path}: len go={len(go)} php={len(php)}")
        for i, (a, b) in enumerate(zip(go, php)):
            out += diff(a, b, f"{path}[{i}]")
    elif go != php:
        out.append(f"{path}: go={go!r} php={php!r}")
    return out[:10]


def check(name, go, php):
    d = diff(normalize(go), normalize(php))
    if d:
        print(f"FAIL  {name}")
        for line in d:
            print("      " + line)
        return False
    print(f"PASS  {name}")
    return True


def expect(name, cond):
    print(("PASS  " if cond else "FAIL  ") + name)
    return bool(cond)


def auth_smoke():
    """Smoke da autenticação (sessão server-side) contra o banco de staging."""
    email = os.environ.get("GO_TEST_EMAIL", "contract-test@staging.local")
    password = os.environ.get("GO_TEST_PASSWORD", "test-12345678")

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    def call(method, path, payload=None, with_cookies=True):
        req = urllib.request.Request(GO_BASE + path, method=method)
        if payload is not None:
            req.data = json.dumps(payload).encode()
            req.add_header("Content-Type", "application/json")
        op = opener if with_cookies else urllib.request.build_opener()
        try:
            with op.open(req, timeout=10) as r:
                return r.status, json.loads(r.read().decode() or "{}")
        except urllib.error.HTTPError as e:
            body = e.read().decode()
            try:
                return e.code, json.loads(body or "{}")
            except json.JSONDecodeError:
                return e.code, {}

    ok = True
    st, body = call("POST", "/api/v1/auth/login", {"email": email, "password": password})
    ok &= expect("auth login", st == 200 and body.get("success") and body.get("csrf_token"))

    st, body = call("GET", "/api/v1/auth/me")
    ok &= expect("auth me", st == 200 and body.get("user", {}).get("email") == email)

    st, _ = call("POST", "/api/v1/auth/login", {"email": email, "password": "senha-errada"})
    ok &= expect("auth senha errada -> 401", st == 401)

    st, _ = call("GET", "/api/v1/projetos", with_cookies=False)
    ok &= expect("protegido sem auth -> 401", st == 401)

    st, _ = call("POST", "/api/v1/auth/logout")
    ok &= expect("auth logout", st == 200)

    st, _ = call("GET", "/api/v1/auth/me")
    ok &= expect("me apos logout -> 401", st == 401)
    return ok


def main():
    ok = True

    # categorias (público)
    for target in ("projetos", "artigos"):
        q = f"?target={target}&ativas=1"
        go = http_json(f"{GO_BASE}/api/v1/categorias{q}", TOKEN)
        php = http_json(f"{PHP_BASE}/api/categorias.php?action=listar&target={target}&ativas=1")
        ok &= check(f"categorias listar target={target}", go, php)

    # projetos (público)
    go = http_json(f"{GO_BASE}/api/v1/projetos", TOKEN)
    php = http_json(f"{PHP_BASE}/api/projetos.php?action=listar&limit=100&offset=0")
    ok &= check("projetos listar", go, php)

    projetos_id = next((p["id"] for p in php.get("projetos", [])), None)
    if projetos_id:
        go = http_json(f"{GO_BASE}/api/v1/projetos/{projetos_id}", TOKEN)
        php = http_json(f"{PHP_BASE}/api/projetos.php?action=buscar&id={projetos_id}")
        ok &= check(f"projetos buscar id={projetos_id}", go, php)

    # artigos (autenticado -> referência CLI)
    go = http_json(f"{GO_BASE}/api/v1/artigos", TOKEN)
    php = php_cli("artigos.php", "action=list")
    ok &= check("artigos listar", go, php)

    artigo_id = next((a["id"] for a in php.get("artigos", [])), None)
    if artigo_id:
        go = http_json(f"{GO_BASE}/api/v1/artigos/{artigo_id}", TOKEN)
        php = php_cli("artigos.php", f"action=get&id={artigo_id}")
        ok &= check(f"artigos buscar id={artigo_id}", go, php)
    else:
        print("SKIP  artigos buscar (sem artigos)")

    # i18n/SEO (autenticado -> referência CLI)
    i18n = php_cli("get_i18n_seo.php", "entity=artigo&id=2&lang=en")
    if i18n.get("success"):
        go = http_json(f"{GO_BASE}/api/v1/i18n-seo?entity=artigo&id=2&lang=en", TOKEN)
        ok &= check("i18n-seo artigo id=2 lang=en", go, i18n)
    else:
        print("SKIP  i18n-seo (sem registro de referência)")

    # Autenticação (sessão server-side)
    ok &= auth_smoke()

    print()
    print("RESULTADO:", "TUDO OK" if ok else "DIVERGÊNCIAS ENCONTRADAS")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
