<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_validation\Plugin\Validation\Constraint\PreventEmptyStateOrgConstraint;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests PreventEmptyStateOrg does not block valid press releases on the node form.
 *
 * @see https://jira.mass.gov/browse/DP-46226
 */
class NewsPressReleaseStateOrganizationValidationTest extends MassExistingSiteBase {
  use UserCreationTrait;

  /**
   * Creates and logs in a user with a specific role.
   */
  private function createAndLoginUser($role) {
    $user = $this->createUser();
    $user->addRole($role);
    $user->save();
    $this->drupalLogin($user);
  }

  /**
   * Press release with a state organization signee saves from the edit form without error.
   */
  public function testPressReleaseWithStateOrganizationSigneeSavesFromEditForm() {
    $org_term = $this->createTerm(Vocabulary::load('user_organization'), [
      'name' => 'TestOrgTermPressRelease',
    ]);
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page Press Release Validation',
    ]);
    $signee_paragraph = Paragraph::create([
      'type' => 'state_organization',
      'field_state_org_ref_org' => $org_node,
    ]);
    $media_contact_paragraph = Paragraph::create([
      'type' => 'media_contact',
      'field_media_contact_name' => 'Test Media Contact',
      'field_media_contact_email' => 'media.contact@example.com',
    ]);

    $node = $this->createNode([
      'type' => 'news',
      'title' => 'Test Press Release DP-46226',
      'field_news_type' => 'press_release',
      'field_date_published' => '2012-12-31',
      'field_state_organization_tax' => $org_term,
      'field_news_body' => 'Test body for validation.',
      'field_news_lede' => 'Test lede for validation.',
      'field_news_signees' => $signee_paragraph,
      'field_news_media_contac' => $media_contact_paragraph,
      'field_organizations' => [$org_node],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createAndLoginUser('administrator');
    $this->visit($node->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->pressButton('edit-submit');

    $this->assertSession()->pageTextNotContains(PreventEmptyStateOrgConstraint::MESSAGE);
  }

}
