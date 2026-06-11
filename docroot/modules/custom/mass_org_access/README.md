# Mass Org Access

Restricts who can edit / publish / transition / schedule / bulk-change /
delete content on Mass.gov to users assigned to that content's
organization. **View and clone access stay open sitewide.** Clone keeps
the original Organization(s) of the source entity. Implements DP-45788.

## Data model

```
User
  └─ field_user_org                 → user_organization terms

user_organization (taxonomy)
  └─ field_state_organization       → org_page node
  └─ parent                         → user_organization (hierarchy)

org_page (node bundle)               source of truth for OOG content
  └─ field_content_organization     → user_organization terms (curated)

Content node / media.document
  ├─ field_organizations            → org_page nodes        (editor-facing)
  │  └─ binder / decision / person bundles use
  │     field_binder_ref_organization,
  │     field_decision_ref_organization,
  │     field_person_ref_org instead
  └─ field_content_organization     → user_organization terms (hidden,
                                       drives access check)
```

| Field | On | Card. | Purpose |
|-------|----|-------|---------|
| `field_user_org` | user | ∞ | Orgs the user belongs to |
| `field_state_organization` | term `user_organization` | 1 | Maps term to org_page node |
| `field_organizations` | content | ∞ | Editor-facing org tagging |
| `field_binder_ref_organization` | node `binder` | ∞ | Binder's org tagging |
| `field_decision_ref_organization` | node `decision` | 1 | Decision's org tagging |
| `field_person_ref_org` | node `person` | ∞ | Person's org tagging |
| `field_content_organization` | content | ∞ | Hidden OOG list (drives access) |

## Feature switch

`OrgAccessSettings::isEnforcementEnabled()` gates the access decision,
form validator, and login warning. **Off by default.**

- Env `MASS_ORG_ACCESS_ENFORCE` (`1`/`true`/`yes`/`on` case-insensitive)
  wins when set. Use this on Acquia.
- State key `mass_org_access.enforce` is the fallback — DB-backed, so it
  propagates between PHPUnit and webserver processes during DTT tests.

The Permission Groups widget itself is **not gated by the switch** — its
Release 1 visibility rules (hidden from everyone except administrators and
on `org_page`; see "Owner Groups widget" below) apply whether enforcement is
on or off. A separate **debug mode** reveals the field for troubleshooting:
append `?debug_show_pg=<secret>` to an edit URL, where `<secret>` matches the
`MASS_ORG_ACCESS_DEBUG_SECRET` env var (no secret set → always off).

**Off:** access hooks return neutral. Editors can save anything they
can already save, and the JS auto-populates Permission Groups so the
field is correct by the time enforcement turns on.

**On:** access hooks block update/delete on out-of-org content.
Empty Permission Groups → admin-only-editable. Bypass users
(`bypass org access`) skip the gate.

## Access decision

```
allowed = !empty(array_intersect(
  user.field_user_org TIDs,
  entity.field_content_organization TIDs
))
```

Layered on top, in order:

1. **Operation gate** — only `update` / `delete`. `view` is always neutral.
2. **Switch off** → neutral.
3. **Bypass** — `bypass org access` permission → neutral.
4. **User has no org** → forbidden.
5. **Entity has no OOG terms** → forbidden (admin-only-editable).
6. **Intersection** — non-empty → neutral; empty → forbidden.

Cache: `cachePerUser()` + `user:UID` tag, so decisions invalidate when
the user's `field_user_org` changes.

## Hooks

OOP hooks (Drupal 11.3+ `#[Hook(...)]`) in `src/Hook/MassOrgAccessHooks.php`.

