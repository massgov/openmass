# mass_inline_message tests

## ExistingSite (PHP, no browser) — run by default

Fast tests (~seconds). Covers normalization, filter output, validation, preview, and `message_box_body` format.

```bash
ddev exec vendor/bin/phpunit docroot/modules/custom/mass_inline_message/tests/src/ExistingSite
```

## ExistingSiteJavascript (Selenium) — optional smoke tests

Four browser smoke tests only (LP tests use the LP builder UI to create Service Section + Rich text). Deeper behavior is covered by ExistingSite tests.

```bash
ddev exec vendor/bin/phpunit docroot/modules/custom/mass_inline_message/tests/src/ExistingSiteJavascript
```

## Full module path

Runs both suites (slow because of Selenium):

```bash
ddev exec vendor/bin/phpunit docroot/modules/custom/mass_inline_message/tests
```

Shared helpers live in `src/Traits/`.
