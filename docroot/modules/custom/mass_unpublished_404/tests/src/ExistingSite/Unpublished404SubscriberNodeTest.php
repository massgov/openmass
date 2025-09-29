<?php
declare(strict_types=1);

namespace Drupal\Tests\mass_unpublished_404\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;

/**
 * @group mass_unpublished_404
 *
 * Verifies 403→404 conversion for unpublished service_page (anon only).
 */
class Unpublished404SubscriberNodeTest extends MassExistingSiteBase {

  use NodeCreationTrait;

  protected UserInterface $admin;
  protected NodeInterface $serviceNode;

  protected function setUp(): void {
    parent::setUp();

    // Admin (can view unpublished).
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->admin = $admin;

    // Create one reusable service_page.
    $node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Unpublished 404 Test Page',
      'status' => 0,
      'moderation_state' => MassModeration::DRAFT,
    ]);
    $this->serviceNode = $node;
    $this->markEntityForCleanup($this->serviceNode);
  }

  /**
   * Force the shared service_page into a specific state.
   * Uses content moderation when present.
   */
  private function setNodeState(string $state): NodeInterface {
    $node = $this->serviceNode;
    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', $state);
      $node->set('status', $state === MassModeration::PUBLISHED ? 1 : 0);
    }
    $node->save();
    // Refresh in-memory reference after save.
    $this->serviceNode = $node;
    return $this->serviceNode;
  }

  private function serviceUrl(): string {
    return $this->serviceNode->toUrl('canonical')->toString();
  }

  /**
   * Ensure anon gets 404 when visiting unpublished service_page.
   */
  public function testAnonGets404OnUnpublishedServicePage(): void {
    $this->setNodeState(MassModeration::DRAFT);

    $this->drupalGet($this->serviceUrl());
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Ensure anon sees 200 for published service_page.
   */
  public function testAnonSees200OnPublishedServicePage(): void {
    $this->setNodeState(MassModeration::PUBLISHED);

    $this->drupalGet($this->serviceUrl());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Ensure editor/admin can access unpublished service_page (200).
   */
  public function testEditorSees200OnUnpublishedServicePage(): void {
    $this->setNodeState(MassModeration::DRAFT);

    $this->drupalLogin($this->admin);
    $this->drupalGet($this->serviceUrl());
    $this->assertSession()->statusCodeEquals(200);
  }

}
