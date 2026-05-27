# Redirect Link Normalizer

This module rewrites internal links that still point at **redirect source paths** so they use the **final path** instead. For rich text, when the final target is a node, it also adds `data-entity-*` attributes.

The same logic runs in two places:

- **Bulk Drush command** — scan eligible entities and enqueue normalization work.
- **`hook_entity_presave()`** — when an editor saves a node or paragraph, enqueue normalization for later processing.

Run queued work with `ddev drush queue:run mass_redirect_normalizer_link_normalization`. Until the queue worker runs, presave and bulk enqueue changes are **eventual**, not immediate.

---

## What gets scanned

For each **node** or **paragraph**, the code looks at:

- Text fields: `text_long`, `text_with_summary`, `string_long` (HTML `href` values inside the markup).
- **Link** fields (`link` type): the stored URI.
- **Entity reference** fields (`entity_reference` type) targeting `node` or `media`: the stored `target_id` when strict-safe redirect resolution finds one unambiguous replacement target.

It does **not** change random text; it only rewrites values the resolver treats as redirect-based internal links (see integration tests for examples).

## Why there are two classes

- `RedirectLinkResolver`:
  - Only link logic.
  - It finds the final path and rewrites one text value or one link value.
  - It does **not** save entities.
- `RedirectLinkNormalizationManager`:
  - Entity workflow logic.
  - It loops fields on node/paragraph, calls the resolver, handles dry-run, and saves revisions when needed.

This split makes the code easier to test and maintain.

---

## Drush command

| Item | Value |
|------|--------|
| Command | `mass-redirect-normalizer:normalize-links` |
| Alias | `mnrl` |

### Options

| Option | Meaning |
|--------|---------|
| `--release-enqueue-lock` | Delete the enqueue sweep lock row from `{semaphore}` and exit (no sweep). Use after a **crash or kill** when PHP could not release the lock. |

`mnrl` enqueues **all published nodes**, then **all paragraphs**, using fast ID
streaming (no entity loads in Drush). Queue items are batched (up to 100 entity
refs per row).

Execute mode **acquires** a sweep-wide lock so two `mnrl` runs do not enqueue in
parallel. If a run dies without releasing, run:

```bash
ddev drush mnrl --release-enqueue-lock
```

Then rerun `ddev drush mnrl`.

### Queue worker responsibilities

The queue worker loads each entity, applies eligibility, normalizes redirect
links, saves revisions when needed, and writes **change log rows** only when a
field value actually changed.

- Nodes must be published (bulk node ID query is published-only).
- Paragraphs are processed only when their parent node is published.
- If a published node has a newer unpublished draft revision, that node and its
  child paragraphs are skipped.

Entities with **no** redirect-based links to fix are no-ops in the worker.

### Change report (admin UI)

Report page:

`/admin/reports/redirect-link-normalizer`

Permissions:

- `view mass redirect normalizer report`
- `export mass redirect normalizer report`
- `clear mass redirect normalizer report`

Granted to **Content Administrator** (`content_team`) and **Administrator**
(`is_admin: true`). Not granted to Author or Editor.

The report lists changed field values (newest first), with actions to export CSV
to `public://mnrl-reports/` or clear all records.

### Operator workflow

```bash
# 1) Enqueue full site
ddev drush mnrl

# 2) Process queue (cron every 5 min, or manual)
ddev drush queue:run mass_redirect_normalizer_link_normalization

# 3) Review changes
# Open /admin/reports/redirect-link-normalizer
```

If `mnrl` was interrupted:

```bash
ddev drush mnrl --release-enqueue-lock
ddev drush mnrl
```

Progress notices print every 500 entities per phase (node, then paragraph).

### Important detail about saved content

On **editor save**, `hook_entity_presave()` enqueues normalization work. While the **queue worker** is processing, presave does **not** re-enqueue (avoids the queue growing during `queue:run`).

Entity-reference behavior:

- Entity-reference fields to `node` and `media` are normalized when strict-safe
  redirect resolution finds exactly one deterministic replacement target.
- Ambiguous/unresolved/cross-type targets are skipped.
- Active-alias conflict guard: if a non-canonical alias path still resolves to
  the same referenced entity, that alias-based redirect is ignored for
  entity-reference rewrites.

---

## Automated tests

Existing-site integration tests validate end-to-end behavior of:
- redirect-chain resolution and safety guards (loops, unresolved/ambiguous targets),
- rich-text and link-field normalization (including Linkit-friendly entity URIs),
- entity-reference rewrites for node/media with strict-safe rules,
- command output/reporting behavior (simulate rows, CSV export, filters),
- queue enqueue, worker processing, dedupe, resume, and enqueue-lock behavior.

Test file:

`docroot/modules/custom/mass_redirect_normalizer/tests/src/ExistingSite/RedirectLinkNormalizationTest.php`

Run:

```bash
ddev exec ./vendor/bin/phpunit docroot/modules/custom/mass_redirect_normalizer/tests/src/ExistingSite/RedirectLinkNormalizationTest.php
```

### What is covered

- Redirect chain resolution (including query and fragment support).
- Rich-text rewriting (`href`) and node metadata attributes (`data-entity-*`).
- Link field URI normalization (`internal:/...`, absolute local mass.gov URLs, and entity-backed `entity:node/{nid}` values where resolvable).
- Redirect loops and max-depth behavior (no infinite follow, expected stop point).
- External URL behavior (ignored; no rewrite).
- Alias-like non-node targets (rewrite link, but do not add node metadata).
- Presave enqueue path for nodes (`hook_entity_presave()` behavior).
- Manager behavior:
  - Run it twice gives same result (first run fixes links, second run has nothing new to fix).
  - Multi-value link field handling (only redirecting values change).
  - Link item metadata preservation (`title`, `options`).
  - Entity-reference rewrites for node/media fields (including strict-safe skips and alias-conflict guard behavior).
- Drush enqueue-only command behavior (fast ID sweep, batched queue rows).
- Queue worker change-log writes for entities that actually changed.
- Report permissions scoped to content admin and administrator roles.
- Presave enqueue and worker processing paths.

---

## Periodic / bulk cleanup

Use the Drush command to enqueue work, then drain the queue with `queue:run` for one-off or scheduled bulk runs.

---

## Post-run usage refresh

For large backfills, regenerate entity usage so usage reports stay accurate.
