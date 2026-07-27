# Mass Entity Usage

Customizations for the [Entity Usage](https://www.drupal.org/project/entity_usage) module: usage UI, unpublish warnings, and a Drush workflow to rebuild usage statistics at scale.

## Rebuild usage statistics

Rebuilding is a **two-step** process:

1. **Enqueue** — `mass-content:usage-regenerate` walks tracked entity types (typically `node` and `paragraph`) and adds items to the `entity_usage_tracker` queue.
2. **Process** — `queue:run entity_usage_tracker` rebuilds rows in the `entity_usage` table.

```bash
ddev drush mass-content:usage-regenerate
ddev drush queue:run entity_usage_tracker
```

| Item | Value |
|------|--------|
| Command | `mass-content:usage-regenerate` |
| Aliases | `maur`, `mass-usage-regenerate` |

### Default behavior (no flags)

The command is **non-interactive** unless you pass `--reset`. It evaluates conditions in this order:

| Priority | Situation | Behavior |
|----------|-----------|----------|
| 1 | Interrupted enqueue (unfinished progress in state) | **Resume** — no prompt; completed entity types are skipped |
| 2 | Enqueue finished within the last **24 hours** | **Exit** — prints completion time and suggests `queue:run` or `--reset` |
| 3 | No interrupted progress and 24h flag expired (or first run) | **Start new enqueue** — no prompt |

On resume, existing queue items and uniqueness records are kept. Progress continues from the last saved entity ID per type.

On a new enqueue (case 3), per-entity-type usage records are wiped as each type is processed, then entities are re-queued from ID 0.

### `--reset` (manual full rebuild)

Use when something unexpected happened and you need to discard all progress and start over.

`--reset` clears saved progress, empties the `entity_usage_tracker` queue, clears uniqueness records, and assigns a new run ID so nothing resumes from a prior run. **Confirmation is required** unless you pass `--force` or global `-y` / `--yes`.

```bash
# Resume after Ctrl+C (default)
ddev drush mass-content:usage-regenerate

# Re-enqueue completed within 24h — exits with instructions
ddev drush mass-content:usage-regenerate

# Force a full rebuild (prompts for confirmation)
ddev drush mass-content:usage-regenerate --reset

# Force a full rebuild, non-interactive
ddev drush mass-content:usage-regenerate --reset --force
```

### Options

| Option | Description |
|--------|-------------|
| `--reset` | Clear progress and queue; start from entity ID 0 (with confirmation) |
| `--force` | Skip the `--reset` confirmation prompt |
| `-y` / `--yes` | Same as `--force` for the reset confirmation |
| `--batch-size=N` | Entities per batch step (default `1000`) |

### Operator notes

- **Ctrl+C during enqueue** — rerun `ddev drush mass-content:usage-regenerate` to resume; do not use `--reset`.
- **Enqueue finished** — run `ddev drush queue:run entity_usage_tracker` to rebuild the `entity_usage` table. Re-running `usage-regenerate` within 24 hours will not re-enqueue.
- **Start over** — use `--reset` (or `--reset --force` in CI/cron).

## Tests

```bash
# Unit tests (batch manager logic)
ddev exec ./vendor/bin/phpunit docroot/modules/custom/mass_entity_usage/tests/src/Unit/

# Existing-site tests (state, queue, completion guard)
ddev exec ./vendor/bin/phpunit docroot/modules/custom/mass_entity_usage/tests/src/ExistingSite/UsageRegenerateEnqueueTest.php
```
