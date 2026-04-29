# Mass Org Access

Restricts who can edit, publish, transition, schedule, bulk-change, or delete
content on Mass.gov to users assigned to that content's organization.
Editors and authors retain full **view** and **clone** access to all
content sitewide. Implements DP-45788.

## Data model

```
User
  └─ field_user_org (multi-value, → user_organization terms)

user_organization taxonomy term
  └─ field_state_organization (→ org_page node)

org_page node
  └─ field_parent (→ parent org_page)

Content node / media.document
  ├─ field_organizations           (→ org_page nodes)   ← editor-facing
  └─ field_content_organization    (→ user_organization terms, hidden)
                                                          auto-populated
```

### Field reference

| Field | Entity | Cardinality | Type | Purpose |
|-------|--------|-------------|------|---------|
| `field_user_org` | user | unlimited | entity_reference → taxonomy_term (`user_organization`) | The orgs the user belongs to |
| `field_state_organization` | taxonomy_term (`user_organization`) | 1 | entity_reference → node (`org_page`) | Maps a user_organization term to its real org_page node |
| `field_parent` | node (`org_page`) | 1 | entity_reference → node (`org_page`) | Org hierarchy (parent organization) |
| `field_organizations` | node (any of 31 bundles) / media.document | unlimited | entity_reference → node (`org_page`) | Editor-facing org tagging on content |
| `field_content_organization` | node (any of 31 bundles) / media.document | unlimited | entity_reference → taxonomy_term (`user_organization`) | Hidden, denormalized list of user_organization term TIDs (incl. ancestors). Drives the access check. |

### How the bridge works

`field_organizations` references **org_page nodes**, but Drupal's access check
is fastest when it can compare term IDs on both sides without joins.
`field_content_organization` is the bridge — populated automatically on save
by `entity_presave`:

1. Read the org_page NIDs from `field_organizations` (and the entity's own
   NID if the bundle is `org_page`).
2. Walk `field_parent` upward to collect every ancestor org_page NID.
3. Look up every `user_organization` term whose `field_state_organization`
   matches any of those NIDs.
4. Write the resulting term IDs into `field_content_organization`.

A node tagged with a child org therefore carries the parent-org user_organization
terms too. A user assigned to the parent org passes the access check
without any traversal at request time — `array_intersect` does the job.

### Multi-org content example

When `field_organizations` holds several orgs, the sync walks **every**
chain upward in parallel and unions the results. Each starting NID goes
into the same BFS queue; cycle protection (a `$seen` map keyed by NID)
prevents re-processing.

```
field_organizations = [Dept A, Dept B]

Dept A
  └─ Office X
       └─ Top-level M

Dept B
  └─ Office Y
```

After `collectAncestorNids`: `[Dept A, Dept B, Office X, Office Y, Top-level M]`.
After `getTermIdsByOrgNids`: every `user_organization` term whose
`field_state_organization` matches any of those NIDs.
That full set is written to `field_content_organization`.

A user assigned to **any** of those orgs (the two leaves, the two
mid-level offices, or the top-level) passes the intersection check.

## Access decision

`userHasOrgAccess` (and the hooks built on it) decides write access by
intersecting two flat lists:

```
allowed = !empty(array_intersect(
  user.field_user_org TIDs,
  entity.field_content_organization TIDs
))
```

The hooks layer additional rules on top:

1. **Operation gate** — only `update` and `delete` are checked. `view` is
   always neutral.
2. **Bypass** — anyone with `bypass org access` permission (granted to
   `content_team`; admins inherit it via `is_admin: true`) is neutral.
3. **No org assigned** — if the user has no `field_user_org` value, write
   access is forbidden across the board, even on content with no org.
4. **Empty `field_content_organization`** — neutral (rollout safety; lets
   un-backfilled content fall through to Drupal's normal checks).
5. **Intersection** — non-empty intersection → neutral; empty → forbidden.

## Hooks

All hooks are OOP hooks (Drupal 11.3+ `#[Hook(...)]` attributes) in
`src/Hook/MassOrgAccessHooks.php`.

- **`hook_node_access`** — Blocks `update`/`delete` on nodes outside the user's
  org. View is always neutral. Adds `user:UID` cache tag so decisions
  invalidate when the user's `field_user_org` changes.
- **`hook_media_access`** — Same logic for media (currently used for
  `media.document`).
- **`hook_entity_presave`** — Syncs `field_organizations` →
  `field_content_organization` on every node and media save.
- **`hook_form_node_form_alter`** — Adds a `#validate` callback (named static
  method, not a closure — closures break paragraphs AJAX rebuild). Shows a
  human-readable error if a save somehow reaches form validation.
- **`hook_entity_field_access`** — Locks down `field_user_org` so only users
  with `administer users` can edit it — prevents an editor from
  self-assigning organizations on `/user/UID/edit`.
- **`hook_user_login`** — Shows a warning to editors / authors at login when
  they have no `field_user_org` assigned, telling them to contact a site
  administrator.

## Service: `OrgAccessChecker`

Inject as `mass_org_access.org_access_checker` (or by class via the alias).

- **`getUserOrgTids(AccountInterface): int[]`** — User's `field_user_org` term
  IDs, cached per request via `drupal_static()`.
- **`getEntityOrgTids(EntityInterface): int[]`** — The entity's
  `field_content_organization` term IDs.
- **`userHasOrgAccess(AccountInterface, EntityInterface): bool`** —
  `array_intersect` between the two.
- **`syncContentOrganization(EntityInterface): void`** — The presave bridge:
  walks `field_parent` and writes `field_content_organization`.

## Permissions

- **`bypass org access`** — custom permission. Granted to `content_team`;
  inherited by `administrator` via `is_admin: true`. Skips the org gate
  entirely while leaving Drupal's per-bundle `edit any X content` /
  `delete any X content` permissions intact.

## Drush commands

```sh
drush mass-org-access:backfill        # alias: moab — full backfill
drush mass-org-access:backfill-dev    # alias: moab-dev — first 100 nodes + 100 media, prints IDs
```

The backfill saves with `setNewRevision(FALSE)` and `setSyncing(TRUE)` so
content moderation hooks don't create new revisions for what is purely
metadata maintenance.

## Bundles in scope

`field_content_organization` exists on the 31 content node bundles that
have `field_organizations`, plus `media.document`. The list of bundles
the editor role can actually edit (and which are therefore tested
end-to-end) is in `tests/src/ExistingSite/MassOrgAccessTest::NODE_BUNDLES`.

## Tests

```sh
ddev exec phpunit docroot/modules/custom/mass_org_access/tests/src/ExistingSite/MassOrgAccessTest.php
```

Coverage:
- Same-org / cross-org write across all 28 editor-editable node bundles + media.document.
- View neutrality across anonymous, authenticated, editor, author, viewer,
  mmg_editor, content_team, bulk_edit.
- User without org denied; warning shown at login.
- `bypass org access` (content_team) bypasses the gate.
- Ancestor-org user can edit child-org content (denormalization works).
- Multi-org content allows any matching-org user.
- Multi-org user can edit any of their orgs but not unrelated orgs.
- Editor cannot self-edit `field_user_org`; admin can.
