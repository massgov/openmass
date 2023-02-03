Backstop Visual Regression Testing
==================================

Visual regression tests are run manually before and after release.  Before release, we capture reference screenshots from production, then compare those screenshots to the staging environment.

Following release, we smoke test by checking the same production URLS to ensure they look the same.

> **Note:**  This test will not catch any functional test steps or Javascript changes. The following items would not be tested by Backstop: dropdown, accordion, table of contents, or menu within a page. These need to be manually checked to see if any visual regession has happened to these items. (e.g. organization subnav dropdown, moble view of the menu, info details, table of contents, or contact accordion.)
>
> We are expecting to see changes in some instances.  Pages may change from the "reference" screenshots based on either code level changes or content level changes.  It's up to you to use your judgment on whether these changes are expected/acceptable or not.

#### Before the first time you use this tool:
1. If testing an Acquia environment, make sure the `LOWER_ENVIR_AUTH_USER` and `LOWER_ENVIR_AUTH_PASS` environment variables are set up in your `.env` file.

#### Target and list flags

```
--target=local  # Choose an enviornment to target(feature#, test, or prod)
                # `local` is default
--list=all	    # Choose a json page to run with backstop (all or post-release)
                # `all` is default
                # `post-release` runs fewer scenarios, and is run automatically after a release
```

#### Capturing reference screenshots
Before doing any testing, you will need to capture the "reference" screenshots, or the screenshots you want to use as the baseline for comparison.  These reference screenshots will usually come from the production environment.
```bash
# Captures reference screenshots to backstop/reference
ahoy backstop reference -- --target=prod
```

#### Comparing changes to the reference screenshot
Running a "test" will capture screenshots from a given environment and run a comparison with the references.
```bash
# Capture test screenshots for mass.local to
# backstop/runs/DATE and run comparisons.
ahoy backstop test -- --target=local
# Or use a Tugboat URL as target
ahoy backstop test -- --target=tugboat --tugboat=https://pr1109-3njbhyjchfr06gmuwiojlcdcxrimpaox.tugboat.qa/
# Open the report for viewing:
open backstop/report/index.html
```

## Modifying Tests

If you need test "representative samples" of content types, please use the `all.json`, which includes QAG pages that were created for testing purposes.
