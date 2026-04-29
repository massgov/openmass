<?php

namespace Drupal\Tests\mass_org_access\ExistingSite;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Verifies mass_org_access enforcement across every supported bundle.
 *
 * Blocks update/delete on content outside the user's organization while
 * leaving same-org content editable, across every supported node bundle and
 * media.document.
 *
 * @group mass_org_access
 */
class MassOrgAccessTest extends MassExistingSiteBase {

  use MediaCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * Node bundles exercised by this test.
   *
   * Every bundle that has field_content_organization AND that the editor
   * role can edit. Excluded: action, sitewide_alert, stacked_layout — the
   * editor role has no edit permission for these bundles, so our hook never
   * matters for them.
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
  private TermInterface $termA;
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
    $this->termA = $this->createTerm($vocab, [
      'name' => 'Test Term A ' . $this->randomMachineName(),
      'field_state_organization' => $this->orgPageA->id(),
    ]);
    $termB = $this->createTerm($vocab, [
      'name' => 'Test Term B ' . $this->randomMachineName(),
      'field_state_organization' => $this->orgPageB->id(),
    ]);

    $this->userA = $this->createUser();
    $this->userA->addRole('editor');
    $this->userA->set('field_user_org', $this->termA->id());
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
   * Roles that must not be blocked from cross-org view access.
   *
   * The mass_org_access module leaves view neutral for every role.
   */
  public static function viewRoleProvider(): array {
    return [
      'anonymous' => ['anonymous'],
      'authenticated' => ['authenticated'],
      'editor' => ['editor'],
      'author' => ['author'],
      'viewer' => ['viewer'],
      'mmg_editor' => ['mmg_editor'],
      'content_team' => ['content_team'],
      'bulk_edit' => ['bulk_edit'],
    ];
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

  /**
   * Test 5: VIEW must never be blocked by mass_org_access.
   *
   * Verified across anonymous, authenticated and every editorial role. The
   * user is set to Org A; the node is tagged with Org B — a strict cross-org
   * scenario.
   *
   * @dataProvider viewRoleProvider
   */
  public function testViewAccessNotBlockedByOrg(string $role): void {
    $node = $this->createTestNode('info_details', $this->orgPageB);
    $user = $this->createUserForRole($role);
    $this->assertTrue(
      $node->access('view', $user),
      sprintf('Role "%s" should be able to view a node in a different organization.', $role)
    );
  }

  private function createUserForRole(string $role) {
    if ($role === 'anonymous') {
      return new AnonymousUserSession();
    }
    $user = $this->createUser();
    if ($role !== 'authenticated') {
      $user->addRole($role);
    }
    if ($user->hasField('field_user_org')) {
      $user->set('field_user_org', $this->termA->id());
    }
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * Editor without field_user_org cannot update or delete any content.
   *
   * Even on entities tagged with their (would-be) org, an unassigned editor
   * is denied — the gate is "must have an org" before "must match the org".
   */
  public function testUserWithoutOrgIsDenied(): void {
    $no_org_user = $this->createUser();
    $no_org_user->addRole('editor');
    $no_org_user->activate();
    $no_org_user->save();

    $node = $this->createTestNode('info_details', $this->orgPageA);
    $this->assertFalse(
      $node->access('update', $no_org_user),
      'Editor without field_user_org must not update any node.'
    );
    $this->assertFalse(
      $node->access('delete', $no_org_user),
      'Editor without field_user_org must not delete any node.'
    );
    $this->assertTrue(
      $node->access('view', $no_org_user),
      'Editor without field_user_org may still view content.'
    );
  }

  /**
   * Users with bypass org access permission can edit any content.
   *
   * Validates the content_team escape hatch — the bundle-specific delete
   * permission is independent and not the concern of this test.
   */
  public function testBypassOrgAccessGrantsAllUpdates(): void {
    $bypass_user = $this->createUser();
    $bypass_user->addRole('content_team');
    $bypass_user->addRole('editor');
    $bypass_user->activate();
    $bypass_user->save();

    $cross_org_node = $this->createTestNode('info_details', $this->orgPageB);
    $this->assertTrue(
      $cross_org_node->access('update', $bypass_user),
      'content_team must update content from any organization.'
    );
  }

  /**
   * A user whose org is an ancestor of the content org may edit the content.
   *
   * The presave sync writes ancestor TIDs into field_content_organization, so
   * a parent-org user matches a child-org node via simple intersection.
   */
  public function testAncestorOrgUserCanUpdateChildOrgContent(): void {
    $child_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Child Org ' . $this->randomMachineName(),
      'field_parent' => ['target_id' => $this->orgPageA->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test child-org node ' . $this->randomMachineName(),
      'field_organizations' => [$child_org->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->assertTrue(
      $node->access('update', $this->userA),
      'User A (parent-org term) must update content tagged with their child org.'
    );
  }

  /**
   * A user assigned to multiple orgs can edit content tagged with any of them.
   *
   * The field_user_org is multi-valued; a user with org A and org B should
   * pass the access check on a node tagged with either org.
   */
  public function testMultiOrgUserCanUpdateAnyOfTheirOrgs(): void {
    $vocab = Vocabulary::load('user_organization');
    $termA = $this->getUserTermForOrg($this->orgPageA);
    $termB = $this->getUserTermForOrg($this->orgPageB);

    $multi_org_user = $this->createUser();
    $multi_org_user->addRole('editor');
    $multi_org_user->set('field_user_org', [
      ['target_id' => $termA->id()],
      ['target_id' => $termB->id()],
    ]);
    $multi_org_user->activate();
    $multi_org_user->save();

    $node_a = $this->createTestNode('info_details', $this->orgPageA);
    $node_b = $this->createTestNode('info_details', $this->orgPageB);

    $this->assertTrue(
      $node_a->access('update', $multi_org_user),
      'Multi-org user must update content tagged with their first org.'
    );
    $this->assertTrue(
      $node_b->access('update', $multi_org_user),
      'Multi-org user must update content tagged with their second org.'
    );
  }

  /**
   * A user with multiple orgs cannot edit content tagged with a third, unrelated org.
   *
   * Sanity check that adding orgs widens access only for those orgs, not blanket.
   */
  public function testMultiOrgUserStillBlockedFromUnrelatedOrg(): void {
    $third_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org C ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $vocab = Vocabulary::load('user_organization');
    $this->createTerm($vocab, [
      'name' => 'Test Term C ' . $this->randomMachineName(),
      'field_state_organization' => $third_org->id(),
    ]);
    $termA = $this->getUserTermForOrg($this->orgPageA);
    $termB = $this->getUserTermForOrg($this->orgPageB);

    $multi_org_user = $this->createUser();
    $multi_org_user->addRole('editor');
    $multi_org_user->set('field_user_org', [
      ['target_id' => $termA->id()],
      ['target_id' => $termB->id()],
    ]);
    $multi_org_user->activate();
    $multi_org_user->save();

    $third_node = $this->createTestNode('info_details', $third_org);

    $this->assertFalse(
      $third_node->access('update', $multi_org_user),
      'Multi-org user must not update content from an org they are not a member of.'
    );
  }

  /**
   * Editors must not be able to change their own field_user_org.
   *
   * Without a field-level access guard the entire org gate is bypassable —
   * any editor could open /user/UID/edit, append any org via autocomplete,
   * save, and instantly gain access to that org's content.
   */
  public function testEditorCannotChangeOwnFieldUserOrg(): void {
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->set('field_user_org', $this->termA->id());
    $editor->activate();
    $editor->save();

    $this->assertFalse(
      $editor->get('field_user_org')->access('edit', $editor),
      'An editor must not be able to change their own field_user_org.'
    );
    $this->assertTrue(
      $editor->get('field_user_org')->access('view', $editor),
      'An editor may still view their own field_user_org.'
    );
  }

  /**
   * Users with administer users permission can manage field_user_org.
   *
   * Required so admins / user managers can actually assign organizations.
   */
  public function testAdminCanChangeFieldUserOrg(): void {
    $user_manager = $this->createUser(['administer users']);
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->set('field_user_org', $this->termA->id());
    $editor->activate();
    $editor->save();

    $this->assertTrue(
      $editor->get('field_user_org')->access('edit', $user_manager),
      'A user with administer users must be able to assign organizations.'
    );
  }

  /**
   * Loads the user_organization term that maps to a given org_page node.
   *
   * Used in multi-org tests where termA / termB might be needed by reference,
   * by querying instead of relying on class properties.
   */
  private function getUserTermForOrg(NodeInterface $orgPage) {
    $tids = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', 'user_organization')
      ->condition('field_state_organization', $orgPage->id())
      ->range(0, 1)
      ->execute();
    return \Drupal\taxonomy\Entity\Term::load(reset($tids));
  }

  /**
   * Multi-org content is editable by users from any of the listed orgs.
   *
   * The field_organizations is multi-valued; an editor from any one of those
   * orgs should see access('update') = TRUE because their term intersects
   * the denormalized list.
   */
  public function testMultiOrgContentAllowsAnyMatchingOrgUser(): void {
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Multi-org node ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageA->id(), $this->orgPageB->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->assertTrue(
      $node->access('update', $this->userA),
      'User A must update a node tagged with both Org A and Org B.'
    );
    $this->assertTrue(
      $node->access('update', $this->userB),
      'User B must update a node tagged with both Org A and Org B.'
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
