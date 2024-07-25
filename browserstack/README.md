# BrowserStack Visual Regression Testing

Visual regression tests are run manually before and after release. Before
release, we capture reference screenshots from production, then compare those
screenshots to the staging environment.

Following release, we smoke test by checking the same production URLS to ensure
they look the same.

## About

Visual regression tests are run with a suite of tools offered and supported by BrowserStack. Automation in CircleCI runs `npm run snapshots-test`, which runs a single JestJS test through `browserstack-node-sdk` that takes all Percy snapshots. The test initiates a build in Browser Automate. Once the Browser Automate build is complete, visual regression snapshots are sent to Percy. Build success is reported in CircleCI and a link to Percy visual regression test results can be viewed under the checks on a given pull request. If the test results in false positives, they can be reviewed and approved in Percy, which will mark the Percy check as a success in GitHub.

Below is a description of each of the components used in openmass's visual regression testing.

### Percy

Percy is a visual regression tool offered by BrowserStack, and is the primary use case for using BrowserStack's automation features. The features of Percy are significantly expanded when Percy is used in conjunction with BrowserStack Automate via the BrowserStack SDK. Features like Intelli Ignore are only available when used with BrowserStack Automate, which allows Percy to more intelligently ignore layout shifts and other visual changes that would otherwise trigger a false positive result.

### BrowserStack SDK

BrowserStack SDK is the primary entrypoint into BrowserStack's suite of tools. Most of the set up needed to run and configure Percy, BrowserStack Automate, and the Selenium WebDriver can be configured in a browserstack.yml file. To see the full list of capabilities available for browserstack.yml, see the Capabilities Generator: https://www.browserstack.com/docs/automate/capabilities. For openmass, the defaults of Selenium and the BrowserStack SDK should be selected. Also see the [browserstack.yml](browserstack.yml) file.

### BrowerStack Automate

BrowserStack Automate is a cross-browser testing tool offered by BrowserStack. It is built on Selenium Grid, which allows for the execution of Selenium WebDriver on multiple devices. This functionality enables tests to be run on real world devices and browsers rather than relying on headless browser user agents.

BrowserStack Automate supports many test suites for various purposes with different levels of support depending on the combination of features you want to use.

### Selenium WebDriver

Selenium WebDriver is used to load and manipulate browser actions in BrowserStack Automate. Selenium WebDriver supports many shared browser capabilities along with browser-specific capabilities. The WebDriver is called in tests to accept configurations from the BrowserStack SDK and other capabilities defined in the test in order to operate browsers in BrowserStack Automate.

### JestJS

JestJS is the test suite used for looping through test pages and taking Percy snapshots. Snapshots are taken by running a single JestJS test through the `browserstack-node-sdk` command that sets up the Selenium WebDriver driver, adds additional browser and SDK settings, and loads all test pages to manually takes a Percy snapshot.
