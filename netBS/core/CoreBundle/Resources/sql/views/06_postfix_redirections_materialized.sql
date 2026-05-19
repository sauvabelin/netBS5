-- Materialized table for fast Postfix socketmap lookups.
-- Populated by a sync job from mailing_list_destinations_resolved (07).
-- Not a view — a real table — so CREATE TABLE IF NOT EXISTS to stay idempotent.
CREATE TABLE IF NOT EXISTS postfix_redirections_materialized (
    source_address VARCHAR(255) NOT NULL,
    destination_email VARCHAR(255) NOT NULL,
    mailing_list_id INT NULL,
    resolved_at DATETIME NOT NULL,
    PRIMARY KEY (source_address, destination_email),
    INDEX idx_source (source_address),
    INDEX idx_destination (destination_email),
    INDEX idx_mailing_list (mailing_list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
