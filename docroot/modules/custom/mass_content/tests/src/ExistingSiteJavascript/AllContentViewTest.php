<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Exception;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests "All Content" view at admin/content.
 */
class AllContentViewTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * The All Content view.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $view = NULL;

  /**
   * Asserts a random row has a specific text value.
   */
  private function checkRandomRowHasValue($value) {
    $table = $this->view->find('css', '.views-view-table');

    // Zero results scenario.
    if (!$table) {
      return;
    }

    $rows = $table->findAll('css', 'tbody > tr');
    $randomRow = $rows[\random_int(0, count($rows) - 1)];
    $text = $randomRow->getText();
    $this->assertStringContainsString($value, $text);
  }

  /**
   * Returns the node types machine names and labels.
   */
  private function nodeTypeFilterOptions() {
    return [
      'advisory' => 'Advisory',
      'alert' => 'Alert (Page-level and Organization)',
      'sitewide_alert' => 'Alert (Sitewide)',
      'page' => 'Basic page (prototype)',
      'binder' => 'Binder',
      'contact_information' => 'Contact Information',
      'curated_list' => 'Curated List',
      'decision' => 'Decision',
      'decision_tree' => 'Decision Tree',
      'decision_tree_branch' => 'Decision Tree Branch',
      'decision_tree_conclusion' => 'Decision Tree Conclusion',
      'error_page' => 'Error',
      'event' => 'Event',
      'executive_order' => 'Executive Order',
      'fee' => 'Fee',
      'form_page' => 'Form',
      'guide_page' => 'Guide',
      'how_to_page' => 'How-to',
      'info_details' => 'Information Details',
      'interstitial' => 'Interstitial',
      'location' => 'Location',
      'location_details' => 'Location Detail',
      'news' => 'News',
      'org_page' => 'Organization',
      'person' => 'Person',
      'campaign_landing' => 'Promotional page',
      'regulation' => 'Regulation',
      'action' => 'Right-rail (prototype)',
      'rules' => 'Rules of Court',
      'service_page' => 'Service',
      'stacked_layout' => 'Stacked layout (prototype)',
      'topic_page' => 'Topic Page',
      'utility_drawer' => 'Utility Drawer',
    ];
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
    $options = $selectElem->findAll('css', 'option:not([value=All])');
    // Pick a random option.
    $randomOption = $options[\array_rand($options)];
    $randomOptionLabel = $randomOption->getText();
    // Fill the select option.
    $selectElem->selectOption($randomOptionLabel);
    return $randomOptionLabel;
  }

  /**
   * Asserts a select filter works.
   */
  private function checkSelectFilterWorks($description, $value = NULL) {
    $this->reset();
    if ($value) {
      $this->view->findField($description)->selectOption($value);
    }
    else {
      $value = $this->selectSetAnyValue($description);
    }
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
    $this->view->pressButton('Apply');
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
    $actions = [
      'flag_action.watch_content_flag' => 'Watch',
      'flag_action.unwatch_content_flag' => 'Unwatch',
      'node_save_action' => 'Edit content',
    ];

    foreach ($actions as $action_label) {
      $this->reset();
      $num = \random_int(5, 10);
      $this->selectRows($num);

      $this->view->findField('Action')->selectOption($action_label);
      $this->view->pressButton('Apply to selected items');
      $message = $this->getCurrentPage()->find('css', '.messages--status')->getText();
      $this->assertStringContainsString($action_label . ' was applied to ' . $num, $message);
    }
  }

  /**
   * Creates one unpublished node.
   */
  private function createUnpublishedNode() {
    $unpublished_page = $this->createNode([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => 0,
      'moderation_state' => MassModeration::UNPUBLISHED,
    ]);
    $unpublished_page->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();

    // An admin is needed.
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    // Visiting the view, and submit to see ther Actions.
    $this->drupalGet('admin/content');
    $page = $this->getSession()->getPage();
    $page->pressButton('Apply');
    $this->view = $this->getCurrentPage()->find('css', '.view.view-content');

    // Ensure we have at least one unpublished page,
    // so we can also test the Unpublished nodes filter.
    $this->createUnpublishedNode();
  }

  /**
   * Tests a few things for the "All content" view at admin/content.
   */
  public function testView() {
    // Checking select filter options.
    $this->checkSelectFilterOptions('Action',
      ['Watch', 'Unwatch', 'Edit content']
    );
    $this->checkSelectFilterOptions('Publication status',
      ['- Any -', 'Published', 'Unpublished']
    );
    $this->checkSelectFilterOptions('Content type', $this->nodeTypeFilterOptions());

    // Checking textbox filters.
    $this->checkTextboxFilteredByNodePropertyWorks('Title');
    $this->checkTextboxFilteredByNodePropertyWorks('ID');
    $this->checkTextboxFilteredByUserWorks('Author', 'Authored by');
    $this->checkTextboxFilteredByUserWorks('Last revised by');

    // Check status filter.
    $this->checkSelectFilterWorks('Publication status', 'Published');
    $this->checkSelectFilterWorks('Publication status', 'Unpublished');

    // Check randomly the content type filter 10 times.
    for ($i = 0; $i++ < 10; $this->checkSelectFilterWorks('Content type'));

    // @todo Test without without checking messages
    // Check actions.
    // $this->checkActions();
  }

}
