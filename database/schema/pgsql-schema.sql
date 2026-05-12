--
-- PostgreSQL database dump
--

\restrict xWlsfUYOsYqQfQQZKF9tnsl4kGn4JW0LRV7ndxmr3wc608uFBgU0d78SMgVzJFw

-- Dumped from database version 18.3
-- Dumped by pg_dump version 18.3 (Ubuntu 18.3-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: btree_gin; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS btree_gin WITH SCHEMA public;


--
-- Name: EXTENSION btree_gin; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION btree_gin IS 'support for indexing common datatypes in GIN';


--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: unaccent; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;


--
-- Name: EXTENSION unaccent; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION unaccent IS 'text search dictionary that removes accents';


--
-- Name: anotacoes_immutable_trigger_fn(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.anotacoes_immutable_trigger_fn() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'anotacoes is append-only: UPDATE and DELETE are not permitted';
    END;
    $$;


--
-- Name: audit_logs_cold_immutable(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.audit_logs_cold_immutable() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'audit_logs_cold is append-only: UPDATE and DELETE are not permitted';
    END;
    $$;


--
-- Name: audit_logs_immutable(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.audit_logs_immutable() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'audit_logs is append-only: UPDATE and DELETE are not permitted';
    END;
    $$;


--
-- Name: eventos_timeline_immutable_trigger_fn(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.eventos_timeline_immutable_trigger_fn() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'eventos_timeline is append-only: UPDATE and DELETE are not permitted';
    END;
    $$;


--
-- Name: immutable_unaccent(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.immutable_unaccent(text) RETURNS text
    LANGUAGE sql IMMUTABLE STRICT
    AS $_$ SELECT public.unaccent($1) $_$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: ai_usage_meters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_usage_meters (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    year_month character(7) NOT NULL,
    messages_count integer DEFAULT 0 NOT NULL,
    included_quota_snapshot integer NOT NULL,
    overage_count integer DEFAULT 0 NOT NULL,
    hard_cap integer,
    hard_cap_triggered_at timestamp(0) with time zone,
    last_reset_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: ai_usage_meters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_usage_meters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_usage_meters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_usage_meters_id_seq OWNED BY public.ai_usage_meters.id;


--
-- Name: anotacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.anotacoes (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    paciente_id bigint NOT NULL,
    tipo character varying(20) NOT NULL,
    texto text NOT NULL,
    autor_id bigint NOT NULL,
    retratacao_de_anotacao_id bigint,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT anotacoes_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['geral'::character varying, 'clinica'::character varying, 'comportamental'::character varying, 'financeira'::character varying])::text[])))
);


--
-- Name: anotacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.anotacoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: anotacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.anotacoes_id_seq OWNED BY public.anotacoes.id;


--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    tenant_id bigint,
    user_id bigint,
    actor_type character varying(20) NOT NULL,
    action character varying(100) NOT NULL,
    auditable_type character varying(150),
    auditable_id bigint,
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    ip inet,
    user_agent character varying(500),
    request_id character varying(50),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    executor_id bigint,
    CONSTRAINT audit_logs_actor_type_check CHECK (((actor_type)::text = ANY ((ARRAY['user'::character varying, 'system'::character varying, 'webhook'::character varying])::text[])))
);


--
-- Name: audit_logs_cold; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_logs_cold (
    id bigint NOT NULL,
    tenant_id bigint,
    user_id bigint,
    actor_type character varying(20) NOT NULL,
    action character varying(100) NOT NULL,
    auditable_type character varying(150),
    auditable_id bigint,
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    ip inet,
    user_agent character varying(500),
    request_id character varying(50),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT audit_logs_cold_actor_type_check CHECK (((actor_type)::text = ANY ((ARRAY['user'::character varying, 'system'::character varying, 'webhook'::character varying])::text[])))
);


--
-- Name: audit_logs_cold_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_logs_cold_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_logs_cold_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_logs_cold_id_seq OWNED BY public.audit_logs_cold.id;


--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: convenios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.convenios (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    nome character varying(150) NOT NULL,
    codigo_ans character varying(10),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: convenios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.convenios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: convenios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.convenios_id_seq OWNED BY public.convenios.id;


--
-- Name: eventos_timeline; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.eventos_timeline (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    paciente_id bigint NOT NULL,
    tipo character varying(60) NOT NULL,
    autor_id bigint,
    actor_type character varying(20) DEFAULT 'user'::character varying NOT NULL,
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    referencia_tipo character varying(150),
    referencia_id bigint,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT eventos_timeline_actor_type_check CHECK (((actor_type)::text = ANY ((ARRAY['user'::character varying, 'system'::character varying, 'webhook'::character varying, 'ia'::character varying])::text[])))
);


--
-- Name: eventos_timeline_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.eventos_timeline_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: eventos_timeline_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.eventos_timeline_id_seq OWNED BY public.eventos_timeline.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: funil_colunas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.funil_colunas (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    nome character varying(50) NOT NULL,
    slug character varying(50) NOT NULL,
    posicao integer NOT NULL,
    cor character varying(7),
    is_terminal boolean DEFAULT false NOT NULL,
    motivo_obrigatorio boolean DEFAULT false NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: funil_colunas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.funil_colunas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: funil_colunas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.funil_colunas_id_seq OWNED BY public.funil_colunas.id;


--
-- Name: importacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.importacoes (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    executor_id bigint NOT NULL,
    arquivo_path character varying(255) NOT NULL,
    arquivo_nome_original character varying(255) NOT NULL,
    arquivo_hash character(64) NOT NULL,
    arquivo_tamanho_bytes bigint NOT NULL,
    total_linhas integer,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    status_inicial_pacientes character varying(10) NOT NULL,
    checkpoint jsonb DEFAULT '{}'::jsonb NOT NULL,
    relatorio jsonb,
    started_at timestamp(0) with time zone,
    finished_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT importacoes_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'completed'::character varying, 'partial_failure'::character varying, 'failed'::character varying, 'retrying'::character varying])::text[]))),
    CONSTRAINT importacoes_status_inicial_check CHECK (((status_inicial_pacientes)::text = ANY ((ARRAY['lead'::character varying, 'ativo'::character varying])::text[])))
);


--
-- Name: importacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.importacoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: importacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.importacoes_id_seq OWNED BY public.importacoes.id;


--
-- Name: invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invitations (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    email character varying(254) NOT NULL,
    intended_role character varying(50) NOT NULL,
    inviter_user_id bigint NOT NULL,
    token_hash character varying(255) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    expires_at timestamp(0) with time zone NOT NULL,
    accepted_at timestamp(0) with time zone,
    revoked_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT invitations_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'accepted'::character varying, 'expired'::character varying, 'revoked'::character varying])::text[])))
);


