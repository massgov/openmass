<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests which roles can read and write the SME / Content Owner field.
 *
 * Roles on this site are additive: nearly every active account carries editor,
 * with extras such as bulk_edit or content_team layered on top, while author,
 * mmg_editor and viewer appear on their own. The combinations below are the
 * ones real active accounts actually hold.
 */
class SmeContentOwnerFieldAccessTest extends MassExistingSiteBase {

  private const FIELD_NAME = 'field_sme_content_owner';

  /**
   * Role combinations held by real accounts, with the access each should get.
   *
   * @return array
   *   Each case is [roles, may edit content, may read the owner].
   */
  public static function roleProvider(): array {
    return [
      'editor' => [['editor'], TRUE, TRUE],
      'author' => [['author'], TRUE, TRUE],
      'bulk_edit + editor' => [['bulk_edit', 'editor'], TRUE, TRUE],
      'content_team + editor' => [['content_team', 'editor'], TRUE, TRUE],
      'editor + tester' => [['editor', 'tester'], TRUE, TRUE],
      'data_administrator + editor' => [['data_administrator', 'editor'], TRUE, TRUE],
      // Read only staff account: sees the editorial listings, edits nothing.
      'viewer' => [['viewer'], FALSE, TRUE],
      'mmg_editor' => [['mmg_editor'], FALSE, FALSE],
      'no roles' => [[], FALSE, FALSE],
    ];
  }

  /**
   * Roles that may author content can also fill in the owner field.
   *
   * The field carries no permission of its own on purpose: whoever may edit a
   * page may record who owns it. This test fails if someone later restricts
   * the field and locks editors out of it.
   *
   * @dataProvider roleProvider
   */
  public function testAuthoringRolesCanWriteTheField(array $roles, bool $may_edit, bool $may_read): void {
    $account = $this->accountWithRoles($roles);
    $node = $this->createOwnedNode();

    $this->assertSame($may_edit, $node->access('update', $account), 'Unexpected node access for ' . implode('+', $roles));

    if ($may_edit) {
      $this->assertTrue(
        $node->get(self::FIELD_NAME)->access('edit', $account),
        'A role that may edit the page cannot record its owner: ' . implode('+', $roles)
      );
    }
  }

  /**
   * Roles that cannot edit content never reach the field on a form.
   *
   * @dataProvider roleProvider
   */
  public function testNonAuthoringRolesCannotReachTheForm(array $roles, bool $may_edit, bool $may_read): void {
    if ($may_edit) {
      $this->markTestSkipped('Covered by testEditorSeesTheFieldOnTheForm.');
    }

    $node = $this->createOwnedNode();
    $this->drupalLogin($this->accountWithRoles($roles));
    $this->visit($node->toUrl('edit-form')->toString());

    $this->assertContains(
      $this->getSession()->getStatusCode(),
      [403, 404],
      'A role without authoring rights opened the edit form: ' . implode('+', $roles)
    );
  }

  /**
   * An editor, the role nearly every account holds, gets a usable widget.
   */
  public function testEditorSeesTheFieldOnTheForm(): void {
    $node = $this->createOwnedNode();
    $this->drupalLogin($this->accountWithRoles(['editor']));
    $this->visit($node->toUrl('edit-form')->toString());

    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $this->assertSession()->fieldExists(self::FIELD_NAME . '[0][target_id]');
    $this->assertSession()->pageTextContains('Subject Matter Expert / Content Owner');
  }

  /**
   * Only roles that already see the editorial listings may read the owner.
   *
   * @dataProvider roleProvider
   */
  public function testOnlyEditorialRolesCanReadTheField(array $roles, bool $may_edit, bool $may_read): void {
    $node = $this->createOwnedNode();

    $this->assertSame(
      $may_read,
      $node->get(self::FIELD_NAME)->access('view', $this->accountWithRoles($roles)),
      'Unexpected read access for ' . (implode('+', $roles) ?: 'an account with no roles')
    );
  }

  /**
   * Anonymous visitors cannot read the owner at all.
   *
   * This is the backstop: even if a display, view or template starts printing
   * the field, the public never sees a name.
   */
  public function testAnonymousCannotReadTheField(): void {
    $node = $this->createOwnedNode();
    $anonymous = \Drupal::entityTypeManager()->getStorage('user')->load(0);

    $this->assertFalse($node->get(self::FIELD_NAME)->access('view', $anonymous));
    $this->assertFalse($node->access('update', $anonymous));
  }

  /**
   * Creates an active account carrying exactly the given roles.
   */
  private function accountWithRoles(array $roles): UserInterface {
    $account = $this->createUser();
    foreach ($roles as $role) {
      $account->addRole($role);
    }
    $account->activate();
    $account->save();

    return $account;
  }

  /**
   * Creates a published page carrying an owner.
   */
  private function createOwnedNode() {
    $owner = $this->createTerm(Vocabulary::load('sme_owner'), [
      'name' => 'Access Owner ' . $this->randomMachineName(8),
      'langcode' => 'en',
    ]);

    return $this->createNode([
      'type' => 'advisory',
      'title' => 'Owner access ' . $this->randomMachineName(8),
      'field_sme_content_owner' => [$owner->id()],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

}
