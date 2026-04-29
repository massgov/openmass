# `user_organization.field_state_organization` — usage outside mass_org_access

This field lives on `taxonomy_term` (vocabulary `user_organization`) and is
an `entity_reference` to a single `org_page` node. It's the bridge that maps
a user's taxonomy-based org membership to the real org_page used to tag
content.

> Not to be confused with `field_state_organization_tax`, a separate field
> that lives on **content nodes** and feeds the `mg_stakeholder_org` metatag
> token. That one is unrelated.

## Real consumers

### 1. `mass_utility.module` — default org on new media form

`mass_utility_form_media_form_alter()` (around line 340) pre-fills the
`field_organizations` widget on the **new media** form with the current
user's org so the editor doesn't have to pick it manually. It iterates
the user's `field_user_org` term IDs, loads each term, and reads
`field_state_organization` to find the corresponding org_page.

This writes a `#default_value` into the form structure for the media
being created — it does **not** modify the user account.

After cardinality on `field_user_org` changed to unlimited, the loop runs
over every term but assigns to the same `widget[0]` slot, so only the
last iteration's org becomes the pre-filled default. The field is still
multi-valued — the editor can add the rest with "Add another item" —
but the auto-fill is no longer comprehensive.

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
