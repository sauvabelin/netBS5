-- Nextcloud user-group assignments: three sources unioned together.
--   1) Users assigned to fonction-based groups (via active attributions)
--   2) Users assigned to mapped units (direct or parent group via CL/CP fonction)
--   3) All Nextcloud users in 'tous', except equipiers in equipe groups and adabs members
-- Depends on nextcloud_mapped_units (02).
CREATE OR REPLACE VIEW nextcloud_user_groups AS
SELECT
    u.username,
    CONCAT('[', f.id, '] ', f.nom, ' (fonction)') AS groupname
FROM sauvabelin_netbs_users u
JOIN sauvabelin_netbs_membres m ON u.membre_id = m.id
JOIN netbs_fichier_attributions a ON m.id = a.membre_id
JOIN netbs_fichier_fonctions f ON f.id = a.fonction_id
WHERE a.dateDebut < CURRENT_TIMESTAMP()
  AND (a.dateFin IS NULL OR a.dateFin > CURRENT_TIMESTAMP())
  AND u.nextcloud_account = 1

UNION ALL

SELECT
    u.username,
    g.nc_group_name AS groupname
FROM sauvabelin_netbs_users u
JOIN sauvabelin_netbs_membres m ON m.id = u.membre_id
JOIN netbs_fichier_attributions a ON a.membre_id = m.id
JOIN nextcloud_mapped_units g ON (
    a.groupe_id = g.group_id
    OR (
        EXISTS (
            SELECT 1 FROM sauvabelin_netbs_groupes sng
            WHERE sng.id = a.groupe_id AND sng.parent_id = g.group_id
        )
        AND a.fonction_id IN (
            SELECT CAST(value AS UNSIGNED) FROM netbs_core_parameters
            WHERE namespace = 'bs' AND paramKey IN ('fonction.cl_id', 'fonction.cp_id')
        )
    )
)
WHERE a.dateDebut < CURRENT_TIMESTAMP()
  AND (a.dateFin IS NULL OR a.dateFin > CURRENT_TIMESTAMP())
  AND g.nc_group_name IS NOT NULL
  AND u.nextcloud_account = 1

UNION ALL

SELECT
    u.username,
    'tous' AS groupname
FROM sauvabelin_netbs_users u
JOIN sauvabelin_netbs_membres m ON m.id = u.membre_id
WHERE u.nextcloud_account = 1
  AND NOT EXISTS (
      SELECT 1
      FROM netbs_fichier_attributions a
      JOIN sauvabelin_netbs_groupes g ON g.id = a.groupe_id
      WHERE a.membre_id = m.id
        AND a.dateDebut < CURRENT_TIMESTAMP()
        AND (a.dateFin IS NULL OR a.dateFin > CURRENT_TIMESTAMP())
        AND (
            g.id = (
                SELECT CAST(value AS UNSIGNED) FROM netbs_core_parameters
                WHERE namespace = 'bs' AND paramKey = 'groupe.adabs_id'
            )
            OR a.fonction_id = (
                SELECT CAST(value AS UNSIGNED) FROM netbs_core_parameters
                WHERE namespace = 'bs' AND paramKey = 'fonction.equipier_id'
            )
        )
  );