| Hook | Behavior |
|------|----------|
| `node_access` / `media_access` | The access decision above. |
| `entity_field_access` | Locks `field_user_org` to `administer users` (prevents self-promotion). Permission Groups itself is not field-restricted. |
| `form_node_form_alter` | Adds `validateOrgAccess` callback (static method — closures break paragraphs AJAX). Defense-in-depth: surfaces an error if a save reaches form validation despite `node_access` already denying it. |
| `field_widget_complete_form_alter` | Renders Permission Groups as a read-only list + attaches both JS libraries. Release 1: hides the field (CSS wrapper `.oog-hidden-from-author`) from anyone without `view permission groups field` (admins only — Content Administrators excluded) unless the bundle is `org_page` or the debug URL secret is present; the field stays in the form so its value still derives from Organization(s) and saves. |
| `user_login` | At login, warns editor/author roles without `field_user_org`. Silent while switch is off. |

## Routing

`Routing/RouteSubscriber.php` swaps `_entity_access` from `node.view` to
`node.update` on three side-door routes so the org gate fires there too:

- `entity.node.entity_hierarchy_reorder` (reorder children)
- `view.change_parents.page_1` (move children between parents)
- `entity.node.redirects`

## Owner Groups "widget"

**Release 1 visibility.** The field is shown only to users with
`view permission groups field` — administrators only; **Content
Administrators are excluded** — on every bundle, plus anyone editing an
`org_page`. For everyone else (Content Administrators, editors, authors) it
is wrapped in `.oog-hidden-from-author` (`display:none`) — present in the DOM
(so the JS-derived value still submits and the org-taxonomy permission data
stays populated) but not visible. Drop the wrapper in Release 2 to restore
visibility. The debug URL secret (`?debug_show_pg=<secret>` matching
`MASS_ORG_ACCESS_DEBUG_SECRET`) skips the wrapper for that request.

Two JS layers on `field_content_organization`:

**Read-only display** (`js/oog-readonly-display.js` +
`css/oog-readonly-display.css`). The default `entity_reference_tree`
autocomplete is hidden via the `.oog-readonly-source-hidden` class on its
wrapper; a sibling `#type item` element renders the term labels as a
`<ul>`. The autocomplete input still submits whatever the "Browse
organizations" popup wrote into it, so save validation goes through
Drupal's native entity reference machinery unchanged. JS watches the
input for value changes (event listener + MutationObserver + 500 ms
polling fallback) because jQuery `.val()` doesn't fire native events.

**Owner Groups augmentation from Organizations**
(`js/oog-from-organizations.js`). When the author adds an organization
into `field_organizations` (or the bundle-specific equivalent for
binder/decision/person), the JS fetches
`/mass-org-access/lookup-user-orgs?org_page_nids[]=…`, which returns the
Permission Groups curated on that org_page's own
`field_content_organization` — copied verbatim, no taxonomy walk, since
the hierarchy picker already stores any ancestor terms — and appends the
union to `field_content_organization`. Removing the organization drops
only the terms that organization auto-added and that no other tracked
organization still pulls in (reference counting via
`Map<orgNid, Set<tid>>`). Manual terms stay. Polling catches autocomplete
picks because jQuery UI's `.val()` doesn't fire events.

Placement: `field_content_organization` is weighted to be first inside
the **Page Info** field group on all 28 node form displays. Help text on
all bundles except `org_page` warns "DO NOT CHANGE unless approved by
Product Manager / DEV team" and that the field is populated automatically
from the Organizations on the item; `org_page` carries its own
author-facing help text (Browse organizations, parents auto-added),
since that is the one bundle where authors edit the field by hand.

## Services

