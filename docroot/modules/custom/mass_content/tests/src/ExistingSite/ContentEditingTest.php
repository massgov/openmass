<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Ensures editor can save the nodes.
 */
class ContentEditingTest extends MassExistingSiteBase {

  const QAG_PATHS = [
    "/forms/qag-form-with-file-uploads",
    "/audit/qag-binderaudit",
    "/report/qag-binderreport",
    "/qagcampaign-landing-with-solid-color-key-message-header",
    "/lists/qag-curatedlist",
    "/mandate/qag-decisionmandate",
    "/decision-tree/qag-decision-tree",
    // @todo Giving a 404 on edit page
    // "/event/qag-event-general-past-2018-07-24t124500-0400-2018-07-24t134500-0400",
    "/executive-orders/no-1-qag-executiveorder",
    "/guides/qag-guide",
    "/how-to/qag-request-help-with-a-computer-problem",
    "/info-details/qag-info-detail-with-landing-page-features",
    "/location-details/qag-locationdetails",
    "/locations/qag-locationgeneral1",
    "/news/qag-newsnews",
    "/orgs/qag-executive-office-of-technology-services-and-security",
    "/regulations/900-CMR-2-qag-regulation-title",
    "/person/qag-person-boardmember-role",
    "/trial-court-rules/qag-rulesofcourt",
    "/qag-service1",
    "/topics/qag-topicpage1",
    // @todo make the QAG glossary page
    // "/glossary/qag-glossary",
  ];

  /**
   * Temporarily uncomment to avoid failures when new validation is added or Prod content has become invalid.
   */
  protected function setUp(): void {
    if (FALSE) {
      $this->markTestSkipped('Please comment out this skip after next deployment');
    }
    parent::setUp();
  }

  /**
   * Creates an editor, saves it and returns it.
   */
  private function createEditor() {
    // Bypass mass_org_access — this test edits real content across orgs.
    $editor = $this->createUser(['bypass org access']);
    $editor->addRole('editor');
    $editor->activate();
    $editor->save();
    return $editor;
  }

  /**
   * Tests saving the content.
   */
  public function testSaveContent() {
    $this->drupalLogin($this->createEditor());
    $paths = self::QAG_PATHS;
    $session = $this->getSession();
    foreach ($paths as $path) {
      // Organization pages now require a Permission Groups value; in the real
      // rollout the content team populates org pages before they are edited.
      // Seed it here so this generic save loop mirrors that.
      $this->ensureOrgPagePermissionGroups($path);
      // Edit the node.
      $session->visit($path . '/edit');
      $this->assertEquals(200, $session->getStatusCode(), 'Failed to retrieve ' . $path . '/edit');
      $page = $session->getPage();
      $page->findButton('Save')->press();
      // Ensure the save was successful, redirected back to the view route, and does not encounter errors.
      $this->assertEquals($this->baseUrl . $path, $session->getCurrentUrl());
    }
  }

  /**
   * Gives an org_page a Permission Groups value when it has none.
   *
   * The field_content_organization field is required on org_page, so a form
   * save would otherwise fail validation and never redirect.
   */
  private function ensureOrgPagePermissionGroups(string $path): void {
    $system_path = \Drupal::service('path_alias.manager')->getPathByAlias($path);
    if (!preg_match('#^/node/(\d+)$#', $system_path, $matches)) {
      return;
    }
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($matches[1]);
    if (!$node || $node->bundle() !== 'org_page' || !$node->hasField('field_content_organization')) {
      return;
    }
    if (!$node->get('field_content_organization')->isEmpty()) {
      return;
    }
    $tids = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', 'user_organization')
      ->range(0, 1)
      ->execute();
    if (empty($tids)) {
      return;
    }
    $node->set('field_content_organization', [['target_id' => (int) reset($tids)]]);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }

}
