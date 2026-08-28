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
    $this->waitForViewResults();
    $table = $this->view->find('css', '.views-view-table');

    // Zero results scenario.
    if (!$table) {
      return;
    }

    $rows = $table->findAll('css', 'tbody > tr');
    if (count($rows) === 0) {
      $this->markTestSkipped('No rows returned for the current filter.');
      return;
    }
    $randomRow = $rows[\random_int(0, count($rows) - 1)];
    $text = $randomRow->getText();
    $this->assertStringContainsString($value, $text);
  }

  /**
   * Waits for the view results table or empty state to finish loading.
   */
  private function waitForViewResults(): void {
    $this->getSession()->wait(
      10000,
      "document.querySelector('.view.view-content .views-view-table tbody tr') !== null || document.querySelector('.view.view-content .view-empty') !== null"
    );
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
      'stacked_layout' => 'Stacked layout - prototype',
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
    $this->waitForViewResults();
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
    $this->waitForViewResults();
  }

  /**
   * Selects N numbers of row in the view results table.
   */
  private function selectRows($num) {
    // Click through the DOM rather than the WebDriver. Selecting the first row
    // reveals the sticky bulk actions bar, which the driver then reports as
    // covering the rows underneath it and refuses to click.
    for ($index = 0; $index < $num; $index++) {
      $this->getSession()->executeScript(sprintf(
        'var el = document.querySelectorAll(".vbo-view-form .js-vbo-checkbox")[%d]; if (el) { el.click(); }',
        $index
      ));
    }
  }

  /**
   * Creates published pages sharing a title prefix, and returns them.
   */
  private function createPagesWithPrefix($prefix, $count) {
    $nodes = [];
    for ($index = 1; $index <= $count; $index++) {
      $nodes[] = $this->createNode([
        'type' => 'page',
        'title' => $prefix . '-' . $index,
        'status' => 1,
        'moderation_state' => MassModeration::PUBLISHED,
      ]);
    }
    return $nodes;
  }

  /**
   * Narrows the view down to the rows with the given title prefix.
   */
  private function filterByTitle($prefix, $expected_rows) {
    $this->drupalGet('admin/content');
    $page = $this->getCurrentPage();
    $page->fillField('Title', $prefix);
    $page->pressButton('Apply');
    $this->waitForViewResults();
    $this->assertTrue(
      $this->getSession()->wait(
        10000,
        \sprintf(
          'document.querySelectorAll(".vbo-view-form .js-vbo-checkbox").length === %d',
          $expected_rows
        )
      ),
      \sprintf('All Content should list the %d created rows.', $expected_rows)
    );
    $this->view = $page->find('css', '.view.view-content');
    return $page;
  }

  /**
   * Picks rows and an action, then applies it.
   */
  private function applyActionToRows($page, $num, $action_label) {
    $this->selectRows($num);
    $checked = (int) $this->getSession()->evaluateScript(
      'document.querySelectorAll(".vbo-view-form .js-vbo-checkbox:checked").length'
    );
    $this->assertSame($num, $checked, 'The expected number of rows should be selected.');
    $page->selectFieldOption('Action', $action_label);

    $enabled = $this->getSession()->wait(
      5000,
      'document.querySelector(\'[data-vbo="vbo-action"]:not(:disabled)\') !== null'
    );
    $this->assertTrue($enabled, 'Apply to selected items should be enabled after selecting rows and an action.');
    $page->pressButton('Apply to selected items');
  }

  /**
   * Waits for a bulk operation batch to hand the editor back to the view.
   */
  private function waitForBatchToFinish() {
    $this->assertTrue(
      $this->getSession()->wait(
        120000,
        'window.location.pathname.indexOf("/admin/content") !== -1'
      ),
      'The batch should finish and return to All Content.'
    );
    $this->assertSession()->pageTextNotContains('An error has occurred');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
  }

  /**
   * Bulk editing several rows reaches the action configuration form.
   */
  public function testBulkApplyWithMultipleItemsSelected() {
    $prefix = 'DP-47588-' . $this->randomMachineName(8);
    $nodes = $this->createPagesWithPrefix($prefix, 2);

    $page = $this->filterByTitle($prefix, 2);

    $this->selectRows(2);
    $checked = (int) $this->getSession()->evaluateScript(
      'document.querySelectorAll(".vbo-view-form .js-vbo-checkbox:checked").length'
    );
    $this->assertSame(2, $checked, 'Two All Content rows should be selected.');
    $page->selectFieldOption('Action', 'Edit content');

    $enabled = $this->getSession()->wait(
      5000,
      'document.querySelector(\'[data-vbo="vbo-action"]:not(:disabled)\') !== null'
    );
    $this->assertTrue($enabled, 'Apply to selected items should be enabled after selecting multiple items and an action.');

    // Resolving the selection runs the view query with a condition on the base
    // field, which used to be ambiguous against the tables joined into the All
    // Content view and made this step fail with a database error.
    $page->pressButton('Apply to selected items');
    $this->assertTrue(
      $this->getSession()->wait(
        20000,
        'window.location.pathname.indexOf("/views-bulk-operations/configure/") !== -1'
      ),
      'Applying the action should lead to the action configuration form.'
    );
    $this->assertSession()->addressMatches('/views-bulk-operations\/configure\/content\//');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
    $this->assertSession()->elementExists('css', 'form#views-bulk-operations-configure-action');
    foreach ($nodes as $node) {
      $this->assertSession()->pageTextContains($node->getTitle());
    }
  }

  /**
   * An action without a configuration form reaches every selected row.
   */
  public function testActionAppliesToSelectedRows() {
    $prefix = 'DP-47588-' . $this->randomMachineName(8);
    $nodes = $this->createPagesWithPrefix($prefix, 3);

    $page = $this->filterByTitle($prefix, 3);
    $this->applyActionToRows($page, 3, 'Watch');
    $this->waitForBatchToFinish();

    $flag = \Drupal::service('flag')->getFlagById('watch_content');
    foreach ($nodes as $node) {
      $this->assertNotNull(
        \Drupal::service('flag')->getFlagging($flag, $node, $this->loggedInUser),
        \sprintf('"%s" should be flagged after the bulk action.', $node->getTitle())
      );
    }
  }

  /**
   * A label typed during bulk edit is created once and shared by every row.
   */
  public function testBulkEditCreatesOneLabelForTheWholeBatch() {
    // Bulk operations hand the rows to the batch API in pages of ten, and the
    // batch restores the stored action configuration from scratch for each of
    // those pages. Take more than one page, so a label the editor types is
    // restored - and used to be created - more than once.
    $prefix = 'DP-47588-' . $this->randomMachineName(8);
    $nodes = $this->createPagesWithPrefix($prefix, 12);
    $label = 'DP-47588 label ' . $this->randomMachineName(8);

    $page = $this->filterByTitle($prefix, 12);
    $this->applyActionToRows($page, 12, 'Edit content');
    $this->assertTrue(
      $this->getSession()->wait(
        20000,
        'window.location.pathname.indexOf("/views-bulk-operations/configure/") !== -1'
      ),
      'Applying the action should lead to the action configuration form.'
    );

    $configure = $this->getCurrentPage();
    $configure->checkField('node[page][_field_selector][field_reusable_label]');
    $configure->fillField('node[page][field_reusable_label][0][target_id]', $label);
    $configure->pressButton('Apply');
    $this->waitForBatchToFinish();

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'label', 'name' => $label]);
    $this->assertCount(1, $terms, 'The typed label should be created exactly once.');
    $term = \reset($terms);
    $this->markEntityForCleanup($term);

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache();
    foreach ($nodes as $node) {
      $saved = $storage->load($node->id());
      $tids = \array_column($saved->get('field_reusable_label')->getValue(), 'target_id');
      $this->assertContains(
        $term->id(),
        $tids,
        \sprintf('"%s" should carry the label applied to the whole batch.', $node->getTitle())
      );
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
  }

}
