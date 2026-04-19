-- Migration 014: criar video_jobs
CREATE TABLE IF NOT EXISTS video_jobs (
    id SERIAL PRIMARY KEY,
    variant_id INTEGER NOT NULL REFERENCES artigos_social_variants(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, processing, success, failed
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    output_file VARCHAR(255),
    params JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_video_jobs_status ON video_jobs(status);
CREATE INDEX IF NOT EXISTS idx_video_jobs_created_at ON video_jobs(created_at);
