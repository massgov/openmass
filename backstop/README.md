Backstop Visual Regression Testing
==================================

Visual regression tests are run manually before and after release. Before
release, we capture reference screenshots from production, then compare those
screenshots to the staging environment.

Following release, we smoke test by checking the same production URLS to ensure
they look the same.

> **Note:**  This test will not catch any functional test steps or Javascript
> changes. The following items would not be tested by Backstop: dropdown,
> accordion, table of contents, or menu within a page. These need to be manually
> checked to see if any visual regression has happened to these items. (e.g.
> organization subnav dropdown, mobile view of the menu, info details, table of
> contents, or contact accordion.)
>
> We are expecting to see changes in some instances. Pages may change from the
> "reference" screenshots based on either code level changes or content level
> changes. It's up to you to use your judgment on whether these changes are
> expected/acceptable or not.

## Options

```
--target=local   # Choose an enviornment to target(feature#, test, or prod)
                 # `local` is default
--list=page	     # Choose a json page to run with backstop (page or all)
                 # `page` is default and will run 36 scenarios
                 # `all` will run 54 scenarios
                 # post-relase
--viewport=all   # Options are desktop, tablet, phone, or all (default)
```

- `pages.json` is the list pages that are tested when Backstop is run. This is
  the default option. There are currently 39 pages, which is about as many pages
  as we want the default option to include. If there are too many pages
  included, the risk of failed test runs increases.
- `all.json` has additional pages to test a larger number of pages -- for
  example, additional displays for some content types. There are currently 57
  total (this includes the default pages). To use this option, add `--list=all`

## Usage

> 🛑 If you're using an M1 Mac, the ddev commands will not work. You can try the
> local circleci runner as described below.

- If testing an Acquia environment, make sure the `LOWER_ENVIR_AUTH_USER` and
`LOWER_ENVIR_AUTH_PASS` environment variables are set up in your `.env` file.
- Enable the Backstop docker image for local running.
  `ddev service enable backstop`
- Before doing any testing, you will need to capture the "reference" screenshots,
  or the screenshots you want to use as the baseline for comparison. These
  reference screenshots will usually come from the production environment. Take
  screen captures of production pages with
  `ddev backstop reference --target=prod --list=all`
  Reference screenshots can also be captued from a Tugboat environment with
  `ddev backstop test -- --target=tugboat --viewport=desktop --tugboat=https://pr1109-3njbhyjchfr06gmuwiojlcdcxrimpaox.tugboat.qa/`
- Take screen captures of local pages to compare
  `ddev backstop test --target=local --list=all`
- Open the report from the comparison
  `open backstop/report/index.html`

## Modifying Tests

You can change the pages that are captured by editing the `pages.json` file in
this directory. We _do not_ want to test every page on the site, as this type of
testing is very slow, but it is good to have one or two "representative samples"
of all the different page variations on the site.

If you need other "representative samples" of content types, please use
the `all.json` file. By `Copy + Paste` all or some of the lines into
the `pages.json`. The `all.json` file includes a few more of the QAG pages that
were created for testing purposes.

## Runing Backstop in CircleCI

`drush ma:backstop` will run Backstop in the CircleCI infrastructure.
See [DeployCommands.php](../drush/Commands/DeployCommands.php).

You can also run the tests using CircleCi's [local CLI](https://circleci.com/docs/local-cli/).

> 🛑 Do not use the `snap` installer for Linux, use the alternative installation
> method.

After installation, you can run the backstop job in [`.circleci/config.yml`](../.circleci/config.yml)
with `circleci local execute backstop -e LOWER_ENVIR_AUTH_USER="<USER>" -e LOWER_ENVIR_AUTH_PASS="<PASSWORD>"`.

The `store_test_results` and `store_artifacts` steps are not supported, so you
may wish to comment those out temporarily.
