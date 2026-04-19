-- 012_grant_sequences.sql
-- Date: 2026-01-18
-- Ensure application role has usage/select on existing sequences and default privileges for future sequences
-- Safe to run multiple times (idempotent).

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'washiviana_app') THEN
        -- Grant usage/select on all existing sequences in public
        EXECUTE 'GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO washiviana_app';

        -- Ensure default privileges for sequences created in the future in schema public
        EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO washiviana_app';

        RAISE NOTICE 'Granted sequence privileges to role washiviana_app and adjusted default privileges.';
    ELSE
        RAISE NOTICE 'Role washiviana_app does not exist. Create the role and re-run this migration.';
    END IF;
END$$;

-- Optional: if you prefer to change ownership of specific sequences, uncomment and adapt below:
-- ALTER SEQUENCE artigos_id_seq OWNER TO washiviana_app;

-- To apply locally (as postgres superuser):
-- sudo -u postgres psql -d washiviana -f migrations/012_grant_sequences.sql
