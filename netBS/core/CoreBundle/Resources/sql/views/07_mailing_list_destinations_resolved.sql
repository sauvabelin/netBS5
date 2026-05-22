-- Live resolution of mailing-list destinations: aliases + base addresses
-- resolved through email / user / list / unite / role targets.
-- A sync job materializes this into postfix_redirections_materialized (06)
-- for Postfix lookups.
CREATE OR REPLACE VIEW mailing_list_destinations_resolved AS

SELECT DISTINCT
    mla.address as source_address,
    ml.baseAddress as destination_email,
    ml.id as mailing_list_id,
    NOW() as resolved_at
FROM mailing_list_alias mla
JOIN mailing_list ml ON mla.mailingList_id = ml.id
WHERE ml.active = 1
  AND mla.address IS NOT NULL
  AND ml.baseAddress IS NOT NULL

UNION

SELECT DISTINCT
    ml.baseAddress as source_address,
    CASE
        WHEN mt.type = 'email' THEN mt.targetEmail
        WHEN mt.type = 'user' THEN u.email
        WHEN mt.type = 'list' THEN target_ml.baseAddress
        WHEN mt.type = 'unite' THEN u_unite.email
        WHEN mt.type = 'role' THEN u_role.email
    END as destination_email,
    ml.id as mailing_list_id,
    NOW() as resolved_at
FROM mailing_list ml
JOIN mailing_target mt ON mt.mailingList_id = ml.id
LEFT JOIN sauvabelin_netbs_users u ON mt.type = 'user' AND mt.targetUser_id = u.id
LEFT JOIN mailing_list target_ml ON mt.type = 'list' AND mt.targetList_id = target_ml.id AND target_ml.active = 1
LEFT JOIN netbs_fichier_attributions a_unite ON mt.type = 'unite' AND a_unite.groupe_id = mt.targetGroup_id
    AND (a_unite.dateFin IS NULL OR a_unite.dateFin > NOW()) AND a_unite.dateDebut <= NOW()
LEFT JOIN sauvabelin_netbs_users u_unite ON a_unite.membre_id = u_unite.membre_id
LEFT JOIN netbs_fichier_attributions a_role ON mt.type = 'role' AND a_role.fonction_id = mt.targetFonction_id
    AND (a_role.dateFin IS NULL OR a_role.dateFin > NOW()) AND a_role.dateDebut <= NOW()
LEFT JOIN sauvabelin_netbs_users u_role ON a_role.membre_id = u_role.membre_id
WHERE ml.active = 1
  AND ml.baseAddress IS NOT NULL
  AND (
      (mt.type = 'email' AND mt.targetEmail IS NOT NULL) OR
      (mt.type = 'user' AND u.email IS NOT NULL) OR
      (mt.type = 'list' AND target_ml.baseAddress IS NOT NULL) OR
      (mt.type = 'unite' AND u_unite.email IS NOT NULL) OR
      (mt.type = 'role' AND u_role.email IS NOT NULL)
  );
