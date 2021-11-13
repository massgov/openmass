<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\Component\Utility\Html;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Exception;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests "All Content" view at admin/content.
 */
class BulkEditingTest extends ExistingSiteWebDriverTestBase {

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

  private function newNode($data) {
    $node = $this->createNode($data);
    $node->save();
    $this->newNodesByType[$data['type']][$node->id()] = $node->getTitle();
  }

  private function createNodesExecutiveOrder($node_data) {
    $executive_order_data = $node_data + [
      'type' => 'executive_order',
      'field_executive_title' => $this->randomMachineName(20),
      'field_executive_order_number' => 777,
    ];
    $this->newNode($executive_order_data);
    $this->newNode($executive_order_data);
  }

  private function createNodesRegulation($node_data) {
    $regulation_data = $node_data + [
      'type' => 'regulation',
      'field_regulation_title' => $this->randomMachineName(20),
    ];
    $this->newNode($regulation_data);
    $this->newNode($regulation_data);
  }

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

  private function createEveryNodeType() {
    $node_data = [
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
    ];

    $this->createNodesExecutiveOrder($node_data);
    $this->createNodesRegulation($node_data);
    $this->createNodesPerson($node_data);

    $types = $this->nodeTypeFilterOptions();

    $admin_use_only_types = [
      'error_page',
      'interstitial',
      'executive_order',
      'regulation',
      'utility_drawer',
    ];

    foreach ($admin_use_only_types as $admin_use_only_type) {
      unset($types[$admin_use_only_type]);
    }

    unset($types['person']);

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

    $this->createEveryNodeType();
  }

  private function doBulkEdit($type, $suffix) {
    $this->page = $this->getCurrentPage();
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

  private function filterViewContentByNewTitle($new_title) {
    $this->view = $this->getCurrentPage()->find('css', '.view.view-content');
    $this->view->fillField('Title', $new_title);
    $this->view->pressButton('Apply');
  }

  function selectNodesForBulkEdit($title) {
    $this->drupalGet('admin/content');
    $this->view = $this->getCurrentPage()->find('css', '.view.view-content');
    // Filter nodes using its unique titles.
    $this->view->fillField('Title', $title);
    $this->view->pressButton('Apply');
    // Select the only 2 rows available.
    $this->view = $this->getCurrentPage()->find('css', '.view.view-content');
    $this->selectRows(2);
    // Bulk edit those 2 items.
    $this->view->selectFieldOption('Action', 'Edit content');
    $this->view->pressButton('Apply to selected items');
  }

  private function disableAutoEntityLabelIfNecessary($type) {
    $this->drupalGet('admin/structure/types/manage/' . $type . '/auto-label');
    $autoEntityLabelEnabled = !$this->getCurrentPage()->findField('Disabled')->isChecked();

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

  private function reEnableAutoEntityLabel($type) {
    $this->drupalGet('admin/structure/types/manage/' . $type . '/auto-label');
    $this->getCurrentPage()->find('css', '#edit-status-1')->click();
    $this->getCurrentPage()->pressButton('Save configuration');
  }

  private function checkBulkEditingOnContentType($type, $newNodes) {
    $autoEntityLabelWasDisabled = $this->disableAutoEntityLabelIfNecessary($type);

    /** @var Node[] $newNodes */
    $title = current($newNodes);
    // Select 2 nodes (with the same title) and trigger bulk editing.
    $this->selectNodesForBulkEdit($title);
    // Value to be appended to the title, using bulk editing.
    $suffix = '_' . $this->randomMachineName(20);
    // Edit the title of the 2 chosen nodes, which are the same type.
    $this->doBulkEdit($type, $suffix);
    // On the "All Content" view, search the bulk edited nodes, by their
    // new title.
    $this->filterViewContentByNewTitle($title . ' '.$suffix);
    // Ensure we have results.
    $this->assertNotNull($this->view->find('css', '.views-view-table'));
    // Re-enable auto_entitylabel if it was disabled.
    $autoEntityLabelWasDisabled ? $this->reEnableAutoEntityLabel($type) : NULL;
  }

  /**
   * Tests a few things for the "All content" view at admin/content.
   */
  public function testBulkEditingOnAllContentTypes() {
    foreach ($this->newNodesByType as $type => $newNodes) {
      $this->checkBulkEditingOnContentType($type, $newNodes);
    }
  }

}
