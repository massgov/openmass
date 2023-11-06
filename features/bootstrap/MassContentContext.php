<?php
/**
 * @file
 *
 */

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Context\MinkContext;
use Drupal\DrupalExtension\Context\DrupalContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines content features specific to Mass.gov.
 */
class MassContentContext extends RawDrupalContext implements SnippetAcceptingContext {

  /**
   * @var Object[] Array of action nodes keyed on 'title'.
   */
  private $action = [];

  /**
   * @var Object[] Array of subtopic nodes keyed on 'title'.
   */
  private $subtopic = [];

  /**
   * @var Object[] Array of topic nodes keyed on 'title'.
   */
  private $topic = [];

  /**
   * @var Object[] Array of section landing nodes keyed on 'title'.
   */
  private $section_landing = [];

  /**
   * @var DrupalContext
   */
  private $drupalContext;

  /**
   * @var MinkContext
   */
  private $minkContext;

  /**
   * @var FeatureContext
   */
  private $featureContext;

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope)
  {
    $environment = $scope->getEnvironment();
    $this->drupalContext = $environment->getContext(DrupalContext::class);
    $this->minkContext = $environment->getContext(MinkContext::class);
    $this->featureContext = $environment->getContext(FeatureContext::class);
  }

  /**
   * Create all default content.
   *
   * @Given default test content exists
   */
  public function createDefaultTestContent() {
    $vocabularies = [
      'icons' => $this->defaultIcons(),
    ];
    foreach ($vocabularies as $vocabulary => $terms) {
      foreach ($terms as $term) {
        $term += ['vocabulary_machine_name' => $vocabulary];
        $this->drupalContext->termCreate((object) $term);
      }
    }

    $types = [
      'section_landing' => $this->defaultSectionLandings(),
      'topic' => $this->defaultTopics(),
      'subtopic' => $this->defaultSubtopics(),
      'action' => $this->defaultActions(),
    ];

    foreach ($types as $type => $nodes) {
      foreach ($nodes as $node) {
        $node += ['type' => $type];
        $this->createNode($node);
      }
    }

    // Now that all the data structures exist, we need to go back through and
    // add content dependencies.
    $this->updateNodes('subtopic');
    $this->updateNodes('topic');
  }

  /**
   * Get a list of fields that have content dependencies.
   *
   * @return Array
   *   An array of fields with content dependencies keyed by node type.
   */
  public function relationshipFields() {
    return [
      'action' => [],
      'subtopic' => [
        'field_featured_content',
      ],
      'topic' => [
        'field_common_content',
      ],
      'section_landing' => [],
    ];
  }

  /**
   * Create default test actions.
   *
   * The Action Parent field is required, thus Subtopics must be created before
   * these actions can be instantiated.
   *
   * @Given test actions exist
   */
  public function defaultActions() {
    return [
      [
        'title' => 'Behat Test: Get a State Park Pass',
        'field_action_parent' => 'Behat Test: State Parks & Recreation',
      ],
      [
        'title' => 'Behat Test: Reserve a Campsite (external)',
        'field_external-url' => 'http://www.google.com',
        'field_action_parent' => 'Behat Test: Nature & Outdoor Activities',
      ],
      [
        'title' => 'Behat Test: Find Scenic Viewing Areas',
        'field_action_parent' => 'Behat Test: Nature & Outdoor Activities',
      ],
      [
        'title' => 'Behat Test: Find Horseback Riding Trails (external)',
        'field_external-url' => 'http://www.google.com',
        'field_action_parent' => 'Behat Test: Nature & Outdoor Activities',
      ],
      [
        'title' => 'Behat Test: Download a Trail Map',
        'field_action_parent' => 'Behat Test: Nature & Outdoor Activities',
      ],
      [
        'title' => 'Behat Test: Get a Boating License',
        'field_action_parent' => 'Behat Test: Recreational Licenses & Permits',
      ],
      [
        'title' => 'Behat Test: Get a Fishing License (external)',
        'field_external-url' => 'http://www.google.com',
        'field_action_parent' => 'Behat Test: Recreational Licenses & Permits',
      ],
      [
        'title' => 'Behat Test: Get a Hunting License',
        'field_action_parent' => 'Behat Test: Recreational Licenses & Permits',
      ],
      [
        'title' => 'Behat Test: Post a Job',
        'field_action_parent' => 'Behat Test: Search Jobs',
      ],
      [
        'title' => 'Behat Test: Find a State Job',
        'field_action_parent' => 'Behat Test: Search Jobs',
      ],
    ];
  }

  /**
   * Create default icons terms for use in tests of
   * content types: Section Landing, Topic
   * paragraphs: Action Step.
   *
   * Note: the fields that reference this vocabulary are required, so
   * these terms need to be created before the nodes/paragraphs.
   *
   * @Given icons vocabulary exists
   */
  public function defaultIcons() {
    return [
      [
        'name' => 'Behat Test: Family',
        'field_sprite_name' => 'family',
      ],
      [
        'name' => 'Behat Test: Apple',
        'field_sprite_name' => 'apple',
      ],
      [
        'name' => 'Behat Test: Camping',
        'field_sprite_name' => 'camping',
      ]
    ];
  }

  /**
   * Create default test subtopics.
   *
   * Note: The "field_featured_content" field is optional, so content relationships
   * will need to be updated after all the stub data is created.
   *
   * @Given test subtopics exist
   */
  public function defaultSubtopics() {
    return [
      [
        'title' => 'Behat Test: Get a State Park Pass',
        'status' => 1,
        'moderation_state' => 'published',
        'field_lede' => 'The lede text for Nature & Outdoor Activities',
        'field_description' => 'The description text for Nature & Outdoor Activities',
        'field_featured_content' => implode(', ', [
          'Behat Test: Find a State Park',
          'Behat Test: Download a Trail Map',
        ]),
        'field_agency_links' => implode(', ', [
          'MassParks - http://www.google.com',
          'Department of Fish - http://www.google.com',
        ]),
        'field_topic_callout_links' => implode(', ', [
          'Camping - https://mass.local/subtopic/nature-outdoor-activities?filter=Camping',
          'Hiking - https://mass.local/subtopic/nature-outdoor-activities?filter=Hiking',
          'Biking - https://mass.local/subtopic/nature-outdoor-activities?filter=Biking',
        ]),
      ],
      [
        'title' => 'Behat Test: Nature & Outdoor Activities',
        'status' => 1,
        'moderation_state' => 'published',
        'field_lede' => 'The lede text for Nature & Outdoor Activities',
        'field_description' => 'The description text for Nature & Outdoor Activities',
        'field_featured_content' => implode(', ', [
          'Behat Test: Find a State Park',
          'Behat Test: Download a Trail Map',
        ]),
        'field_agency_links' => implode(', ', [
          'MassParks - http://www.google.com',
          'Department of Fish - http://www.google.com',
        ]),
        'field_topic_callout_links' => implode(', ', [
          'Camping - https://mass.local/subtopic/nature-outdoor-activities?filter=Camping',
          'Hiking - https://mass.local/subtopic/nature-outdoor-activities?filter=Hiking',
          'Biking - https://mass.local/subtopic/nature-outdoor-activities?filter=Biking',
        ]),
      ],
      [
        'title' => 'Behat Test: Recreational Licenses & Permits',
        'status' => 1,
        'moderation_state' => 'published',
        'field_lede' => 'The lede text for Recreational Licenses & Permits',
        'field_description' => 'The description text for Recreational Licenses & Permits',
        'field_featured_content' => implode(', ', [
          'Behat Test: Get a Boating License',
        ]),
        'field_agency_links' => implode(', ', [
          'Department of Agricultural Resources - http://www.google.com',
        ]),
      ],
      [
        'title' => 'Behat Test: Search Jobs',
        'status' => 1,
        'moderation_state' => 'published',
        'field_lede' => 'The lede text for Search Jobs',
        'field_description' => 'The description text for Search Jobs',
        'field_featured_content' => implode(', ', [
          'Behat Test: Find a State Job',
        ]),
        'field_agency_links' => implode(', ', [
          'MassCareers - http://www.google.com',
          'MassIT - http://www.google.com'
        ]),
        'field_topic_callout_links' => implode(', ', [
          'Education - https://mass.local/subtopic/search-jobs?filter=Education',
          'Public Sector - https://mass.local/subtopic/search-jobs?filter=Public Sector',
          'Public Safety - https://mass.local/subtopic/search-jobs?filter=Public Safety',
        ]),
      ],
    ];
  }

  /**
   * Create default test topics.
   *
   * The Common Content field here is optional, and the actions may or may not
   * exist, so they should be created in a followup.
   *
   * @Given test topics exist
   */
  public function defaultTopics() {
    return [
      [
        'title' => 'Behat Test: State Parks & Recreation',
        'status' => 1,
        'moderation_state' => 'published',
        'field_section' => 'Behat Test: Visiting & Exploring',
        'field_lede' => 'Lede text for State Parks & Rec.',
        'field_icon_term' => 'Behat Test: Camping',
        'field_common_content' => implode(', ', [
          'Behat Test: Get a State Park Pass',
          'Behat Test: Download a Trail Map',
        ]),
      ],
      [
        'title' => 'Behat Test: Finding a Job',
        'status' => 1,
        'moderation_state' => 'published',
        'field_section' => 'Behat Test: Working',
        'field_lede' => 'Lede text for Finding a Job',
        'field_icon_term' => 'Behat Test: Apple',
        'field_common_content' => implode(', ', [
          'Behat Test: Post a Job',
        ]),
      ],
    ];
  }

  /**
   * Create default test section landings.
   *
   * @Given test section landings exist
   */
  public function defaultSectionLandings() {
    return [
      [
        'title' => 'Behat Test: Visiting & Exploring',
        'field_icon_term' => 'Behat Test: Family',
      ],
      [
        'title' => 'Behat Test: Working',
        'field_icon_term' => 'Behat Test: Apple',
      ],
    ];
  }

  /**
   * Create a node in Drupal from an array.
   *
   * @param array $node
   * @return object
   */
  protected function createNode(Array $node) {
    $type = $node['type'];
    // If there is not a title, set one.
    $node['title'] = ($node['title']) ?: $this->randomTitle($type);

    // Strip out fields that cannot be created because of content dependencies.
    if (!empty($this->relationshipFields()[$type])) {
      foreach($this->relationshipFields()[$type] as $field) {
        unset($node[$field]);
      }
    }

    $node = $this->drupalContext->nodeCreate((object) $node);

    // Track the node as a local array entry for ease of reference.
    if (is_array($this->{$type})) {
      $this->{$type}[$node->title] = $node;
    }

    return $node;
  }

  /**
   * Creates a paragraph of the given type, identified by the given key.
   *
   * @Given I add a :type paragraph in the :key field with values:
   */
  public function iAddAParagraph($type, $key, TableNode $fields) {
    $paragraph_values = ['type' => $type];
    foreach ($fields->getRowsHash() as $field => $value) {
      $paragraph_values[$field] = $value;
    }
    $paragraph = Paragraph::create($paragraph_values);
    $paragraph->save();

    if (empty($this->nodes)) {
      throw new \Exception('No pages have been created.');
    }
    $latest_node = end($this->nodes);

    $node = Node::load($latest_node->nid);
    $node->set($key, [
      [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ]
    ]);
    $node->save();
  }

  /**
   * Creates a node of the given type.
   *
   * @Given I create a :type content:
   */
  public function iCreateAContent($type, TableNode $fields) {
    $node = (object) array(
      'type' => $type,
    );
    foreach ($fields->getRowsHash() as $field => $value) {
      $node->{$field} = $value;
    }

    $this->nodeCreate($node);
  }

  /**
   * Visits the most recently-created node.
   *
   * @When I visit the newest page
   */
  public function iVisitTheNewestPage() {
    if (empty($this->nodes)) {
      throw new \Exception('No pages have been created.');
    }
    $latest_node = end($this->nodes);
    $this->minkContext->visitPath('/node/' . $latest_node->nid);
  }

  /**
   * Update reference fields on nodes of a given type.
   *
   * @param string $type
   *   The type of nodes to update.
   */
  protected function updateNodes($type) {
    if (empty($this->{$type})) {
      return;
    }

    if (empty($this->relationshipFields()[$type])) {
      return;
    }

    foreach ($this->{$type} as $title => $old_node) {
      $node = Node::load($old_node->nid);
      foreach ($this->relationshipFields()[$type] as $field) {
        if ($node->hasField($field)) {
          $refs = $this->getDefaultValue($type, $node->title->value, $field);
          foreach ($refs as $ref) {
            $node->{$field}->appendItem($ref->nid);
          }
        }
      }
      $node->save();
    }
  }

  /**
   * Adds users to restricted content access list
   *
   * @When I visit the node with restricted access to :user on :title :type content
   */
  public function iRestrictAccessContent($user, $title, $type) {
    if (empty($user)) {
      throw new \Exception('The user must be provided.');
    }

    if (empty($title)) {
      throw new \Exception('The node title must be provided.');
    }

    if (empty($type)) {
      throw new \Exception('The node type must be provided.');
    }

    // Creates an unpublished node.
    $node = (object) [
      'type' => $type,
      'title' => $title,
      'moderation_state' => MassModeration::DRAFT,
    ];

    $node = Node::load($this->nodeCreate($node)->nid);

    if (!isset($node)) {
      throw new \Exception('Cannot load the newly created node.');
    }

    $nid = $node->id();

    $users = Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $user]);

    if (empty($users)) {
      throw new \Exception('The user does not exist and must be provided.');
    }

    foreach ($users as $user) {
      $additional_users[] = $user->id();
    }

    $storage = \Drupal::entityTypeManager()->getStorage('user_ref_access');

    $storage->create(
      [
        'entity_type' => $node->getEntityType()->id(),
        'entity_id' => $node->id(),
        'user_id' => 0,
        'additional_users' => $additional_users,
        'enabled' => 1,
      ]
    )->save();

    $this->minkContext->visitPath('node/' . $nid);
  }

  /**
   * Get node info for an entity reference field for a given node.
   *
   * @param $type
   *   The type that has an entity reference.
   * @param $title
   *   The title of the node.
   * @param $field
   *   The entity reference node content will be saved to.
   * @return array|void
   *   The base node info for the entity reference.
   */
  private function getDefaultValue($type, $title, $field) {

    $action = $this->defaultActions();
    $subtopic = $this->defaultSubtopics();
    $topic = $this->defaultTopics();
    $section_landing = $this->defaultSectionLandings();

    $lookups = [];

    foreach (${$type} as $default_node) {
      if ($default_node['title'] == $title) {
        $lookups = $default_node[$field];
      }
    }

    $lookups = explode(', ', $lookups);

    if (empty($lookups)) {
      return;
    }

    foreach ($lookups as $lookup_title) {
      foreach (array_keys($this->relationshipFields()) as $lookup_type) {
        if (array_key_exists($lookup_title, $this->{$lookup_type})) {
          $return[] = $this->{$lookup_type}[$lookup_title];
        }
      }
    }

    return $return;
  }
  /** end of really, really bad ideas... */

  /**
   * Visit a given test node.
   *
   * @When I visit the test :type :title
   *
   * @param $type The test content type
   * @param $title The test node title
   *
   * @throws Exception
   */
  public function vistsTestNode($type, $title) {
    if (empty($type)) {
      throw new \Exception('The node type must be provided.');
    }

    if (empty($title)) {
      throw new \Exception('The node title must be provided.');
    }

    if (!isset($this->{$type}[$title])) {
      throw new \Exception('Cannot load the specified node.');
    }

    $node = $this->{$type}[$title];
    $this->minkContext->visitPath('node/' . $node->nid);
  }

  /**
   * Generate a random title for a node type.
   *
   * @param $type
   * @return string
   */
  public function randomTitle($type, $prefix = 'Behat Test -')
  {
    $random = strtolower($this->drupalContext->getRandom()->name());
    $type = str_replace('_', ' ', $type);

    return ucwords("{$prefix} {$type} {$random}");
  }

  /**
   * Helper function to lookup a node by type/title.
   */
  protected function getNodesByTitle($type, $title) {
    return \Drupal::entityTypeManager()->getStorage('node')
      ->loadByProperties(['type' => $type, 'title' => $title]);
  }

  protected function getTermsByTitle($vid, $name) {
    return \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $vid, 'name' => $name]);
  }

  /**
   * @Given a news item :title referencing :name
   */
  public function aNewsItemReferencing($name, $title) {
    $referenceds = $this->getNodesByTitle('org_page', $name);
    if($referenced = reset($referenceds)) {
      $signee = Paragraph::create([
        'type' => 'state_organization',
        'field_state_org_ref_org' => $referenced
      ]);
      $node = Node::create([
        'type' => 'news',
        'title' => $title,
        'status' => 1,
        'field_news_signees' => [$signee],
        'moderation_state' => 'published',
      ]);
      $node->save();
      // Add the news node for deletion.
      $this->drupalContext->nodes[] = $node;
    }
  }

  /**
   * @Given the :type node :node_name references org :org_node
   */
  public function nodeWithOrgRef($type, $node_name, $org_name) {
    $org_nodes = $this->getNodesByTitle('org_page', $org_name);
    if (!empty($org_nodes)) {
      $org_node = reset($org_nodes);
      $ref_field_name = 'field_organizations';
      // Several node types use a special field to handle setting the reference
      // values in field_organizations. See: mass_validation_entity_presave.
      $special_field_mapping = [
        'binder' => 'field_binder_ref_organization',
        'decision' => 'field_decision_ref_organization',
      ];
      if (isset($special_field_mapping[$type])) {
        $ref_field_name = $special_field_mapping[$type];
      }
      $node = Node::create([
        'type' => $type,
        'title' => $node_name,
        'status' => 1,
        $ref_field_name => [$org_node],
      ]);
      $node->save();
      // Register for deletion.
      $this->drupalContext->nodes[] = $node;
    }
  }

  /**
   * @Given the executive_order :node_name with org ref to :org_node
   */
  public function executiveOrderWithOrgRef($node_name, $org_name) {
    $org_nodes = $this->getNodesByTitle('org_page', $org_name);
    if (!empty($org_nodes)) {
      $org_node = reset($org_nodes);
      // Executive order is even more special than binder or decision for
      // field_organizations. It references orgs through an Issuer paragraph.
      // See: mass_validation_entity_presave.
      $issuer = Paragraph::create([
        'type' => 'issuer',
        'field_issuer_issuers' => $org_node
      ]);
      $node = Node::create([
        'type' => 'executive_order',
        'title' => $node_name,
        'status' => 1,
        'field_executive_order_issuer' => [$issuer],
      ]);
      $node->save();
      // Register for deletion.
      $this->drupalContext->nodes[] = $node;
    }
  }

  /**
   * @Given an event :title referencing :reftype :refname happening at :dateSpec
   */
  public function anEventItemReferencing($dateSpec, $title, $reftype, $refname) {
    $date = new \DateTime($dateSpec);
    $referenceds = $this->featureContext->getLastNodeByTitle($reftype, $refname);
    if ($referenceds) {
      $node = Node::create([
        'type' => 'event',
        'title' => $title,
        'status' => 1,
        'field_event_ref_parents' => [$referenceds],
        'field_event_date' => [
          'value' => $date->format('Y-m-d H:i:s'),
          'end_value' => $date->format('Y-m-d H:i:s'),
        ],
        'moderation_state' => 'published',
      ]);
      $node->save();
      // Register for deletion.
      $this->drupalContext->nodes[] = $node;
    }
  }

  /**
   * @Given I am viewing any :bundle node
   */
  public function viewAnyNode($bundle) {
    $ids = \Drupal::entityQuery('node')
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->sort('nid', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    if ($id = reset($ids)) {
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($id);

      $this->visitPath('/node/' . $node->id());
      $this->assertSession()->statusCodeEquals(200);
      return;
    }
    throw new \Exception(sprintf('Unable to find a published node for %s', $bundle));
  }

  /**
   * @Given I am viewing any :bundle media
   */
  public function viewAnyMedia($bundle) {
    $ids = \Drupal::entityQuery('media')
      ->condition('bundle', $bundle)
      ->condition('status', 1)
      ->sort('mid', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    if ($id = reset($ids)) {
      $media = \Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($id);
      $this->visitPath($media->toUrl()->toString());
      return;
    }

    throw new \Exception(sprintf('Unable to find a published media entity for %s', $bundle));
  }

  /**
   * @Given a curated list :title with an automatic section :label
   */
  public function aCuratedListWithAutomaticSection($title, $sectionLabel) {
    $terms = $this->getTermsByTitle('label', $sectionLabel);
    $term = reset($terms);

    $section = Paragraph::create([
      'type' => 'list_dynamic',
      'field_listdynamic_label' => [$term]
    ]);
    $node = Node::create([
      'type' => 'curated_list',
      'title' => $title,
      'status' => 1,
      'field_curatedlist_list_section' => [$section],
      'moderation_state' => 'published',
    ]);
    $node->save();
    $this->drupalContext->nodes[] = $node;
    $this->drupalContext->visitPath('/node/' . $node->id());
  }

  /**
   * @Then I should see the :code error page
   */
  public function assertErrorPage($code) {
    // Verify that the error page is shown.
    $this->assertSession()->elementTextContains('css', '.ma__error-page__type', $code);
    // But that the header and footer are not.
    $this->assertSession()->elementNotExists('css', '#header');
    $this->assertSession()->elementNotExists('css', '#footer');
  }

  /**
   * @Given I delete the :type :name
   */
  public function deleteNode($type, $name) {
    if($referenced = $this->getNodesByTitle($type, $name)) {
      $this->drupalContext->getDriver()->nodeDelete(reset($referenced));
    }
  }

  /**
   * Creates a 'page' type paragraph for the binder node.
   *
   * This function adds to the current binder node a paragraph that has a link
   * field and links to the node title parameter passed into this function.
   *
   * @Given I add a page paragraph that links to :nodeTitle
   *
   * @param string $nodeTitle
   *   Node title.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function iAddAPageParagraphThatLinksTo($nodeTitle) {

    $reference_node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $nodeTitle]);
    if (empty($reference_node)) {
      throw new \Exception('Reference node does not exist.');
    }
    $reference_node = reset($reference_node);

    $paragraph = Paragraph::create(['type' => 'page']);
    $paragraph->set('field_page_page', [
      'uri' => 'entity:node/' . $reference_node->id(),
      'title' => $reference_node->getTitle(),
    ]);
    $paragraph->save();

    if (empty($this->nodes)) {
      throw new \Exception('No nodes have been created yet.');
    }

    $latest_node = end($this->nodes);
    $node = Node::load($latest_node->nid);
    $node->get('field_binder_pages')->appendItem($paragraph);
    $node->save();
  }

}
