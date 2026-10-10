CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS ai_document (
  id bigserial PRIMARY KEY,
  drupal_entity_type text NOT NULL,
  drupal_entity_id bigint NOT NULL,
  drupal_revision_id bigint,
  langcode text NOT NULL,
  org_id bigint,
  bundle text,
  title text,
  url text,
  content_hash text,
  indexed_at timestamptz DEFAULT now(),
  UNIQUE (drupal_entity_type, drupal_entity_id, langcode)
);

CREATE TABLE IF NOT EXISTS ai_document_chunk (
  id bigserial PRIMARY KEY,
  document_id bigint NOT NULL REFERENCES ai_document(id) ON DELETE CASCADE,
  chunk_delta integer NOT NULL,
  chunk_hash text NOT NULL,
  heading text,
  text text NOT NULL,
  token_estimate integer,
  embedding_model text,
  embedding vector(768),
  embedded_at timestamptz,
  UNIQUE (document_id, chunk_delta)
);

CREATE INDEX IF NOT EXISTS ai_document_chunk_embedding_hnsw
ON ai_document_chunk
USING hnsw (embedding vector_cosine_ops);

