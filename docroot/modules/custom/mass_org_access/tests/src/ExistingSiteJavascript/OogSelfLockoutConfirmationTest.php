<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSiteJavascript;

use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Verifies the self-lockout confirmation on Save.
 *
 * Kept apart from OogAugmentFromOrganizationsTest so CircleCI, which splits
 * the suite by file, can run the two on different nodes.
 */
class OogSelfLockoutConfirmationTest extends OogAugmentTestBase {

  /**
   * Saving content that would lock the author out needs explicit confirmation.
   *
   * A non-admin user whose Permission Groups do not match the content's
   * Permission Groups gets the first Save blocked with an inline notice +
   * checkbox; the save only goes through after they tick it, so an accidental
   * self-lockout cannot happen silently. Runs across every supported bundle.
   *
   * The user's own Permission Group is a freshly created term that no content
   * references, so any entity's Permission Groups are guaranteed disjoint —
   * the confirmation must trigger regardless of the entity's current value.
   *
   * @dataProvider entityProvider
   */
  public function testSelfLockoutSaveRequiresConfirmation(string $entityType, string $bundle): void {
    $userTerm = $this->createTerm(
      Vocabulary::load('user_organization'),
      ['name' => 'Lockout user org ' . $this->randomMachineName(6)]
    );
    $user = $this->createUser([
      'bypass node access',
      'administer media',
      'create document media',
    ]);
    $user->addRole('editor');
    $user->set('field_user_org', $userTerm->id());
    $user->activate();
    $user->save();

    // The self-lockout confirmation is gated on the enforcement switch, which
    // ships off in Release 1; turn it on so the warning path is active.
    \Drupal::state()->set('mass_org_access.enforce', TRUE);

    $entity = $this->createEntityForBundle($entityType, $bundle, []);
    $this->drupalLogin($user);
    $this->drupalGet(sprintf('%s/%d/edit', $entityType, $entity->id()));

    $session = $this->getSession();
    $page = $session->getPage();
    $session->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );

    // The confirmation JS only attaches where an organization widget is
    // rendered (it derives Permission Groups from the orgs). Bundles that
    // show no organization widget have no self-lockout path to guard.
    $orgInputSelectors = '\'input[name^="field_organizations["][name$="[target_id]"], '
      . 'input[name^="field_binder_ref_organization["][name$="[target_id]"], '
      . 'input[name^="field_decision_ref_organization["][name$="[target_id]"], '
      . 'input[name^="field_person_ref_org["][name$="[target_id]"]\'';
    $hasOrgInput = $session->evaluateScript(
      'document.querySelector(' . $orgInputSelectors . ') !== null'
    );
    if (!$hasOrgInput) {
      $this->markTestSkipped(sprintf(
        '%s:%s renders no organization widget, so there is no lockout path.',
        $entityType,
        $bundle
      ));
    }

    // First Save: the disjoint Permission Groups block the submit and the
    // inline confirmation appears; the form is not left.
    $this->clickSaveButton();
    $confirm = $page->waitFor(10, function () use ($page) {
      return $page->find('css', '.oog-lockout-confirm');
    });
    $this->assertNotNull(
      $confirm,
      sprintf('First save on %s:%s must be blocked with the confirmation.', $entityType, $bundle)
    );
    $this->assertStringContainsString(
      '/edit',
      $session->getCurrentUrl(),
      sprintf('%s:%s must not be submitted before the user confirms.', $entityType, $bundle)
    );

    // Tick the confirmation, then Save again. Our gate now releases the
    // submit: the form posts and the server re-renders the page, replacing
    // the client-injected notice. (If the gate wrongly re-blocked, the box
    // would persist with no round-trip.) This holds whether the save then
    // succeeds or fails unrelated validation, so the assertion stays robust.
    $session->executeScript(
      "document.querySelector('.oog-lockout-confirm input[type=\"checkbox\"]').checked = true;"
    );
    $this->clickSaveButton();
    $released = $page->waitFor(15, function () use ($page) {
      return $page->find('css', '.oog-lockout-confirm') === NULL;
    });
    $this->assertTrue(
      $released,
      sprintf('After confirming on %s:%s the gate must release the save.', $entityType, $bundle)
    );
  }

}
