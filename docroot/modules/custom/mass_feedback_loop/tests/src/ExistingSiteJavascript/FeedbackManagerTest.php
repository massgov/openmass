<?php

namespace Drupal\Tests\mass_feedback_loop\ExistingSiteJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\user\Entity\User;
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

    // Skip test. This test can be used for local debugging of Feedback Manager.
    $this->markTestSkipped('Disabled by default.');

    /** @var \Drupal\Tests\DocumentElement */
    $this->page = $this->getSession()->getPage();

    // An admin is needed.
    $admin = $this->createUser();
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
   * Asserts textbox filtering works.
   */
  private function checkTextFilter($filter, $value) {
    $this->reset();
    $this->results->findField($filter)->setValue($value);
    $this->getSession()->wait(1000);
    $this->results->pressButton('Filter');
    $this->checkFilterHasResults();
  }

  /**
   * Asserts select filtering works.
   */
  private function checkSelectFilter($filter, $options) {
    $this->reset();
    $rand_key = array_rand($options);
    $this->results->findField($filter)->selectOption($options[$rand_key]);
    $this->getSession()->wait(1000);
    $this->results->pressButton('Filter');
    $this->checkFilterHasResults();
  }

  /**
   * Asserts select filtering works.
   */
  private function checkCheckboxFilter($filter) {
    $this->reset();
    $this->results->checkField($filter);
    $this->getSession()->wait(1000);
    $this->results->pressButton('Filter');
    $this->checkFilterHasResults();
  }

  /**
   * Asserts filter by param works.
   */
  private function checkFilterByParam($param, $value) {
    $this->drupalGet('admin/ma-dash/feedback', [
      'query' => [
        $param => $value,
      ],
    ]);
    $this->checkFilterHasResults();
  }

  /**
   * Tests for the "Feedback Manager" page at admin/ma-dash/feedback.
   */
  public function testFilters() {
    $yesterday = new DrupalDateTime('-1 days');
    $yesterday = $yesterday->format('m/d/Y');

    $today = new DrupalDateTime();
    $today = $today->format('m/d/Y');

    $this->checkTextFilter('Search feedback for specific text', 'help');
    $this->checkTextFilter('Search feedback for specific text', 'help, officer, health');
    $this->checkTextFilter('Start Date', $yesterday);
    $this->checkTextFilter('End Date', $today);
    $this->checkSelectFilter('Sort by', ['Date (Newest first)', 'Date (Oldest first)']);
    $this->checkSelectFilter('filter_by_info_found', ['true', 'false', 0]);
    $this->checkCheckboxFilter('Watched pages only');
    $this->checkCheckboxFilter('Show feedback flagged as low quality');

    // Check filter using URL param in cases where filters are autocomplete.
    $this->checkFilterByParam('org_id[0]', 31866);
    $this->checkFilterByParam('author_id[0]', 131);
    $this->checkFilterByParam('node_id[0]', 11931);
    $this->checkFilterByParam('label_id[0]', 90651);
  }

}
