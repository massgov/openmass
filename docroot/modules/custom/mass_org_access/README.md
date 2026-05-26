# Mass Org Access

Restricts who can edit / publish / transition / schedule / bulk-change /
delete content on Mass.gov to users assigned to that content's
organization. **View and clone access stay open sitewide.** Implements
DP-45788.

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

The Permission Groups widget itself is **not gated by the switch** — it
is visible to every role that can edit the host entity, so authors can
see and broaden the field before enforcement is enabled.

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
| `entity_field_access` | Locks `field_user_org` edit to `administer users` (prevents self-promotion). Locks `field_approved` and `field_approval_notes` view/edit to `administer users`. Permission Groups itself is not field-restricted. |
| `entity_prepare_form` | On new entities only: pre-fills `field_content_organization` from the creator's `field_user_org` + ancestors. Existing entities are populated exclusively by `drush moab`. |
| `form_node_form_alter` | Adds `validateOrgAccess` callback (static method — closures break paragraphs AJAX). Defense-in-depth: surfaces an error if a save reaches form validation despite `node_access` already denying it. |
| `field_widget_complete_form_alter` | Renders Permission Groups as a read-only list (see widget section). Attaches both JS libraries. |
| `user_login` | At login, warns editor/author roles without `field_user_org`. Silent while switch is off. |

## Routing

`Routing/RouteSubscriber.php` swaps `_entity_access` from `node.view` to
`node.update` on three side-door routes so the org gate fires there too:

- `entity.node.entity_hierarchy_reorder` (reorder children)
- `view.change_parents.page_1` (move children between parents)
- `entity.node.redirects`

## Owner Groups "widget"

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
`/mass-org-access/lookup-user-orgs?org_page_nids[]=…`, finds any
`user_organization` terms whose `field_state_organization` references
the added org_page, collects their ancestors via `loadAllParents()`, and
appends the union to `field_content_organization`. Removing the
organization drops only the terms that organization auto-added and that
no other tracked organization still pulls in (reference counting via
`Map<orgNid, Set<tid>>`). Manual terms stay. Polling catches autocomplete
picks because jQuery UI's `.val()` doesn't fire events.

Placement: `field_content_organization` is weighted to be first inside
the **Page Info** field group on all 28 node form displays. Help text is
rewritten across 29 bundle field configs.

## Services

| Service ID | Class | Role |
|------------|-------|------|
| `mass_org_access.settings` | `OrgAccessSettings` | Reads env + State for the feature switch |
| `mass_org_access.org_access_checker` | `OrgAccessChecker` | Access intersection + `populateOwnerGroupsFromCurrentUser` (form pre-fill) + `populateOwnerGroupsFromOrgPage` (backfill) |
| `mass_org_access.backfill_runner` | `BackfillRunner` | Resumable drush backfill driver |
| `mass_org_access.route_subscriber` | `Routing\RouteSubscriber` | Side-door route hardening |

The endpoint is served by `Controller\OrgLookupController::lookup`
(route `mass_org_access.lookup_user_orgs`). Access: any authenticated
user with `access content` — matches who can see the widget.

## User default organizations and labels (DP-46788)

Authors and editors maintain optional profile fields:

| Field | Purpose |
|-------|---------|
| `field_default_organizations` | org_page nodes pre-filled on **new** content |
| `field_default_labels` | label terms pre-filled on **new** pages/documents |

`field_user_org` (Permission Groups on the user) remains **admin-only**
via `entity_field_access`. `field_approved` and `field_approval_notes` are
also restricted to users with `administer users` (view and edit). Authors
edit defaults on their own profile.

**New content only** (`entity_prepare_form` when `$entity->isNew()`):

- Organization(s) (or bundle-specific org field for binder/decision/person)
  from `field_default_organizations`.
- Label(s) (`field_reusable_label` or `field_document_label` on media) from
  `field_default_labels`.
- Permission Groups on content (`field_content_organization`) still come
  from the creator's `field_user_org` + ancestors (unchanged).

**New media.document** when default organizations are empty: falls back to
org_page nodes mapped from the user's permission groups
(`field_state_organization` on each `field_user_org` term).

Editors may remove or change pre-filled values before the first save; nothing
is enforced on submit.

### Deploy hook: populate default organizations

`mass_org_access_deploy_populate_user_default_orgs` runs via `drush deploy`.
For each active user with `field_user_org`, copies mapped org_page NIDs into
`field_default_organizations` when that field is still empty. Does **not**
set default labels.

Verify locally:

```sh
ddev drush deploy
# or only this hook:
ddev drush deploy:hook mass_org_access_populate_user_default_orgs
```

## Permission

`bypass org access` — granted to `content_team`, inherited by
`administrator` via `is_admin: true`. Skips the org gate (allows
update/delete regardless of org match). Does not affect widget
visibility — the widget is open to all roles with entity edit access.

## Drush

```sh
drush mass-org-access:backfill        # alias: moab
```

Resumable via the `mass_org_access.backfill` State key (totals + last
processed id + processed counter, kept separately for nodes and media).
For each entity it loads the first `field_organizations` value, then
copies `org_page.field_content_organization` verbatim onto the entity
— no ancestor walk, because the content team includes ancestors in the
curated org_page values. `org_page` itself is skipped (it's the source
of truth). Saves use `setNewRevision(FALSE)` and `setSyncing(TRUE)` to
skip revision bloat and mass_validation overrides. Timestamps land in
`private://mass_org_access/backfill.log`.

## Bundles in scope

28 node bundles carry both `field_organizations` (or a bundle-specific
ref field) and `field_content_organization`, plus `media.document`. See
`tests/src/ExistingSiteJavascript/OogAugmentFromOrganizationsTest::entityProvider`
for the canonical list.

## Tests

```sh
# Backend behavior — access decisions, populate, backfill (~30s)
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSite/MassOrgAccessTest.php

# JS visibility per role + bundle (87 cases, ~5-7 min)
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSiteJavascript/OwnerGroupsWidgetVisibilityTest.php

# JS Owner Groups augmentation (31 cases incl. real autocomplete typing, ~2.5 min)
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSiteJavascript/OogAugmentFromOrganizationsTest.php
```

`OwnerGroupsWidgetVisibilityTest` checks that the widget is **visible**
for every role with entity edit access (administrator, content_team,
editor) across all 28 node bundles + media.document.

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
   `org_page` nodes.
3. `drush moab` on prod (resumable, hours).
4. Wait ≥1 week, identify users who would lose edit access.
5. Flip switch on (`drush sset mass_org_access.enforce 1` or set the env
   var). The widget opens up to editors, hooks start enforcing.
