BrowserStack Visual Regression Testing
======================================

Visual regression tests are run manually before and after release. Before
release, we capture reference screenshots from production, then compare those
screenshots to the staging environment.

Following release, we smoke test by checking the same production URLS to ensure
they look the same.

## About

Visual regression tests are run with a suite of tools offered and supported by BrowserStack and described below:

# Percy

Percy is a visual regression tool offered by BrowserStack. The features of Percy are significantly expanded when used in conjunction with BrowserStack Automate. With BrowserStack Automate, Percy is able to more intelligently ignore layout shifts and other visual changes that would otherwise trigger a false positive result.

# BrowerStack Automate

BrowserStack Automate is a cross-browser testing tool offered by BrowserStack. It is built on Selenium Grid, which allows for the execution of Selenium WebDriver on multiple devices. This functionality enables tests to be run on real world devices and browsers rather than relying on headless browsers and user agent and resolution combinations.

BrowserStack Automate supports many test suites for various purposes

# Selenium WebDriver

# JestJS
