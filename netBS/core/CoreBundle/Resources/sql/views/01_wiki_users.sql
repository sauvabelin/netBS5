-- Wiki authentication view: read by MediaWiki (wikibs) LocalSettings.php
-- to authenticate users against the netbs credential store.
CREATE OR REPLACE VIEW wiki_users AS
SELECT u.username, u.password, u.salt, u.wiki_admin
FROM sauvabelin_netbs_users u
WHERE u.wiki_account = 1;
