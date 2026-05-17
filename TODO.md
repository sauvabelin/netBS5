# TODO — replace `*_id` parameters with a less brittle pattern

## What this is about

`src/Resources/config/parameters.yaml` ships ~25 parameter keys whose values are
**auto-increment primary keys** of rows in `netbs_fichier_fonctions`,
`netbs_fichier_groupes`, `netbs_fichier_groupe_types`, `netbs_fichier_groupe_categories`,
and `netbs_fichier_distinctions`. They all default to `-1` because the IDs
aren't known until the fixtures that create those rows run. The expectation is
that an admin opens the "Paramètres" page after install and manually binds
each parameter to the correct row.

Problems with this pattern:

1. **Fragile defaults.** `-1` is a sentinel that means "broken". Code that
   reads these parameters silently produces empty result sets, no-ops, or
   wrong behavior until the admin remembers to fix it.
2. **No referential integrity.** Nothing prevents the referenced row from
   being deleted, leaving a dangling ID. Nothing prevents an admin from
   typing a random number.
3. **Cross-environment drift.** IDs are auto-increment, so the same logical
   "Commandant" row gets ID 7 in dev and ID 23 in prod. The parameters can't
   be synced via fixtures; every environment has to be wired up by hand.
4. **Stringly-typed lookups.** `$params->getValue('bs', 'fonction.commandant_id')`
   returns a value the caller has to cast to `int` and then re-query the DB
   for the entity. Two lookups per use, no type safety.
5. **Dead entries.** A handful of keys ship in YAML but are never read in
   code (see "Dead parameters" below).

## Where they're used (file:line)

### `fonction.*` — fonction row IDs

| Parameter | Where read | What it does |
|---|---|---|
| `fonction.louveteau_id` | `src/Automatics/GarsQuiBougentAutomatic.php:48`<br>`src/Form/CirculaireMembreType.php:39` | Selects louveteaux-fonction members for the "gars qui bougent" automatic; preselects them in circulaire form |
| `fonction.eclaireur_id` | `src/Automatics/GarsQuiBougentAutomatic.php:49`<br>`src/Form/CirculaireMembreType.php:40` | Same as above for éclaireurs |
| `fonction.cp_id` | `src/Automatics/TestDentreeAutomatic.php:45` | Identifies CP fonction for second-year leaders in entrance-exam list |
| `fonction.cl_id` | `src/Automatics/TestDentreeAutomatic.php:46`<br>`netBS/core/CoreBundle/Resources/sql/views/05_nextcloud_user_groups.sql:35` | Same as CP, plus controls "parent-group visibility via CL role" in the Nextcloud user-groups view |
| `fonction.rouge_id` | `src/Automatics/TestDentreeAutomatic.php:47` | Same idea for the "rouge" fonction |
| `fonction.equipier_id` | `netBS/core/CoreBundle/Resources/sql/views/05_nextcloud_user_groups.sql:66` | Excludes equipiers from the `tous` Nextcloud group |
| `fonction.commandant_id` | **unused** | — |
| `fonction.secretaire_general_id` | **unused** | — |

### `groupe.*` — groupe row IDs

| Parameter | Where read | What it does |
|---|---|---|
| `groupe.adabs_id` | `src/Automatics/CDCAutomatic.php:56`<br>`src/Binder/NoAdabsbinder.php:38`<br>`src/Listener/DoctrineMembreAdabsIdListener.php:23`<br>`src/Subscriber/DoctrineUserAccountSubscriber.php:109`<br>`src/Subscriber/OldFichierMapperSubscriber.php:87`<br>`netBS/core/CoreBundle/Resources/sql/views/05_nextcloud_user_groups.sql:62` | The "ADABS" group ID — referenced everywhere ADABS members need special handling (skip user account, exclude from CDC eligibility, mark on membre at load, exclude from `tous` Nextcloud group) |
| `groupe.apmbs_id` | `src/Binder/NoAPMBSBinder.php:38` | Filter to exclude APMBS members from member lists |
| `groupe.branche_louveteaux_id` | `src/Automatics/GarsQuiBougentAutomatic.php:94` | Louveteaux branche → identifies age-appropriate transitions |
| `groupe.branche_eclaireurs_id` | `src/Automatics/GarsQuiBougentAutomatic.php:96` | Same for éclaireurs |
| `groupe.branche_louvettes_id` | `src/Automatics/GarsQuiBougentAutomatic.php:101` | Same for louvettes |
| `groupe.branche_eclaireuses_id` | `src/Automatics/GarsQuiBougentAutomatic.php:103` | Same for éclaireuses |
| `groupe.branche_smt_id` | `src/Automatics/CotisationsAutomatic.php:107` | SMT-branche members get a reduced cotisation rate |

