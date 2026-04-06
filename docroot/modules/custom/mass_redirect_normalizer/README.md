# Redirect Link Normalizer

This module rewrites internal links that still point at **redirect source paths** so they use the **final path** instead. For rich text, when the final target is a node, it also adds `data-entity-*` attributes.

The same logic runs in two places:

- **Bulk Drush command** — scan many entities and fix stored values.
- **`hook_entity_presave()`** — when an editor saves a node or paragraph, links are normalized on that save.

---

## What gets scanned

For each **node** or **paragraph**, the code looks at:

- Text fields: `text_long`, `text_with_summary`, `string_long` (HTML `href` values inside the markup).
- **Link** fields (`link` type): the stored URI.

It does **not** change random text; it only rewrites values the resolver treats as redirect-based internal links (see integration tests for examples).

## Mre about code

- `RedirectLinkResolver`:
  - Only link logic.
  - It finds the final path and rewrites one text value or one link value.
  - It does **not** save entities.
- `RedirectLinkNormalizationManager`:
  - Entity workflow logic.
  - It loops fields on node/paragraph, calls the resolver, handles dry-run, and saves revisions when needed.

This split keeps code easier to test and easier to maintain.

---

## Drush command

| Item | Value |
|------|--------|
| Command | `mass-redirect-normalizer:normalize-links` |
| Alias | `mnrl` |

### Options

| Option | Meaning |
|--------|---------|
| `--simulate` | Dry run: **no** database writes. Same idea as global `ddev drush --simulate ...`. |
| `--limit=N` | Max entities **per entity type** to load from the query. **`0` = no limit.** When `--entity-type=all`, you get up to **N nodes** and up to **N paragraphs** (two separate caps). |
| `--entity-type=node\|paragraph\|all` | Default **`all`** (nodes and paragraphs). |
| `--bundle=...` | Only that bundle (node type or paragraph type machine name). Still checked after load. |
| `--entity-ids=1,2,3` | Only these IDs. **Requires** `--entity-type=node` or `paragraph` (**not** `all`). Ignores `--limit`. |

### Default table columns

| Column | Notes |
|--------|--------|
| Status | `would_update` (simulate) or `updated` (real run). |
| Entity type | `node` or `paragraph`. |
| Entity ID | Entity id. |
| Parent node ID | For **paragraphs**, the host node id from `Helper::getParentNode()`. For nodes, `-`. |
| Bundle | Bundle / type machine name. |
| URL before / URL after | This is just the link value, not full HTML. For link fields, it shows the stored path/URL. For text fields, it shows only links that changed (`href`). If many links changed in one field, they are joined with `; `. If the value is too long, CLI shortens it. |

### What the command skips

- **Orphan paragraphs** — paragraphs that are not attached to real host content (`Helper::isParagraphOrphan()`). They are **not** processed and **do not** appear as rows.
- Entities with **no** redirect-based links to fix produce **no** rows (empty table is normal).

### Simulate, then run, then verify (manual QA)

1. **Preview:**
   `ddev drush mass-redirect-normalizer:normalize-links --simulate --limit=100`
2. **Apply:**
   `ddev drush mass-redirect-normalizer:normalize-links --limit=100`
3. **Re-check:** run **simulate** again with the same filters. Items that were fixed should **not** show `would_update` anymore (unless something else changed them back).

For a narrow retest after you know specific IDs:

`ddev drush mass-redirect-normalizer:normalize-links --simulate --entity-type=paragraph --entity-ids=123,456`

### Important detail about saved content

On **first save**, `hook_entity_presave()` may already rewrite links in the stored field values. So if you create test content in the UI and then expect the bulk command to “see” the old redirect URL in the database, it might already be normalized. The automated tests handle that case where needed.

---

## Automated tests

Existing-site integration tests live here:

`docroot/modules/custom/mass_redirect_normalizer/tests/src/ExistingSite/RedirectLinkNormalizationTest.php`

Run tests:

```bash
ddev exec ./vendor/bin/phpunit docroot/modules/custom/mass_redirect_normalizer/tests/src/ExistingSite/RedirectLinkNormalizationTest.php
```

### What is covered

- Redirect chain resolution (including query and fragment support).
- Rich-text rewriting (`href`) and node metadata attributes (`data-entity-*`).
- Link field URI normalization (`internal:/...` and absolute local mass.gov URLs).
- Redirect loops and max-depth behavior (no infinite follow, expected stop point).
- External URL behavior (ignored; no rewrite).
- Alias-like non-node targets (rewrite link, but do not add node metadata).
- Presave normalization path for nodes (`hook_entity_presave()` behavior).
- Manager behavior:
  - Run it twice gives same result (first run fixes links, second run has nothing new to fix).
  - Multi-value link field handling (only redirecting values change).
  - Link item metadata preservation (`title`, `options`).
- Drush command behavior:
  - Entity type and bundle filters.
  - Targeted runs with `--entity-ids`.
  - Simulate mode row output (`would_update`) and URL before/after columns.

---

## Periodic / bulk cleanup

Use the Drush command above for one-off or scheduled bulk runs.

---

## Post-run usage refresh

For large backfills, regenerate entity usage so usage reports stay accurate.
