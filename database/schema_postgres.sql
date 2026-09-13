-- ============================================================
-- Washiviana Site - Esquema canonico PostgreSQL
-- Fonte de verdade do banco de dados.
-- Gerado do ambiente de producao com:
--   pg_dump --schema-only --no-owner --no-privileges
--
-- Instalacao nova:
--   psql -d <db> -f database/schema_postgres.sql
--   psql -d <db> -f database/seed.sql
--
-- As migrations em migrations/ servem para evoluir instalacoes
-- antigas. Este arquivo e gerado: nao editar manualmente.
-- ============================================================

--
-- PostgreSQL database dump
--

\restrict uKLym2CgfvYyM8QGiLLZEVfWqqid5w1NvbW3pn0BrGjbaUucqSfidRJ9U9hsrmM

-- Dumped from database version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: agendamentos_posts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agendamentos_posts (
    id integer NOT NULL,
    artigo_id integer,
    data_publicacao timestamp without time zone NOT NULL,
    rede_social character varying(50) NOT NULL,
    formato_imagem character varying(10),
    status character varying(20) DEFAULT 'pendente'::character varying,
    post_id character varying(255),
    erro text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    executed_at timestamp without time zone
);


--
-- Name: agendamentos_posts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agendamentos_posts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agendamentos_posts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agendamentos_posts_id_seq OWNED BY public.agendamentos_posts.id;


--
-- Name: artigos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.artigos (
    id integer NOT NULL,
    titulo character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    resumo text,
    conteudo text,
    categoria_id integer,
    imagem_principal character varying(255),
    autor character varying(100),
    destaque boolean DEFAULT false,
    ativo boolean DEFAULT true,
    fonte_ia character varying(100),
    prompt_usado text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    video_url character varying(255),
    status character varying(20) DEFAULT 'rascunho'::character varying,
    publicar_linkedin boolean DEFAULT false,
    publicar_instagram boolean DEFAULT false,
    linkedin_post_id character varying(100),
    instagram_post_id character varying(100),
    data_publicacao timestamp without time zone,
    data_agendamento timestamp without time zone,
    tipo_midia character varying(10) DEFAULT 'imagem'::character varying,
    prompt_texto text,
    prompt_imagem text,
    imagem_1x1 character varying(255),
    imagem_9x16 character varying(255),
    recorrencia_tipo character varying(20) DEFAULT 'nenhuma'::character varying,
    recorrencia_dias character varying(50),
    recorrencia_dia_mes integer,
    recorrencia_fim date,
    redes_destino jsonb,
    status_publicacao character varying(20) DEFAULT 'rascunho'::character varying,
    erro_publicacao text,
    ultima_tentativa timestamp without time zone
);


--
-- Name: artigos_i18n; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.artigos_i18n (
    id integer NOT NULL,
    artigo_id integer NOT NULL,
    lang character varying(5) NOT NULL,
    titulo character varying(255),
    slug character varying(255),
    resumo text,
    conteudo text,
    meta_title character varying(255),
    meta_description character varying(255),
    og_title character varying(255),
    og_description character varying(255),
    og_image character varying(255),
    keywords text,
    schema_jsonld jsonb,
    status_traducao character varying(20) DEFAULT 'generated'::character varying NOT NULL,
    generated_by character varying(50),
    generated_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT artigos_i18n_lang_check CHECK (((lang)::text = ANY (ARRAY[('pt'::character varying)::text, ('en'::character varying)::text, ('es'::character varying)::text]))),
    CONSTRAINT artigos_i18n_status_traducao_check CHECK (((status_traducao)::text = ANY (ARRAY[('draft'::character varying)::text, ('generated'::character varying)::text, ('reviewed'::character varying)::text])))
);


--
-- Name: artigos_i18n_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.artigos_i18n_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: artigos_i18n_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.artigos_i18n_id_seq OWNED BY public.artigos_i18n.id;


--
-- Name: artigos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.artigos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: artigos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.artigos_id_seq OWNED BY public.artigos.id;


