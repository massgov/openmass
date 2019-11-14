Backstop Visual Regression Testing
==================================

Visual regression tests are run manually before and after release.  Before release, we capture reference screenshots from production, then compare those screenshots to the staging environment.

Following release, we smoke test by checking the same production URLS to ensure they look the same. 

> **Note:**  This test will not catch any functional test steps or Javascript changes. The following items would not be tested by Backstop: dropdown, accordion, table of contents, or menu within a page. These need to be manually checked to see if any visual regession has happened to these items. (e.g. organization subnav dropdown, moble view of the menu, info details, table of contents, or contact accordion.)
>
> We are expecting to see changes in some instances.  Pages may change from the "reference" screenshots based on either code level changes or content level changes.  It's up to you to use your judgment on whether these changes are expected/acceptable or not.

#### Before the first time you use this tool:
1. Make sure the `LOWER_ENVIR_AUTH_USER` and `LOWER_ENVIR_AUTH_PASS` environment variables are set up in your `.env` file.

#### Target and list flags

```
--target=local  # Choose an enviornment to target(feature#, test, or prod)
                # `local` is default
--list=page	    # Choose a json page to run with backstop (page or all)
                # `page` is default and will run 36 scenarios 
                # `all` will run 54 scenarios 
```

#### Capturing reference screenshots
Before doing any testing, you will need to capture the "reference" screenshots, or the screenshots you want to use as the baseline for comparison.  These reference screenshots will usually come from the production environment.
```bash
# Captures reference screenshots to backstop/reference
ahoy backstop reference --target=prod
```

#### Comparing changes to the reference screenshot
Running a "test" will capture screenshots from a given environment and run a comparison with the references.
```bash
# Capture test screenshots for mass.local to
# backstop/runs/DATE and run comparisons.
ahoy backstop test --target=local
# Open the report for viewing:
open backstop/report/index.html
```

### Release Workflow

#### Before Production Release
Once you've released to staging, compare production and staging by running the following commands from your local machine:
1. `ahoy backstop reference --target=prod --list=all`
2. `ahoy backstop test --target=test --list=all`
3. `open backstop/report/index.html`

#### After Release (smoke testing)
Compare production to the reference screenshots by running the following commands from your local machine:
1. `ahoy backstop test --target=prod --list=all`
2. `open backstop/report/index.html`

## Modifying Tests

You can change the pages that are captured by editing the `pages.json` file in this directory.  We _do not_ want to test every page on the site, as this type of testing is very slow, but it is good to have one or two "representative samples" of all the different page variations on the site.

If you need other "representative samples" of content types, please use the `all.json` file. By `Copy + Paste` all or some of the lines into the `pages.json`. The `all.json` file includes a few more of the QAG pages that were created for testing purposes. 