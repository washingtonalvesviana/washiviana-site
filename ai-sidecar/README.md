# ai-sidecar (PoC — Fase 3e)

PoC do sidecar de IA descrito em `docs/migration/08-PYTHON-SIDECAR-AVALIACAO.md`.

**Status:** prova de conceito. **NÃO implantado** em produção, sem systemd unit e sem rota no nginx.

## Rodar

```bash
SIDECAR_HOST=127.0.0.1 SIDECAR_PORT=8090 python3 ai-sidecar/app.py

curl -s http://127.0.0.1:8090/health
curl -s -X POST http://127.0.0.1:8090/v1/embed -H 'Content-Type: application/json' -d '{"text":"exemplo"}'
```

- `GET /health` → status.
- `POST /v1/embed` → embedding determinístico (PoC; **não** é um modelo real).

## Decisão

Python só entra quando houver necessidade real de IA pesada (inferência local, embeddings/RAG, visão, áudio). Enquanto a IA for HTTP para provedores, **fica em Go** — ver o documento de avaliação.