### `groupe_categorie.*` — groupe-category row IDs

| Parameter | Where read | What it does |
|---|---|---|
| `groupe_categorie.unite_id` | `src/Listener/PageGroupeListener.php:42` | If the displayed groupe's catégorie is "unité", attach the étiquettes and liste-unité blocks |
| `groupe_categorie.sous_unite_id` | **unused** | — |

### `groupe_type.*` — groupe-type row IDs

These are the bulk of usages. Same set of IDs read in two places:
the `nextcloud_mapped_units` view (which groups them as "syncable to Nextcloud
units") and `TalkCheckAttributionsCommand::isGroupMapped()` (which gates Talk
notifications on the same set).

| Parameter | Where read |
|---|---|
| `groupe_type.troupe_id` | `Resources/sql/views/02_nextcloud_mapped_units.sql:17`<br>`src/Command/TalkCheckAttributionsCommand.php:176` |
| `groupe_type.meute_id` | `:18` / `:177` |
| `groupe_type.clan_id` | `:19` / `:178` |
| `groupe_type.association_id` | `:20` / `:179` |
| `groupe_type.edc_id` | `:21` / `:180` |
| `groupe_type.equipe_interne_id` | `:22` / `:181` |
| `groupe_type.branche_id` | `:23` / `:182` — also `src/Listener/PageGroupeListener.php:43` (étiquettes block on branche pages) and `netBS/ovesco/FacturationBundle/Exporter/BaseFactureExporter.php:242` (branche hierarchy for invoice grouping) |
| `groupe_type.equipe_id` | `:24` (only the SQL view; the command's `isGroupMapped()` list stops before it — see "Inconsistency" below) |
| `groupe_type.brigade_id` | `:25` (SQL only) |
| `groupe_type.cda_id` | `:26` (SQL only) |

### `distinction.*`

| Parameter | Where read |
|---|---|
| `distinction.cravate_bleue_id` | **unused** |

## Dead parameters (zero references in code)

Safe to drop from `parameters.yaml`:
- `fonction.commandant_id`
- `fonction.secretaire_general_id`
- `distinction.cravate_bleue_id`
- `groupe_categorie.sous_unite_id`

## Inconsistency to flag

`TalkCheckAttributionsCommand::isGroupMapped()` checks 7 groupe_type IDs
(troupe, meute, clan, association, edc, equipe_interne, branche, equipe — though
its `$typeParams` array stops at `equipe_id` in `src/Command/TalkCheckAttributionsCommand.php:175-184`).

The SQL view `nextcloud_mapped_units` checks **10** IDs (the same set plus
`equipe_id`, `brigade_id`, `cda_id`).

These two definitions of "mapped unit" should agree. Either the command is
missing the last three, or the SQL is including more than intended.

---

## Proposed alternatives

The goal: stop encoding "which row do I mean?" as a per-environment integer.

### Option A — Stable string slugs on the referenced rows (recommended)

Add a `slug` (or `code`, `key`) column to `netbs_fichier_fonctions`,
`netbs_fichier_groupes`, `netbs_fichier_groupe_types`,
`netbs_fichier_groupe_categories`. Make it `VARCHAR(64) UNIQUE NOT NULL`.

Then replace each parameter lookup with a slug lookup:

```php
// before
$id = $params->getValue('bs', 'fonction.cl_id');
$cl = $em->getRepository(Fonction::class)->find($id);

// after
$cl = $em->getRepository(Fonction::class)->findOneBy(['slug' => 'cl']);
```

Fixtures set the slugs; SQL views join on the slug; queries become
self-documenting; the row can be reassigned to a different ID without breaking
anything (FK by slug, not by `id`).

**Pros:** type-safe, version-controllable, environment-independent, queries are
self-documenting, FK integrity stays intact.
**Cons:** schema migration on prod (add column, backfill values, add NOT NULL +
UNIQUE), and need to update every call site. The SQL views need rewriting to
join through the slug column (slightly more complex queries).

### Option B — Enum-style "well-known rows" table

Same idea as A but in a side table: `netbs_well_known_rows (slug, table, id)`
with one row per logical binding. The application reads the table once at boot
and exposes it via a typed service:

```php
class WellKnown {
    public function fonctionCL(): Fonction { ... }
    public function groupeAdabs(): BSGroupe { ... }
    public function groupeTypeTroupe(): GroupeType { ... }
}
```

**Pros:** no schema change on existing tables; centralized; typed API.
**Cons:** still an indirection (IDs in a side table), still needs a fixture/UI
to bind. Refactoring magnitude is similar to A. Less "obvious from the schema"
what the bindings are.

### Option C — Replace the parameter type with discriminator columns

For the `groupe_type.*` set specifically: instead of "which groupe_type ID is
'troupe'?", add a `category` enum column on `netbs_fichier_groupe_types`
(values: `troupe`, `meute`, `clan`, …) and query by the enum. Same shape for
the branche IDs (could be `kind = 'eclaireurs'` on the groupe row).

**Pros:** removes the indirection entirely — the row knows what it is.
**Cons:** restructures the schema; some of these "kinds" might already be
encoded redundantly elsewhere (groupe.nom, groupe.acronyme, etc.); risk of
two-truths drift if not migrated cleanly. Best fit for the `groupe_type.*`
set, less natural for the standalone "the Adabs group" (singleton) case.

### Option D — Resolver fixture that runs after the base data

Keep parameters.yaml but flip it: instead of `fonction.cl_id: -1`, ship
`fonction.cl_name: "Chef de Ligue"`. Add a post-install fixture that, for
every `bs.*_name` parameter, looks up the matching row by name and writes the
resolved ID into a sibling `bs.*_id` parameter automatically.

**Pros:** smallest code change; no schema work; existing call sites keep
reading IDs.
**Cons:** still stringly-typed (the names have to match prod exactly, which
fails if anyone renames a fonction in the UI); doesn't fix the dangling-FK or
brittleness problems, just the "ships broken on fresh install" symptom. Effectively
a band-aid.

## My recommendation

Land **A** as the long-term answer. Migration plan:

1. Add `slug VARCHAR(64) NULLABLE UNIQUE` to the four tables in a Doctrine
   migration.
2. Backfill slugs in the same migration based on currently-bound parameter
   values (read each `bs.<x>_id`, find the row, set its slug = `<x>` minus
   the trailing `_id`).
3. Update the ~15 call sites to look up by slug instead of by ID.
4. Update the SQL views (`02_nextcloud_mapped_units.sql`, `05_nextcloud_user_groups.sql`)
   to join through the slug column.
5. Once everything reads via slug, drop the dead parameters and add a follow-up
   migration that drops the now-unused `bs.*_id` rows from
   `netbs_core_parameters`. Make `slug` `NOT NULL`.

If a full A migration is too much surface area for now, **D as a short-term
unblock** is fine and only touches one fixture file — the immediate "all my
queries return empty on fresh install" pain goes away. But it should be
explicitly tracked as a stopgap, not the destination.

## Adjacent cleanups while we're here

- Fix the `isGroupMapped()` vs SQL-view inconsistency (8 vs 10 IDs).
- Remove the 4 dead parameters from `parameters.yaml`.
- The `groupe.branche_*_id` series duplicates what `groupe_type.branche_id` +
  groupe.nom could express. After A, these can probably collapse into a
  single "branche" concept with a `kind` slug on the branche group row.
