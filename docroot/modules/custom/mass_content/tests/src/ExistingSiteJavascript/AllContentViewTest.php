<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Exception;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests "All Content" view at admin/content.
 */
class AllContentViewTest extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * The element for the entire document.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $page;

  /**
   * The All Content view.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $view;

  /**
   * Asserts a random row has a specific text value.
   */
  private function checkRandomRowHasValue($value) {
    $table = $this->view->find('css', '.views-view-table');
    $rows = $table->findAll('css', 'tbody > tr');
    $randomRow = $rows[\random_int(0, count($rows) - 1)];
    $text = $randomRow->getText();
    $this->assertStringContainsString($value, $text);
  }

  /**
   * Gets the index of a result column based on the description.
   */
  private function getColumnIndexFromResultsView($description) {
    $columns = $this->view->findAll('css', 'th');
    foreach ($columns as $index => $column) {
      if ($column->getText() == $description) {
        return $index + 1;
      }
    }
    throw new Exception("Column not found on results table: " . $description);
  }

  /**
   * Gets any username from a results' user column with a specific description.
   */
  private function getAnyUserFromResultsColumn($description) {
    $columnIndex = $this->getColumnIndexFromResultsView($description);
    $usernamesTds = $this->view->findAll('css', ".views-table > tbody td:nth-child($columnIndex)");
    $randomUsernameTd = $usernamesTds[\random_int(0, count($usernamesTds) - 1)];
    $randomUsername = trim($randomUsernameTd->getText());
    return $randomUsername;
  }

  /**
   * Asserts a textbox filtering users works.
   */
  private function checkTextboxFilteredByUserWorks($description, $descriptionTr = '') {
    $this->reset();
    $descriptionTr = $descriptionTr ?: $description;
    $username = $this->getAnyUserFromResultsColumn($descriptionTr);
    $this->view->findField($description)->setValue($username);
    $this->getSession()->wait(1000);
    $this->view->pressButton('Apply');
    $this->checkRandomRowHasValue($username);
  }

  /**
   * Asserts a textfilter filtering node property works.
   */
  private function checkTextboxFilteredByNodePropertyWorks($description) {
    $this->reset();

    // Gets random content.
    $node = $this->getRandomNode();

    // Maps value from content to the filter value.
    $mapping = [
      'Title' => 'mapTitle',
      'ID' => 'mapId',
    ];

    if (!isset($mapping[$description])) {
      throw new Exception("Mapping method for " . $description . " does not exists");
    }
    else {
      $value = $this->{$mapping[$description]}($node);
    }

    // Sets value for the filter.
    $this->view->findField($description)->setValue($value);

    // Submits the exposed form.
    $this->view->pressButton('Apply');

    // Checks results.
    $this->checkRandomRowHasValue($value);
  }

  /**
   * Mapper private function to get the label value of a node.
   *
   * @phpcs:disable
   */
  private function mapTitle($node) {

    return $node->label();
  }
  // @phpcs:enable

  /**
   * Mapper private function to get the Id value of a node.
   *
   * @phpcs:disable
   */
  private function mapId($node) {
    return $node->id();
  }
  // @phpcs:enable

  /**
   * Asserts an array of options exists on for a select filter.
   */
  private function checkSelectFilterOptions($description, $optionsToCheck) {
    // Get select.
    $selectElem = $this->view->findField($description);

    // Get options.
    $selectOptions = [];
    $optionsInPage = $selectElem->findAll('css', 'option');
    foreach ($optionsInPage as $option) {
      $selectOptions[] = $option->getText();
    }

    // Check the optionsToCheck are options in the select.
    $selectOptions = array_flip($selectOptions);
    foreach ($optionsToCheck as $option) {
      $this->assertArrayHasKey($option, $selectOptions);
    }
  }

  /**
   * Selects a random value from a select filter. Returns its label.
   */
  private function selectSetAnyValue($description) {
    // Get select.
    $selectElem = $this->view->findField($description);
    // All options, except the "All option".
    $options = $selectElem->findAll('css', 'option');
    array_shift($options);
    // Pick a random option.
    $randomOption = $options[\random_int(0, count($options) - 1)];
    $randomValue = $randomOption->getAttribute('value');
    $randomValueLabel = $randomOption->getText();
    // Fill the select option.
    $selectElem->setValue($randomValue);
    return $randomValueLabel;
  }

  /**
   * Asserts a select filter works.
   */
  private function checkSelectFilterWorks($description) {
    $this->reset();
    $value = $this->selectSetAnyValue($description);
    $this->view->pressButton('Apply');
    $this->checkRandomRowHasValue($value);
  }

  /**
   * Returns a node not in the trash.
   */
  private function getRandomNode() {
    $nidsTds = $this->view->findAll('css', 'td.views-field-nid');
    $randomNidTd = $nidsTds[\array_rand($nidsTds)];
    $nid = trim($randomNidTd->getText());
    return Node::load($nid);
  }

  /**
   * Resets a view exposed form.
   */
  private function reset() {
    $this->view->hasButton('Reset') ? $this->view->pressButton('Reset') : NULL;
  }

  /**
   * Selects N numbers of row in the view results table.
   */
  private function selectRows($num) {
    $checkboxes = $this->view->findAll('css', '.views-table > tbody > tr .views-field-node-bulk-form input');
    $checkboxes = \array_slice($checkboxes, 0, $num);
    foreach ($checkboxes as $checkbox) {
      $checkbox->click();
    }
  }

  /**
   * Checks status messages when actions are applied.
   */
  private function checkActions() {
    $this->reset();
    $num = \random_int(5, 10);
    $this->selectRows($num);
    $value = $this->selectSetAnyValue('Action');
    $this->view->pressButton('Apply to selected items');
    $message = $this->page->find('css', '.messages--status')->getText();
    $this->assertStringContainsString($value . ' was applied to ' . $num, $message);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    /** @var \Drupal\Tests\DocumentElement */
    $this->page = $this->getSession()->getPage();

    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    // Visiting the view.
    $this->drupalGet('admin/content');
    $this->view = $this->page->find('css', '.view.view-content');
  }

  /**
   * Tests a few things for the "All content" view at admin/content.
   */
  public function testView() {
    $this->checkSelectFilterOptions('Action',
      ['Watch', 'Unwatch', 'Save content']
    );
    $this->checkSelectFilterOptions('Publication status',
      ['- Any -', 'Published', 'Unpublished']
    );
    $this->checkSelectFilterOptions('Snoozed',
      ['All', 'Yes', 'No']
    );

    $this->checkTextboxFilteredByNodePropertyWorks('Title');
    $this->checkTextboxFilteredByNodePropertyWorks('ID');
    $this->checkSelectFilterWorks('Publication status');

    $this->checkTextboxFilteredByUserWorks('Author', 'Authored by');
    $this->checkTextboxFilteredByUserWorks('Last revised by');

    $this->checkActions();

  }

}
