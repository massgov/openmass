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
   * Role combinations held by real accounts, and whether they may author.
   *
   * @return array
   *   Each case is [roles, may edit content].
   */
  public static function roleProvider(): array {
    return [
      'editor' => [['editor'], TRUE],
      'author' => [['author'], TRUE],
      'bulk_edit + editor' => [['bulk_edit', 'editor'], TRUE],
      'content_team + editor' => [['content_team', 'editor'], TRUE],
      'editor + tester' => [['editor', 'tester'], TRUE],
      'data_administrator + editor' => [['data_administrator', 'editor'], TRUE],
      'mmg_editor' => [['mmg_editor'], FALSE],
      'viewer' => [['viewer'], FALSE],
      'no roles' => [[], FALSE],
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
  public function testAuthoringRolesCanWriteTheField(array $roles, bool $may_edit): void {
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
  public function testNonAuthoringRolesCannotReachTheForm(array $roles, bool $may_edit): void {
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
   * Reading the field is not restricted, so nothing may publish it by accident.
   *
   * Field level read access is open, including to anonymous clients. The only
   * thing keeping owners internal is that no display, view or API resource
   * serves the field, which is what SmeContentOwnerPrivacyTest guards. This
   * test records the situation so the reasoning is not lost.
   */
  public function testReadAccessIsNotRestrictedByItself(): void {
    $node = $this->createOwnedNode();
    $anonymous = \Drupal::entityTypeManager()->getStorage('user')->load(0);

    $this->assertTrue(
      $node->get(self::FIELD_NAME)->access('view', $anonymous),
      'Read access became restricted; the privacy tests should now be revisited.'
    );
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
