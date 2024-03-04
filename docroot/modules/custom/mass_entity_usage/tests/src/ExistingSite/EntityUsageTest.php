<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use DrupalTest\QueueRunnerTrait\QueueRunnerTrait;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\LoginTrait\LoginTrait;

/**
 * Class EntityUsageTest.
 */
class EntityUsageTest extends MassExistingSiteBase {

  use LoginTrait;
  use MediaCreationTrait;
  use QueueRunnerTrait;

  /**
   * Administrator that does the tests on the UI.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * Create the user.
   */
  protected function setUp(): void {
    parent::setUp();

    $GLOBALS['config']['entity_usage_queue_tracking.settings']['queue_tracking'] = TRUE;
    $this->container->get('config.factory')->clearStaticCache();

    // Remove everything from the entity_usage table
    // to avoid long cleaning times that break this test.
    // Note this avoids triggering events on bulk deletes, such as at
    // \Drupal\entity_usage\EntityUsage::bulkDeleteTargets().
    \Drupal::service('database')->truncate('entity_usage')->execute();

    $this->emptyEntityUsageQueues();
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->addRole('administrator');
    $user->activate();
    $user->save();
    $this->user = $user;
    $this->drupalLogin($user);
  }

  /**
   * Counts how many usage rows an entity has on its /mass-usage page.
   */
  private function countUsageRows($entity) {
    /** @var \Drupal\Core\Entity\EntityBase $entity */
    $this->drupalGet($entity->getEntityType()->id() . '/' . $entity->id() . '/mass-usage');
    $table = $this->getCurrentPage()->find('css', 'main table');
    if ($table->find('css', 'td.empty.message')) {
      return 0;
    }
    return count($table->findAll('css', 'tbody tr td a'));
  }

  /**
   * Asserts how many usage rows an entity has on its /mass-usage page.
   */
  private function assertUsageRows($entity, $usage_expected) {
    $usage_actual = $this->countUsageRows($entity);
    $this->assertEquals($usage_expected, $usage_actual, 'Usage is ' . $usage_actual . ' instead of ' . $usage_expected);
  }

