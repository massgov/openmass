<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\Component\Utility\Html;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests Bulk editing for all content types, except the "admin only" ones.
 *
 * Creates 2 nodes of each type, with unique title names.
 * Filters by title the 2 nodes, and edits it in bulk.
 * In the bulk edit, it appends to the title another unique string.
 * Searches on the "All Content" view the unique string just appended.
 * If there are results, it means the bulk editing worked.
 * For most content types, auto_entitylabel will be disabled, to simplify
 * the title generation.
 */
class BulkEditingTest extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * The All Content view.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $view;

  /**
   * The nodes created for bulk editing.
   *
   * @var array
   */
  protected array $newNodesByType;

  /**
   * Returns the node types machine names and labels.
   */
  private function nodeTypeFilterOptions() {

    return [
      'advisory' => 'Advisory',
      'alert' => 'Alert (Page-level and Organization)',
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
      'external_data_resource' => 'External data resource',
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
      'service_details' => 'Service Details',
      'stacked_layout' => 'Stacked layout (prototype)',
      'topic_page' => 'Topic Page',
      'utility_drawer' => 'Utility Drawer',
    ];
  }

  /**
   * Creates a new node using the $data and stores it on newNodesByType.
   */
  private function newNode($data) {
    $node = $this->createNode($data);
    $node->save();
    $this->newNodesByType[$data['type']][$node->id()] = $node->getTitle();
  }

  /**
   * Creates 2 executive_order nodes.
   */
  private function createNodesExecutiveOrder($node_data) {
    $executive_order_data = $node_data + [
      'type' => 'executive_order',
      'field_executive_title' => $this->randomMachineName(20),
      'field_executive_order_number' => 777,
    ];
    $this->newNode($executive_order_data);
    $this->newNode($executive_order_data);
  }

  /**
   * Creates 2 regulation nodes.
   */
  private function createNodesRegulation($node_data) {
    $regulation_data = $node_data + [
      'type' => 'regulation',
      'field_regulation_title' => $this->randomMachineName(20),
    ];
    $this->newNode($regulation_data);
    $this->newNode($regulation_data);
  }

  /**
   * Creates 2 person nodes.
   */
  private function createNodesPerson($node_data) {
    // Create 2 persons.
    // A Person's title is built from its first_name and last_name.
    $person_data = $node_data + [
      'type' => 'person',
      'field_person_first_name' => $this->randomMachineName(5),
      'field_person_last_name' => $this->randomMachineName(5),
    ];
    $this->newNode($person_data);
    $this->newNode($person_data);
  }

  /**
   * Creates 2 nodes of every node type (except admin-only ones).
   */
  private function createEveryNodeType() {

    $types = $this->nodeTypeFilterOptions();
    $admin_use_only_types = ['error_page', 'interstitial', 'utility_drawer'];
    $special_types = ['person', 'executive_order', 'regulation'];

    foreach (array_merge($admin_use_only_types, $special_types) as $type) {
      unset($types[$type]);
    }

    $node_data = [
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
    ];

    // Types executive_order and regulation have no title field
    // on its default form display, and its title is generated based
    // on other fields with auto_entitylabel.
    $this->createNodesExecutiveOrder($node_data);
    $this->createNodesRegulation($node_data);

    // Person type have the title field in its node creation form,
    // although it is hidden with auto entity_label.
    $this->createNodesPerson($node_data);

    // For the rest of content types, we are going to rely on the title field
    // to generate the label.
    $type_machine_names = array_keys($types);
    $title = $this->randomMachineName(20);
    foreach ($type_machine_names as $type_machine_name) {
      $node_data['title'] = $title . '_' . $type_machine_name;
      $node_data['type'] = $type_machine_name;
      $this->newNode($node_data);
      $this->newNode($node_data);
    }
  }

  /**
   * Selects N numbers of row in the view results table.
   */
  private function selectRowsFromView($num) {
    $selector = '.views-table > tbody > tr .views-field-node-bulk-form input';
    $checkboxes = $this->view->findAll('css', $selector);
    $checkboxes = \array_slice($checkboxes, 0, $num);
    foreach ($checkboxes as $checkbox) {
      $checkbox->click();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    // To test bulk editing, we will create 2 nodes per content type.
    $this->createEveryNodeType();
  }

  /**
   * Bulk edit the nodes, appending a "unique string" to the title.
   */
  private function doBulkEdit($type, $suffix) {
    $edit_node_type = HTML::getId('edit-node-' . $type);
    $title_field = 'title';

    switch ($type) {
      case 'executive_order':
        $title_field = 'field-executive-title';
        break;

      case 'regulation':
        $title_field = 'field-regulation-title';
        break;
    }

    $title_check_id = '#' . $edit_node_type . '-field-selector-' . $title_field;
    $title_input_id = '#' . $edit_node_type . '-' . $title_field . '-0-value';
    $append_option_id = '#' . $edit_node_type . '-' . $title_field . '-change-method-append';

    // Select to modify title.
    $this->getCurrentPage()->find('css', $title_check_id)->check();
    // Modify title value.
    $this->getCurrentPage()->find('css', $title_input_id)->setValue($suffix);
    // Select to append $suffix to the title.
    $this->getCurrentPage()->find('css', $append_option_id)->click();
    // Submit the bulk edit form.
    $this->getCurrentPage()->pressButton('Confirm');
  }

  /**
   * Filter view by title.
   */
  private function filterViewContentByTitle($title) {
    $this->view = $this->getCurrentPage()->find('css', '.view.view-content');
    $this->view->fillField('Title', $title);
    $this->view->pressButton('Apply');
  }

  /**
   * Selects nodes from the content view, previously filtered by title.
   */
  private function selectNodesForBulkEdit($title) {
    $this->drupalGet('admin/content');
    // Filter nodes using its unique titles.
    $this->filterViewContentByTitle($title);
    // Select the only 2 rows available.
    $this->view = $this->getCurrentPage()->find('css', '.view.view-content');
    $this->selectRowsFromView(2);
    // Bulk edit those 2 items.
    $this->view->selectFieldOption('Action', 'Edit content');
    $this->view->pressButton('Apply to selected items');
  }

  /**
   * Disables auto_entitylabel for $type with generated label & no title field.
   */
  private function disableAutoEntityLabelIfNecessary($type) {
    $this->drupalGet('admin/structure/types/manage/' . $type . '/auto-label');
    $autoEntityLabelEnabled =
      !$this->getCurrentPage()->findField('Disabled')->isChecked();

    $typesWithoutTitleFieldOnFormDisplay = [
      'executive_order',
      'regulation',
    ];

    $typeHasTitleField = !in_array($type, $typesWithoutTitleFieldOnFormDisplay);

    $needsToBeDisabled = $autoEntityLabelEnabled && $typeHasTitleField;
    if ($needsToBeDisabled) {
      $this->getCurrentPage()->find('css', '#edit-status-0')->click();
      $this->getCurrentPage()->pressButton('Save configuration');
    }
    return $needsToBeDisabled;
  }

  /**
   * Enables auto_entitylabel for a specific content type.
   */
  private function reEnableAutoEntityLabel($type) {
    $this->drupalGet('admin/structure/types/manage/' . $type . '/auto-label');
    $this->getCurrentPage()->find('css', '#edit-status-1')->click();
    $this->getCurrentPage()->pressButton('Save configuration');
  }

  /**
   * Test bulk editing with 2 nodes of a specific content type.
   */
  private function checkBulkEditingOnContentType($type, $newNodes) {
    // Is easier to test bulk editing by appending something to the title,
    // hence we need to disable auto entity_label in most of the node types
    // where it is enabled.
    $autoEntityLabelWasDisabled = $this->disableAutoEntityLabelIfNecessary($type);

    /** @var Node[] $newNodes */
    $title = current($newNodes);
    // Select 2 nodes (with the same title) and trigger bulk editing.
    $this->selectNodesForBulkEdit($title);
    // Value to be appended to the title, using bulk editing.
    // This acts as a unique identifier to later filter by type using this
    // suffix, and ensure the bulk editing worked.
    $suffix = '_' . $this->randomMachineName(20);
    // Edit the title of the 2 chosen nodes, which are the same type.
    $this->doBulkEdit($type, $suffix);
    // On the "All Content" view, search the bulk edited nodes, by their
    // new title.
    $this->filterViewContentByTitle($title . ' ' . $suffix);
    // Ensure we have results.
    $this->assertNotNull($this->view->find('css', '.views-view-table'));
    // Re-enable auto_entitylabel if it was disabled.
    $autoEntityLabelWasDisabled ? $this->reEnableAutoEntityLabel($type) : NULL;
  }

  /**
   * Tests bulk editing on all content types.
   */
  public function testBulkEditingOnAllContentTypes() {
    foreach ($this->newNodesByType as $type => $newNodes) {
      $this->checkBulkEditingOnContentType($type, $newNodes);
    }
  }

}
