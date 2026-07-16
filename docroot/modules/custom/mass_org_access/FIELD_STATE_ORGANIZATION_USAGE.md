# `user_organization.field_state_organization` — usage outside mass_org_access

This field lives on `taxonomy_term` (vocabulary `user_organization`) and is
an `entity_reference` to a single `org_page` node. It's the bridge that maps
a user's taxonomy-based org membership to the real org_page used to tag
content.

> Not to be confused with `field_state_organization_tax`, a separate field
> that lives on **content nodes** and feeds the `mg_stakeholder_org` metatag
> token. That one is unrelated.

> **Primary consumer (inside `mass_org_access`, since v2-2 / 2026-06-02):**
> this field is the source of truth for deriving a node/media entity's
> Permission Groups from its `Organization(s)`. Given an org_page NID,
> `OrgAccessChecker::ownerGroupTermsForOrg()` finds the `user_organization`
> term whose `field_state_organization` references that org (+ ancestors via
> `loadAllParents()`). Both the live edit form (`OrgLookupController`) and the
> bulk backfill (`drush moab`) use it — one shared reverse lookup. The org_page
> node itself is never read for permissions (it is content; can be deleted /
> unpublished). The forward direction (term → org_page) below is the older
> new-content-prefill use; both directions now matter.

## Real consumers

### 1. `mass_org_access` — default org on new media and node forms

`OrgAccessChecker::applyDefaultsToNewEntity()` (via `entity_prepare_form`)
pre-fills Organization(s) on **new** node and media forms from the user's
`field_default_organizations` when set.

For **new media.document** only, when default organizations are empty,
`getDefaultOrganizationNidsWithFallback()` uses the same mapping as the
legacy flow: each `field_user_org` term's `field_state_organization` →
org_page NID.

### 2. `mass_utility/OrganizationTransfer.php` — media queue worker (DEAD CODE)

The queue worker reads `field_contributing_organization` from a media
entity, resolves the referenced `user_organization` terms, then copies
each term's `field_state_organization` (org_page reference) into the
media's `field_organizations`.

🪦 **This worker no longer functions.** `field_contributing_organization`
does not exist anywhere in the codebase or database:

- No `FieldStorageConfig` entry
- No `FieldConfig` instance on any entity (media, node, user, term)
- No `media__field_contributing_organization` table

Calling `$media->get('field_contributing_organization')` would throw
`InvalidArgumentException: Field field_contributing_organization is
unknown` the moment a queue item is processed. The same field is also
referenced (orphaned) in `views.view.documents_by_filter.yml` as a Views
argument that resolves to nothing.

This appears to be leftover from an earlier Mass.gov data model. No
impact on `mass_org_access`. Worth a separate cleanup ticket to remove
the worker, the queue, and the orphaned Views argument.

### 3. `mass_bigquery/TopPrioritiesForm.php` — BigQuery dashboard

The form loads the org_page tied to the current user so it can scope a
BigQuery query to that org. It calls `$user->field_user_org->target_id`,
loads the matching term, then reads `field_state_organization->target_id`.

⚠️ **Single-value assumption**: this code reads only the first
`field_user_org` value. After cardinality became unlimited, multi-org
users see priorities for one org only. Worth a follow-up to support all
of the user's orgs (or a deliberate "primary org" selector).

### 4. View display config

`core.entity_view_display.taxonomy_term.user_organization.default.yml`
exposes the field in the term's default view mode. Display-only, no
functional impact.

## Summary

- **`mass_utility.module` form_alter** — Pre-fills org on the new-media
  form (writes to `$form`, not the user). Partial multi-org support: only
  the last iterated org becomes the pre-filled default; editor can add
  the rest manually.
- **`mass_utility` `OrganizationTransfer` worker** — 🪦 Dead code. References
  a field (`field_contributing_organization`) that no longer exists.
- **`mass_bigquery` `TopPrioritiesForm`** — Org-scoped BigQuery dashboard.
  Reads only the first `field_user_org` value; multi-org users see
  priorities for one org only. Follow-up needed.
- **`user_organization` view display config** — Shows the field on the
  term's view page. Display-only, no functional impact.
