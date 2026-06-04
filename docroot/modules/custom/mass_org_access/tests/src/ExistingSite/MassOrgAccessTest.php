<?php

namespace Drupal\Tests\mass_org_access\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_org_access\OrgAccessSettings;
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
   * role can edit.
   *
   * Excluded for permission reasons: action, sitewide_alert, stacked_layout
   * (editor has no edit permission). Excluded for scope reasons:
   * executive_order, sitewide_alert, stacked_layout (field intentionally
   * not attached per DP-45788 scope). Excluded for derived-org reasons:
   * binder, decision, person (mass_validation derives field_organizations
   * from a bundle-specific reference field on presave; setting
   * field_organizations directly in tests is overwritten). Excluded as
   * source-of-truth: org_page (Owner Groups maintained manually by the
   * content team; backfill skips).
   */
  private const NODE_BUNDLES = [
    'advisory', 'alert', 'campaign_landing',
    'contact_information', 'curated_list', 'decision_tree',
    'decision_tree_branch', 'decision_tree_conclusion', 'event',
    'external_data_resource', 'fee', 'form_page',
    'glossary', 'guide_page', 'how_to_page', 'info_details', 'location',
    'location_details', 'news', 'regulation', 'rules',
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

    // Tests assert the gated behavior; flip the feature switch on for the
    // duration of the test. tearDown() resets it. State is used (not env)
    // because it is shared between the test process and the webserver
    // process that handles DTT HTTP requests.
    \Drupal::state()->set('mass_org_access.enforce', TRUE);

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

    // Mimic the content team's manual population of Owner Groups on
    // org_page nodes (REQUIREMENTS.md Section C). The drush moab backfill
    // copies these values onto every content entity that references the
    // org_page.
    $this->setOrgPageOwnerGroups($this->orgPageA, [$this->termA->id()]);
    $this->setOrgPageOwnerGroups($this->orgPageB, [$termB->id()]);

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

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::state()->delete('mass_org_access.enforce');
    \Drupal::state()->delete(OrgAccessSettings::DEBUG_STATE_KEY);
    parent::tearDown();
  }

  /**
   * Manually sets Organization Owner Groups on an org_page.
   *
   * In production the content team populates this field by hand with the
   * matching user_organization term plus all ancestor terms. The drush
   * moab backfill then copies the value verbatim onto downstream content.
   */
  private function setOrgPageOwnerGroups(NodeInterface $orgPage, array $tids): void {
    $orgPage->set(
      'field_content_organization',
      array_map(fn($tid) => ['target_id' => $tid], $tids)
    );
    $orgPage->setNewRevision(FALSE);
    $orgPage->setSyncing(TRUE);
    $orgPage->save();
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
   * A user whose org is a CHILD of the content org cannot edit the content.
   *
   * Mirror of testAncestorOrgUserCanUpdateChildOrgContent — explicit example
   * from the spec: page set for "EOHHS" must NOT be savable by an editor
   * with only "Department of Public Health" assigned. Inheritance is
   * upstream-only (parent-org users access child content), not downstream.
   */
  public function testChildOrgUserCannotUpdateParentOrgContent(): void {
    $child_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Child Org ' . $this->randomMachineName(),
      'field_parent' => ['target_id' => $this->orgPageA->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $vocab = Vocabulary::load('user_organization');
    $child_term = $this->createTerm($vocab, [
      'name' => 'Child Term ' . $this->randomMachineName(),
      'field_state_organization' => $child_org->id(),
    ]);

    $child_user = $this->createUser();
    $child_user->addRole('editor');
    $child_user->set('field_user_org', $child_term->id());
    $child_user->activate();
    $child_user->save();

    // Parent-org content should remain off-limits to a child-org user.
    $parent_node = $this->createTestNode('info_details', $this->orgPageA);
    $this->assertFalse(
      $parent_node->access('update', $child_user),
      'A child-org user must NOT be able to update parent-org content.'
    );
    $this->assertTrue(
      $parent_node->access('view', $child_user),
      'View access remains neutral regardless of org direction.'
    );
  }

  /**
   * A user whose org is an ancestor of the content org may edit the content.
   *
   * The content team curates each org_page's Permission Groups with the org's
   * term plus its ancestors (the hierarchy picker adds parents). Backfill
   * copies that field verbatim, so content tagged with a child org carries the
   * child term AND its parent term, and a parent-org user matches via simple
   * intersection without runtime traversal.
   */
  public function testAncestorOrgUserCanUpdateChildOrgContent(): void {
    $child_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Child Org ' . $this->randomMachineName(),
      'field_parent' => ['target_id' => $this->orgPageA->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $vocab = Vocabulary::load('user_organization');
    // The child term sits UNDER termA (userA's org) in the taxonomy. The
    // content team curates the child org_page's Permission Groups with the
    // child term plus its ancestor termA (their hierarchy picker adds the
    // parents), so content tagged with the child org carries termA and userA
    // can edit it.
    $child_term = $this->createTerm($vocab, [
      'name' => 'Child Term ' . $this->randomMachineName(),
      'parent' => [$this->termA->id()],
    ]);
    $this->setOrgPageOwnerGroups($child_org, [$child_term->id(), $this->termA->id()]);

    $node = $this->createTestNode('info_details', $child_org);

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
    $third_term = $this->createTerm($vocab, [
      'name' => 'Test Term C ' . $this->randomMachineName(),
      'field_state_organization' => $third_org->id(),
    ]);
    $this->setOrgPageOwnerGroups($third_org, [$third_term->id()]);
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
   * Backfill copies the Permission Groups curated on the referenced org_page.
   *
   * The command reads each org node in Organization(s) and copies that
   * org_page's own field_content_organization onto the entity. This test makes
   * the org_page-curated value and a field_state_organization-mapped term
   * diverge to prove the direct org_page value wins.
   */
  public function testBackfillCopiesOrgPagePermissionGroups(): void {
    $org_page = $this->createNode([
      'type' => 'org_page',
      'title' => 'Direct Org ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $vocab = Vocabulary::load('user_organization');
    // The org_page's own curated Permission Groups — the direct source.
    $curated_term = $this->createTerm($vocab, [
      'name' => 'Curated Term ' . $this->randomMachineName(),
    ]);
    $this->setOrgPageOwnerGroups($org_page, [$curated_term->id()]);
    // A term mapped to the org via field_state_organization but absent from
    // the org_page's own field. The direct lookup must ignore it.
    $mapped_term = $this->createTerm($vocab, [
      'name' => 'Mapped Term ' . $this->randomMachineName(),
      'field_state_organization' => $org_page->id(),
    ]);

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Direct lookup test node ' . $this->randomMachineName(),
      'field_organizations' => [$org_page->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    \Drupal::service('mass_org_access.org_access_checker')
      ->populateOwnerGroupsFromOrganizations($node);

    $tids = array_map(
      'strval',
      array_column($node->get('field_content_organization')->getValue(), 'target_id')
    );
    $this->assertEqualsCanonicalizing(
      [(string) $curated_term->id()],
      $tids,
      "Backfill must copy the org_page's own Permission Groups value."
    );
    $this->assertNotContains(
      (string) $mapped_term->id(),
      $tids,
      'Backfill must ignore terms mapped only via field_state_organization.'
    );
  }

  /**
   * Backfill leaves the entity untouched when its org_page has no groups.
   *
   * An entity with no Permission Groups is editable only by admins. If the
   * referenced org_page carries no Permission Groups on its own
   * field_content_organization, the direct lookup yields nothing and backfill
   * must leave the field empty — preserving the content team's staged rollout.
   */
  public function testBackfillSkipsEntityWhenNoOrgMapping(): void {
    $org_page = $this->createNode([
      'type' => 'org_page',
      'title' => 'Unmapped Org ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    // This org_page has no Permission Groups on its own
    // field_content_organization, so the direct lookup finds nothing.
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Unmapped test node ' . $this->randomMachineName(),
      'field_organizations' => [$org_page->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    \Drupal::service('mass_org_access.org_access_checker')
      ->populateOwnerGroupsFromOrganizations($node);

    $this->assertEmpty(
      $node->get('field_content_organization')->getValue(),
      'Backfill must leave Permission Groups empty when no term maps to the org.'
    );
  }

  /**
   * Backfill populates a forward (unpublished) draft, not just the published.
   *
   * Edit access is checked against the latest revision, so the draft must
   * also receive Permission Groups — otherwise its rightful editors are
   * locked out and publishing it would wipe the backfilled value.
   */
  public function testBackfillPopulatesForwardDraft(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Forward draft backfill ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageA->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $nid = $node->id();
    $published_vid = (int) $node->getRevisionId();

    // Create a forward (unpublished) draft; the published revision stays the
    // default.
    $node->set('moderation_state', 'draft');
    $node->set('title', 'Forward draft backfill (draft) ' . $this->randomMachineName());
    $node->save();
    $draft_vid = (int) $storage->getLatestRevisionId($nid);
    $this->assertNotSame($published_vid, $draft_vid, 'A forward draft revision must exist.');

    // Backfill the default (published) revision; the runner must also fill
    // the forward draft.
    $storage->resetCache([$nid]);
    \Drupal::service('mass_org_access.backfill_runner')->backfillEntity($storage->load($nid));

    $storage->resetCache([$nid]);
    $published_pg = array_map('strval', array_column(
      $storage->loadRevision($published_vid)->get('field_content_organization')->getValue(),
      'target_id'
    ));
    $draft_pg = array_map('strval', array_column(
      $storage->loadRevision($draft_vid)->get('field_content_organization')->getValue(),
      'target_id'
    ));

    $this->assertContains(
      (string) $this->termA->id(),
      $published_pg,
      'Backfill must populate the published revision.'
    );
    $this->assertContains(
      (string) $this->termA->id(),
      $draft_pg,
      'Backfill must also populate the forward draft revision.'
    );
  }

  /**
   * Permission Groups is required on org_page, optional on other bundles.
   *
   * Authors must populate it when creating an Organization page; everywhere
   * else it stays optional (auto-derived from Organization(s)).
   */
  public function testPermissionGroupsRequiredOnOrgPageOnly(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $org_page = $storage->create([
      'type' => 'org_page',
      'title' => 'Required check org ' . $this->randomMachineName(),
    ]);
    $this->assertGreaterThan(
      0,
      $org_page->validate()->getByField('field_content_organization')->count(),
      'Permission Groups must be required on org_page.'
    );

    $info = $storage->create([
      'type' => 'info_details',
      'title' => 'Required check info ' . $this->randomMachineName(),
    ]);
    $this->assertSame(
      0,
      $info->validate()->getByField('field_content_organization')->count(),
      'Permission Groups must not be required on info_details.'
    );
  }

  /**
   * Side-door write routes are blocked for users without org access.
   *
   * The children-reorder, move-children, and redirects routes used to
   * require only `node.view`, so hook_node_access never gated them.
   * RouteSubscriber raises the requirement to `node.update`, routing
   * the decision through mass_org_access like the canonical edit form.
   */
  public function testSideDoorWriteRoutesBlockedWithoutOrgAccess(): void {
    // /node/X/redirects is reachable only on trashed nodes per
    // mass_redirects' _entity_is_state requirement, so flip the state
    // explicitly. orgPageB carries termB; user A has only termA → denied.
    $trashed = $this->createTestNode('info_details', $this->orgPageB);
    $trashed->set('moderation_state', MassModeration::TRASH)->save();

    $this->assertFalse(
      Url::fromRoute('entity.node.redirects', ['node' => $trashed->id()])->access($this->userA),
      '/node/X/redirects must be denied for a user without org access.'
    );
    $this->assertFalse(
      Url::fromRoute('entity.node.entity_hierarchy_reorder', ['node' => $this->orgPageB->id()])->access($this->userA),
      '/node/X/children must be denied for a user without org access.'
    );
    $this->assertFalse(
      Url::fromRoute('view.change_parents.page_1', ['node' => $this->orgPageB->id()])->access($this->userA),
      '/node/X/move-children must be denied for a user without org access.'
    );
  }

  /**
   * Entities with no Owner Groups are denied to regular editors.
   *
   * REQUIREMENTS.md "Done when" point 6: nodes and media with no
   * Organization Owner Groups value are editable only by admins and
   * content admins. The positive case (bypass user can still edit)
   * is covered by testBypassOrgAccessGrantsAllUpdates with OOG set;
   * for empty OOG the bypass branch returns neutral and the bundle's
   * default permissions decide — outside this module's contract.
   */
  public function testEmptyOwnerGroupsDeniedForRegularEditors(): void {
    // Build a node, then strip Owner Groups to simulate un-curated content.
    $node = $this->createTestNode('info_details', $this->orgPageA);
    $node->set('field_content_organization', []);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();

    $this->assertFalse(
      $node->access('update', $this->userA),
      'Empty Owner Groups must be denied for regular editors per spec point 6.'
    );
    $this->assertFalse(
      $node->access('delete', $this->userA),
      'Empty Owner Groups must also block delete for regular editors.'
    );
  }

  /**
   * OrgAccessSettings reads MASS_ORG_ACCESS_ENFORCE and the State fallback.
   *
   * Env var wins over State. Both default to OFF when unset.
   */
  public function testOrgAccessSettingsParsesSwitchSources(): void {
    $settings = \Drupal::service('mass_org_access.settings');
    $state = \Drupal::state();

    // Wipe both layers.
    putenv('MASS_ORG_ACCESS_ENFORCE');
    $state->delete('mass_org_access.enforce');
    $this->assertFalse($settings->isEnforcementEnabled(), 'Both unset → OFF.');

    // State alone.
    $state->set('mass_org_access.enforce', TRUE);
    $this->assertTrue($settings->isEnforcementEnabled(), 'State TRUE → ON.');
    $state->set('mass_org_access.enforce', FALSE);
    $this->assertFalse($settings->isEnforcementEnabled(), 'State FALSE → OFF.');

    // Env wins regardless of State.
    $state->set('mass_org_access.enforce', FALSE);
    foreach (['1', 'true', 'TRUE', 'yes', 'on'] as $truthy) {
      putenv('MASS_ORG_ACCESS_ENFORCE=' . $truthy);
      $this->assertTrue(
        $settings->isEnforcementEnabled(),
        sprintf('Env "%s" → ON (overrides State FALSE).', $truthy)
      );
    }
    putenv('MASS_ORG_ACCESS_ENFORCE');

    // Restore the value setUp() set so the rest of tearDown() is clean.
    $state->set('mass_org_access.enforce', TRUE);
  }

  /**
   * With the feature switch off, the org gate is silent.
   *
   * Release 1 ships the module without enforcement. User A (termA) on a
   * node tagged with orgPageB (termB) would normally be denied; with
   * the switch off, mass_org_access returns neutral and Drupal's
   * default `administer nodes` perm on the editor role lets the user
   * through.
   */
  public function testEnforcementSwitchControlsBlocking(): void {
    $node = $this->createTestNode('info_details', $this->orgPageB);

    // Sanity: with the switch ON (set in setUp()), user A is blocked.
    $this->assertFalse(
      $node->access('update', $this->userA),
      'Sanity: enforcement ON blocks cross-org update.'
    );

    // Flip switch OFF via State; reset entity access cache so the next
    // access() call re-runs the hook.
    \Drupal::state()->set('mass_org_access.enforce', FALSE);
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();

    $this->assertTrue(
      $node->access('update', $this->userA),
      'Enforcement OFF must let user A through despite the org mismatch.'
    );
  }

  /**
   * The "Add Collections" VBO actions respect mass_org_access.
   *
   * Both mass_views actions previously bypassed their own access check
   * (compared an EntityType object to the string 'node'/'media' which
   * never matched, falling through to AccessResult::allowed()). With
   * the typo fixed, the actions defer to $entity->access('update'),
   * which routes through mass_org_access.
   *
   * Positive case is not asserted here: the action also requires
   * status field edit access, which is admin-only by design.
   */
  public function testChangeCollectionsActionRespectsOrgAccess(): void {
    $manager = \Drupal::service('plugin.manager.action');
    $node = $this->createTestNode('info_details', $this->orgPageB);

    $this->assertFalse(
      $manager->createInstance('mass_views_change_collections')->access($node, $this->userA),
      'ChangeCollections must deny user A (termA) on a node tagged with orgPageB.'
    );

    $media = $this->createTestMedia('document', $this->orgPageB);
    $this->assertFalse(
      $manager->createInstance('mass_views_add_documents_collections')->access($media, $this->userA),
      'AddCollectionsDocuments must deny user A (termA) on media tagged with orgPageB.'
    );
  }

  /**
   * Side-door write routes remain open to a user with matching org.
   *
   * Same routes / same node as the negative test, but the user's
   * `field_user_org` term matches the node's Owner Groups, so access
   * must pass — proving the RouteSubscriber did not over-block.
   */
  public function testSideDoorWriteRoutesOpenWithOrgAccess(): void {
    $trashed = $this->createTestNode('info_details', $this->orgPageB);
    $trashed->set('moderation_state', MassModeration::TRASH)->save();

    $this->assertTrue(
      Url::fromRoute('entity.node.redirects', ['node' => $trashed->id()])->access($this->userB),
      '/node/X/redirects must be reachable by a user whose org matches the node.'
    );
    $this->assertTrue(
      Url::fromRoute('entity.node.entity_hierarchy_reorder', ['node' => $this->orgPageB->id()])->access($this->userB),
      '/node/X/children must be reachable by a user whose org matches the node.'
    );
    $this->assertTrue(
      Url::fromRoute('view.change_parents.page_1', ['node' => $this->orgPageB->id()])->access($this->userB),
      '/node/X/move-children must be reachable by a user whose org matches the node.'
    );
  }

  /**
   * Editor without an organization sees a warning at login.
   *
   * The warning tells the user why they cannot edit content and points them
   * to the site administrator.
   */
  public function testWarningDisplayedWhenEditorHasNoOrg(): void {
    $no_org_user = $this->createUser();
    $no_org_user->addRole('editor');
    $no_org_user->activate();
    $no_org_user->save();

    // drupalLogin lands the user on /user/UID where any flash messages set
    // by hook_user_login are rendered.
    $this->drupalLogin($no_org_user);

    $this->assertSession()->pageTextContains('Your account is not associated with any organization');
    $this->assertSession()->pageTextContains('contact your site administrator');
  }

  /**
   * Editors with at least one organization assigned do not see the warning.
   */
  public function testNoWarningWhenEditorHasOrg(): void {
    $this->drupalLogin($this->userA);

    $this->assertSession()->pageTextNotContains('Your account is not associated with any organization');
  }

  /**
   * Edit form does not pre-fill Owner Groups on existing content.
   *
   * Existing entities are populated only by drush moab. If their Owner
   * Groups are still empty when an editor opens the form, the field
   * must stay empty so admin-only-editable holds — the editor's own
   * field_user_org must not seep in.
   */
  public function testEditFormDoesNotPreFillOwnerGroupsOnExistingContent(): void {
    \Drupal::currentUser()->setAccount($this->userA);

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Existing un-backfilled ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageB->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    foreach (['node__field_content_organization', 'node_revision__field_content_organization'] as $table) {
      \Drupal::database()
        ->delete($table)
        ->condition('entity_id', $node->id())
        ->execute();
    }
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($node);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);

    $this->assertEmpty(
      $form_object->getEntity()->get('field_content_organization')->getValue(),
      'Owner Groups must stay empty on an existing entity — populated only by drush moab.'
    );
  }

  /**
   * New entity form does not stamp Owner Groups from the creator's profile.
   *
   * Permission Groups are derived from Organization(s) on the form (by the
   * augmentation JS), never copied straight from the creator's own
   * field_user_org. So on a server-built new entity form — no JS — the
   * field stays empty even when the creator has permission groups assigned.
   */
  public function testNewEntityFormDoesNotPreFillOwnerGroupsFromUser(): void {
    \Drupal::currentUser()->setAccount($this->userA);

    $entity = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'info_details',
      'title' => 'New unsaved ' . $this->randomMachineName(),
    ]);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($entity);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $entity = $form_object->getEntity();

    $tids = array_filter(array_column(
      $entity->get('field_content_organization')->getValue(),
      'target_id'
    ));
    $this->assertEmpty(
      $tids,
      'Owner Groups must not be pre-filled from the creator field_user_org; it derives from Organization(s) via JS.'
    );
  }

  /**
   * Debug mode reveals the Permission Groups field to non-admin editors.
   *
   * Off by default the field is wrapped in the author-hiding div for editors;
   * the settings-form switch drops that wrapper so they can see which
   * organizations are attached to the content.
   */
  public function testDebugModeRevealsPermissionGroupsToEditors(): void {
    \Drupal::currentUser()->setAccount($this->userA);

    \Drupal::state()->delete(OrgAccessSettings::DEBUG_STATE_KEY);
    $this->assertStringContainsString(
      'oog-hidden-from-author',
      $this->permissionGroupsWidgetPrefix('info_details'),
      'Permission Groups must be hidden from editors when debug mode is off.'
    );

    \Drupal::state()->set(OrgAccessSettings::DEBUG_STATE_KEY, TRUE);
    $this->assertStringNotContainsString(
      'oog-hidden-from-author',
      $this->permissionGroupsWidgetPrefix('info_details'),
      'Permission Groups must be visible to editors when debug mode is on.'
    );
  }

  /**
   * Builds a node add form and returns the Permission Groups widget #prefix.
   */
  private function permissionGroupsWidgetPrefix(string $bundle): string {
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => $bundle,
      'title' => 'Debug widget ' . $this->randomMachineName(),
    ]);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($node);
    $form_state = (new FormState())->setFormObject($form_object);
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    return (string) ($form['field_content_organization']['#prefix'] ?? '');
  }

  /**
   * Backfill no-ops on an entity whose Owner Groups already match.
   *
   * Drives the "skip save() when nothing changed" optimization in
   * BackfillRunner::processQueue — populateOwnerGroupsFromOrganizations now
   * returns a bool the runner uses to bail before touching storage.
   */
  public function testPopulateReturnsFalseWhenOwnerGroupsAlreadyMatch(): void {
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Already correct ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageA->id()],
      'field_content_organization' => [$this->termA->id()],
    ]);

    $changed = \Drupal::service('mass_org_access.org_access_checker')
      ->populateOwnerGroupsFromOrganizations($node);

    $this->assertFalse(
      $changed,
      'populateOwnerGroupsFromOrganizations must return FALSE when no value changes.'
    );
  }

  /**
   * Backfill never overwrites Owner Groups a human has already filled in.
   *
   * Uses a term that does NOT appear in any org_page so we can tell the
   * difference between "skip because equal" and "skip because populated"
   * — the latter is what protects manual admin additions.
   */
  public function testPopulateSkipsEntityWithExistingOwnerGroups(): void {
    $manualTerm = $this->createTerm(
      Vocabulary::load('user_organization'),
      ['name' => 'Manual ' . $this->randomMachineName()]
    );
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Curated ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageA->id()],
      'field_content_organization' => [$manualTerm->id()],
    ]);

    $changed = \Drupal::service('mass_org_access.org_access_checker')
      ->populateOwnerGroupsFromOrganizations($node);

    $this->assertFalse(
      $changed,
      'populateOwnerGroupsFromOrganizations must skip entities with non-empty Owner Groups.'
    );
    $tids = array_map('intval', array_column(
      $node->get('field_content_organization')->getValue(),
      'target_id'
    ));
    $this->assertSame(
      [(int) $manualTerm->id()],
      $tids,
      'Manual Owner Groups value must survive backfill untouched.'
    );
  }

  /**
   * Multi-org content gets the union of every referenced org_page's terms.
   *
   * Content tagged to two organizations must end up with Owner Groups
   * from both — not just the first one — so editors of either org can
   * still edit/delete the content after enforcement is on.
   */
  public function testPopulateOwnerGroupsUnionsAllReferencedOrgPages(): void {
    $termB = $this->getUserTermForOrg($this->orgPageB);
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Multi-org ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageA->id(), $this->orgPageB->id()],
    ]);
    $node->set('field_content_organization', []);

    $changed = \Drupal::service('mass_org_access.org_access_checker')
      ->populateOwnerGroupsFromOrganizations($node);

    $this->assertTrue($changed, 'Multi-org populate writes a value.');
    $tids = array_map('intval', array_column(
      $node->get('field_content_organization')->getValue(),
      'target_id'
    ));
    $this->assertContains(
      (int) $this->termA->id(),
      $tids,
      'Owner Groups must include terms from the first referenced org_page.'
    );
    $this->assertContains(
      (int) $termB->id(),
      $tids,
      'Owner Groups must include terms from the second referenced org_page.'
    );
  }

  /**
   * Owner Groups widget is visible to editors regardless of the switch.
   *
   * Field-level access is not restricted — any role that can edit the
   * host entity sees the widget. The enforcement switch only gates the
   * access decision on update/delete, not who can read/write the
   * Owner Groups field on the form.
   */
  public function testOwnerGroupsFieldVisibleToEditorsRegardlessOfSwitch(): void {
    $node = $this->createTestNode('info_details', $this->orgPageA);

    \Drupal::state()->set('mass_org_access.enforce', FALSE);
    $this->assertTrue(
      $node->get('field_content_organization')->access('edit', $this->userA),
      'Editor must see Owner Groups widget with enforcement OFF.'
    );

    \Drupal::state()->set('mass_org_access.enforce', TRUE);
    $this->assertTrue(
      $node->get('field_content_organization')->access('edit', $this->userA),
      'Editor must see Owner Groups widget with enforcement ON.'
    );

    $this->assertTrue(
      $node->get('field_content_organization')->access('view', $this->userA),
      'View access for Owner Groups stays neutral.'
    );
  }

  /**
   * Bypass users always see Owner Groups regardless of the switch.
   */
  public function testOrgOwnerGroupsFieldVisibleToBypassUsers(): void {
    $bypass_user = $this->createUser();
    $bypass_user->addRole('content_team');
    $bypass_user->activate();
    $bypass_user->save();
    $node = $this->createTestNode('info_details', $this->orgPageA);

    \Drupal::state()->set('mass_org_access.enforce', FALSE);
    $this->assertTrue(
      $node->get('field_content_organization')->access('edit', $bypass_user),
      'Bypass user must see Owner Groups while enforcement is OFF.'
    );

    \Drupal::state()->set('mass_org_access.enforce', TRUE);
    $this->assertTrue(
      $node->get('field_content_organization')->access('edit', $bypass_user),
      'Bypass user must still see Owner Groups when enforcement is ON.'
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
   * The Permission Groups (field_user_org) user field is required.
   */
  public function testFieldUserOrgIsRequired(): void {
    $field = \Drupal::configFactory()
      ->get('field.field.user.user.field_user_org');
    $this->assertTrue(
      (bool) $field->get('required'),
      'field_user_org must be configured as required.'
    );
  }

  /**
   * Admins cannot save a user that has no Permission Groups term.
   *
   * The field is required and admins (administer users) see it on the user
   * form, so leaving it empty blocks the save — forcing every managed user
   * to be assigned an organization.
   */
  public function testRequiredBlocksAdminSaveWhenOrgEmpty(): void {
    $user_manager = $this->createUser(['administer users']);
    $target = $this->createUser();
    $target->addRole('editor');
    $target->activate();
    $target->save();

    $this->drupalLogin($user_manager);
    $this->drupalGet('user/' . $target->id() . '/edit');
    $this->submitForm([], 'Save');

    $this->assertSession()->pageTextNotContains('The changes have been saved.');
    $this->assertSession()->elementExists('css', '.messages--error');
  }

  /**
   * A non-admin user without a term can still save their own profile.
   *
   * The field_user_org field is admin-only, so it is not on the user's own
   * form and the required constraint does not block their save — only admins
   * assign it.
   */
  public function testRequiredDoesNotBlockOwnProfileSave(): void {
    $author = $this->createUser();
    $author->addRole('author');
    $author->activate();
    $author->save();

    $this->drupalLogin($author);
    $this->drupalGet('user/' . $author->id() . '/edit');
    $this->submitForm([], 'Save');

    $this->assertSession()->pageTextContains('The changes have been saved.');
  }

  /**
   * Saving content with no matching Permission Groups warns the user.
   */
  public function testLockoutWarningWhenPermissionGroupsDisjoint(): void {
    $term_b = $this->getUserTermForOrg($this->orgPageB);
    \Drupal::messenger()->deleteAll();
    \Drupal::currentUser()->setAccount($this->userA);

    $this->createNode([
      'type' => 'info_details',
      'title' => 'Lockout ' . $this->randomMachineName(),
      'field_content_organization' => [$term_b->id()],
    ]);

    $this->assertNotEmpty(
      $this->lockoutWarnings(),
      'Saving content whose Permission Groups exclude the user must warn them.'
    );
  }

  /**
   * No warning when the saved Permission Groups include one of the user's.
   */
  public function testNoLockoutWarningWhenPermissionGroupsOverlap(): void {
    \Drupal::messenger()->deleteAll();
    \Drupal::currentUser()->setAccount($this->userA);

    $this->createNode([
      'type' => 'info_details',
      'title' => 'No lockout ' . $this->randomMachineName(),
      'field_content_organization' => [$this->termA->id()],
    ]);

    $this->assertEmpty(
      $this->lockoutWarnings(),
      'No lockout warning when the user shares a Permission Group with the content.'
    );
  }

  /**
   * Users with bypass org access never get the lockout warning.
   */
  public function testNoLockoutWarningForBypassUser(): void {
    $term_b = $this->getUserTermForOrg($this->orgPageB);
    $bypass_user = $this->createUser(['bypass org access']);
    \Drupal::messenger()->deleteAll();
    \Drupal::currentUser()->setAccount($bypass_user);

    $this->createNode([
      'type' => 'info_details',
      'title' => 'Bypass ' . $this->randomMachineName(),
      'field_content_organization' => [$term_b->id()],
    ]);

    $this->assertEmpty(
      $this->lockoutWarnings(),
      'A bypass-org-access user must never see the lockout warning.'
    );
  }

  /**
   * Returns the queued lockout warning messages, as strings.
   */
  private function lockoutWarnings(): array {
    $messages = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING);
    return array_filter(array_map('strval', $messages), static function (string $m): bool {
      return str_contains($m, 'lost access to this content');
    });
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
    $termA = $this->getUserTermForOrg($this->orgPageA);
    $termB = $this->getUserTermForOrg($this->orgPageB);

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Multi-org node ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageA->id(), $this->orgPageB->id()],
      'field_content_organization' => [$termA->id(), $termB->id()],
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
    $node = $this->createNode([
      'type' => $bundle,
      'title' => 'Test ' . $bundle . ' ' . $this->randomMachineName(),
      'field_organizations' => [$orgPage->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->syncOwnerGroupsAndSave($node);
    return $node;
  }

  private function createTestMedia(string $bundle, NodeInterface $orgPage) {
    $uri = 'public://test-' . $this->randomMachineName() . '.txt';
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);
    file_put_contents(\Drupal::service('file_system')->realpath($uri), 'test');

    $media = $this->createMedia([
      'name' => 'Test ' . $bundle . ' ' . $this->randomMachineName(),
      'bundle' => $bundle,
      'field_upload_file' => ['target_id' => $file->id()],
      'field_organizations' => [$orgPage->id()],
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->syncOwnerGroupsAndSave($media);
    return $media;
  }

  /**
   * Mimics what the drush moab backfill does on each entity.
   *
   * Copies Owner Groups from the related org_page and persists. We need
   * this in tests because mass_org_access does not sync in entity_presave
   * — values are populated only at form load (from the editor's terms)
   * or via drush.
   */
  private function syncOwnerGroupsAndSave($entity): void {
    \Drupal::service('mass_org_access.org_access_checker')
      ->populateOwnerGroupsFromOrganizations($entity);
    if (method_exists($entity, 'setNewRevision')) {
      $entity->setNewRevision(FALSE);
    }
    $entity->setSyncing(TRUE);
    $entity->save();
  }

}
