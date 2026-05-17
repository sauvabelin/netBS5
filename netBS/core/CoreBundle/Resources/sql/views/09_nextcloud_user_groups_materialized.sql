-- Materialized table populated by refresh_nextcloud_user_groups_materialized()
-- from the live nextcloud_user_groups view (05). The Nextcloud user_sql app
-- queries this table directly to avoid the cost of the complex view join on
-- every auth/sync request.
--
-- Charset matches the existing Doctrine-managed definition (Version0001
-- baseline) so that running this command on an already-installed DB is a
-- no-op (CREATE TABLE IF NOT EXISTS) rather than a conflict.
CREATE TABLE IF NOT EXISTS nextcloud_user_groups_materialized (
    username VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
    groupname VARCHAR(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    INDEX idx_updated (last_updated),
    INDEX idx_username (username),
    INDEX idx_groupname (groupname)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