--
-- Name: invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.invitations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invitations_id_seq OWNED BY public.invitations.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: mesclagens_pacientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mesclagens_pacientes (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    paciente_alvo_id bigint NOT NULL,
    pacientes_origem_ids jsonb NOT NULL,
    executor_id bigint NOT NULL,
    snapshot_pre_merge jsonb NOT NULL,
    resolucoes jsonb DEFAULT '{}'::jsonb NOT NULL,
    executada_em timestamp(0) with time zone NOT NULL,
    reversivel_ate timestamp(0) with time zone NOT NULL,
    revertida_em timestamp(0) with time zone,
    revertida_por_user_id bigint,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: mesclagens_pacientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mesclagens_pacientes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mesclagens_pacientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mesclagens_pacientes_id_seq OWNED BY public.mesclagens_pacientes.id;


--
-- Name: messaging_assignment_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_assignment_rules (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    channel_id bigint,
    strategy character varying(30) NOT NULL,
    priority integer DEFAULT 100 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT messaging_assignment_rules_strategy_check CHECK (((strategy)::text = ANY ((ARRAY['round_robin'::character varying, 'patient_owner'::character varying, 'manual'::character varying])::text[])))
);


--
-- Name: messaging_assignment_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_assignment_rules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_assignment_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_assignment_rules_id_seq OWNED BY public.messaging_assignment_rules.id;


--
-- Name: messaging_channel_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_channel_templates (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    channel_id bigint NOT NULL,
    provider_template_id character varying(100) NOT NULL,
    meta_template_name character varying(100) NOT NULL,
    meta_template_status character varying(20) NOT NULL,
    language character varying(10) DEFAULT 'pt_BR'::character varying NOT NULL,
    category character varying(30),
    body_preview text,
    variables_schema jsonb DEFAULT '[]'::jsonb NOT NULL,
    last_synced_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT messaging_channel_templates_status_check CHECK (((meta_template_status)::text = ANY ((ARRAY['approved'::character varying, 'pending'::character varying, 'rejected'::character varying, 'paused'::character varying])::text[])))
);


--
-- Name: messaging_channel_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_channel_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_channel_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_channel_templates_id_seq OWNED BY public.messaging_channel_templates.id;


--
-- Name: messaging_channels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_channels (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    type character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    status character varying(20) DEFAULT 'ativo'::character varying NOT NULL,
    credentials_encrypted text,
    provider_metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    quality_rating character varying(20),
    quality_rating_updated_at timestamp(0) with time zone,
    last_health_check_at timestamp(0) with time zone,
    auto_send_disabled boolean DEFAULT false NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    CONSTRAINT messaging_channels_quality_rating_check CHECK (((quality_rating IS NULL) OR ((quality_rating)::text = ANY ((ARRAY['high'::character varying, 'medium'::character varying, 'low'::character varying, 'flagged'::character varying])::text[])))),
    CONSTRAINT messaging_channels_status_check CHECK (((status)::text = ANY ((ARRAY['ativo'::character varying, 'desconectado'::character varying, 'invalido'::character varying, 'expirado'::character varying, 'degradado'::character varying, 'suspenso'::character varying])::text[]))),
    CONSTRAINT messaging_channels_type_check CHECK (((type)::text = ANY ((ARRAY['whatsapp'::character varying, 'instagram'::character varying, 'web'::character varying])::text[])))
);


--
-- Name: messaging_channels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_channels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_channels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_channels_id_seq OWNED BY public.messaging_channels.id;


--
-- Name: messaging_conversation_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_conversation_assignments (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    conversation_id bigint NOT NULL,
    user_id bigint,
    assigned_by bigint,
    assignment_role character varying(30),
    assigned_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    unassigned_at timestamp(0) with time zone,
    transfer_note text,
    reason character varying(50),
    CONSTRAINT messaging_conversation_assignments_reason_check CHECK (((reason IS NULL) OR ((reason)::text = ANY ((ARRAY['inicial'::character varying, 'manual'::character varying, 'transferencia'::character varying, 'reassign_offline'::character varying, 'auto_atribuicao'::character varying])::text[]))))
);


--
-- Name: messaging_conversation_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_conversation_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_conversation_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_conversation_assignments_id_seq OWNED BY public.messaging_conversation_assignments.id;


--
-- Name: messaging_conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_conversations (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    channel_id bigint NOT NULL,
    patient_id bigint,
    external_thread_id character varying(255) NOT NULL,
    status character varying(20) DEFAULT 'aberta'::character varying NOT NULL,
    assigned_user_id bigint,
    assigned_at timestamp(0) with time zone,
    assignment_strategy character varying(30),
    ai_paused_until timestamp(0) with time zone,
    ai_pause_set_by bigint,
    last_message_at timestamp(0) with time zone,
    last_inbound_message_at timestamp(0) with time zone,
    opened_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    resolved_at timestamp(0) with time zone,
    resolution_mode character varying(20),
    priority character varying(10) DEFAULT 'normal'::character varying NOT NULL,
    received_outside_hours boolean DEFAULT false NOT NULL,
    unread_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    external_thread_id_normalized character varying(255) GENERATED ALWAYS AS (lower((external_thread_id)::text)) STORED,
    CONSTRAINT messaging_conversations_assignment_strategy_check CHECK (((assignment_strategy IS NULL) OR ((assignment_strategy)::text = ANY ((ARRAY['manual'::character varying, 'auto_round_robin'::character varying, 'auto_patient_owner'::character varying, 'transfer'::character varying])::text[])))),
    CONSTRAINT messaging_conversations_priority_check CHECK (((priority)::text = ANY ((ARRAY['alta'::character varying, 'normal'::character varying, 'baixa'::character varying])::text[]))),
    CONSTRAINT messaging_conversations_resolution_mode_check CHECK (((resolution_mode IS NULL) OR ((resolution_mode)::text = ANY ((ARRAY['manual'::character varying, 'auto_inatividade'::character varying])::text[])))),
    CONSTRAINT messaging_conversations_status_check CHECK (((status)::text = ANY ((ARRAY['aberta'::character varying, 'pendente'::character varying, 'resolvida'::character varying, 'reaberta'::character varying])::text[])))
);


--
-- Name: messaging_conversations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_conversations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_conversations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_conversations_id_seq OWNED BY public.messaging_conversations.id;


--
-- Name: messaging_message_media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_message_media (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    message_id bigint NOT NULL,
    storage_disk character varying(20) DEFAULT 'media'::character varying NOT NULL,
    storage_path character varying(500) NOT NULL,
    mime_type character varying(100) NOT NULL,
    size_bytes bigint NOT NULL,
    original_filename character varying(255),
    checksum_sha256 character(64) NOT NULL,
    sensitive_hint boolean DEFAULT true NOT NULL,
    media_purged_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: messaging_message_media_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_message_media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_message_media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_message_media_id_seq OWNED BY public.messaging_message_media.id;


--
-- Name: messaging_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_messages (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    conversation_id bigint NOT NULL,
    direction character varying(3) NOT NULL,
    sender_type character varying(20) NOT NULL,
    sender_id bigint,
    body text,
    body_searchable character varying(2000),
    body_preview character varying(140),
    content_type character varying(20) NOT NULL,
    template_provider_id character varying(100),
    template_variables jsonb,
    external_id character varying(255),
    external_metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    status character varying(20) DEFAULT 'queued'::character varying NOT NULL,
    failure_reason character varying(255),
    idempotency_key character varying(64),
    sent_at timestamp(0) with time zone,
    delivered_at timestamp(0) with time zone,
    read_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    body_searchable_normalized character varying(2000) GENERATED ALWAYS AS (lower(public.immutable_unaccent((COALESCE(body_searchable, ''::character varying))::text))) STORED,
    CONSTRAINT messaging_messages_content_type_check CHECK (((content_type)::text = ANY ((ARRAY['text'::character varying, 'image'::character varying, 'audio'::character varying, 'video'::character varying, 'document'::character varying, 'template'::character varying, 'interactive'::character varying, 'location'::character varying, 'contact'::character varying])::text[]))),
    CONSTRAINT messaging_messages_direction_check CHECK (((direction)::text = ANY ((ARRAY['in'::character varying, 'out'::character varying])::text[]))),
    CONSTRAINT messaging_messages_sender_type_check CHECK (((sender_type)::text = ANY ((ARRAY['patient'::character varying, 'user'::character varying, 'system'::character varying, 'ai'::character varying])::text[]))),
    CONSTRAINT messaging_messages_status_check CHECK (((status)::text = ANY ((ARRAY['queued'::character varying, 'sent'::character varying, 'delivered'::character varying, 'read'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: messaging_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_messages_id_seq OWNED BY public.messaging_messages.id;


--
-- Name: messaging_quick_replies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_quick_replies (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    owner_user_id bigint,
    shortcut character varying(50) NOT NULL,
    content text NOT NULL,
    has_media boolean DEFAULT false NOT NULL,
    variables_used jsonb DEFAULT '[]'::jsonb NOT NULL,
    usage_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: messaging_quick_replies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_quick_replies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_quick_replies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_quick_replies_id_seq OWNED BY public.messaging_quick_replies.id;


--
-- Name: messaging_user_presence; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_user_presence (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    user_id bigint NOT NULL,
    status character varying(20) DEFAULT 'offline'::character varying NOT NULL,
    last_seen_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    max_concurrent_conversations integer DEFAULT 15 NOT NULL,
    current_assigned_count integer DEFAULT 0 NOT NULL,
    notification_preferences jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT messaging_user_presence_status_check CHECK (((status)::text = ANY ((ARRAY['online'::character varying, 'away'::character varying, 'offline'::character varying])::text[])))
);


--
-- Name: messaging_user_presence_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_user_presence_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_user_presence_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_user_presence_id_seq OWNED BY public.messaging_user_presence.id;


--
-- Name: messaging_web_widget_configs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_web_widget_configs (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    channel_id bigint NOT NULL,
    public_key character varying(64) NOT NULL,
    allowed_origins jsonb DEFAULT '[]'::jsonb NOT NULL,
    appearance jsonb DEFAULT '{}'::jsonb NOT NULL,
    initial_message text,
    business_hours jsonb DEFAULT '{}'::jsonb NOT NULL,
    outside_hours_behavior character varying(20) DEFAULT 'fila'::character varying NOT NULL,
    pre_chat_form character varying(30) DEFAULT 'opcional'::character varying NOT NULL,
    outside_hours_message text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT messaging_web_widget_configs_outside_hours_behavior_check CHECK (((outside_hours_behavior)::text = ANY ((ARRAY['bloqueia'::character varying, 'fila'::character varying, 'normal'::character varying])::text[]))),
    CONSTRAINT messaging_web_widget_configs_pre_chat_form_check CHECK (((pre_chat_form)::text = ANY ((ARRAY['opcional'::character varying, 'exigido_para_iniciar'::character varying, 'exigido_para_enviar'::character varying])::text[])))
);


--
-- Name: messaging_web_widget_configs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_web_widget_configs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_web_widget_configs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_web_widget_configs_id_seq OWNED BY public.messaging_web_widget_configs.id;


--
-- Name: messaging_web_widget_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_web_widget_sessions (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    widget_config_id bigint NOT NULL,
    visitor_token character varying(64) NOT NULL,
    ip_hash character(64) NOT NULL,
    user_agent text,
    referer_domain character varying(255),
    started_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    last_activity_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    identified_patient_id bigint,
    provisional_data jsonb DEFAULT '{}'::jsonb NOT NULL,
    expires_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: messaging_web_widget_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_web_widget_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_web_widget_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_web_widget_sessions_id_seq OWNED BY public.messaging_web_widget_sessions.id;


--
-- Name: messaging_webhook_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messaging_webhook_events (
    id bigint NOT NULL,
    provider character varying(20) NOT NULL,
    external_id character varying(255) NOT NULL,
    channel_id bigint,
    tenant_id bigint,
    event_type character varying(50) NOT NULL,
    raw_payload_encrypted text NOT NULL,
    signature_verified boolean NOT NULL,
    received_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    status character varying(20) DEFAULT 'received'::character varying NOT NULL,
    processing_started_at timestamp(0) with time zone,
    processed_at timestamp(0) with time zone,
    attempts integer DEFAULT 0 NOT NULL,
    failure_reason text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT messaging_webhook_events_provider_check CHECK (((provider)::text = ANY ((ARRAY['twilio'::character varying, 'meta'::character varying, 'widget'::character varying])::text[]))),
    CONSTRAINT messaging_webhook_events_status_check CHECK (((status)::text = ANY ((ARRAY['received'::character varying, 'processing'::character varying, 'processed'::character varying, 'failed'::character varying, 'duplicate'::character varying])::text[])))
);


--
-- Name: messaging_webhook_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messaging_webhook_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messaging_webhook_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messaging_webhook_events_id_seq OWNED BY public.messaging_webhook_events.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    tenant_id bigint
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    tenant_id bigint
);


--
-- Name: paciente_convenios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.paciente_convenios (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    paciente_id bigint NOT NULL,
    convenio_id bigint NOT NULL,
    numero_carteirinha character varying(30),
    papel character varying(20) NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT paciente_convenios_papel_check CHECK (((papel)::text = ANY ((ARRAY['principal'::character varying, 'secundario'::character varying])::text[])))
);


--
-- Name: paciente_convenios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.paciente_convenios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: paciente_convenios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.paciente_convenios_id_seq OWNED BY public.paciente_convenios.id;


--
-- Name: paciente_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.paciente_tags (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    paciente_id bigint NOT NULL,
    tag_id bigint NOT NULL,
    aplicada_por_user_id bigint,
    aplicada_at timestamp(0) with time zone NOT NULL
);


--
-- Name: paciente_tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.paciente_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: paciente_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.paciente_tags_id_seq OWNED BY public.paciente_tags.id;


--
-- Name: pacientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pacientes (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    nome character varying(150) NOT NULL,
    cpf character varying(14),
    documento_estrangeiro character varying(30),
    data_nascimento date,
    telefone_primario character varying(20),
    telefones_secundarios jsonb DEFAULT '[]'::jsonb NOT NULL,
    email character varying(254),
    endereco jsonb,
    status character varying(20) DEFAULT 'lead'::character varying NOT NULL,
    origem character varying(20) DEFAULT 'outro'::character varying NOT NULL,
    origem_detalhe character varying(255),
    origem_origem character varying(10) DEFAULT 'manual'::character varying NOT NULL,
    profissional_responsavel_id bigint,
    convenio_principal_id bigint,
    funil_coluna_atual_id bigint,
    funil_posicao numeric(20,10),
    anonimizado_em timestamp(0) with time zone,
    merged_into_paciente_id bigint,
    merged_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    nome_normalizado character varying(150) GENERATED ALWAYS AS (lower(public.immutable_unaccent((nome)::text))) STORED,
    telefone_primario_normalizado character varying(20) GENERATED ALWAYS AS (regexp_replace((COALESCE(telefone_primario, ''::character varying))::text, '\D'::text, ''::text, 'g'::text)) STORED,
    CONSTRAINT pacientes_origem_check CHECK (((origem)::text = ANY ((ARRAY['site'::character varying, 'indicacao'::character varying, 'whatsapp'::character varying, 'instagram'::character varying, 'telefone'::character varying, 'presencial'::character varying, 'outro'::character varying])::text[]))),
    CONSTRAINT pacientes_origem_origem_check CHECK (((origem_origem)::text = ANY ((ARRAY['manual'::character varying, 'canal'::character varying])::text[]))),
    CONSTRAINT pacientes_status_check CHECK (((status)::text = ANY ((ARRAY['lead'::character varying, 'ativo'::character varying, 'inativo'::character varying, 'bloqueado'::character varying])::text[])))
);


--
-- Name: pacientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pacientes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pacientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pacientes_id_seq OWNED BY public.pacientes.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(254) NOT NULL,
    tenant_id bigint,
    token character varying(255) NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(125) NOT NULL,
    guard_name character varying(125) NOT NULL,
    tenant_id bigint,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) with time zone,
    expires_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: plans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plans (
    id bigint NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    base_price_cents bigint NOT NULL,
    included_professionals integer DEFAULT 0 NOT NULL,
    included_ai_messages integer NOT NULL,
    overage_price_cents integer NOT NULL,
    max_users integer NOT NULL,
    max_channels integer NOT NULL,
    stripe_price_id_base character varying(100) NOT NULL,
    stripe_price_id_overage character varying(100) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: plans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: plans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.plans_id_seq OWNED BY public.plans.id;


--
-- Name: professionals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.professionals (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    user_id bigint,
    name character varying(150) NOT NULL,
    council_type character varying(10),
    council_number character varying(20),
    council_state character(2),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: professionals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.professionals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: professionals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.professionals_id_seq OWNED BY public.professionals.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(125) NOT NULL,
    guard_name character varying(125) NOT NULL,
    tenant_id bigint,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: stripe_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stripe_events (
    id character varying(255) NOT NULL,
    type character varying(100) NOT NULL,
    payload jsonb NOT NULL,
    received_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    processed_at timestamp(0) with time zone,
    failure_reason text
);


--
-- Name: subscription_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_items (
    id bigint NOT NULL,
    subscription_id bigint NOT NULL,
    stripe_id character varying(255) NOT NULL,
    stripe_product character varying(100),
    stripe_price character varying(100) NOT NULL,
    quantity integer,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: subscription_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_items_id_seq OWNED BY public.subscription_items.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    type character varying(50) DEFAULT 'default'::character varying NOT NULL,
    stripe_id character varying(255) NOT NULL,
    stripe_status character varying(20) NOT NULL,
    stripe_price character varying(100),
    quantity integer,
    plan_id bigint NOT NULL,
    plan_snapshot jsonb NOT NULL,
    professionals_quantity integer DEFAULT 0 NOT NULL,
    current_period_start timestamp(0) with time zone NOT NULL,
    current_period_end timestamp(0) with time zone NOT NULL,
    trial_ends_at timestamp(0) with time zone,
    ends_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tags (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    nome character varying(50) NOT NULL,
    nome_normalizado character varying(50) NOT NULL,
    tipo character varying(10) DEFAULT 'livre'::character varying NOT NULL,
    cor character varying(7),
    descricao character varying(255),
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT tags_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['livre'::character varying, 'sistemica'::character varying])::text[])))
);


--
-- Name: tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tags_id_seq OWNED BY public.tags.id;


--
-- Name: tarefas_reatribuicao; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tarefas_reatribuicao (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    profissional_desativado_id bigint NOT NULL,
    pacientes_orfaos_ids jsonb NOT NULL,
    total_pacientes integer NOT NULL,
    criada_em timestamp(0) with time zone NOT NULL,
    concluida_em timestamp(0) with time zone,
    concluida_por_user_id bigint,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: tarefas_reatribuicao_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tarefas_reatribuicao_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tarefas_reatribuicao_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tarefas_reatribuicao_id_seq OWNED BY public.tarefas_reatribuicao.id;


--
-- Name: tenants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenants (
    id bigint NOT NULL,
    slug character varying(63) NOT NULL,
    name character varying(150) NOT NULL,
    cnpj character varying(14) NOT NULL,
    responsible_name character varying(150) NOT NULL,
    responsible_email character varying(254) NOT NULL,
    responsible_phone character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'trial'::character varying NOT NULL,
    trial_ends_at timestamp(0) with time zone,
    overdue_since timestamp(0) with time zone,
    restrictions_applied_at timestamp(0) with time zone,
    plan_id bigint,
    stripe_customer_id character varying(255),
    subdomain_custom character varying(255),
    terms_accepted_at timestamp(0) with time zone NOT NULL,
    terms_version character varying(20) NOT NULL,
    onboarding_state jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    CONSTRAINT tenants_status_check CHECK (((status)::text = ANY ((ARRAY['trial'::character varying, 'active'::character varying, 'overdue'::character varying, 'suspended'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: tenants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tenants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tenants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tenants_id_seq OWNED BY public.tenants.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    tenant_id bigint,
    name character varying(150) NOT NULL,
    email character varying(254) NOT NULL,
    email_verified_at timestamp(0) with time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    status character varying(20) DEFAULT 'invited'::character varying NOT NULL,
    first_login_at timestamp(0) with time zone,
    last_login_at timestamp(0) with time zone,
    last_login_ip inet,
    failed_login_attempts smallint DEFAULT '0'::smallint NOT NULL,
    locked_until timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    CONSTRAINT users_status_check CHECK (((status)::text = ANY ((ARRAY['invited'::character varying, 'active'::character varying, 'disabled'::character varying])::text[])))
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: ai_usage_meters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_usage_meters ALTER COLUMN id SET DEFAULT nextval('public.ai_usage_meters_id_seq'::regclass);


--
-- Name: anotacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anotacoes ALTER COLUMN id SET DEFAULT nextval('public.anotacoes_id_seq'::regclass);


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: audit_logs_cold id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs_cold ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_cold_id_seq'::regclass);


--
-- Name: convenios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.convenios ALTER COLUMN id SET DEFAULT nextval('public.convenios_id_seq'::regclass);


--
-- Name: eventos_timeline id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.eventos_timeline ALTER COLUMN id SET DEFAULT nextval('public.eventos_timeline_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: funil_colunas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funil_colunas ALTER COLUMN id SET DEFAULT nextval('public.funil_colunas_id_seq'::regclass);


--
-- Name: importacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importacoes ALTER COLUMN id SET DEFAULT nextval('public.importacoes_id_seq'::regclass);


--
-- Name: invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations ALTER COLUMN id SET DEFAULT nextval('public.invitations_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: mesclagens_pacientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesclagens_pacientes ALTER COLUMN id SET DEFAULT nextval('public.mesclagens_pacientes_id_seq'::regclass);


--
-- Name: messaging_assignment_rules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_assignment_rules ALTER COLUMN id SET DEFAULT nextval('public.messaging_assignment_rules_id_seq'::regclass);


--
-- Name: messaging_channel_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_channel_templates ALTER COLUMN id SET DEFAULT nextval('public.messaging_channel_templates_id_seq'::regclass);


--
-- Name: messaging_channels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_channels ALTER COLUMN id SET DEFAULT nextval('public.messaging_channels_id_seq'::regclass);


--
-- Name: messaging_conversation_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversation_assignments ALTER COLUMN id SET DEFAULT nextval('public.messaging_conversation_assignments_id_seq'::regclass);


--
-- Name: messaging_conversations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversations ALTER COLUMN id SET DEFAULT nextval('public.messaging_conversations_id_seq'::regclass);


--
-- Name: messaging_message_media id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_message_media ALTER COLUMN id SET DEFAULT nextval('public.messaging_message_media_id_seq'::regclass);


--
-- Name: messaging_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_messages ALTER COLUMN id SET DEFAULT nextval('public.messaging_messages_id_seq'::regclass);


--
-- Name: messaging_quick_replies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_quick_replies ALTER COLUMN id SET DEFAULT nextval('public.messaging_quick_replies_id_seq'::regclass);


--
-- Name: messaging_user_presence id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_user_presence ALTER COLUMN id SET DEFAULT nextval('public.messaging_user_presence_id_seq'::regclass);


--
-- Name: messaging_web_widget_configs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_configs ALTER COLUMN id SET DEFAULT nextval('public.messaging_web_widget_configs_id_seq'::regclass);


--
-- Name: messaging_web_widget_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_sessions ALTER COLUMN id SET DEFAULT nextval('public.messaging_web_widget_sessions_id_seq'::regclass);


--
-- Name: messaging_webhook_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_webhook_events ALTER COLUMN id SET DEFAULT nextval('public.messaging_webhook_events_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: paciente_convenios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_convenios ALTER COLUMN id SET DEFAULT nextval('public.paciente_convenios_id_seq'::regclass);


--
-- Name: paciente_tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_tags ALTER COLUMN id SET DEFAULT nextval('public.paciente_tags_id_seq'::regclass);


--
-- Name: pacientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pacientes ALTER COLUMN id SET DEFAULT nextval('public.pacientes_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: plans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans ALTER COLUMN id SET DEFAULT nextval('public.plans_id_seq'::regclass);


--
-- Name: professionals id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals ALTER COLUMN id SET DEFAULT nextval('public.professionals_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: subscription_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items ALTER COLUMN id SET DEFAULT nextval('public.subscription_items_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags ALTER COLUMN id SET DEFAULT nextval('public.tags_id_seq'::regclass);


--
-- Name: tarefas_reatribuicao id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tarefas_reatribuicao ALTER COLUMN id SET DEFAULT nextval('public.tarefas_reatribuicao_id_seq'::regclass);


--
-- Name: tenants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants ALTER COLUMN id SET DEFAULT nextval('public.tenants_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: ai_usage_meters ai_usage_meters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_usage_meters
    ADD CONSTRAINT ai_usage_meters_pkey PRIMARY KEY (id);


--
-- Name: anotacoes anotacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anotacoes
    ADD CONSTRAINT anotacoes_pkey PRIMARY KEY (id);


--
-- Name: audit_logs_cold audit_logs_cold_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs_cold
    ADD CONSTRAINT audit_logs_cold_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: convenios convenios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.convenios
    ADD CONSTRAINT convenios_pkey PRIMARY KEY (id);


--
-- Name: eventos_timeline eventos_timeline_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.eventos_timeline
    ADD CONSTRAINT eventos_timeline_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: funil_colunas funil_colunas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funil_colunas
    ADD CONSTRAINT funil_colunas_pkey PRIMARY KEY (id);


--
-- Name: importacoes importacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importacoes
    ADD CONSTRAINT importacoes_pkey PRIMARY KEY (id);


--
-- Name: invitations invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: mesclagens_pacientes mesclagens_pacientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesclagens_pacientes
    ADD CONSTRAINT mesclagens_pacientes_pkey PRIMARY KEY (id);


--
-- Name: messaging_assignment_rules messaging_assignment_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_assignment_rules
    ADD CONSTRAINT messaging_assignment_rules_pkey PRIMARY KEY (id);


--
-- Name: messaging_channel_templates messaging_channel_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_channel_templates
    ADD CONSTRAINT messaging_channel_templates_pkey PRIMARY KEY (id);


--
-- Name: messaging_channels messaging_channels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_channels
    ADD CONSTRAINT messaging_channels_pkey PRIMARY KEY (id);


--
-- Name: messaging_conversation_assignments messaging_conversation_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversation_assignments
    ADD CONSTRAINT messaging_conversation_assignments_pkey PRIMARY KEY (id);


--
-- Name: messaging_conversations messaging_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversations
    ADD CONSTRAINT messaging_conversations_pkey PRIMARY KEY (id);


--
-- Name: messaging_message_media messaging_message_media_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_message_media
    ADD CONSTRAINT messaging_message_media_pkey PRIMARY KEY (id);


--
-- Name: messaging_messages messaging_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_messages
    ADD CONSTRAINT messaging_messages_pkey PRIMARY KEY (id);


--
-- Name: messaging_quick_replies messaging_quick_replies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_quick_replies
    ADD CONSTRAINT messaging_quick_replies_pkey PRIMARY KEY (id);


--
-- Name: messaging_user_presence messaging_user_presence_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_user_presence
    ADD CONSTRAINT messaging_user_presence_pkey PRIMARY KEY (id);


--
-- Name: messaging_web_widget_configs messaging_web_widget_configs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_configs
    ADD CONSTRAINT messaging_web_widget_configs_pkey PRIMARY KEY (id);


--
-- Name: messaging_web_widget_sessions messaging_web_widget_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_sessions
    ADD CONSTRAINT messaging_web_widget_sessions_pkey PRIMARY KEY (id);


--
-- Name: messaging_webhook_events messaging_webhook_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_webhook_events
    ADD CONSTRAINT messaging_webhook_events_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: paciente_convenios paciente_convenios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_convenios
    ADD CONSTRAINT paciente_convenios_pkey PRIMARY KEY (id);


--
-- Name: paciente_tags paciente_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_tags
    ADD CONSTRAINT paciente_tags_pkey PRIMARY KEY (id);


--
-- Name: pacientes pacientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: plans plans_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_code_unique UNIQUE (code);


--
-- Name: plans plans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_pkey PRIMARY KEY (id);


--
-- Name: professionals professionals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: stripe_events stripe_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stripe_events
    ADD CONSTRAINT stripe_events_pkey PRIMARY KEY (id);


--
-- Name: subscription_items subscription_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_pkey PRIMARY KEY (id);


--
-- Name: subscription_items subscription_items_stripe_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_stripe_id_unique UNIQUE (stripe_id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_stripe_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_stripe_id_unique UNIQUE (stripe_id);


--
-- Name: tags tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: tarefas_reatribuicao tarefas_reatribuicao_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tarefas_reatribuicao
    ADD CONSTRAINT tarefas_reatribuicao_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_cnpj_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_cnpj_unique UNIQUE (cnpj);


--
-- Name: tenants tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_slug_unique UNIQUE (slug);


--
-- Name: tenants tenants_stripe_customer_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_stripe_customer_id_unique UNIQUE (stripe_customer_id);


--
-- Name: tenants tenants_subdomain_custom_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_subdomain_custom_unique UNIQUE (subdomain_custom);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: ai_usage_hard_cap_triggered_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_usage_hard_cap_triggered_idx ON public.ai_usage_meters USING btree (hard_cap_triggered_at);


--
-- Name: ai_usage_tenant_month_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX ai_usage_tenant_month_uniq ON public.ai_usage_meters USING btree (tenant_id, year_month);


--
-- Name: anotacoes_tenant_paciente_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX anotacoes_tenant_paciente_created_idx ON public.anotacoes USING btree (tenant_id, paciente_id, created_at DESC);


--
-- Name: anotacoes_tipo_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX anotacoes_tipo_idx ON public.anotacoes USING btree (tipo);


--
-- Name: audit_action_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_action_created_idx ON public.audit_logs USING btree (action, created_at DESC);


--
-- Name: audit_auditable_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_auditable_idx ON public.audit_logs USING btree (auditable_type, auditable_id);


--
-- Name: audit_created_at_brin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_created_at_brin ON public.audit_logs USING brin (created_at);


--
-- Name: audit_logs_cold_created_at_brin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_logs_cold_created_at_brin ON public.audit_logs_cold USING brin (created_at);


--
-- Name: audit_logs_cold_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_logs_cold_tenant_id_index ON public.audit_logs_cold USING btree (tenant_id);


--
-- Name: audit_logs_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_logs_tenant_id_index ON public.audit_logs USING btree (tenant_id);


--
-- Name: audit_tenant_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_tenant_created_idx ON public.audit_logs USING btree (tenant_id, created_at DESC);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: convenios_tenant_active_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX convenios_tenant_active_idx ON public.convenios USING btree (tenant_id, is_active);


--
-- Name: convenios_tenant_nome_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX convenios_tenant_nome_unique ON public.convenios USING btree (tenant_id, nome);


--
-- Name: eventos_timeline_created_at_brin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX eventos_timeline_created_at_brin ON public.eventos_timeline USING brin (created_at);


--
-- Name: eventos_timeline_tenant_paciente_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX eventos_timeline_tenant_paciente_created_idx ON public.eventos_timeline USING btree (tenant_id, paciente_id, created_at DESC);


--
-- Name: eventos_timeline_tenant_tipo_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX eventos_timeline_tenant_tipo_created_idx ON public.eventos_timeline USING btree (tenant_id, tipo, created_at DESC);


--
-- Name: funil_colunas_tenant_posicao_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX funil_colunas_tenant_posicao_idx ON public.funil_colunas USING btree (tenant_id, posicao);


--
-- Name: funil_colunas_tenant_posicao_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX funil_colunas_tenant_posicao_unique ON public.funil_colunas USING btree (tenant_id, posicao);


--
-- Name: funil_colunas_tenant_slug_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX funil_colunas_tenant_slug_unique ON public.funil_colunas USING btree (tenant_id, slug);


--
-- Name: importacoes_executor_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX importacoes_executor_idx ON public.importacoes USING btree (executor_id);


--
-- Name: importacoes_tenant_status_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX importacoes_tenant_status_created_idx ON public.importacoes USING btree (tenant_id, status, created_at DESC);


--
-- Name: invitations_expires_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invitations_expires_at_idx ON public.invitations USING btree (expires_at);


--
-- Name: invitations_tenant_email_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invitations_tenant_email_idx ON public.invitations USING btree (tenant_id, email);


--
-- Name: invitations_tenant_email_pending_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX invitations_tenant_email_pending_uniq ON public.invitations USING btree (tenant_id, email) WHERE ((status)::text = 'pending'::text);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: mesclagens_tenant_alvo_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mesclagens_tenant_alvo_idx ON public.mesclagens_pacientes USING btree (tenant_id, paciente_alvo_id);


--
-- Name: mesclagens_tenant_reversivel_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mesclagens_tenant_reversivel_idx ON public.mesclagens_pacientes USING btree (tenant_id, reversivel_ate, revertida_em);


--
-- Name: messages_body_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messages_body_trgm_idx ON public.messaging_messages USING gin (tenant_id, body_searchable_normalized public.gin_trgm_ops);


--
-- Name: messages_created_at_brin_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messages_created_at_brin_idx ON public.messaging_messages USING brin (created_at);


--
-- Name: messaging_assignment_rules_channel_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_assignment_rules_channel_idx ON public.messaging_assignment_rules USING btree (channel_id) WHERE (channel_id IS NOT NULL);


--
-- Name: messaging_assignment_rules_tenant_active_priority_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_assignment_rules_tenant_active_priority_idx ON public.messaging_assignment_rules USING btree (tenant_id, is_active, priority);


--
-- Name: messaging_channel_templates_channel_provider_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_channel_templates_channel_provider_unique ON public.messaging_channel_templates USING btree (channel_id, provider_template_id);


--
-- Name: messaging_channel_templates_tenant_channel_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_channel_templates_tenant_channel_status_idx ON public.messaging_channel_templates USING btree (tenant_id, channel_id, meta_template_status);


--
-- Name: messaging_channels_degraded_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_channels_degraded_status_idx ON public.messaging_channels USING btree (status) WHERE ((status)::text = ANY ((ARRAY['degradado'::character varying, 'invalido'::character varying])::text[]));


--
-- Name: messaging_channels_instagram_account_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_channels_instagram_account_unique ON public.messaging_channels USING btree (tenant_id, type, ((provider_metadata ->> 'ig_business_account_id'::text))) WHERE ((type)::text = 'instagram'::text);


--
-- Name: messaging_channels_tenant_type_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_channels_tenant_type_status_idx ON public.messaging_channels USING btree (tenant_id, type, status);


--
-- Name: messaging_channels_web_public_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_channels_web_public_key_unique ON public.messaging_channels USING btree (((provider_metadata ->> 'public_key'::text))) WHERE ((type)::text = 'web'::text);


--
-- Name: messaging_channels_whatsapp_phone_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_channels_whatsapp_phone_unique ON public.messaging_channels USING btree (tenant_id, type, ((provider_metadata ->> 'phone_number_id'::text))) WHERE ((type)::text = 'whatsapp'::text);


--
-- Name: messaging_conv_assignments_conv_assigned_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conv_assignments_conv_assigned_at_idx ON public.messaging_conversation_assignments USING btree (tenant_id, conversation_id, assigned_at DESC);


--
-- Name: messaging_conv_assignments_user_active_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conv_assignments_user_active_idx ON public.messaging_conversation_assignments USING btree (tenant_id, user_id, unassigned_at) WHERE (unassigned_at IS NULL);


--
-- Name: messaging_conversations_ai_paused_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conversations_ai_paused_idx ON public.messaging_conversations USING btree (tenant_id, ai_paused_until) WHERE (ai_paused_until IS NOT NULL);


--
-- Name: messaging_conversations_auto_resolve_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conversations_auto_resolve_idx ON public.messaging_conversations USING btree (tenant_id, status, last_message_at) WHERE ((status)::text = ANY ((ARRAY['aberta'::character varying, 'pendente'::character varying])::text[]));


--
-- Name: messaging_conversations_tenant_assigned_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conversations_tenant_assigned_status_idx ON public.messaging_conversations USING btree (tenant_id, assigned_user_id, status);


--
-- Name: messaging_conversations_tenant_patient_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conversations_tenant_patient_idx ON public.messaging_conversations USING btree (tenant_id, patient_id, last_message_at DESC) WHERE (patient_id IS NOT NULL);


--
-- Name: messaging_conversations_tenant_status_last_msg_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conversations_tenant_status_last_msg_idx ON public.messaging_conversations USING btree (tenant_id, status, last_message_at DESC);


--
-- Name: messaging_conversations_thread_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_conversations_thread_unique ON public.messaging_conversations USING btree (tenant_id, channel_id, external_thread_id_normalized);


--
-- Name: messaging_conversations_unassigned_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_conversations_unassigned_idx ON public.messaging_conversations USING btree (assigned_user_id) WHERE (assigned_user_id IS NULL);


--
-- Name: messaging_message_media_purge_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_message_media_purge_idx ON public.messaging_message_media USING btree (tenant_id, created_at) WHERE (media_purged_at IS NULL);


--
-- Name: messaging_message_media_tenant_message_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_message_media_tenant_message_idx ON public.messaging_message_media USING btree (tenant_id, message_id);


--
-- Name: messaging_messages_idempotency_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_messages_idempotency_key_unique ON public.messaging_messages USING btree (idempotency_key) WHERE (idempotency_key IS NOT NULL);


--
-- Name: messaging_messages_tenant_conv_created_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_messages_tenant_conv_created_idx ON public.messaging_messages USING btree (tenant_id, conversation_id, created_at DESC);


--
-- Name: messaging_messages_tenant_external_id_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_messages_tenant_external_id_unique ON public.messaging_messages USING btree (tenant_id, external_id) WHERE (external_id IS NOT NULL);


--
-- Name: messaging_messages_tenant_failed_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_messages_tenant_failed_idx ON public.messaging_messages USING btree (tenant_id, status) WHERE ((status)::text = 'failed'::text);


--
-- Name: messaging_messages_tenant_queued_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_messages_tenant_queued_idx ON public.messaging_messages USING btree (tenant_id, status) WHERE ((status)::text = 'queued'::text);


--
-- Name: messaging_quick_replies_scope_shortcut_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_quick_replies_scope_shortcut_unique ON public.messaging_quick_replies USING btree (tenant_id, owner_user_id, shortcut);


--
-- Name: messaging_quick_replies_tenant_scope_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_quick_replies_tenant_scope_idx ON public.messaging_quick_replies USING btree (tenant_id) WHERE (owner_user_id IS NULL);


--
-- Name: messaging_quick_replies_user_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_quick_replies_user_idx ON public.messaging_quick_replies USING btree (tenant_id, owner_user_id) WHERE (owner_user_id IS NOT NULL);


--
-- Name: messaging_user_presence_tenant_online_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_user_presence_tenant_online_idx ON public.messaging_user_presence USING btree (tenant_id, status, last_seen_at) WHERE ((status)::text = 'online'::text);


--
-- Name: messaging_user_presence_tenant_user_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_user_presence_tenant_user_unique ON public.messaging_user_presence USING btree (tenant_id, user_id);


--
-- Name: messaging_web_widget_configs_channel_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_web_widget_configs_channel_unique ON public.messaging_web_widget_configs USING btree (channel_id);


--
-- Name: messaging_web_widget_configs_public_key_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_web_widget_configs_public_key_unique ON public.messaging_web_widget_configs USING btree (public_key);


--
-- Name: messaging_web_widget_configs_tenant_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_web_widget_configs_tenant_idx ON public.messaging_web_widget_configs USING btree (tenant_id);


--
-- Name: messaging_web_widget_sessions_purge_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_web_widget_sessions_purge_idx ON public.messaging_web_widget_sessions USING btree (expires_at) WHERE (identified_patient_id IS NULL);


--
-- Name: messaging_web_widget_sessions_token_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_web_widget_sessions_token_unique ON public.messaging_web_widget_sessions USING btree (visitor_token);


--
-- Name: messaging_web_widget_sessions_widget_activity_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_web_widget_sessions_widget_activity_idx ON public.messaging_web_widget_sessions USING btree (tenant_id, widget_config_id, last_activity_at DESC);


--
-- Name: messaging_webhook_events_channel_received_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_webhook_events_channel_received_idx ON public.messaging_webhook_events USING btree (channel_id, received_at DESC) WHERE (channel_id IS NOT NULL);


--
-- Name: messaging_webhook_events_provider_external_id_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX messaging_webhook_events_provider_external_id_unique ON public.messaging_webhook_events USING btree (provider, external_id);


--
-- Name: messaging_webhook_events_purge_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_webhook_events_purge_idx ON public.messaging_webhook_events USING btree (received_at);


--
-- Name: messaging_webhook_events_queue_monitor_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX messaging_webhook_events_queue_monitor_idx ON public.messaging_webhook_events USING btree (status, received_at) WHERE ((status)::text = ANY ((ARRAY['received'::character varying, 'processing'::character varying, 'failed'::character varying])::text[]));


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_permissions_permission_model_type_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX model_has_permissions_permission_model_type_uniq ON public.model_has_permissions USING btree (permission_id, model_id, model_type, COALESCE(tenant_id, (0)::bigint));


--
-- Name: model_has_permissions_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_tenant_id_index ON public.model_has_permissions USING btree (tenant_id);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: model_has_roles_role_model_type_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX model_has_roles_role_model_type_uniq ON public.model_has_roles USING btree (role_id, model_id, model_type, COALESCE(tenant_id, (0)::bigint));


--
-- Name: model_has_roles_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_tenant_id_index ON public.model_has_roles USING btree (tenant_id);


--
-- Name: paciente_convenios_convenio_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX paciente_convenios_convenio_idx ON public.paciente_convenios USING btree (convenio_id);


--
-- Name: paciente_convenios_paciente_papel_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX paciente_convenios_paciente_papel_unique ON public.paciente_convenios USING btree (paciente_id, papel);


--
-- Name: paciente_convenios_tenant_paciente_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX paciente_convenios_tenant_paciente_idx ON public.paciente_convenios USING btree (tenant_id, paciente_id);


--
-- Name: paciente_tags_paciente_tag_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX paciente_tags_paciente_tag_unique ON public.paciente_tags USING btree (paciente_id, tag_id);


--
-- Name: paciente_tags_tenant_paciente_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX paciente_tags_tenant_paciente_idx ON public.paciente_tags USING btree (tenant_id, paciente_id);


--
-- Name: paciente_tags_tenant_tag_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX paciente_tags_tenant_tag_idx ON public.paciente_tags USING btree (tenant_id, tag_id);


--
-- Name: pacientes_cpf_tenant_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX pacientes_cpf_tenant_unique ON public.pacientes USING btree (cpf, tenant_id) WHERE (cpf IS NOT NULL);


--
-- Name: pacientes_merged_into_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_merged_into_idx ON public.pacientes USING btree (merged_into_paciente_id);


--
-- Name: pacientes_nome_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_nome_trgm_idx ON public.pacientes USING gin (tenant_id, nome_normalizado public.gin_trgm_ops);


--
-- Name: pacientes_telefone_trgm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_telefone_trgm_idx ON public.pacientes USING gin (tenant_id, telefone_primario_normalizado public.gin_trgm_ops);


--
-- Name: pacientes_tenant_anonimizado_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_tenant_anonimizado_idx ON public.pacientes USING btree (tenant_id, anonimizado_em);


--
-- Name: pacientes_tenant_funil_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_tenant_funil_idx ON public.pacientes USING btree (tenant_id, funil_coluna_atual_id, funil_posicao);


--
-- Name: pacientes_tenant_origem_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_tenant_origem_idx ON public.pacientes USING btree (tenant_id, origem);


--
-- Name: pacientes_tenant_profissional_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_tenant_profissional_idx ON public.pacientes USING btree (tenant_id, profissional_responsavel_id);


--
-- Name: pacientes_tenant_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pacientes_tenant_status_idx ON public.pacientes USING btree (tenant_id, status);


--
-- Name: password_reset_tokens_email_tenant_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX password_reset_tokens_email_tenant_unique ON public.password_reset_tokens USING btree (email, COALESCE(tenant_id, (0)::bigint));


--
-- Name: password_reset_tokens_tenant_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX password_reset_tokens_tenant_id_idx ON public.password_reset_tokens USING btree (tenant_id);


--
-- Name: permissions_name_guard_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX permissions_name_guard_name_index ON public.permissions USING btree (name, guard_name);


--
-- Name: permissions_name_guard_tenant_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX permissions_name_guard_tenant_uniq ON public.permissions USING btree (name, guard_name, COALESCE(tenant_id, (0)::bigint));


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: plans_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX plans_is_active_index ON public.plans USING btree (is_active);


--
-- Name: professionals_tenant_id_active_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX professionals_tenant_id_active_idx ON public.professionals USING btree (tenant_id, is_active);


--
-- Name: professionals_user_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX professionals_user_id_idx ON public.professionals USING btree (user_id);


--
-- Name: roles_name_guard_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX roles_name_guard_name_index ON public.roles USING btree (name, guard_name);


--
-- Name: roles_name_guard_tenant_uniq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX roles_name_guard_tenant_uniq ON public.roles USING btree (name, guard_name, COALESCE(tenant_id, (0)::bigint));


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: stripe_events_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stripe_events_type_idx ON public.stripe_events USING btree (type);


--
-- Name: stripe_events_unprocessed_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stripe_events_unprocessed_idx ON public.stripe_events USING btree (received_at) WHERE (processed_at IS NULL);


--
-- Name: subscription_items_subscription_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_items_subscription_id_index ON public.subscription_items USING btree (subscription_id);


--
-- Name: subscriptions_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_tenant_id_index ON public.subscriptions USING btree (tenant_id);


--
-- Name: tags_tenant_nome_normalizado_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX tags_tenant_nome_normalizado_unique ON public.tags USING btree (tenant_id, nome_normalizado);


--
-- Name: tags_tenant_tipo_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tags_tenant_tipo_idx ON public.tags USING btree (tenant_id, tipo);


--
-- Name: tarefas_reatribuicao_tenant_concluida_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tarefas_reatribuicao_tenant_concluida_idx ON public.tarefas_reatribuicao USING btree (tenant_id, concluida_em);


--
-- Name: tenants_overdue_since_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tenants_overdue_since_index ON public.tenants USING btree (overdue_since);


--
-- Name: tenants_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tenants_status_index ON public.tenants USING btree (status);


--
-- Name: users_email_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_email_idx ON public.users USING btree (email);


--
-- Name: users_email_tenant_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX users_email_tenant_unique ON public.users USING btree (email, COALESCE(tenant_id, (0)::bigint));


--
-- Name: users_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_tenant_id_index ON public.users USING btree (tenant_id);


--
-- Name: users_tenant_id_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_tenant_id_status_idx ON public.users USING btree (tenant_id, status);


--
-- Name: anotacoes anotacoes_immutable_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER anotacoes_immutable_trigger BEFORE DELETE OR UPDATE ON public.anotacoes FOR EACH ROW EXECUTE FUNCTION public.anotacoes_immutable_trigger_fn();


--
-- Name: audit_logs_cold audit_logs_cold_immutable_trg; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_logs_cold_immutable_trg BEFORE DELETE OR UPDATE ON public.audit_logs_cold FOR EACH ROW EXECUTE FUNCTION public.audit_logs_cold_immutable();


--
-- Name: audit_logs audit_logs_immutable_trg; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_logs_immutable_trg BEFORE DELETE OR UPDATE ON public.audit_logs FOR EACH ROW EXECUTE FUNCTION public.audit_logs_immutable();


--
-- Name: eventos_timeline eventos_timeline_immutable_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER eventos_timeline_immutable_trigger BEFORE DELETE OR UPDATE ON public.eventos_timeline FOR EACH ROW EXECUTE FUNCTION public.eventos_timeline_immutable_trigger_fn();


--
-- Name: ai_usage_meters ai_usage_meters_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_usage_meters
    ADD CONSTRAINT ai_usage_meters_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: anotacoes anotacoes_autor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anotacoes
    ADD CONSTRAINT anotacoes_autor_id_foreign FOREIGN KEY (autor_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: anotacoes anotacoes_paciente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anotacoes
    ADD CONSTRAINT anotacoes_paciente_id_foreign FOREIGN KEY (paciente_id) REFERENCES public.pacientes(id) ON DELETE CASCADE;


--
-- Name: anotacoes anotacoes_retratacao_de_anotacao_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anotacoes
    ADD CONSTRAINT anotacoes_retratacao_de_anotacao_id_foreign FOREIGN KEY (retratacao_de_anotacao_id) REFERENCES public.anotacoes(id) ON DELETE RESTRICT;


--
-- Name: anotacoes anotacoes_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anotacoes
    ADD CONSTRAINT anotacoes_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: audit_logs_cold audit_logs_cold_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs_cold
    ADD CONSTRAINT audit_logs_cold_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: audit_logs_cold audit_logs_cold_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs_cold
    ADD CONSTRAINT audit_logs_cold_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: audit_logs audit_logs_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: audit_logs audit_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: convenios convenios_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.convenios
    ADD CONSTRAINT convenios_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: eventos_timeline eventos_timeline_autor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.eventos_timeline
    ADD CONSTRAINT eventos_timeline_autor_id_foreign FOREIGN KEY (autor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: eventos_timeline eventos_timeline_paciente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.eventos_timeline
    ADD CONSTRAINT eventos_timeline_paciente_id_foreign FOREIGN KEY (paciente_id) REFERENCES public.pacientes(id) ON DELETE CASCADE;


--
-- Name: eventos_timeline eventos_timeline_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.eventos_timeline
    ADD CONSTRAINT eventos_timeline_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: funil_colunas funil_colunas_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funil_colunas
    ADD CONSTRAINT funil_colunas_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: importacoes importacoes_executor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importacoes
    ADD CONSTRAINT importacoes_executor_id_foreign FOREIGN KEY (executor_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: importacoes importacoes_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importacoes
    ADD CONSTRAINT importacoes_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: invitations invitations_inviter_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_inviter_user_id_foreign FOREIGN KEY (inviter_user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: invitations invitations_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: mesclagens_pacientes mesclagens_pacientes_executor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesclagens_pacientes
    ADD CONSTRAINT mesclagens_pacientes_executor_id_foreign FOREIGN KEY (executor_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: mesclagens_pacientes mesclagens_pacientes_paciente_alvo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesclagens_pacientes
    ADD CONSTRAINT mesclagens_pacientes_paciente_alvo_id_foreign FOREIGN KEY (paciente_alvo_id) REFERENCES public.pacientes(id) ON DELETE CASCADE;


--
-- Name: mesclagens_pacientes mesclagens_pacientes_revertida_por_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesclagens_pacientes
    ADD CONSTRAINT mesclagens_pacientes_revertida_por_user_id_foreign FOREIGN KEY (revertida_por_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: mesclagens_pacientes mesclagens_pacientes_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesclagens_pacientes
    ADD CONSTRAINT mesclagens_pacientes_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_assignment_rules messaging_assignment_rules_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_assignment_rules
    ADD CONSTRAINT messaging_assignment_rules_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.messaging_channels(id) ON DELETE CASCADE;


--
-- Name: messaging_assignment_rules messaging_assignment_rules_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_assignment_rules
    ADD CONSTRAINT messaging_assignment_rules_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_channel_templates messaging_channel_templates_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_channel_templates
    ADD CONSTRAINT messaging_channel_templates_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.messaging_channels(id) ON DELETE CASCADE;


--
-- Name: messaging_channel_templates messaging_channel_templates_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_channel_templates
    ADD CONSTRAINT messaging_channel_templates_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_channels messaging_channels_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_channels
    ADD CONSTRAINT messaging_channels_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_conversation_assignments messaging_conversation_assignments_assigned_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversation_assignments
    ADD CONSTRAINT messaging_conversation_assignments_assigned_by_foreign FOREIGN KEY (assigned_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: messaging_conversation_assignments messaging_conversation_assignments_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversation_assignments
    ADD CONSTRAINT messaging_conversation_assignments_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.messaging_conversations(id) ON DELETE CASCADE;


--
-- Name: messaging_conversation_assignments messaging_conversation_assignments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversation_assignments
    ADD CONSTRAINT messaging_conversation_assignments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_conversation_assignments messaging_conversation_assignments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversation_assignments
    ADD CONSTRAINT messaging_conversation_assignments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: messaging_conversations messaging_conversations_ai_pause_set_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversations
    ADD CONSTRAINT messaging_conversations_ai_pause_set_by_foreign FOREIGN KEY (ai_pause_set_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: messaging_conversations messaging_conversations_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversations
    ADD CONSTRAINT messaging_conversations_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: messaging_conversations messaging_conversations_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversations
    ADD CONSTRAINT messaging_conversations_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.messaging_channels(id) ON DELETE RESTRICT;


--
-- Name: messaging_conversations messaging_conversations_patient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversations
    ADD CONSTRAINT messaging_conversations_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES public.pacientes(id) ON DELETE SET NULL;


--
-- Name: messaging_conversations messaging_conversations_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_conversations
    ADD CONSTRAINT messaging_conversations_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_message_media messaging_message_media_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_message_media
    ADD CONSTRAINT messaging_message_media_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messaging_messages(id) ON DELETE CASCADE;


--
-- Name: messaging_message_media messaging_message_media_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_message_media
    ADD CONSTRAINT messaging_message_media_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_messages messaging_messages_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_messages
    ADD CONSTRAINT messaging_messages_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.messaging_conversations(id) ON DELETE CASCADE;


--
-- Name: messaging_messages messaging_messages_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_messages
    ADD CONSTRAINT messaging_messages_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_quick_replies messaging_quick_replies_owner_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_quick_replies
    ADD CONSTRAINT messaging_quick_replies_owner_user_id_foreign FOREIGN KEY (owner_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: messaging_quick_replies messaging_quick_replies_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_quick_replies
    ADD CONSTRAINT messaging_quick_replies_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_user_presence messaging_user_presence_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_user_presence
    ADD CONSTRAINT messaging_user_presence_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_user_presence messaging_user_presence_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_user_presence
    ADD CONSTRAINT messaging_user_presence_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: messaging_web_widget_configs messaging_web_widget_configs_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_configs
    ADD CONSTRAINT messaging_web_widget_configs_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.messaging_channels(id) ON DELETE CASCADE;


--
-- Name: messaging_web_widget_configs messaging_web_widget_configs_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_configs
    ADD CONSTRAINT messaging_web_widget_configs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_web_widget_sessions messaging_web_widget_sessions_identified_patient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_sessions
    ADD CONSTRAINT messaging_web_widget_sessions_identified_patient_id_foreign FOREIGN KEY (identified_patient_id) REFERENCES public.pacientes(id) ON DELETE SET NULL;


--
-- Name: messaging_web_widget_sessions messaging_web_widget_sessions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_sessions
    ADD CONSTRAINT messaging_web_widget_sessions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messaging_web_widget_sessions messaging_web_widget_sessions_widget_config_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_web_widget_sessions
    ADD CONSTRAINT messaging_web_widget_sessions_widget_config_id_foreign FOREIGN KEY (widget_config_id) REFERENCES public.messaging_web_widget_configs(id) ON DELETE CASCADE;


--
-- Name: messaging_webhook_events messaging_webhook_events_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_webhook_events
    ADD CONSTRAINT messaging_webhook_events_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.messaging_channels(id) ON DELETE SET NULL;


--
-- Name: messaging_webhook_events messaging_webhook_events_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messaging_webhook_events
    ADD CONSTRAINT messaging_webhook_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: paciente_convenios paciente_convenios_convenio_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_convenios
    ADD CONSTRAINT paciente_convenios_convenio_id_foreign FOREIGN KEY (convenio_id) REFERENCES public.convenios(id) ON DELETE RESTRICT;


--
-- Name: paciente_convenios paciente_convenios_paciente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_convenios
    ADD CONSTRAINT paciente_convenios_paciente_id_foreign FOREIGN KEY (paciente_id) REFERENCES public.pacientes(id) ON DELETE CASCADE;


--
-- Name: paciente_convenios paciente_convenios_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_convenios
    ADD CONSTRAINT paciente_convenios_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: paciente_tags paciente_tags_aplicada_por_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_tags
    ADD CONSTRAINT paciente_tags_aplicada_por_user_id_foreign FOREIGN KEY (aplicada_por_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: paciente_tags paciente_tags_paciente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_tags
    ADD CONSTRAINT paciente_tags_paciente_id_foreign FOREIGN KEY (paciente_id) REFERENCES public.pacientes(id) ON DELETE CASCADE;


--
-- Name: paciente_tags paciente_tags_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_tags
    ADD CONSTRAINT paciente_tags_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.tags(id) ON DELETE CASCADE;


--
-- Name: paciente_tags paciente_tags_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.paciente_tags
    ADD CONSTRAINT paciente_tags_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: pacientes pacientes_convenio_principal_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_convenio_principal_fkey FOREIGN KEY (convenio_principal_id) REFERENCES public.paciente_convenios(id) ON DELETE SET NULL;


--
-- Name: pacientes pacientes_funil_coluna_atual_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_funil_coluna_atual_id_fkey FOREIGN KEY (funil_coluna_atual_id) REFERENCES public.funil_colunas(id) ON DELETE SET NULL;


--
-- Name: pacientes pacientes_merged_into_paciente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_merged_into_paciente_id_foreign FOREIGN KEY (merged_into_paciente_id) REFERENCES public.pacientes(id) ON DELETE SET NULL;


--
-- Name: pacientes pacientes_profissional_responsavel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_profissional_responsavel_id_foreign FOREIGN KEY (profissional_responsavel_id) REFERENCES public.professionals(id) ON DELETE SET NULL;


--
-- Name: pacientes pacientes_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: password_reset_tokens password_reset_tokens_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: permissions permissions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: professionals professionals_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: professionals professionals_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professionals
    ADD CONSTRAINT professionals_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: roles roles_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: subscription_items subscription_items_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE CASCADE;


--
-- Name: subscriptions subscriptions_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES public.plans(id) ON DELETE RESTRICT;


--
-- Name: subscriptions subscriptions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: tags tags_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: tarefas_reatribuicao tarefas_reatribuicao_concluida_por_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tarefas_reatribuicao
    ADD CONSTRAINT tarefas_reatribuicao_concluida_por_user_id_foreign FOREIGN KEY (concluida_por_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tarefas_reatribuicao tarefas_reatribuicao_profissional_desativado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tarefas_reatribuicao
    ADD CONSTRAINT tarefas_reatribuicao_profissional_desativado_id_foreign FOREIGN KEY (profissional_desativado_id) REFERENCES public.professionals(id) ON DELETE RESTRICT;


--
-- Name: tarefas_reatribuicao tarefas_reatribuicao_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tarefas_reatribuicao
    ADD CONSTRAINT tarefas_reatribuicao_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: tenants tenants_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES public.plans(id) ON DELETE RESTRICT;


--
-- Name: users users_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict xWlsfUYOsYqQfQQZKF9tnsl4kGn4JW0LRV7ndxmr3wc608uFBgU0d78SMgVzJFw

--
-- PostgreSQL database dump
--

\restrict KWokmx2ObnnJJhIAPUDfYLQFAZZpFocm1dvEac7EDx0UkBNS2nt2ry0hrtDMDnD

-- Dumped from database version 18.3
-- Dumped by pg_dump version 18.3 (Ubuntu 18.3-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000001_create_cache_table	1
2	0001_01_01_000002_create_jobs_table	1
3	2026_05_10_000001_create_tenants_table	1
4	2026_05_10_000002_create_plans_table	1
5	2026_05_10_000003_create_subscriptions_table	1
6	2026_05_10_000004_create_subscription_items_table	1
7	2026_05_10_000005_create_users_table	1
8	2026_05_10_000006_create_password_reset_tokens_table	1
9	2026_05_10_000007_create_personal_access_tokens_table	1
10	2026_05_10_000008_create_sessions_table	1
11	2026_05_10_000009_create_permission_tables	1
12	2026_05_10_000010_create_invitations_table	1
13	2026_05_10_000011_create_professionals_table	1
14	2026_05_10_000012_create_audit_logs_table	1
15	2026_05_10_000013_create_audit_logs_cold_table	1
16	2026_05_10_000014_create_ai_usage_meters_table	1
17	2026_05_10_000015_create_stripe_events_table	1
18	2026_05_11_000001_enable_pg_trgm_and_unaccent	1
19	2026_05_11_000002_create_pacientes_table	1
20	2026_05_11_000003_create_convenios_table	1
21	2026_05_11_000004_create_paciente_convenios_table	1
22	2026_05_11_000005_create_tags_table	1
23	2026_05_11_000006_create_paciente_tags_table	1
24	2026_05_11_000007_create_anotacoes_table	1
25	2026_05_11_000008_create_eventos_timeline_table	1
26	2026_05_11_000009_create_importacoes_table	1
27	2026_05_11_000010_create_mesclagens_pacientes_table	1
28	2026_05_11_000011_create_funil_colunas_table	1
29	2026_05_11_000012_create_tarefas_reatribuicao_table	1
30	2026_05_11_000013_add_pacientes_trigram_indexes	1
31	2026_05_12_014241_create_messaging_channels_table	2
32	2026_05_12_014256_create_messaging_channel_templates_table	2
33	2026_05_12_014310_create_messaging_conversations_table	2
34	2026_05_12_014317_create_messaging_conversation_assignments_table	2
35	2026_05_12_014319_create_messaging_messages_table	2
36	2026_05_12_014322_create_messaging_message_media_table	2
37	2026_05_12_014324_create_messaging_quick_replies_table	2
38	2026_05_12_014327_create_messaging_web_widget_configs_table	2
39	2026_05_12_014329_create_messaging_web_widget_sessions_table	2
40	2026_05_12_014332_create_messaging_assignment_rules_table	2
41	2026_05_12_014334_create_messaging_user_presence_table	2
42	2026_05_12_014337_create_messaging_webhook_events_table	3
43	2026_05_12_014339_add_messaging_trigram_indexes	3
44	2026_05_12_031846_add_executor_id_to_audit_logs	4
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 44, true);


--
-- PostgreSQL database dump complete
--

\unrestrict KWokmx2ObnnJJhIAPUDfYLQFAZZpFocm1dvEac7EDx0UkBNS2nt2ry0hrtDMDnD

