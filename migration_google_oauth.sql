-- ============================================================
--  EMOL - Google OAuth Migration
--  Run this once against your emol_db database
-- ============================================================

USE emol_db;

-- Allow password to be NULL (Google users have no password)
ALTER TABLE users
    MODIFY COLUMN password VARCHAR(255) NULL DEFAULT NULL;

-- Add Google ID and avatar URL columns
ALTER TABLE users
    ADD COLUMN google_id  VARCHAR(100) DEFAULT NULL AFTER avatar_initials,
    ADD COLUMN avatar_url VARCHAR(500) DEFAULT NULL AFTER google_id;

-- Index for fast google_id lookups
CREATE INDEX idx_users_google_id ON users (google_id);
