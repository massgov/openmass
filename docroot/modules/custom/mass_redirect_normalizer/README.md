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
| `--simulate` | Dry run: **no** database writes. Same idea as global `ddev drush --simulate ...`. |
| `--limit=N` | Max eligible entities to process **total** across node + paragraph. Command stops when it reaches `N`. **`0` = no limit. |
| `--bundle=...` | Only that bundle (node type or paragraph type machine name). Still checked after load. |
| `--entity-ids=1,2,3` | Only these IDs. IDs are checked in both node and paragraph entities. Ignores `--limit`. |
| `--kinds=text,link,entity_reference` | Include only these change kinds in table output, CSV output, and updated/value counters. |
| `--csv-path=./redirect-normalizer-report.csv` | Write a full CSV report in the current directory (or other relative/absolute path). |
| `--entity-type=node|paragraph|all` | Restrict scans to one entity type, or both (`all`/default). |
| `--start-id=N` | Start scanning from this ID (inclusive). Useful for manual resume windows. |
| `--resume` | Continue from saved progress checkpoint (last processed ID per entity type). |
| `--show-progress` | Print saved progress checkpoint and exit without scanning. |
| `--reset-progress` | Clear saved progress checkpoint before run. |

By default, bulk command processes only **published** content.

- Nodes must be published.
- Paragraphs are processed only when their parent node is published.
- If a published node has a newer unpublished draft revision, that node and its
  child paragraphs are skipped by bulk command (so we do not touch draft work).

### Default table columns

| Column | Notes |
|--------|--------|
| Status | `would_update` (simulate) or `updated` (real run). |
| Entity type | `node` or `paragraph`. |
| Entity ID | Entity id. |
| Parent node ID | For **paragraphs**, the host node id from `Helper::getParentNode()`. For nodes, `-`. |
| Bundle | Bundle / type machine name. |
| URL before / URL after | This is just the changed value, not full HTML. For link fields, it shows stored path/URL. For text fields, it shows only changed `href` values. For entity references, it shows `node:123` or `media:456`. If many links changed in one field, they are joined with `; `. If too long, CLI shortens it. |

### CSV reporting

- CSV includes the same rows as table output (including `--kinds` filtering).
- CSV `before` / `after` values are full values (not table-truncated).
- Header columns:
  - `status,entity_type,entity_id,parent_node_id,bundle,field,delta,kind,before,after,details`

Examples:

```bash
# Full simulation report in current directory.
ddev drush mnrl --simulate --limit=20000 --csv-path=./redirect-normalizer-report.csv

# Only entity-reference changes, current directory CSV.
ddev drush mnrl --simulate --kinds=entity_reference --csv-path=./entity-reference-only-report.csv

# Apply only selected IDs and export report.
ddev drush mnrl --entity-ids=857211,858626 --csv-path=./mnrl-selected-exec.csv
```

### What the command skips

- **Orphan paragraphs** — paragraphs that are not attached to real host content (`Helper::isParagraphOrphan()`). They are **not** processed and **do not** appear as rows.
- Entities with **no** redirect-based links to fix produce **no** rows (empty table is normal).
- Unpublished/trashed content is skipped.
- Published content with newer unpublished draft revisions is skipped.

### Simulate, then run, then verify (manual QA)

1. **Preview:**
   `ddev drush mass-redirect-normalizer:normalize-links --simulate --limit=100 --csv-path=./redirect-normalizer-preview.csv`
2. **Apply:**
   `ddev drush mass-redirect-normalizer:normalize-links --limit=100 --csv-path=./redirect-normalizer-exec.csv`
3. **Re-check:** run **simulate** again with the same filters. Items that were fixed should **not** show `would_update` anymore (unless something else changed them back).

For big runs, command prints progress notice every 100 processed entities. This
is expected and helps confirm it is still running.

For a narrow retest after you know specific IDs:

`ddev drush mass-redirect-normalizer:normalize-links --simulate --entity-ids=123,456`

### Long-run recovery and resume

For long/background runs (for example Acquia SSH), use checkpoint options so you
can return later, inspect state, and safely continue.

```bash
# 1) Start a long run and save report in current directory.
ddev drush mnrl --entity-type=paragraph --limit=50000 --csv-path=./mnrl-paragraph-run.csv

# 2) Later, check saved checkpoint only (no scan).
ddev drush mnrl --show-progress

# 3) Resume from last checkpoint.
ddev drush mnrl --entity-type=paragraph --resume --csv-path=./mnrl-paragraph-resume.csv

# 4) If you need a clean restart, clear checkpoint then run.
ddev drush mnrl --reset-progress --entity-type=paragraph --start-id=1 --csv-path=./mnrl-paragraph-fresh.csv
```

Notes:
- Checkpoint stores last processed ID per entity type (`node` / `paragraph`),
  processed count, updated entity count, changed field values, mode, and
  timestamp.
- When `--resume` and `--start-id` are both set, the command uses the higher
  effective start position.

### Important detail about saved content

On **first save**, `hook_entity_presave()` may already rewrite links in the stored field values. So if you create test content in the UI and then expect the bulk command to “see” the old redirect URL in the database, it might already be normalized. The automated tests handle that case where needed.

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
- command output/reporting behavior (simulate rows, CSV export, filters).

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
- Presave normalization path for nodes (`hook_entity_presave()` behavior).
- Manager behavior:
  - Run it twice gives same result (first run fixes links, second run has nothing new to fix).
  - Multi-value link field handling (only redirecting values change).
  - Link item metadata preservation (`title`, `options`).
  - Entity-reference rewrites for node/media fields (including strict-safe skips and alias-conflict guard behavior).
- Drush command behavior:
  - Bundle filter:
    - Process one bundle only.
    - Example: `ddev drush mnrl --simulate --bundle=info_details --csv-path=./mnrl-info-details.csv`
  - Targeted runs with `--entity-ids`:
    - Restrict processing to specific IDs (checked in nodes and paragraphs).
    - Example: `ddev drush mnrl --simulate --entity-ids=857211,858626 --csv-path=./mnrl-targeted.csv`
  - Kind filter with `--kinds`:
    - Include only selected change types: `text`, `link`, `entity_reference`.
    - Example (entity-reference only): `ddev drush mnrl --simulate --kinds=entity_reference --csv-path=./mnrl-entity-reference.csv`
    - Example (text + link only): `ddev drush mnrl --simulate --kinds=text,link --csv-path=./mnrl-text-link.csv`
  - CSV export with `--csv-path`:
    - Writes a parseable report with full `before`/`after` values.
    - Example: `ddev drush mnrl --simulate --limit=20000 --csv-path=./redirect-normalizer-report.csv`
  - Simulate vs execution output:
    - `--simulate` rows show `would_update`.
    - Execution rows show `updated`.
    - `before` / `after` columns show what will change or changed.

---

## Periodic / bulk cleanup

Use the Drush command above for one-off or scheduled bulk runs.

---

## Post-run usage refresh

For large backfills, regenerate entity usage so usage reports stay accurate.
