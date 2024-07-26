# BrowserStack Visual Regression Testing

Visual regression tests are run with a suite of tools offered and supported by BrowserStack. The workflow is:

1. A CircleCI job runs `npm run snapshots-test`. This command runs a single JestJS test through `browserstack-node-sdk`, which initiates a build in Browser Automate and instructs BrowserStack to take visual regression snapshots in Percy.
2. Once the Browser Automate build is complete, visual regression snapshots are sent to Percy.
3. Build success or failure for BrowserStack Automate is reported in CircleCI.
4. Percy success or failure is reported in a Percy GitHub pull request check. False positives result in a failed check, but can be reviewed and approved in Percy, marking the check as a success.

Below is a description of each of the components used in openmass's visual regression testing.

### Percy

Percy is a visual regression tool offered by BrowserStack, and is openmass's primary use case for using BrowserStack's automation features. The features of Percy are significantly expanded when Percy is used in conjunction with BrowserStack Automate via the BrowserStack SDK. Features like [Intelli Ignore](https://www.browserstack.com/docs/percy/build-results/intelli-ignore), for example, are only available when used with BrowserStack Automate, which allows Percy to more intelligently discern image differences. With BrowserStack Automate, Percy is also able to more intelligently discern other visual changes that would trigger a false positive result.

### BrowserStack SDK

BrowserStack SDK is the primary entrypoint into BrowserStack's suite of tools. Most of the set up needed to run and configure Percy, BrowserStack Automate, and the Selenium WebDriver can be configured in a browserstack.yml file. To see the full list of capabilities available for browserstack.yml, see the Capabilities Generator: https://www.browserstack.com/docs/automate/capabilities. For openmass, the defaults of Selenium and the BrowserStack SDK should be selected. Also see the [browserstack.yml](browserstack.yml) file.

### BrowerStack Automate

BrowserStack Automate is a cross-browser testing tool offered by BrowserStack. It is built on Selenium Grid, which allows for the execution of Selenium WebDriver on multiple devices. This functionality enables tests to be run on real world devices and browsers rather than relying on headless browser user agents.

BrowserStack Automate supports many test suites for various purposes with different levels of support depending on the combination of features you want to use. Selenium WebDriver and JestJS were selected as the best combination of tools to fit the needs of openmass. When searching BrowserStack's documentation, check that you are looking at documentation for this combination.

### Selenium WebDriver

Selenium WebDriver is used to load and manipulate browser actions in BrowserStack Automate. Selenium WebDriver supports many shared browser capabilities along with browser-specific capabilities. The WebDriver is called in tests to accept configurations from the BrowserStack SDK and other capabilities defined in the test in order to operate browsers in BrowserStack Automate. Understanding Selenium WebDriver is important to know in cases where any capabilities need to be added or researched that would affect how requests and responses are manipulated in the browser, requiring modifications to either browserstack.yml or the defined driver in the JestJS test.

### JestJS

JestJS is the test suite used in this project for looping through test pages and taking Percy snapshots. It was selected from a [list of Percy on Automate-compatible frameworks using NodeJS](https://www.browserstack.com/docs/percy/integrate/functional-and-visual#compatibility-matrix). Snapshots are taken by running a single JestJS test through the `browserstack-node-sdk` command that sets up the Selenium WebDriver driver, adds additional browser and SDK settings, and loads all test pages to manually take Percy snapshots.
