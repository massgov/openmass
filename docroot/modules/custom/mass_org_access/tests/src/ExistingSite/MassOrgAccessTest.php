<?php

namespace Drupal\Tests\mass_org_access\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Verifies that mass_org_access blocks update/delete on content outside the
 * user's organization while leaving same-org content editable, across every
 * supported node bundle and media.document.
 *
 * @group mass_org_access
 */
class MassOrgAccessTest extends MassExistingSiteBase {

  use MediaCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * Node bundles to exercise: every bundle that has
   * field_content_organization AND that the editor role can edit.
   *
   * Excluded: action, sitewide_alert, stacked_layout — the editor role has no
   * edit permission for these bundles, so our hook never matters for them.
   */
  private const NODE_BUNDLES = [
    'advisory', 'alert', 'binder', 'campaign_landing',
    'contact_information', 'curated_list', 'decision', 'decision_tree',
    'decision_tree_branch', 'decision_tree_conclusion', 'event',
    'executive_order', 'external_data_resource', 'fee', 'form_page',
    'glossary', 'guide_page', 'how_to_page', 'info_details', 'location',
    'location_details', 'news', 'org_page', 'person', 'regulation', 'rules',
    'service_page', 'topic_page',
  ];

  private const MEDIA_BUNDLES = ['document'];

  private NodeInterface $orgPageA;
  private NodeInterface $orgPageB;
  private UserInterface $userA;
  private UserInterface $userB;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->orgPageA = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org A ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->orgPageB = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org B ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $vocab = Vocabulary::load('user_organization');
    $termA = $this->createTerm($vocab, [
      'name' => 'Test Term A ' . $this->randomMachineName(),
      'field_state_organization' => $this->orgPageA->id(),
    ]);
    $termB = $this->createTerm($vocab, [
      'name' => 'Test Term B ' . $this->randomMachineName(),
      'field_state_organization' => $this->orgPageB->id(),
    ]);

    $this->userA = $this->createUser();
    $this->userA->addRole('editor');
    $this->userA->set('field_user_org', $termA->id());
    $this->userA->activate();
    $this->userA->save();

    $this->userB = $this->createUser();
    $this->userB->addRole('editor');
    $this->userB->set('field_user_org', $termB->id());
    $this->userB->activate();
    $this->userB->save();
  }

  public static function nodeBundleProvider(): array {
    return array_combine(
      self::NODE_BUNDLES,
      array_map(fn(string $b) => [$b], self::NODE_BUNDLES)
    );
  }

  public static function mediaBundleProvider(): array {
    return array_combine(
      self::MEDIA_BUNDLES,
      array_map(fn(string $b) => [$b], self::MEDIA_BUNDLES)
    );
  }

  /**
   * Test 1: User can update a node tagged with their own organization.
   *
   * @dataProvider nodeBundleProvider
   */
  public function testUserCanUpdateNodeInOwnOrg(string $bundle): void {
    $node = $this->createTestNode($bundle, $this->orgPageA);
    $this->assertTrue(
      $node->access('update', $this->userA),
      sprintf('User A should be able to update a %s node in their own organization.', $bundle)
    );
  }

  /**
   * Test 2: User cannot update a node tagged with a different organization.
   *
   * @dataProvider nodeBundleProvider
   */
  public function testUserCannotUpdateNodeInOtherOrg(string $bundle): void {
    $node = $this->createTestNode($bundle, $this->orgPageB);
    $this->assertFalse(
      $node->access('update', $this->userA),
      sprintf('User A should not be able to update a %s node in a different organization.', $bundle)
    );
    $this->assertFalse(
      $node->access('delete', $this->userA),
      sprintf('User A should not be able to delete a %s node in a different organization.', $bundle)
    );
    $this->assertTrue(
      $node->access('view', $this->userA),
      sprintf('User A should still be able to view a %s node in a different organization.', $bundle)
    );
  }

  /**
   * Test 3: User can update a media tagged with their own organization.
   *
   * @dataProvider mediaBundleProvider
   */
  public function testUserCanUpdateMediaInOwnOrg(string $bundle): void {
    $media = $this->createTestMedia($bundle, $this->orgPageB);
    $this->assertTrue(
      $media->access('update', $this->userB),
      sprintf('User B should be able to update a %s media in their own organization.', $bundle)
    );
  }

  /**
   * Test 4: User cannot update a media tagged with a different organization.
   *
   * @dataProvider mediaBundleProvider
   */
  public function testUserCannotUpdateMediaInOtherOrg(string $bundle): void {
    $media = $this->createTestMedia($bundle, $this->orgPageA);
    $this->assertFalse(
      $media->access('update', $this->userB),
      sprintf('User B should not be able to update a %s media in a different organization.', $bundle)
    );
    $this->assertFalse(
      $media->access('delete', $this->userB),
      sprintf('User B should not be able to delete a %s media in a different organization.', $bundle)
    );
  }

  private function createTestNode(string $bundle, NodeInterface $orgPage): NodeInterface {
    return $this->createNode([
      'type' => $bundle,
      'title' => 'Test ' . $bundle . ' ' . $this->randomMachineName(),
      'field_organizations' => [$orgPage->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  private function createTestMedia(string $bundle, NodeInterface $orgPage) {
    $uri = 'public://test-' . $this->randomMachineName() . '.txt';
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);
    file_put_contents(\Drupal::service('file_system')->realpath($uri), 'test');

    return $this->createMedia([
      'name' => 'Test ' . $bundle . ' ' . $this->randomMachineName(),
      'bundle' => $bundle,
      'field_upload_file' => ['target_id' => $file->id()],
      'field_organizations' => [$orgPage->id()],
      'status' => 1,
      'moderation_state' => 'published',
    ]);
  }

}
