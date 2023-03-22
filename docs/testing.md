# Testing

## Introduction

Mass.gov has a robust test suite that helps us maintain high uptime, deliver high quality, and develop our codebase with confidence and velocity.

Our test suite is run automatically on all PRs via our continuous integration provider, [CircleCI](https://circleci.com/gh/massgov/openmass). PRs must return green to be accepted. This ensures that known regressions stay out of the codebase.

In addition, [BackstopJS](https://github.com/massgov/openmass/blob/develop/backstop/README.md) (visual regression testing) and [Nightcrawler](https://github.com/massgov/openmass/blob/develop/.circleci/nightcrawler/README.md) (5xx error testing) are run a nightly on the CD environment on the latest version of develop (see more below).

Any PR that is created in the Mass repository can be tested locally before being being pushed up to GitHub. Branches without an associated PR will fail the Danger step at CircleCI.

## Tests run on every PR

The following tests are included in the CircleCI workflow automatically each time a PR is pushed to the GitHub repository.

To run these tests locally:

- Run all [Behat](http://behat.org) tests: `ahoy exec behat`

  To run a single Behat test:

  1. Add a tag a single behat feature file that you want to test. e.g. `@cool`
  1. Run `docker-compose exec drupal vendor/bin/behat --tags=@cool`
  1. Locally, debug files are saved to `/tmp` (they start with `behat*`). These same files are uploaded as Artifacts in CircleCI.

- Run all [PHPUnit](https://phpunit.de/) tests: `ahoy exec phpunit docroot/modules/custom`

- Run all tests (Behat & PHPUnit): `ahoy exec scripts/ma-test`

Pass the --help option to learn the arguments and options for each tool.

## Tests run nightly on develop

Nightcrawler and Backstop are run on the develop branch at 10 p.m. EST each night. If your branch is merged into develop by then, it will be tested as part of that night's run.

To run these tests locally:

### Nightcrawler

```
ahoy nightcrawler crawl --samples=50

### Sample size should be between 40-60 for a branch
```

More information about setup or running Nightcrawler can be found in the [Nightcrawler documentation](https://github.com/massgov/openmass/blob/develop/.circleci/nightcrawler/README.md).


### Backstop

More information about setup or running Backstop can be found in the
[Backstop documentation](https://github.com/massgov/openmass/blob/develop/backstop/README.md).

## Good tests

Good tests take the point of view of the end user. We want to test that _their_ expectations are met. We encourage developers to write tests that click on links and buttons, just like end users do.

Good tests focus on high-value activities. We want to test that major functionality keeps on working for years to come. For example, it's important to test content authoring for our major content types. Its important to test our workflow system and cache invalidation system.

### Creating new tests

- Behat test (Found in the [/features](https://github.com/massgov/openmass/tree/develop/features) directory)
- PHPUnit (Found in most of the custom modules under `/test/src/ExistingSites`)

### Updating tests

- Behat test (Found in the [/features](https://github.com/massgov/openmass/tree/develop/features) directory)
- PHPUnit (Found in most of the custom modules under `/test/src/ExistingSites`)
- Backstop (Change the page URLs if needed)

**Please note that anything that is added or updated in the Behat tests or PHPUnit will be run in the CircleCI testing section**

## Test types

### Behavioral (aka behat)

We have [a large suite](https://github.com/massgov/openmass/tree/develop/features) of Behavioral tests. These tests are written in Gherkin. The Gherkin steps map to step definitions that we or others have written. Read our test suite to learn how to author these tests. These tests live in the [/features](https://github.com/massgov/openmass/tree/develop/features) directory.

### Functional (aka PHPUnit+ExistingSiteTestCase)

These are a 2018 addition to our test capabilities. Here you have a fully bootstrapped Drupal and Mink at your disposal. We extend [Drupal Test Traits](https://github.com/weitzman/drupal-test-traits), an open source project which we founded for this project. [See examples](https://github.com/massgov/openmass/tree/develop/docroot/modules/custom/mass_utility/tests/src/ExistingSite). Read our test suite to learn how to author these tests. These tests live inside modules. A good example is [MediaDeleteTest](https://github.com/massgov/openmass/blob/develop/docroot/modules/custom/mass_media/tests/src/ExistingSite/MediaDeleteTest.php). Run `ddev service enable selenium-chromedriver` to start the Selenium with Chrome Docker container, which is required for these tests. Browse to http://mass.local:7910 (password: `secret`) to access the browser over VNC. As well, a regular VNC client can connect on port 5900 using the same password.

### Performance

To prevent database issues we've seen in the past, we MUST test any changes to queries that see significant traffic. This includes ANY CHANGES to the All Content or My Content view. (Needs Review and Trash changes should also be checked, but they see less traffic). In addition, changes that affect the snooze feature or autocomplete (Linkit module) should be scrutinized for any performance impact.

To test these changes we need to compare the performance of the query on production to the performance of the new query that will be introduced. [Performance](https://github.com/massgov/openmass/blob/develop/docs/performance.md) offers documentation on how to compare query times to see if a change will introduce a problem for our content authors.

### Picking a test type

The ideal case is that Product Owners and developers jointly describe new features in Gherkin. When this happens, a Behavioral test makes most sense. Otherwise, a Functional test is preferred as it hits the sweet spot between ease of development and value to the organization. Also, tests that depend on lots of related content are more easily authored as Functional.

## Tips

- Robust tests create and delete their own content. Relying on production content is brittle.
- Do not break up your tests in to too many classes and methods. Each one has a startup cost and contributes to long test times for the whole suite.
- Do not change a test that is failing for an unknown reason. Tests are supposed to fail when new work has a negative impact. Don't change a test until you have investigated and understood what purpose the test serves and are confident your new work and any test changes are an improvement.
