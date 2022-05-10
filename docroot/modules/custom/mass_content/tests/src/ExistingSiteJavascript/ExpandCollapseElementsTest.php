<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Behat\Mink\Element\NodeElement;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Test Expandable/Collapsible elements.
 */
class ExpandCollapseElementsTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Loads a node based on its title.
   */
  private function getNidByTitle(string $title) {
    $eq_node = \Drupal::entityQuery('node');
    $res = $eq_node->condition('title', $title)->execute();
    return reset($res);
  }

  /**
   * Tests one accordion element.
   */
  private function testAccordion(NodeElement $accordion) {
    $session = $this->getSession();

    // Get the accordion state, then click on it.
    $initial_state = $accordion->hasClass('is-open');

    $accordion_link = $accordion->find('css', '.js-accordion-link');
    $accordion_link->click();

    // Wait up to 30 seconds for the accordion to open. Unfortunately, there's
    // no unique identifier to pass into JS beyond the XPath, but luckily
    // all browsers except IE11 support it, even if it's in maintenance mode
    // only.
    $wait_for_open = sprintf('document.evaluate("%s", document, null, XPathResult.ANY_TYPE, null ).iterateNext().classList.contains("is-open")', $accordion->getXpath());
    $session->wait(30000, $wait_for_open);

    // Check the accordion collapsed/expanded state was toggled.
    $this->assertTrue($initial_state != $accordion->hasClass('is-open'));
  }

  /**
   * Tests accordion elements on a node given a $nid.
   */
  private function testAccordionsAtNode(int $nid) {
    $session = $this->getSession();
    $this->drupalGet('node/' . $nid);
    // Wait for async notifications to be processed.
    $session->wait(2000);
    $page = $session->getPage();
    $accordion_links = $page->findAll('css', '.js-accordion');
    foreach ($accordion_links as $accordion_link) {
      // Symfony's CSS selector doesn't support :visible, so we use this to
      // filter out accordions inside hidden menus.
      if ($accordion_link->isVisible()) {
        $this->testAccordion($accordion_link);
      }
      elseif ($accordion_link->hasClass('ma__toc--hierarchy__accordion')) {
        // This is special handling for the accordion inside of the "This is a
        // part of" menu.
        $page->find('css', '.ma__toc__toc__toggle')->click();
        $session->wait(30000, "jQuery('.ma__toc--overlay__container.is-open').length === 1");
        $this->testAccordion($accordion_link);
        $page->findAll('css', '.ma__toc__toc__toggle')[1]->click();
      }
    }
  }

  /**
   * Tests accordions on 2 nodes.
   */
  public function testMultipleAccordions() {
    $this->testAccordionsAtNode($this->getNidByTitle('_QAG Binder_Report'));
    $this->testAccordionsAtNode($this->getNidByTitle('_QAG Request Help with a Computer Problem '));
  }

}
