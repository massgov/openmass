<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Behat\Mink\Element\NodeElement;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Test Expandible/Collapsible elements.
 */
class ExpandCollapseElementsTest extends ExistingSiteWebDriverTestBase {

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
    $accordion_link = $accordion->find('css', '.js-accordion-link');

    // Get the accordion state, then click on it.
    $initial_state = $accordion->hasClass('is-open');
    $accordion_link->click();
    $session->wait(1000);

    // Check the accordion collased/expanded state was toggled.
    $this->assertTrue($initial_state != $accordion->hasClass('is-open'));
  }

  /**
   * Tests accordion elements on a node given a $nid.
   */
  private function testAccordionsAtNode(int $nid) {
    $session = $this->getSession();
    $this->drupalGet('node/' . $nid);
    $page = $session->getPage();
    $accordion_links = $page->findAll('css', '.js-accordion');
    foreach ($accordion_links as $accordion_link) {
      $this->testAccordion($accordion_link);
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
