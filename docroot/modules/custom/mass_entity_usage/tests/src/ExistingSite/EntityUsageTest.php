<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Class EntityUsageTest.
 */
class EntityUsageTest extends ExistingSiteBase {

  use LoginTrait;

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
  }

  /**
   * Assert that usage records are tracked properly.
   */
  public function testEntityUsageTracking() {
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
      'moderation_state' => MassModeration::UNPUBLISHED,
      'field_organizations' => $node_org->id(),
    ]);

    // Verify an Unpublished source does not create a usage record.
    $node_org_usage = \Drupal::service('entity_usage.usage')->listUniqueSourcesCount($node_org);
    // The referenced_organizations computed field on org_page creates a record.
    $this->assertEquals(1, $node_org_usage);

    // Verify a Trash source does not create a usage record.
    $node_curated_list->set('moderation_state', MassModeration::TRASH)->save();
    $node_org_usage = \Drupal::service('entity_usage.usage')->listUniqueSourcesCount($node_org);
    $this->assertEquals(1, $node_org_usage);

    // Verify a Draft source creates a usage record.
    $node_curated_list->set('moderation_state', MassModeration::DRAFT)->save();
    $node_org_usage = \Drupal::service('entity_usage.usage')->listUniqueSourcesCount($node_org);
    $this->assertEquals(2, $node_org_usage);

    // Verify a Prepublished Draft source creates a usage record.
    $node_curated_list->set('moderation_state', MassModeration::PREPUBLISHED_DRAFT)->save();
    $node_org_usage = \Drupal::service('entity_usage.usage')->listUniqueSourcesCount($node_org);
    $this->assertEquals(2, $node_org_usage);

    // Verify a Published source creates a usage record.
    $node_curated_list->set('moderation_state', MassModeration::PUBLISHED)->save();
    $node_org_usage = \Drupal::service('entity_usage.usage')->listUniqueSourcesCount($node_org);
    $this->assertEquals(2, $node_org_usage);
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
    $node_curated_list = $this->createNode([
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
                            <th>ID</th>
                            <th>Content Type</th>
                            <th>Field name</th>
                            <th>Status</th>
              </tr>
    </thead>';
    $this->assertStringContainsString($table_headers, $page, 'Usage page table headers are not found.');
  }

}