| Service ID | Class | Role |
|------------|-------|------|
| `mass_org_access.settings` | `OrgAccessSettings` | Feature switch (env + State) and debug mode (URL secret `debug_show_pg` vs `MASS_ORG_ACCESS_DEBUG_SECRET` env) |
| `mass_org_access.org_access_checker` | `OrgAccessChecker` | Access intersection + `ownerGroupTermsForOrg` (shared direct lookup: copies the org_page's own curated Permission Groups) + `populateOwnerGroupsFromOrganizations` (backfill) |
| `mass_org_access.backfill_runner` | `BackfillRunner` | Resumable drush backfill driver (`run()`) + queue filler (`enqueue()`) — see [Queue variant](#queue-variant-moab-queue) |
| `mass_org_access.stage_file_fetcher` | `StageFileFetcher` | Pulls a missing `public://` file from the stage_file_proxy origin on demand (optional dependency; no-op on prod) |
| `mass_org_access.mapping_importer` | `OrgMappingImporter` | CSV parse + per-org_page Permission Groups writer (admin UI batches) |
| `mass_org_access.route_subscriber` | `Routing\RouteSubscriber` | Side-door route hardening |

`Plugin\QueueWorker\BackfillQueueWorker` (queue id `mass_org_access_backfill`)
processes the queued backfill — one entity per item, calling the same
`BackfillRunner::backfillEntity()` as the sync run.

The endpoint is served by `Controller\OrgLookupController::lookup`
(route `mass_org_access.lookup_user_orgs`). Access: any authenticated
user with `access content` — the augmentation JS runs for every editor,
including those the field is hidden from. Both the
endpoint and the drush backfill call the same
`OrgAccessChecker::ownerGroupTermsForOrg()`, so the live edit form and
the bulk job cannot diverge.

## Admin UI

Three local tasks at `/admin/config/content/mass-org-access`
(permission `administer site configuration`). The mapping tabs exist to
support the rollout step where the content team seeds Permission Groups
on the ~1000 `org_page` nodes — the values everything else derives from.

| Tab | Route | What it does |
|-----|-------|--------------|
| **Settings** | `/admin/config/content/mass-org-access` | Documents debug mode — no stored toggle; append `?debug_show_pg=<secret>` (matching `MASS_ORG_ACCESS_DEBUG_SECRET`) to an edit URL to reveal the field for that request. |
| **Edit mappings** | `…/matrix` | Matrix editor: one Select2 multi-picker per org_page ("State organization" terms), paged (50/100/500/all via `?items=`). **Save** persists to State `mass_org_access.matrix` (merges page-by-page, resumable); **Apply to nodes** batch-writes the whole saved matrix onto the org_pages (force overwrite); **Download CSV** exports the saved matrix; two **Clear** buttons re-seed from nodes or start fresh. |
| **Import mappings** | `…/import` | Upload a `nodeid,termid` CSV (header required; one row per node–term pair) → batch sets each org_page's `field_content_organization`. "Force override" checkbox — off skips org_pages that already have Permission Groups. CSV template + detailed downloadable run log. |

Both write paths validate that the NID is an `org_page` and the TIDs are
`user_organization` terms, and write the published revision **and** any
forward draft in place (`setSyncing(TRUE)`, no new revision) — same save
discipline as the drush backfill.

## User default organizations and labels (DP-46788)

Authors and editors maintain optional profile fields:

| Field | Purpose |
|-------|---------|
| `field_default_organizations` | org_page nodes pre-filled on **new** content |
| `field_default_labels` | label terms pre-filled on **new** pages/documents |

`field_user_org` (Permission Groups on the user) remains **admin-only**
via `entity_field_access`. Authors edit defaults on their own profile.

**New content only** (`entity_prepare_form` when `$entity->isNew()`,
in `mass_utility`):

- Organization(s) (or bundle-specific org field for binder/decision/person)
  from `field_default_organizations`.
- Label(s) (`field_reusable_label` or `field_document_label` on media) from
  `field_default_labels`.

Permission Groups (`field_content_organization`) is **not** pre-filled from
the user directly. It derives from Organization(s): the augmentation JS
mirrors each org_page's curated `user_organization` terms (ancestors
included, as stored on the org_page) into it whenever an org is present —
on initial load of the pre-filled Organization(s) and as the author
edits it.

**New media.document** when default organizations are empty: falls back to
org_page nodes mapped from the user's permission groups
(`field_state_organization` on each `field_user_org` term).

Editors may remove or change pre-filled values before the first save; nothing
is enforced on submit.

## Permissions

`bypass org access` — granted to `content_team`, inherited by
`administrator` via `is_admin: true`. Skips the org gate (allows
update/delete regardless of org match).

`view permission groups field` — granted to **no role**, so only
`administrator` has it (via `is_admin: true`); **Content Administrators do
not**. Gates Release 1 widget visibility: only holders see the Permission
Groups field on non-`org_page` bundles (the debug URL secret aside). Kept
separate from `bypass org access` on purpose — Content Administrators still
bypass the access gate, they just no longer see the field.

## Drush

The backfill runs **one entity type per invocation** — `--entity_type` is
required (`node` or `media`):

```sh
drush mass-org-access:backfill --entity_type=node    # alias: moab
drush moab --entity_type=media                       # nodes and media are separate runs
drush moab --entity_type=media --reset               # wipe stored progress, rescan
drush moab --entity_type=node --log=private://moab.log
drush mass-org-access:backfill-dev                   # alias: moab-dev — first 100
                                                     # nodes + 100 media, prints TIDs
```

Resumable via the `mass_org_access.backfill` State key (totals + last
processed id + processed counter, kept separately for nodes and media).
For each entity it reads every `field_organizations` value and copies the
Permission Groups curated on each referenced org_page's own
`field_content_organization` — the shared `ownerGroupTermsForOrg()`
direct lookup, the same derivation the live edit form uses. The org_page
**is** the source of truth; no taxonomy reverse lookup, no ancestor walk
(the hierarchy picker already stores ancestors on the org_page).

- Entities whose Permission Groups are already populated are left
  untouched — hand-curated values win; the backfill only fills empty
  fields. Entities with no orgs stay empty (admin-only-editable).
- `org_page` bundle itself is skipped (its field is the source).
- Both the default revision and any forward (unpublished) draft are
  populated, in place.
- Saves use `setNewRevision(FALSE)` and `setSyncing(TRUE)` to skip
  revision bloat and mass_validation overrides; mass_flagging "Watch"
  notification emails are suppressed (`MASS_FLAGGING_BYPASS`).
- A single entity that fails (e.g. a media item whose source file is
  missing both locally and on the proxy origin) is logged as `SKIPPED`
  and the run continues — one bad row never aborts the backfill.
- Timestamps land in `private://mass_org_access/backfill.log`.

### Missing files on prod-copied databases

When the run saves a `media` item it regenerates the thumbnail from the
source file. On a database copied from production the rows exist but the
files do not, which would throw a `FileNotExistsException`. `BackfillRunner`
guards against this:

- `StageFileFetcher` (`mass_org_access.stage_file_fetcher`) pulls each
  missing `public://` source file from the `stage_file_proxy` origin first —
  the same mechanism stage_file_proxy uses over HTTP, triggered
  programmatically since a drush save makes no HTTP request. No-op on the
  `prod` Acquia environment and when stage_file_proxy is not installed.
- `ensureMediaIconPlaceholders()` copies the media module's generic icon over
  any missing `media-icons/generic/*.png` thumbnail fallback (those icons live
  in the files dir, not in code, and the origin does not serve them).
- A file that is genuinely gone from the origin too (a 404 — an orphaned
  reference) cannot be fetched; that media is logged `SKIPPED` and the run
  moves on.

## Queue variant (`moab-queue`)

The same backfill is also available **through Drupal's core queue**, running
in parallel with the synchronous `moab` — useful for draining the work in
controlled, restartable passes instead of one long-running drush process.

```sh
# Fill the queue with one item per in-scope entity (org_page excluded).
# --entity_type is required; --reset empties the queue first. A progress
# bar shows the fill (≈440k single inserts for media takes tens of minutes).
drush mass-org-access:backfill-queue --entity_type=media   # alias: moab-queue
drush moab-queue --entity_type=node --reset

# Drain it manually, in bounded passes so a long process doesn't accumulate
# memory. Re-run to continue — the queue persists.
drush queue:run mass_org_access_backfill --time-limit=600   # ~10 min then exit
drush queue:run mass_org_access_backfill --items-limit=5000 # or cap by count
```

- **One entity per queue item** — payload is `['entity_type' => …, 'id' => …]`.
  The `mass_org_access_backfill` queue table therefore holds ~138k (node) or
  ~440k (media) rows per fill.
- `BackfillQueueWorker` (`@QueueWorker` id `mass_org_access_backfill`,
  **no cron** — manual drain only) loads each entity and calls the exact same
  `BackfillRunner::backfillEntity()` the sync command uses, so the two paths
  cannot diverge. It sets `MASS_FLAGGING_BYPASS`, self-heals the media-icon
  placeholders once per process, and logs a `SKIPPED` warning (channel
  `mass_org_access`) on any per-entity error — never re-throwing (which would
  requeue the item forever). `backfillEntity()` is idempotent, so reprocessing
  is safe.
- The queue **is** the progress for this path — no State cursor. Re-filling
  just re-enqueues.

## Bundles in scope

28 node bundles carry both `field_organizations` (or a bundle-specific
ref field) and `field_content_organization`, plus `media.document`. See
`tests/src/ExistingSiteJavascript/OogAugmentFromOrganizationsTest::entityProvider`
for the canonical list.

## Tests

```sh
# Backend behavior — access decisions, populate, backfill, debug mode (~30s)
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSite/MassOrgAccessTest.php

# CSV importer — parse, validate, force/skip, draft handling
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSite/OrgMappingImporterTest.php

# Matrix editor — State persistence + CSV export
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSite/OrgMappingMatrixTest.php

# JS visibility per role + bundle (~5-7 min)
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSiteJavascript/OwnerGroupsWidgetVisibilityTest.php

# JS Owner Groups augmentation (incl. real autocomplete typing, ~2.5 min)
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSiteJavascript/OogAugmentFromOrganizationsTest.php
```

> ⚠️ The ExistingSite suites run against the **real database** and toggle
> shared State (`mass_org_access.enforce`) in setUp/tearDown. An
> interrupted run leaves enforcement stuck ON. Never run them against a
> shared environment; recover with
> `drush state:delete mass_org_access.enforce`.

`OwnerGroupsWidgetVisibilityTest` checks the Release 1 visibility rules
across all 28 node bundles + media.document: visible for administrator
and content_team, hidden (present-but-not-visible) for editor and
author, with the `org_page` exception where everyone sees it.

`OogAugmentFromOrganizationsTest` adds an org_page, asserts the mapped
user_organization term appears in OOG, removes the org_page, asserts the
term leaves — across every bundle, both via direct value writes and via
a full type-into-autocomplete + click-suggestion flow on info_details.

JS tests require the `selenium-chrome` DDEV add-on and a correct
`DTT_MINK_DRIVER_ARGS` env var; see `.ddev/config.local.yaml` (or
`config.zlocal.yaml`) for the values that work with Chrome 138+.

## Rollout sequence

1. Deploy with switch **off** (current default).
2. Content team curates `field_content_organization` on the ~1000
   `org_page` nodes — via the **Edit mappings** matrix / **Import
   mappings** CSV admin tabs, or by hand. Org pages go first because
   every other entity copies its Permission Groups from them.
3. Backfill on prod (resumable, hours) — run per entity type, either
   synchronously (`drush moab --entity_type=node`, then `--entity_type=media`)
   or via the queue (`drush moab-queue --entity_type=…` + `drush queue:run
   mass_org_access_backfill --time-limit=…`).
4. Wait ≥1 week, identify users who would lose edit access.
5. Flip switch on (`drush sset mass_org_access.enforce 1` or set the env
   var). Hooks start enforcing; in Release 2 the widget also opens up to
   editors.
