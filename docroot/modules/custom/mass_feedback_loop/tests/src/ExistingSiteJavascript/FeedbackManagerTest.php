<?php

namespace Drupal\Tests\mass_feedback_loop\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Exception;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests "Feedback Manager" page at admin/ma-dash/feedback.
 */
class FeedbackManagerTest extends ExistingSiteSelenium2DriverTestBase {

  use LoginTrait;

  /**
   * The element for the entire document.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $page;

  /**
   * The rendered results on the page..
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $results;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\Tests\DocumentElement */
    $this->page = $this->getSession()->getPage();

    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    // Visiting the Feedback Manager page.
    $this->drupalGet('admin/ma-dash/feedback');
    $this->results = $this->page->find('css', '#mass-feedback-loop-author-interface-form');
  }

  /**
   * Resets filters.
   */
  private function reset() {
    $this->results->hasButton('Reset') ? $this->results->pressButton('Reset') : NULL;
  }

  /**
   * Asserts that filter shows results.
   */
  private function checkFilterHasResults() {
    $table = $this->results->find('css', '#feedback-table');

    // No results scenario.
    $this->assertStringNotContainsString('No feedback available', $table->getText());

    $rows = $table->findAll('css', 'tbody > tr');
    $rowCount = count($rows);
    $this->assertGreaterThanOrEqual(2, $rowCount);
  }

  /**
   * Asserts a textbox filtering users works.
   */
  private function checkTextSearch($filter, $value) {
    $this->reset();
    $this->results->findField($filter)->setValue($value);
    $this->getSession()->wait(1000);
    $this->results->pressButton('Filter');
    $this->checkFilterHasResults();
  }

  /**
   * Tests a few things for the "Feedback Manager" page at admin/ma-dash/feedback.
   */
  public function testFilters() {
    $this->checkTextSearch('Search feedback for specific text', 'help');
    $this->checkTextSearch('Search feedback for specific text', 'help, officer, health');
  }

}
