# Redirect Link Normalizer

This module rewrites internal links that currently rely on redirects so they
point to the final destination path directly. For rich text links, the process
also adds `data-entity-*` attributes when the final target resolves to a node.

## Manual execution

- Dry run:
  - `ddev drush --simulate mass-redirect-normalizer:normalize-links --limit=500`
- Execute:
  - `ddev drush mass-redirect-normalizer:normalize-links --limit=5000`
- Optional filters:
  - `--entity-type=node|paragraph|all`
  - `--bundle=<bundle_machine_name>`
  - `--show-unchanged`

## Periodic execution

For one-time/periodic bulk cleanup, run the Drush command above.

For ongoing maintenance, this module also normalizes links during entity save
via `hook_entity_presave()` for nodes and paragraphs. This means new edits
automatically store final target paths instead of redirecting paths.

## Post-run usage refresh

For large backfills, regenerate entity usage to refresh orphan reports:

- `ddev drush mass-content:usage-regenerate --batch-size=1000`
