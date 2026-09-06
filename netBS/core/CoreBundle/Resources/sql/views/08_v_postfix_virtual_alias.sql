-- Postfix socketmap query view: thin projection of the materialized table
-- exposing only the columns Postfix needs.
CREATE OR REPLACE VIEW v_postfix_virtual_alias AS
SELECT source_address, destination_email
FROM postfix_redirections_materialized;
