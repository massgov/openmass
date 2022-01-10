<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Class EntityUsageTest.
 */
class EntityUsageTest extends ExistingSiteBase {

  use LoginTrait;
  use MediaCreationTrait;

  private $user;

  /**
   * Create the user.
   */
  protected function setUp() {
    parent::setUp();
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

    return [$media, $node_org, $node_curated_list];
  }

  /**
   * Assert that usage records are tracked properly.
   */
  public function testEntityUsageTracking() {

    list($media, $node_org, $node_curated_list) = $this->createEntities(MassModeration::UNPUBLISHED);
    // All created entities should be unused.
    $this->assertUsageRows($media, 0);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 0);

    list($media, $node_org, $node_curated_list) = $this->createEntities(MassModeration::TRASH);
    // All created entities should be unused.
    $this->assertUsageRows($media, 0);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 0);

    list($media, $node_org, $node_curated_list) = $this->createEntities(MassModeration::PREPUBLISHED_DRAFT);
    // Media and Org should have 1 reference.
    $this->assertUsageRows($media, 1);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 1);

    list($media, $node_org, $node_curated_list) = $this->createEntities(MassModeration::PUBLISHED);
    // Media and Org should have 1 reference.
    $this->assertUsageRows($media, 1);
    $this->assertUsageRows($node_curated_list, 0);
    $this->assertUsageRows($node_org, 1);
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
    $this->createNode([
      'type' => 'curated_list',
      'title' => 'Test Curated List',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_organizations' => $node_org->id(),
    ]);
    $this->drupalLogin($this->user);
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
