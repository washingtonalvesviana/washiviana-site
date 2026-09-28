# 08 — Avaliação do Python sidecar (IA) — Fase 3(e)

> Decisão: **não introduzir Python agora.** Manter a IA em Go (HTTP para provedores) e deixar uma *costura* pronta para um sidecar Python quando surgir necessidade real.

## 1. O que a "IA" faz hoje

Toda a IA atual é **chamada HTTP a provedores remotos** + montagem de prompt + parsing:

- `api/gemini.php` (multi-provedor: gemini, openai, deepseek, openrouter, anthropic, ollama; base URL OpenAI-compatível para vLLM).
- `api/i18n_seo.php` / `api/i18n_site.php` (tradução/SEO).
- `api/radar_lib.php` (ideias/análise de hype).

Não há inferência local, modelos próprios, embeddings nem processamento pesado de imagem/áudio no projeto. **Isso o Go faz bem** (HTTP + JSON), sem ganho em trocar de linguagem.

## 2. Quando Python passa a valer

Introduza o sidecar **somente** se um destes aparecer:

| Necessidade | Por que Python |
|---|---|
| Inferência local de LLM (llama.cpp/vLLM client, quantização) | ecossistema pronto |
| **Embeddings + busca vetorial** (pgvector) e RAG | libs maduras (`sentence-transformers`, `pgvector`) |
| Processamento de imagem pesado (segmentação, upscale, OCR) | Pillow/OpenCV/ONNX |
| Avaliação/experimentos de prompt e modelos | tooling científico |
| Áudio/transcrição (Whisper) | ecossistema |

Enquanto for "prompt → HTTP → parse", **não compensa** (mais um runtime, deploy e superfície).

## 3. Interface proposta (quando necessário)

- **Processo local**, bind `127.0.0.1:8090`, **sem rota pública** no nginx.
- Contrato JSON simples, consumido pela abstração `internal/ai` do Go:
  - `GET /health` → `{status:"ok",service,version}`
  - `POST /v1/embed` → `{embedding:[...], dim, model}`
  - (futuro) `POST /v1/rag/query`, `POST /v1/vision/...`
- O Go decide em runtime entre `Provider` remoto e sidecar (feature flag/env), mantendo o resto do sistema igual.
- Timeout curto + fallback: se o sidecar cair, o Go degrada para o provedor remoto (ou erro explícito), nunca derruba o site.

## 4. PoC (não implantada)

- Código: `ai-sidecar/app.py` (stdlib apenas; `/health` + `/v1/embed` determinístico).
- Validado localmente: bind **127.0.0.1:8090**, `GET /health` 200, `POST /v1/embed` 200, texto vazio → 400, **sem rota no nginx**.
- Não habilitado em produção; não há systemd unit.

## 5. Recomendação

1. **Agora:** manter IA em Go. Nada a fazer além de preservar a costura `internal/ai`.
2. **Gatilho:** iniciar o sidecar quando aparecer **embeddings/RAG** (ex.: recomendações, busca semântica no site) ou **inferência local** por custo/privacidade.
3. **Como começar:** promover o PoC (`ai-sidecar/`) para um serviço com systemd (`EnvironmentFile`, 600), adicionar `pgvector` se RAG, e plugar no Go por env (`AI_SIDECAR_URL`).
4. **Guardrails:** localhost-only, sem exposição no nginx, healthcheck, fallback para o provedor remoto.

## 6. Status

- Fase 3(e): **avaliada** (decisão: adiar; costura pronta). PoC entregue e validada localmente.
