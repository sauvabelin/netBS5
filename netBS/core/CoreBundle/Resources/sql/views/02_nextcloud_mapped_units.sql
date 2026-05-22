-- Mapped units: groups synced to Nextcloud as groups.
-- Filters groupes whose groupeType is one of the configured "syncable" types
-- (troupe, meute, clan, ...). Referenced by nextcloud_groups and
-- nextcloud_user_groups below.
CREATE OR REPLACE VIEW nextcloud_mapped_units AS
SELECT
    g.id AS group_id,
    g.nom AS nom,
    g.nc_group_name AS nc_group_name
FROM sauvabelin_netbs_groupes g
JOIN netbs_fichier_groupe_types gt ON g.groupeType_id = gt.id
WHERE gt.id IN (
    SELECT p.value
    FROM netbs_core_parameters p
    WHERE p.namespace = 'bs'
    AND p.paramKey IN (
        'groupe_type.troupe_id',
        'groupe_type.meute_id',
        'groupe_type.clan_id',
        'groupe_type.association_id',
        'groupe_type.edc_id',
        'groupe_type.equipe_interne_id',
        'groupe_type.branche_id',
        'groupe_type.equipe_id',
        'groupe_type.brigade_id',
        'groupe_type.cda_id'
    )
);
