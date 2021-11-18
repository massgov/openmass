<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
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
  }

}
