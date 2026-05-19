-- Refresh nextcloud_user_groups_materialized from the live nextcloud_user_groups view.
CREATE OR REPLACE PROCEDURE RefreshNextcloudUserGroups()
BEGIN
    TRUNCATE TABLE nextcloud_user_groups_materialized;
    INSERT INTO nextcloud_user_groups_materialized (username, groupname, last_updated)
    SELECT username, groupname, NOW()
    FROM nextcloud_user_groups;
END
