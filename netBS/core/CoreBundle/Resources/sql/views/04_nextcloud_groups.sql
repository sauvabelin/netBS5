-- Nextcloud groups: union of fonction-based groups, mapped units, and 'tous'.
-- Depends on nextcloud_mapped_units (02).
CREATE OR REPLACE VIEW nextcloud_groups AS
(SELECT f.nom, CONCAT('[', f.id, '] ', f.nom, ' (fonction)') AS nc_group_name
 FROM netbs_fichier_fonctions f)
UNION
(SELECT g.nom, g.nc_group_name
 FROM nextcloud_mapped_units g)
UNION
(SELECT 'tous' AS nom, 'tous' AS nc_group_name);
