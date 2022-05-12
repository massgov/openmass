<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Behat\Mink\Session;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\DrupalTestTraits\ScreenShotTrait;

/**
 * Test Expandable/Collapsible elements.
 */
class ExpandCollapseElementsTest extends ExistingSiteSelenium2DriverTestBase {

  use ScreenShotTrait;

  /**
   * Tests a single accordion for a given page, located at a CSS selector.
   *
   * @param string $path
   *   The path of the page to load.
   * @param string $css_selector
   *   The CSS selector to find the accordion at.
   * @param \Closure|null $before_function
   *   An optional function to call to set up any preconditions on the page,
   *   such as opening a parent accordion or menu. This closure takes a single
   *   Session parameter.
   *
   * @dataProvider accordionDataProvider
   */
  public function testAccordion(string $path, string $css_selector, \Closure $before_function = NULL): void {
    $session = $this->getSession();
    $this->drupalGet($path);
    if ($before_function) {
      // We can't use $this->getSession() in the closure because PHPUnit creates
      // a new object in between gathering data provider test cases and running
      // the test.
      $before_function($session);
    }

    $page = $session->getPage();
    $accordion = $page->find('css', $css_selector);

    // Get the accordion state, then click on it.
    $initial_state_open = $accordion->hasClass('is-open');
    $accordion_link = $accordion->find('css', '.js-accordion-link');
    $accordion_link->click();

    // Wait up to 30 seconds for the accordion to open. Unfortunately, there's
    // no unique identifier to pass into JS beyond the XPath, but luckily
    // all browsers except IE11 support it, even if it's in maintenance mode
    // only.
    // This is significantly faster than the below API call.
    // @codingStandardsIgnoreLine
    // $this->assertSession()->waitForElementVisible('xpath', $accordion->getXpath());
    $accordion_xpath = str_replace('"', '\"', $accordion->getXpath());
    if ($initial_state_open) {
      $wait_for = sprintf('document.evaluate("%s", document, null, XPathResult.ANY_TYPE, null ).iterateNext().classList.contains("is-open") == false', $accordion_xpath);
    }
    else {
      $wait_for = sprintf('document.evaluate("%s", document, null, XPathResult.ANY_TYPE, null ).iterateNext().classList.contains("is-open")', $accordion_xpath);
    }
    if (!$session->wait(30000, $wait_for)) {
      $this->fail('The accordion never toggled for testing.');
    }

    // Check the accordion collapsed/expanded state was toggled.
    $this->assertNotSame($initial_state_open, $accordion->hasClass('is-open'));

    // Revert it back to the original state.
    $accordion_link->click();
    if ($initial_state_open) {
      $wait_for = sprintf('document.evaluate("%s", document, null, XPathResult.ANY_TYPE, null ).iterateNext().classList.contains("is-open")', $accordion_xpath);
    }
    else {
      $wait_for = sprintf('document.evaluate("%s", document, null, XPathResult.ANY_TYPE, null ).iterateNext().classList.contains("is-open") == false', $accordion_xpath);
    }
    $this->assertSame($initial_state_open, $accordion->hasClass('is-open'));
    if (!$session->wait(30000, $wait_for)) {
      $this->fail('The accordion was never restored to the original state.');
    }
  }

  /**
   * Data provider of accordion test cases.
   *
   * @return array
   *   An array of test cases, indexed by a human-friendly name, containing:
   *     - The path of the page to test accordions on.
   *     - A CSS selector to locate the accordion with.
   *     - An optional function to call before running the test case, accepting
   *       a Session parameter.
   */
  public function accordionDataProvider(): array {
    return [
      '_QAG Binder_Report Table of Contents' => [
        'report/qag-binderreport',
        '.ma__toc--hierarchy__accordion.js-accordion',
      ],
      '_QAG Binder_Report Contact Us' => [
        'report/qag-binderreport',
        '.ma__contact-us.js-accordion',
      ],
      '_QAG Request Help with a Computer Problem Accordion in Table of Contents' => [
        'how-to/qag-request-help-with-a-computer-problem',
        '.ma__toc--hierarchy__accordion.js-accordion',
        function (Session $session): void {
          // Open up the Table of Contents containing the accordion.
          $page = $session->getPage();
          // @codingStandardsIgnoreLine
          /** @noinspection NullPointerExceptionInspection */
          $page->find('css', '.ma__toc__toc__toggle')->click();
          if (!$session->wait(30000, "jQuery('.ma__toc--overlay__container.is-open').is(':visible')")) {
            $this->fail('The Table of Contents overlay did not become visible');
          }
          if (!$session->wait(30000, "jQuery('.js-accordion-link').is(':visible')")) {
            $this->fail('The accordion link did not become visible');
          }
          $this->captureScreenshot();
        },
      ],
      '_QAG Request Help with a Computer Problem Notices and Alerts' => [
        'how-to/qag-request-help-with-a-computer-problem',
        '.ma__header-alerts.js-accordion',
      ],
      '_QAG Request Help with a Computer Problem Inside Alert' => [
        'how-to/qag-request-help-with-a-computer-problem',
        '.ma__action-step.js-accordion.ma__action-step--accordion:nth-child(1)',
      ],
      '_QAG Request Help with a Computer Problem Help by Phone' => [
        'how-to/qag-request-help-with-a-computer-problem',
        '.ma__action-step.js-accordion.ma__action-step--accordion:nth-child(2)',
      ],
      '_QAG Request Help with a Computer Problem Help Online' => [
        'how-to/qag-request-help-with-a-computer-problem',
        '.ma__action-step.js-accordion.ma__action-step--accordion:nth-child(3)',
      ],
      '_QAG Request Help with a Contact in Body' => [
        'how-to/qag-request-help-with-a-computer-problem',
        '.ma__contact-us.ma__contact-us--accordion.js-accordion',
      ],
      // Note this block is actually duplicated in the page-content div for
      // narrow viewports. Since the code inside is the same, and we haven't
      // seen regressions with it, we don't bother changing the viewport and
      // testing it as well.
      // @todo Disabled pending https://massgov.atlassian.net/browse/DP-24885.
      // @codingStandardsIgnoreStart
//      '_QAG Request Help with a Sidebar Contact' => [
//        'how-to/qag-request-help-with-a-computer-problem',
//        '.ma__contact-us.js-accordion',
//      ],
      // @codingStandardsIgnoreStop
    ];
  }

}
