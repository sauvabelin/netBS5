-- Refresh postfix_redirections_materialized from mailing_list_destinations_resolved.
-- Postfix queries postfix_redirections_view (08) which reads this table — refresh
-- must run after any change to mailing lists / users / attributions.
CREATE OR REPLACE PROCEDURE refresh_postfix_redirections_materialized()
BEGIN
    TRUNCATE TABLE postfix_redirections_materialized;
    INSERT INTO postfix_redirections_materialized
        (source_address, destination_email, mailing_list_id, resolved_at)
    SELECT source_address, destination_email, mailing_list_id, resolved_at
    FROM mailing_list_destinations_resolved;
END
