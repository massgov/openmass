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
| `--simulate` | Dry run: **no** database writes and **no** queue writes. Same idea as global `ddev drush --simulate ...`. |
| `--limit=N` | Max eligible entities to process **total** across node + paragraph. Command stops when it reaches `N`. **`0` = no limit. |
| `--bundle=...` | Only that bundle (node type or paragraph type machine name). With `--entity-ids`, IDs are filtered with a single query (no full entity load on the fast enqueue path). |
| `--entity-ids=1,2,3` | Only these IDs. IDs are checked in both node and paragraph entities. Ignores `--limit`. |
| `--kinds=text,link,entity_reference` | **Simulate:** include only these change kinds in table, CSV, and counters. **Execute (enqueue):** only entities with at least one matching kind of change are enqueued; each entity is dry-run first (slower). |
| `--csv-path=./redirect-normalizer-report.csv` | Write a full CSV report. **Simulate:** diffs for scanned entities. **Execute:** same diff columns for entities you enqueue; each entity is dry-run first to build the report (slower). For fastest bulk enqueue, omit both `--kinds` and `--csv-path`. If the file already exists and is non-empty, **new rows are appended** (the header line is not repeated). |
| `--entity-type=node|paragraph|all` | Restrict scans to one entity type, or both (`all`/default). |
| `--start-id=N` | Start scanning from this ID (inclusive). Useful for manual resume windows. |
| `--resume` | Continue simulate runs from saved progress checkpoint. Execute mode auto-resumes by default. |
| `--show-progress` | Print saved progress checkpoint and exit without scanning. |
| `--reset-progress` | Clear saved progress checkpoint before run. |
| `--release-enqueue-lock` | Delete the enqueue sweep lock row from `{semaphore}` and exit (no sweep). Use after a **crash or segfault** when PHP never released the lock—`lock->release()` only clears locks owned by the current request. |

By default, bulk command processes only **published** content.

Execute mode **acquires** a sweep-wide lock so two `mnrl` runs do not enqueue in parallel. If a run dies without releasing (segfault, kill), the next execute may warn that another sweep is running; run `drush mnrl --release-enqueue-lock` once, then rerun.

**Enqueue sweep performance:** plain execute (`mnrl` without `--simulate`,
`--kinds`, or `--csv-path`) only runs entity ID queries and pushes each ID onto
the queue—**no entity loads and no eligibility checks in Drush**. The queue
worker loads each entity, applies the same eligibility rules as simulate mode,
then normalizes. Bulk node lists add a **published (`status = 1`)** filter in
the ID query (not when using `--entity-ids`). With `--kinds` or `--csv-path`,
execute mode dry-runs each entity first (slower) to filter or build the report.
Bulk enqueue writes checkpoints and dedupe keys periodically (same batch stride as load chunks).

- Nodes must be published (enforced in the worker; bulk ID queries for nodes are
  already limited to published where applicable).
- Paragraphs are processed only when their parent node is published (worker).
- If a published node has a newer unpublished draft revision, that node and its
  child paragraphs are skipped when the worker runs (so we do not touch draft work).

### Default table columns

| Column | Notes |
|--------|--------|
| Status | `would_update` in simulate mode. |
| Entity type | `node` or `paragraph`. |
| Entity ID | Entity id. |
| Parent node ID | For **paragraphs**, the host node id from `Helper::getParentNode()`. For nodes, `-`. |
| Bundle | Bundle / type machine name. |
| URL before / URL after | This is just the changed value, not full HTML. For link fields, it shows stored path/URL. For text fields, it shows only changed `href` values. For entity references, it shows `node:123` or `media:456`. If many links changed in one field, they are joined with `; `. If too long, CLI shortens it. |

### CSV reporting

- CSV includes the same rows as simulate table output (including `--kinds` filtering).
- CSV `before` / `after` values are full values (not table-truncated).
- Header columns:
  - `status,entity_type,entity_id,parent_node_id,bundle,field,delta,kind,before,after,details`

Examples:

```bash
# Full simulation report in current directory.
ddev drush mnrl --simulate --limit=20000 --csv-path=./redirect-normalizer-report.csv

# Only entity-reference changes, current directory CSV.
ddev drush mnrl --simulate --kinds=entity_reference --csv-path=./entity-reference-only-report.csv

# Enqueue selected IDs for queue workers.
ddev drush mnrl --entity-ids=857211,858626
```

### What gets skipped (simulate vs queue worker)

- **Simulate mode** applies eligibility before showing rows: orphan paragraphs,
  unpublished content, and newer-draft cases are omitted from output.
- **Fast enqueue** does not evaluate those rules in Drush; the **queue worker**
  skips the same ineligible entities when it runs. You may still see queue items
  for IDs that become no-ops in the worker (cheap claim/delete).
- Entities with **no** redirect-based links to fix produce **no** simulate rows (empty table is normal).

### Simulate, enqueue, then verify (manual QA)

1. **Preview:**
   `ddev drush mass-redirect-normalizer:normalize-links --simulate --limit=100 --csv-path=./redirect-normalizer-preview.csv`
2. **Enqueue:**
   `ddev drush mass-redirect-normalizer:normalize-links --limit=100`
3. **Process queue:**
   `ddev drush queue:run mass_redirect_normalizer_link_normalization`
4. **Re-check:** run **simulate** again with the same filters. Items that were fixed should **not** show `would_update` anymore (unless something else changed them back).

For big runs, the command prints a progress notice every 500 entities. When it
can estimate the sweep size (same filters as the scan), the line includes
**scanned N of total (pct%) in this run**. With `--limit`, total equals that
limit. **Continuing from saved checkpoint** appears only when a prior run did not
finish cleanly (`completed` was not saved); it does not appear after a completed
sweep or on a truly empty checkpoint.

For a narrow retest after you know specific IDs:

`ddev drush mass-redirect-normalizer:normalize-links --simulate --entity-ids=123,456`

### Long-run recovery and resume

For long/background runs (for example Acquia SSH), execute mode continues from
the saved checkpoint automatically. If another enqueue sweep is already active,
the command exits without starting a second sweep.

```bash
# 1) Enqueue a long paragraph sweep.
ddev drush mnrl --entity-type=paragraph --limit=50000

# 2) Process queued work.
ddev drush queue:run mass_redirect_normalizer_link_normalization

# 3) Continue enqueue from the saved checkpoint.
ddev drush mnrl --entity-type=paragraph --limit=50000

# 4) If you need a clean restart, clear checkpoint then run.
ddev drush mnrl --reset-progress --entity-type=paragraph --start-id=1
```

Notes:
- Checkpoint stores last processed ID per entity type (`node` / `paragraph`),
  processed count, enqueued count, changed field values (simulate only), mode, and
  timestamp.
- When `--resume` and `--start-id` are both set, the command uses the higher
  effective start position.

### Important detail about saved content

On **first save**, `hook_entity_presave()` enqueues normalization work. The stored field values may still contain redirect source paths until the queue worker runs. The automated tests handle that case where needed.

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
  - Simulate vs enqueue output:
    - `--simulate` rows show `would_update`.
    - Execute mode enqueues eligible entities and does not save inline.

---

## Periodic / bulk cleanup

Use the Drush command to enqueue work, then drain the queue with `queue:run` for one-off or scheduled bulk runs.

---

## Post-run usage refresh

For large backfills, regenerate entity usage so usage reports stay accurate.