  /**
   * Creates 1 media, 1 org_page and 1 curated_list referencing the org_page.
   */
  private function createEntities($curated_list_state) {
    // Create a file to upload.
    $destination = 'public://file-001.txt';
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();
    $src = 'modules/custom/mass_entity_usage/tests/fixtures/file-001.txt';

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->copy($src, $destination, TRUE);

    // Create media item.
    $media = $this->createMedia([
      'title' => 'Llama',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
    ]);

    $node_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Organization',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $node_curated_list = $this->createNode([
      'type' => 'curated_list',
      'title' => 'Test Curated List',
      'uid' => $this->user->id(),
      'moderation_state' => $curated_list_state,
      'field_organizations' => $node_org->id(),
      'field_curatedlist_overview' => [
        'value' => $media->toLink()->toString(),
        'format' => 'basic_html',
      ],
    ]);

    // Get last created node.
    $res = \Drupal::entityQuery('node')->accessCheck(FALSE)->sort('nid', 'DESC')->range(0, 1)->execute();
    $nid = reset($res);
    $node_curated_list = Node::load($nid);

    return [$media, $node_org, $node_curated_list];
  }

  /**
   * Empties entity usage related queues.
   */
  private function emptyEntityUsageQueues() {
    $this->clearQueue('entity_usage_tracker');
    $this->clearQueue('entity_usage_regenerate_queue');
    \Drupal::service('entity_usage_queue_tracking.clean_usage_table')->clean(['pointing' => TRUE]);
  }

  /**
   * Process entity usage related queues.
   */
  private function processEntityUsageQueues() {
    $this->runQueue('entity_usage_tracker');
    \Drupal::service('entity_usage_queue_tracking.clean_usage_table')->clean(['pointing' => TRUE]);
  }

  /**
   * Assert that usage records are tracked properly.
   */
  public function testEntityUsageTracking() {
    [$media, $node_org, $node_curated_list] = $this->createEntities(MassModeration::PREPUBLISHED_NEEDS_REVIEW);
    $this->processEntityUsageQueues();
    // All created entities should be unused.
    $this->assertUsageRows($media, 0);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 0);

    [$media, $node_org, $node_curated_list] = $this->createEntities(MassModeration::TRASH);
    $this->processEntityUsageQueues();
    // All created entities should be unused.
    $this->assertUsageRows($media, 0);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 0);

    [$media, $node_org, $node_curated_list] = $this->createEntities(MassModeration::PREPUBLISHED_DRAFT);
    $this->processEntityUsageQueues();
    // All created entities should be unused.
    $this->assertUsageRows($media, 0);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 0);

    [$media, $node_org, $node_curated_list] = $this->createEntities(MassModeration::PUBLISHED);
    $this->processEntityUsageQueues();
    // Media and Org should have 1 reference.
    $this->assertUsageRows($media, 1);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 1);

    [$media, $node_org, $node_curated_list] = $this->createEntities(MassModeration::UNPUBLISHED);
    $this->processEntityUsageQueues();
    // All created entities should be unused.
    $this->assertUsageRows($media, 0);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 0);
  }

  /**
   * Usage count checks when updating and deleting referencing entities.
   */
  public function testUsageCountWhenEditingAndDeletingReferences() {
    // Create node1.
    $node_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Organization - Node 1',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->processEntityUsageQueues();
    // Check usages = 0.
    $this->assertUsageRows($node_org, 0);

    // Create node2 linking node1.
    $node_curated_list_1 = $this->createNode([
      'type' => 'curated_list',
      'title' => 'Test Curated List - Node 2',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_curatedlist_overview' => [
        'value' => $node_org->toLink()->toString(),
        'format' => 'basic_html',
      ],
    ]);
    $this->processEntityUsageQueues();
    // Usage for node1 should be 1.
    $this->assertUsageRows($node_org, 1);

    // Create node3, linking node1.
    $node_curated_list_2 = $this->createNode([
      'type' => 'curated_list',
      'title' => 'Test Curated List - Node 3',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_curatedlist_overview' => [
        'value' => $node_org->toLink()->toString(),
        'format' => 'basic_html',
      ],
    ]);
    $this->processEntityUsageQueues();
    // Usage for node1 should be 2.
    $this->assertUsageRows($node_org, 2);

    // Remove link from Node3 to Node1.
    $node_curated_list_2->field_curatedlist_overview->setValue(NULL);
    $node_curated_list_2->save();
    $this->processEntityUsageQueues();
    // Usage for node1 should be 1.
    $this->assertUsageRows($node_org, 1);

    // Delete node2.
    $node_curated_list_1->delete();
    $this->processEntityUsageQueues();
    // Usage for node1 should be 0.
    $this->assertUsageRows($node_org, 0);
  }

  /**
   * Tests multiple references from one entity to another entity.
   */
  public function testCuratedList() {
    $new_test_org = $this->createNode([
      'title' => 'TestOrg',
      'type' => 'org_page',
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
    ]);

    $this->createNode([
      'title' => 'Curated list with references to an Organization.',
      'type' => 'curated_list',
      'field_curatedlist_overview' => [
        'value' => $new_test_org->toLink()->toString(),
        'format' => 'full_html',
      ],
      'field_primary_parent' => $new_test_org->id(),
      'field_organizations' => $new_test_org->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
    ]);

    $this->processEntityUsageQueues();

    // Showing only 1 reference is correct.
    // @see Drupal\entity_usage\EntityUsage::listUniqueSourcesCount
    $this->assertUsageRows($new_test_org, 1);
  }

  /**
   * Creates an organization with nested paragpraphs.
   */
  private function createOrganizationWithNestedParagraphs($topic_page_link, $state) {
    $rich_text = Paragraph::create([
      'type' => 'rich_text',
      'field_body' => [
        'value' => $topic_page_link,
        'format' => 'basic_html',
      ],
    ]);

    $organization_section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [
        $rich_text,
      ],
    ]);

    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
      'moderation_state' => $state,
      'field_organization_sections' => [$organization_section],
    ]);
    return $org_node;
  }

  /**
   * Creates and returns a topic page node.
   */
  private function createTopicPage($state) {
    $node = $this->createNode([
      'type' => 'topic_page',
      'title' => 'Test',
      'field_topic_lede' => 'Short description',
      'moderation_state' => $state,
    ]);

    return $node;
  }

  /**
   * Tests entity usage tracking with nested paragraphs.
   */
  public function testEntityUsageComplexStrucure() {
    $topic_page_node = $this->createTopicPage(MassModeration::PUBLISHED);
    $topic_page_link = '<a href="' . $topic_page_node->toUrl()->toString() . '">LINK</a>';
    $this->createOrganizationWithNestedParagraphs($topic_page_link, MassModeration::PUBLISHED);
    $this->processEntityUsageQueues();
    $this->assertUsageRows($topic_page_node, 1);

    $topic_page_node = $this->createTopicPage(MassModeration::UNPUBLISHED);
    $topic_page_link = '<a href="' . $topic_page_node->toUrl()->toString() . '">LINK</a>';
    $this->createOrganizationWithNestedParagraphs($topic_page_link, MassModeration::UNPUBLISHED);
    $this->processEntityUsageQueues();
    $this->assertUsageRows($topic_page_node, 0);
  }

  /**
   * Assert that the usage page displays properly.
   */
  public function testEntityUsagePage() {
    $node_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Organization',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->drupalLogin($this->user);

    $this->visit('/node/add/curated_list');
    $this->getCurrentPage()->fillField('Title', 'Test Curated List');
    $this->getCurrentPage()->fillField('Short title', 'Test Curated List Short Title');
    $this->getCurrentPage()->fillField('Short description', 'Test Curated List Short Description');
    $this->getCurrentPage()->fillField('Parent page', 'Test Organization (' . $node_org->id() . ') - Organization');
    $this->getCurrentPage()->fillField('Organization(s)', 'Test Organization (' . $node_org->id() . ') - Organization');
    $this->getCurrentPage()->fillField('Save as', MassModeration::PUBLISHED);
    $this->getCurrentPage()->pressButton('Save');
    $this->htmlOutput();
    $this->getCurrentPage()->hasContent('Curated List Test Curated List has been created.');
    $this->processEntityUsageQueues();

    $this->visit($node_org->toUrl()->toString() . '/mass-usage');
    // Verify the usage tab is reachable.
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    // Verify the usage tab contents.
    $page = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('Test Curated List', $page, 'Test Curated List not found on usage page.');
    $table_caption = '<caption>The list below shows pages that include a link to this page in structured and rich text fields. <a href="https://massgovdigital.gitbook.io/knowledge-base/content-improvement-tools/pages-linking-here">Learn how to use Linking Pages.</a></caption>';
    $this->assertStringContainsString($table_caption, $page, 'Table caption not found on usage page.');
    $table_headers = '<thead>
      <tr>
                            <th>Entity</th>
                            <th>Content Type</th>
                            <th>Field name</th>
                            <th>Status</th>
              </tr>
    </thead>';
    $this->assertStringContainsString($table_headers, $page, 'Usage page table headers are not found.');
  }

}