--
-- Name: artigos_social_variants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.artigos_social_variants (
    id integer NOT NULL,
    artigo_id integer NOT NULL,
    rede character varying(50) NOT NULL,
    titulo character varying(255),
    caption text,
    hashtags text,
    media_type character varying(20) DEFAULT 'imagem'::character varying,
    image_1x1 character varying(255),
    image_9x16 character varying(255),
    video_file character varying(255),
    video_meta jsonb,
    status character varying(20) DEFAULT 'rascunho'::character varying,
    scheduled_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: artigos_social_variants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.artigos_social_variants_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: artigos_social_variants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.artigos_social_variants_id_seq OWNED BY public.artigos_social_variants.id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    slug character varying(100) NOT NULL,
    ordem integer DEFAULT 0,
    ativo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: categorias_artigos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias_artigos (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    slug character varying(100) NOT NULL,
    descricao character varying(255),
    cor character varying(7) DEFAULT '#607AFB'::character varying,
    ordem integer DEFAULT 0,
    ativo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: categorias_artigos_i18n; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias_artigos_i18n (
    id integer NOT NULL,
    categoria_id integer NOT NULL,
    lang character varying(5) NOT NULL,
    nome character varying(100) NOT NULL,
    slug character varying(100) NOT NULL,
    descricao character varying(255),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT categorias_artigos_i18n_lang_check CHECK (((lang)::text = ANY (ARRAY[('pt'::character varying)::text, ('en'::character varying)::text, ('es'::character varying)::text])))
);


--
-- Name: categorias_artigos_i18n_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_artigos_i18n_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_artigos_i18n_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_artigos_i18n_id_seq OWNED BY public.categorias_artigos_i18n.id;


--
-- Name: categorias_artigos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_artigos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_artigos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_artigos_id_seq OWNED BY public.categorias_artigos.id;


--
-- Name: categorias_i18n; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias_i18n (
    id integer NOT NULL,
    categoria_id integer NOT NULL,
    lang character varying(5) NOT NULL,
    nome character varying(100) NOT NULL,
    slug character varying(100) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT categorias_i18n_lang_check CHECK (((lang)::text = ANY (ARRAY[('pt'::character varying)::text, ('en'::character varying)::text, ('es'::character varying)::text])))
);


--
-- Name: categorias_i18n_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_i18n_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_i18n_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_i18n_id_seq OWNED BY public.categorias_i18n.id;


--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- Name: config_redes_formatos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.config_redes_formatos (
    id integer NOT NULL,
    rede character varying(50) NOT NULL,
    nome_exibicao character varying(100) NOT NULL,
    formato_padrao character varying(10) NOT NULL,
    formatos_aceitos character varying(50) NOT NULL,
    icone character varying(10),
    ativo boolean DEFAULT true,
    ordem integer DEFAULT 0
);


--
-- Name: config_redes_formatos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.config_redes_formatos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: config_redes_formatos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.config_redes_formatos_id_seq OWNED BY public.config_redes_formatos.id;


--
-- Name: configuracoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuracoes (
    id integer NOT NULL,
    chave character varying(100) NOT NULL,
    valor text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: configuracoes_i18n; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuracoes_i18n (
    id integer NOT NULL,
    chave character varying(100) NOT NULL,
    lang character varying(5) NOT NULL,
    valor text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT configuracoes_i18n_lang_check CHECK (((lang)::text = ANY (ARRAY[('pt'::character varying)::text, ('en'::character varying)::text, ('es'::character varying)::text])))
);


--
-- Name: configuracoes_i18n_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuracoes_i18n_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuracoes_i18n_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuracoes_i18n_id_seq OWNED BY public.configuracoes_i18n.id;


--
-- Name: configuracoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuracoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuracoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuracoes_id_seq OWNED BY public.configuracoes.id;


--
-- Name: historico_imagens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.historico_imagens (
    id integer NOT NULL,
    artigo_id integer,
    formato character varying(10) NOT NULL,
    arquivo character varying(255) NOT NULL,
    prompt_usado text,
    modelo_ia character varying(100),
    ativo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: historico_imagens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.historico_imagens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: historico_imagens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.historico_imagens_id_seq OWNED BY public.historico_imagens.id;


--
-- Name: metricas_publicacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.metricas_publicacoes (
    id integer NOT NULL,
    publicacao_id integer NOT NULL,
    data_coleta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    visualizacoes integer DEFAULT 0,
    curtidas integer DEFAULT 0,
    comentarios integer DEFAULT 0,
    compartilhamentos integer DEFAULT 0,
    cliques integer DEFAULT 0,
    alcance integer DEFAULT 0,
    engajamento numeric(5,2) DEFAULT 0,
    dados_extras jsonb
);


--
-- Name: metricas_publicacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.metricas_publicacoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: metricas_publicacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.metricas_publicacoes_id_seq OWNED BY public.metricas_publicacoes.id;


--
-- Name: posts_linkedin; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posts_linkedin (
    id integer NOT NULL,
    projeto_id integer NOT NULL,
    conteudo text NOT NULL,
    prompt_usado text,
    gerado_em timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: posts_linkedin_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posts_linkedin_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posts_linkedin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posts_linkedin_id_seq OWNED BY public.posts_linkedin.id;


--
-- Name: projetos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projetos (
    id integer NOT NULL,
    titulo character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    descricao text,
    categoria_id integer,
    imagem_principal character varying(255),
    imagens_galeria text,
    tecnologias text,
    url_projeto character varying(255),
    destaque boolean DEFAULT false,
    ativo boolean DEFAULT true,
    ordem integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    prompt_descricao text,
    prompt_linkedin text
);


--
-- Name: projetos_i18n; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projetos_i18n (
    id integer NOT NULL,
    projeto_id integer NOT NULL,
    lang character varying(5) NOT NULL,
    titulo character varying(255),
    slug character varying(255),
    descricao text,
    meta_title character varying(255),
    meta_description character varying(255),
    og_title character varying(255),
    og_description character varying(255),
    og_image character varying(255),
    keywords text,
    schema_jsonld jsonb,
    status_traducao character varying(20) DEFAULT 'generated'::character varying NOT NULL,
    generated_by character varying(50),
    generated_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT projetos_i18n_lang_check CHECK (((lang)::text = ANY (ARRAY[('pt'::character varying)::text, ('en'::character varying)::text, ('es'::character varying)::text]))),
    CONSTRAINT projetos_i18n_status_traducao_check CHECK (((status_traducao)::text = ANY (ARRAY[('draft'::character varying)::text, ('generated'::character varying)::text, ('reviewed'::character varying)::text])))
);


--
-- Name: projetos_i18n_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.projetos_i18n_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projetos_i18n_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.projetos_i18n_id_seq OWNED BY public.projetos_i18n.id;


--
-- Name: projetos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.projetos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projetos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.projetos_id_seq OWNED BY public.projetos.id;


--
-- Name: publicacoes_redes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publicacoes_redes (
    id integer NOT NULL,
    artigo_id integer NOT NULL,
    rede character varying(50) NOT NULL,
    post_id character varying(100),
    url_post character varying(500),
    status character varying(20) DEFAULT 'pendente'::character varying,
    erro_mensagem text,
    publicado_em timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: publicacoes_redes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.publicacoes_redes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: publicacoes_redes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.publicacoes_redes_id_seq OWNED BY public.publicacoes_redes.id;


--
-- Name: radar_ideas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.radar_ideas (
    id integer NOT NULL,
    topic_id integer NOT NULL,
    titulo character varying(255) NOT NULL,
    angulo text,
    resumo text,
    outline text,
    tags text,
    status character varying(20) DEFAULT 'nova'::character varying NOT NULL,
    source_item_ids jsonb,
    ai_model character varying(120),
    ai_prompt text,
    ai_raw text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT radar_ideas_status_check CHECK (((status)::text = ANY (ARRAY[('nova'::character varying)::text, ('selecionada'::character varying)::text, ('descartada'::character varying)::text, ('virou_artigo'::character varying)::text])))
);


--
-- Name: radar_ideas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.radar_ideas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radar_ideas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radar_ideas_id_seq OWNED BY public.radar_ideas.id;


--
-- Name: radar_item_topics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.radar_item_topics (
    item_id integer NOT NULL,
    topic_id integer NOT NULL
);


--
-- Name: radar_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.radar_items (
    id integer NOT NULL,
    source_id integer,
    url text NOT NULL,
    url_norm text NOT NULL,
    titulo text,
    descricao text,
    snippet text,
    tipo_midia character varying(20) DEFAULT 'article'::character varying NOT NULL,
    published_at timestamp without time zone,
    fetched_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    idioma character varying(5),
    regiao character varying(10),
    score numeric(10,2) DEFAULT 0,
    raw jsonb,
    CONSTRAINT radar_items_tipo_midia_check CHECK (((tipo_midia)::text = ANY (ARRAY[('article'::character varying)::text, ('video'::character varying)::text, ('podcast'::character varying)::text, ('other'::character varying)::text])))
);


--
-- Name: radar_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.radar_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radar_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radar_items_id_seq OWNED BY public.radar_items.id;


--
-- Name: radar_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.radar_runs (
    id integer NOT NULL,
    started_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    finished_at timestamp without time zone,
    status character varying(20) DEFAULT 'running'::character varying NOT NULL,
    triggered_by character varying(20) DEFAULT 'manual'::character varying NOT NULL,
    log text,
    meta jsonb,
    CONSTRAINT radar_runs_status_check CHECK (((status)::text = ANY (ARRAY[('running'::character varying)::text, ('success'::character varying)::text, ('error'::character varying)::text]))),
    CONSTRAINT radar_runs_triggered_by_check CHECK (((triggered_by)::text = ANY (ARRAY[('manual'::character varying)::text, ('cron'::character varying)::text])))
);


--
-- Name: radar_runs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.radar_runs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radar_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radar_runs_id_seq OWNED BY public.radar_runs.id;


--
-- Name: radar_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.radar_sources (
    id integer NOT NULL,
    nome character varying(160) NOT NULL,
    tipo character varying(20) NOT NULL,
    url text,
    config jsonb,
    ativo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT radar_sources_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('rss'::character varying)::text, ('api'::character varying)::text, ('scrape'::character varying)::text])))
);


--
-- Name: radar_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.radar_sources_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radar_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radar_sources_id_seq OWNED BY public.radar_sources.id;


--
-- Name: radar_topic_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.radar_topic_sources (
    topic_id integer NOT NULL,
    source_id integer NOT NULL
);


--
-- Name: radar_topics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.radar_topics (
    id integer NOT NULL,
    nome character varying(160) NOT NULL,
    descricao text,
    keywords text,
    idiomas character varying(20) DEFAULT 'pt,en'::character varying NOT NULL,
    regioes character varying(80) DEFAULT 'br,us,eu'::character varying NOT NULL,
    categoria_artigos_id integer,
    ativo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radar_topics_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.radar_topics_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radar_topics_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radar_topics_id_seq OWNED BY public.radar_topics.id;


--
-- Name: redes_sociais_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.redes_sociais_config (
    id integer NOT NULL,
    rede character varying(50) NOT NULL,
    ativo boolean DEFAULT false,
    client_id character varying(255),
    client_secret character varying(255),
    access_token text,
    refresh_token text,
    token_expira_em timestamp without time zone,
    page_id character varying(100),
    user_id character varying(100),
    dados_extras jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    person_urn character varying(255) DEFAULT ''::character varying,
    token_expires_at timestamp without time zone,
    organization_urn character varying(255) DEFAULT ''::character varying,
    publish_target character varying(20) DEFAULT 'person'::character varying
);


--
-- Name: redes_sociais_config_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.redes_sociais_config_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: redes_sociais_config_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.redes_sociais_config_id_seq OWNED BY public.redes_sociais_config.id;


--
-- Name: site_accesses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.site_accesses (
    id integer NOT NULL,
    path character varying(500) NOT NULL,
    user_agent text,
    ip character varying(45),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: site_accesses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.site_accesses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: site_accesses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.site_accesses_id_seq OWNED BY public.site_accesses.id;


--
-- Name: ui_strings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ui_strings (
    id integer NOT NULL,
    chave character varying(150) NOT NULL,
    lang character varying(5) NOT NULL,
    texto text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ui_strings_lang_check CHECK (((lang)::text = ANY (ARRAY[('pt'::character varying)::text, ('en'::character varying)::text, ('es'::character varying)::text])))
);


--
-- Name: ui_strings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ui_strings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ui_strings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ui_strings_id_seq OWNED BY public.ui_strings.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id integer NOT NULL,
    nome character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    senha character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: video_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.video_jobs (
    id integer NOT NULL,
    variant_id integer NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    last_error text,
    output_file character varying(255),
    params jsonb DEFAULT '{}'::jsonb,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now()
);


--
-- Name: video_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.video_jobs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: video_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.video_jobs_id_seq OWNED BY public.video_jobs.id;


--
-- Name: agendamentos_posts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agendamentos_posts ALTER COLUMN id SET DEFAULT nextval('public.agendamentos_posts_id_seq'::regclass);


--
-- Name: artigos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos ALTER COLUMN id SET DEFAULT nextval('public.artigos_id_seq'::regclass);


--
-- Name: artigos_i18n id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_i18n ALTER COLUMN id SET DEFAULT nextval('public.artigos_i18n_id_seq'::regclass);


--
-- Name: artigos_social_variants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_social_variants ALTER COLUMN id SET DEFAULT nextval('public.artigos_social_variants_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- Name: categorias_artigos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos ALTER COLUMN id SET DEFAULT nextval('public.categorias_artigos_id_seq'::regclass);


--
-- Name: categorias_artigos_i18n id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos_i18n ALTER COLUMN id SET DEFAULT nextval('public.categorias_artigos_i18n_id_seq'::regclass);


--
-- Name: categorias_i18n id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_i18n ALTER COLUMN id SET DEFAULT nextval('public.categorias_i18n_id_seq'::regclass);


--
-- Name: config_redes_formatos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_redes_formatos ALTER COLUMN id SET DEFAULT nextval('public.config_redes_formatos_id_seq'::regclass);


--
-- Name: configuracoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes ALTER COLUMN id SET DEFAULT nextval('public.configuracoes_id_seq'::regclass);


--
-- Name: configuracoes_i18n id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes_i18n ALTER COLUMN id SET DEFAULT nextval('public.configuracoes_i18n_id_seq'::regclass);


--
-- Name: historico_imagens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.historico_imagens ALTER COLUMN id SET DEFAULT nextval('public.historico_imagens_id_seq'::regclass);


--
-- Name: metricas_publicacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metricas_publicacoes ALTER COLUMN id SET DEFAULT nextval('public.metricas_publicacoes_id_seq'::regclass);


--
-- Name: posts_linkedin id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts_linkedin ALTER COLUMN id SET DEFAULT nextval('public.posts_linkedin_id_seq'::regclass);


--
-- Name: projetos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos ALTER COLUMN id SET DEFAULT nextval('public.projetos_id_seq'::regclass);


--
-- Name: projetos_i18n id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos_i18n ALTER COLUMN id SET DEFAULT nextval('public.projetos_i18n_id_seq'::regclass);


--
-- Name: publicacoes_redes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publicacoes_redes ALTER COLUMN id SET DEFAULT nextval('public.publicacoes_redes_id_seq'::regclass);


--
-- Name: radar_ideas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_ideas ALTER COLUMN id SET DEFAULT nextval('public.radar_ideas_id_seq'::regclass);


--
-- Name: radar_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_items ALTER COLUMN id SET DEFAULT nextval('public.radar_items_id_seq'::regclass);


--
-- Name: radar_runs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_runs ALTER COLUMN id SET DEFAULT nextval('public.radar_runs_id_seq'::regclass);


--
-- Name: radar_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_sources ALTER COLUMN id SET DEFAULT nextval('public.radar_sources_id_seq'::regclass);


--
-- Name: radar_topics id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_topics ALTER COLUMN id SET DEFAULT nextval('public.radar_topics_id_seq'::regclass);


--
-- Name: redes_sociais_config id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.redes_sociais_config ALTER COLUMN id SET DEFAULT nextval('public.redes_sociais_config_id_seq'::regclass);


--
-- Name: site_accesses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_accesses ALTER COLUMN id SET DEFAULT nextval('public.site_accesses_id_seq'::regclass);


--
-- Name: ui_strings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_strings ALTER COLUMN id SET DEFAULT nextval('public.ui_strings_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Name: video_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_jobs ALTER COLUMN id SET DEFAULT nextval('public.video_jobs_id_seq'::regclass);


--
-- Name: agendamentos_posts agendamentos_posts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agendamentos_posts
    ADD CONSTRAINT agendamentos_posts_pkey PRIMARY KEY (id);


--
-- Name: artigos_i18n artigos_i18n_artigo_id_lang_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_i18n
    ADD CONSTRAINT artigos_i18n_artigo_id_lang_key UNIQUE (artigo_id, lang);


--
-- Name: artigos_i18n artigos_i18n_lang_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_i18n
    ADD CONSTRAINT artigos_i18n_lang_slug_key UNIQUE (lang, slug);


--
-- Name: artigos_i18n artigos_i18n_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_i18n
    ADD CONSTRAINT artigos_i18n_pkey PRIMARY KEY (id);


--
-- Name: artigos artigos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos
    ADD CONSTRAINT artigos_pkey PRIMARY KEY (id);


--
-- Name: artigos artigos_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos
    ADD CONSTRAINT artigos_slug_key UNIQUE (slug);


--
-- Name: artigos_social_variants artigos_social_variants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_social_variants
    ADD CONSTRAINT artigos_social_variants_pkey PRIMARY KEY (id);


--
-- Name: categorias_artigos_i18n categorias_artigos_i18n_categoria_id_lang_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos_i18n
    ADD CONSTRAINT categorias_artigos_i18n_categoria_id_lang_key UNIQUE (categoria_id, lang);


--
-- Name: categorias_artigos_i18n categorias_artigos_i18n_lang_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos_i18n
    ADD CONSTRAINT categorias_artigos_i18n_lang_slug_key UNIQUE (lang, slug);


--
-- Name: categorias_artigos_i18n categorias_artigos_i18n_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos_i18n
    ADD CONSTRAINT categorias_artigos_i18n_pkey PRIMARY KEY (id);


--
-- Name: categorias_artigos categorias_artigos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos
    ADD CONSTRAINT categorias_artigos_pkey PRIMARY KEY (id);


--
-- Name: categorias_artigos categorias_artigos_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos
    ADD CONSTRAINT categorias_artigos_slug_key UNIQUE (slug);


--
-- Name: categorias_i18n categorias_i18n_categoria_id_lang_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_i18n
    ADD CONSTRAINT categorias_i18n_categoria_id_lang_key UNIQUE (categoria_id, lang);


--
-- Name: categorias_i18n categorias_i18n_lang_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_i18n
    ADD CONSTRAINT categorias_i18n_lang_slug_key UNIQUE (lang, slug);


--
-- Name: categorias_i18n categorias_i18n_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_i18n
    ADD CONSTRAINT categorias_i18n_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_slug_key UNIQUE (slug);


--
-- Name: config_redes_formatos config_redes_formatos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_redes_formatos
    ADD CONSTRAINT config_redes_formatos_pkey PRIMARY KEY (id);


--
-- Name: config_redes_formatos config_redes_formatos_rede_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_redes_formatos
    ADD CONSTRAINT config_redes_formatos_rede_key UNIQUE (rede);


--
-- Name: configuracoes configuracoes_chave_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_chave_key UNIQUE (chave);


--
-- Name: configuracoes_i18n configuracoes_i18n_chave_lang_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes_i18n
    ADD CONSTRAINT configuracoes_i18n_chave_lang_key UNIQUE (chave, lang);


--
-- Name: configuracoes_i18n configuracoes_i18n_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes_i18n
    ADD CONSTRAINT configuracoes_i18n_pkey PRIMARY KEY (id);


--
-- Name: configuracoes configuracoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_pkey PRIMARY KEY (id);


--
-- Name: historico_imagens historico_imagens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.historico_imagens
    ADD CONSTRAINT historico_imagens_pkey PRIMARY KEY (id);


--
-- Name: metricas_publicacoes metricas_publicacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metricas_publicacoes
    ADD CONSTRAINT metricas_publicacoes_pkey PRIMARY KEY (id);


--
-- Name: posts_linkedin posts_linkedin_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts_linkedin
    ADD CONSTRAINT posts_linkedin_pkey PRIMARY KEY (id);


--
-- Name: projetos_i18n projetos_i18n_lang_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos_i18n
    ADD CONSTRAINT projetos_i18n_lang_slug_key UNIQUE (lang, slug);


--
-- Name: projetos_i18n projetos_i18n_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos_i18n
    ADD CONSTRAINT projetos_i18n_pkey PRIMARY KEY (id);


--
-- Name: projetos_i18n projetos_i18n_projeto_id_lang_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos_i18n
    ADD CONSTRAINT projetos_i18n_projeto_id_lang_key UNIQUE (projeto_id, lang);


--
-- Name: projetos projetos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos
    ADD CONSTRAINT projetos_pkey PRIMARY KEY (id);


--
-- Name: projetos projetos_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos
    ADD CONSTRAINT projetos_slug_key UNIQUE (slug);


--
-- Name: publicacoes_redes publicacoes_redes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publicacoes_redes
    ADD CONSTRAINT publicacoes_redes_pkey PRIMARY KEY (id);


--
-- Name: radar_ideas radar_ideas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_ideas
    ADD CONSTRAINT radar_ideas_pkey PRIMARY KEY (id);


--
-- Name: radar_item_topics radar_item_topics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_item_topics
    ADD CONSTRAINT radar_item_topics_pkey PRIMARY KEY (item_id, topic_id);


--
-- Name: radar_items radar_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_items
    ADD CONSTRAINT radar_items_pkey PRIMARY KEY (id);


--
-- Name: radar_items radar_items_url_norm_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_items
    ADD CONSTRAINT radar_items_url_norm_key UNIQUE (url_norm);


--
-- Name: radar_runs radar_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_runs
    ADD CONSTRAINT radar_runs_pkey PRIMARY KEY (id);


--
-- Name: radar_sources radar_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_sources
    ADD CONSTRAINT radar_sources_pkey PRIMARY KEY (id);


--
-- Name: radar_topic_sources radar_topic_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_topic_sources
    ADD CONSTRAINT radar_topic_sources_pkey PRIMARY KEY (topic_id, source_id);


--
-- Name: radar_topics radar_topics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_topics
    ADD CONSTRAINT radar_topics_pkey PRIMARY KEY (id);


--
-- Name: redes_sociais_config redes_sociais_config_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.redes_sociais_config
    ADD CONSTRAINT redes_sociais_config_pkey PRIMARY KEY (id);


--
-- Name: redes_sociais_config redes_sociais_config_rede_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.redes_sociais_config
    ADD CONSTRAINT redes_sociais_config_rede_key UNIQUE (rede);


--
-- Name: site_accesses site_accesses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_accesses
    ADD CONSTRAINT site_accesses_pkey PRIMARY KEY (id);


--
-- Name: ui_strings ui_strings_chave_lang_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_strings
    ADD CONSTRAINT ui_strings_chave_lang_key UNIQUE (chave, lang);


--
-- Name: ui_strings ui_strings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ui_strings
    ADD CONSTRAINT ui_strings_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_key UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: video_jobs video_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_jobs
    ADD CONSTRAINT video_jobs_pkey PRIMARY KEY (id);


--
-- Name: idx_agendamentos_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_agendamentos_data ON public.agendamentos_posts USING btree (data_publicacao);


--
-- Name: idx_agendamentos_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_agendamentos_status ON public.agendamentos_posts USING btree (status);


--
-- Name: idx_artigos_agendamento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_agendamento ON public.artigos USING btree (data_agendamento);


--
-- Name: idx_artigos_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_ativo ON public.artigos USING btree (ativo);


--
-- Name: idx_artigos_categoria; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_categoria ON public.artigos USING btree (categoria_id);


--
-- Name: idx_artigos_i18n_artigo_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_i18n_artigo_lang ON public.artigos_i18n USING btree (artigo_id, lang);


--
-- Name: idx_artigos_i18n_slug; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_i18n_slug ON public.artigos_i18n USING btree (slug);


--
-- Name: idx_artigos_slug; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_slug ON public.artigos USING btree (slug);


--
-- Name: idx_artigos_social_variants_artigo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_social_variants_artigo ON public.artigos_social_variants USING btree (artigo_id);


--
-- Name: idx_artigos_social_variants_rede; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_social_variants_rede ON public.artigos_social_variants USING btree (rede);


--
-- Name: idx_artigos_status_pub; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_artigos_status_pub ON public.artigos USING btree (status_publicacao);


--
-- Name: idx_cat_art_i18n_categoria_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cat_art_i18n_categoria_lang ON public.categorias_artigos_i18n USING btree (categoria_id, lang);


--
-- Name: idx_categorias_i18n_categoria_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_categorias_i18n_categoria_lang ON public.categorias_i18n USING btree (categoria_id, lang);


--
-- Name: idx_config_i18n_chave_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_config_i18n_chave_lang ON public.configuracoes_i18n USING btree (chave, lang);


--
-- Name: idx_metricas_publicacao; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_metricas_publicacao ON public.metricas_publicacoes USING btree (publicacao_id);


--
-- Name: idx_posts_linkedin_projeto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_posts_linkedin_projeto ON public.posts_linkedin USING btree (projeto_id);


--
-- Name: idx_projetos_i18n_projeto_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_projetos_i18n_projeto_lang ON public.projetos_i18n USING btree (projeto_id, lang);


--
-- Name: idx_projetos_i18n_slug; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_projetos_i18n_slug ON public.projetos_i18n USING btree (slug);


--
-- Name: idx_publicacoes_artigo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_publicacoes_artigo ON public.publicacoes_redes USING btree (artigo_id);


--
-- Name: idx_publicacoes_rede; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_publicacoes_rede ON public.publicacoes_redes USING btree (rede);


--
-- Name: idx_radar_ideas_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_ideas_created_at ON public.radar_ideas USING btree (created_at DESC);


--
-- Name: idx_radar_ideas_topic_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_ideas_topic_status ON public.radar_ideas USING btree (topic_id, status);


--
-- Name: idx_radar_item_topics_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_item_topics_item ON public.radar_item_topics USING btree (item_id);


--
-- Name: idx_radar_item_topics_topic; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_item_topics_topic ON public.radar_item_topics USING btree (topic_id);


--
-- Name: idx_radar_items_fetched_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_items_fetched_at ON public.radar_items USING btree (fetched_at DESC);


--
-- Name: idx_radar_items_score; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_items_score ON public.radar_items USING btree (score DESC);


--
-- Name: idx_radar_items_source_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_items_source_id ON public.radar_items USING btree (source_id);


--
-- Name: idx_radar_runs_started_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_runs_started_at ON public.radar_runs USING btree (started_at DESC);


--
-- Name: idx_radar_sources_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_sources_ativo ON public.radar_sources USING btree (ativo);


--
-- Name: idx_radar_sources_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_sources_tipo ON public.radar_sources USING btree (tipo);


--
-- Name: idx_radar_topic_sources_source; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_topic_sources_source ON public.radar_topic_sources USING btree (source_id);


--
-- Name: idx_radar_topic_sources_topic; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_topic_sources_topic ON public.radar_topic_sources USING btree (topic_id);


--
-- Name: idx_radar_topics_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_radar_topics_ativo ON public.radar_topics USING btree (ativo);


--
-- Name: idx_site_accesses_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_site_accesses_created_at ON public.site_accesses USING btree (created_at);


--
-- Name: idx_site_accesses_path; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_site_accesses_path ON public.site_accesses USING btree (path);


--
-- Name: idx_ui_strings_chave_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ui_strings_chave_lang ON public.ui_strings USING btree (chave, lang);


--
-- Name: idx_video_jobs_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_video_jobs_created_at ON public.video_jobs USING btree (created_at);


--
-- Name: idx_video_jobs_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_video_jobs_status ON public.video_jobs USING btree (status);


--
-- Name: agendamentos_posts agendamentos_posts_artigo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agendamentos_posts
    ADD CONSTRAINT agendamentos_posts_artigo_id_fkey FOREIGN KEY (artigo_id) REFERENCES public.artigos(id) ON DELETE CASCADE;


--
-- Name: artigos artigos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos
    ADD CONSTRAINT artigos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias_artigos(id) ON DELETE SET NULL;


--
-- Name: artigos_i18n artigos_i18n_artigo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_i18n
    ADD CONSTRAINT artigos_i18n_artigo_id_fkey FOREIGN KEY (artigo_id) REFERENCES public.artigos(id) ON DELETE CASCADE;


--
-- Name: artigos_social_variants artigos_social_variants_artigo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.artigos_social_variants
    ADD CONSTRAINT artigos_social_variants_artigo_id_fkey FOREIGN KEY (artigo_id) REFERENCES public.artigos(id) ON DELETE CASCADE;


--
-- Name: categorias_artigos_i18n categorias_artigos_i18n_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_artigos_i18n
    ADD CONSTRAINT categorias_artigos_i18n_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias_artigos(id) ON DELETE CASCADE;


--
-- Name: categorias_i18n categorias_i18n_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_i18n
    ADD CONSTRAINT categorias_i18n_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE CASCADE;


--
-- Name: historico_imagens historico_imagens_artigo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.historico_imagens
    ADD CONSTRAINT historico_imagens_artigo_id_fkey FOREIGN KEY (artigo_id) REFERENCES public.artigos(id) ON DELETE CASCADE;


--
-- Name: metricas_publicacoes metricas_publicacoes_publicacao_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metricas_publicacoes
    ADD CONSTRAINT metricas_publicacoes_publicacao_id_fkey FOREIGN KEY (publicacao_id) REFERENCES public.publicacoes_redes(id) ON DELETE CASCADE;


--
-- Name: posts_linkedin posts_linkedin_projeto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts_linkedin
    ADD CONSTRAINT posts_linkedin_projeto_id_fkey FOREIGN KEY (projeto_id) REFERENCES public.projetos(id) ON DELETE CASCADE;


--
-- Name: projetos projetos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos
    ADD CONSTRAINT projetos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE SET NULL;


--
-- Name: projetos_i18n projetos_i18n_projeto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projetos_i18n
    ADD CONSTRAINT projetos_i18n_projeto_id_fkey FOREIGN KEY (projeto_id) REFERENCES public.projetos(id) ON DELETE CASCADE;


--
-- Name: publicacoes_redes publicacoes_redes_artigo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publicacoes_redes
    ADD CONSTRAINT publicacoes_redes_artigo_id_fkey FOREIGN KEY (artigo_id) REFERENCES public.artigos(id) ON DELETE CASCADE;


--
-- Name: radar_ideas radar_ideas_topic_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_ideas
    ADD CONSTRAINT radar_ideas_topic_id_fkey FOREIGN KEY (topic_id) REFERENCES public.radar_topics(id) ON DELETE CASCADE;


--
-- Name: radar_item_topics radar_item_topics_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_item_topics
    ADD CONSTRAINT radar_item_topics_item_id_fkey FOREIGN KEY (item_id) REFERENCES public.radar_items(id) ON DELETE CASCADE;


--
-- Name: radar_item_topics radar_item_topics_topic_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_item_topics
    ADD CONSTRAINT radar_item_topics_topic_id_fkey FOREIGN KEY (topic_id) REFERENCES public.radar_topics(id) ON DELETE CASCADE;


--
-- Name: radar_items radar_items_source_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_items
    ADD CONSTRAINT radar_items_source_id_fkey FOREIGN KEY (source_id) REFERENCES public.radar_sources(id) ON DELETE SET NULL;


--
-- Name: radar_topic_sources radar_topic_sources_source_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_topic_sources
    ADD CONSTRAINT radar_topic_sources_source_id_fkey FOREIGN KEY (source_id) REFERENCES public.radar_sources(id) ON DELETE CASCADE;


--
-- Name: radar_topic_sources radar_topic_sources_topic_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_topic_sources
    ADD CONSTRAINT radar_topic_sources_topic_id_fkey FOREIGN KEY (topic_id) REFERENCES public.radar_topics(id) ON DELETE CASCADE;


--
-- Name: radar_topics radar_topics_categoria_artigos_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radar_topics
    ADD CONSTRAINT radar_topics_categoria_artigos_id_fkey FOREIGN KEY (categoria_artigos_id) REFERENCES public.categorias_artigos(id) ON DELETE SET NULL;


--
-- Name: video_jobs video_jobs_variant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_jobs
    ADD CONSTRAINT video_jobs_variant_id_fkey FOREIGN KEY (variant_id) REFERENCES public.artigos_social_variants(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict uKLym2CgfvYyM8QGiLLZEVfWqqid5w1NvbW3pn0BrGjbaUucqSfidRJ9U9hsrmM

