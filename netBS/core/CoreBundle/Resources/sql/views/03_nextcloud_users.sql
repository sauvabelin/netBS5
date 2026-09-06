-- Nextcloud users: read by the Nextcloud user_sql app to provision/auth users.
-- displayname falls back to username when no membre record is linked.
CREATE OR REPLACE VIEW nextcloud_users AS
SELECT
    u.username,
    u.password,
    u.salt,
    u.email,
    u.nextcloud_account,
    u.nextcloud_admin,
    COALESCE(CONCAT(m.prenom, ' ', m.nom), u.username) AS displayname,
    u.nextcloud_quota,
    u.isActive
FROM sauvabelin_netbs_users u
LEFT JOIN sauvabelin_netbs_membres m ON u.membre_id = m.id
WHERE u.nextcloud_account = 1;
