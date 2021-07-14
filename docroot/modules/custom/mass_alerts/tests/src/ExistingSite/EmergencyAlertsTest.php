<?php

namespace Drupal\Tests\mass_alerts\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests basic alert functionality, including JSONAPI customizations.
 */
class EmergencyAlertsTest extends ExistingSiteBase {

  use LoginTrait;

  private $editor;
  private $orgNode;
  private $emergencyAlertPublisher;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $user1 = User::create(['name' => $this->randomMachineName()]);
    $user1->addRole('editor');
    $user1->activate();
    $user1->save();
    $this->editor = $user1;

    $user2 = User::create(['name' => $this->randomMachineName()]);
    $user2->addRole('emergency_alert_publisher');
    $user2->activate();
    $user2->save();
    $this->emergencyAlertPublisher = $user2;

    $this->orgNode = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
  }

  /**
   * Check that the JSONAPI output contains fields we care about.
   */
  public function testEmergencyAlertAppearsInJson() {
    $related = $this->createNode([
      'type' => 'service_page',
      'title' => 'Alert Service Page',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node = $this->createNode([
      'type' => 'alert',
      'title' => 'Test Alert',
      'status' => 1,
      'field_alert_related_links_5' => [
        'uri' => 'entity:node/' . $related->id(),
        'title' => 'Test Alert',
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $session = $this->getSession();
    $session->visit('/jsonapi/node/alert?filter[status][value]=1&sort=-changed');
    $response = json_decode($session->getPage()->getContent(), TRUE);
    $alert = $this->findNodeInResponse($node, $response);
    $this->assertEquals($node->toUrl()->toString(), $alert['attributes']['entity_url'], 'Alert has entity_url attribute with aliased path.');
    $this->assertCount(1, $alert['attributes']['field_alert_related_links_5']);
    $this->assertEquals($related->toUrl()->toString(), $alert['attributes']['field_alert_related_links_5'][0]['uri'], 'Related link field contains links that point to the aliased entity.');
  }

  /**
   * Check that the Alerts response contains the correct data.
   */
  public function testEmergencyAlertResponseSitewide() {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'alert')
      ->condition('status', 1)
      ->condition('field_alert_display', 'site_wide')
      ->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      $node->moderation_state = MassModeration::UNPUBLISHED;
      $node->save();
    }

    $related = $this->createNode([
      'type' => 'service_page',
      'title' => 'EmergencyAlertsTest Service Page',
    ]);
    $alert_message_text = $this->randomMachineName();
    $node = $this->createNode([
      'type' => 'alert',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      'field_alert_display' => 'site_wide',
      // 'State 911 Department (6416)'.
      'field_alert_ref_contact' => ['target_id' => 6416],
      'field_alert_severity' => 'emergency_alert',
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => $alert_message_text,
      ]),
      'field_alert_related_links_5' => [
        'uri' => 'entity:node/' . $related->id(),
        'title' => $related->getTitle(),
      ],
    ]);

    $session = $this->getSession();
    $session->visit('/alerts/sitewide');
    $page = $session->getPage();
    $this->assertContains($alert_message_text, $page->getText());

    $headers = $session->getResponseHeaders();
    $this->assertContains('max-age=60', $headers['Cache-Control'][0]);
    $this->assertNotContains('stale-if-error', $headers['Cache-Control'][0]);
    $this->assertNotContains('stale-while-revalidate', $headers['Cache-Control'][0]);

    $this->assertContains(MASS_ALERTS_TAG_SITEWIDE . ':list', $headers['X-Drupal-Cache-Tags'][0]);
    $this->assertContains('node:' . $node->id(), $headers['X-Drupal-Cache-Tags'][0]);
  }

  /**
   * Check that the Alerts Endpoint for specific page output contains the correct data.
   */
  public function testEmergencyAlertResponsePage() {

    $alert_message_text = $this->randomMachineName();
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $node = $this->createNode([
      'type' => 'alert',
      'title' => $this->randomMachineName(),
      'field_alert_display' => 'specific_target_pages',
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => $alert_message_text,
      ]),
      'field_target_page' => [
        ['target_id' => $org_node->id()],
      ],
    ]);

    $session = $this->getSession();
    $session->visit('/alerts/page/' . $org_node->id());
    $page = $session->getPage();
    $this->assertContains($alert_message_text, $page->getText());

    $headers = $session->getResponseHeaders();

    $this->assertContains(MASS_ALERTS_TAG_PAGE . ':' . $org_node->id(), $headers['X-Drupal-Cache-Tags'][0]);
    $this->assertContains('node:' . $node->id(), $headers['X-Drupal-Cache-Tags'][0]);
  }

  /**
   * Filter a JSONAPI response to a single node.
   */
  private function findNodeInResponse(Node $node, array $response) {
    $uuid = $node->uuid();
    $matching = array_filter($response['data'], function ($item) use ($uuid) {
      return $item['id'] === $uuid;
    });
    $this->assertCount(1, $matching, 'Response contains exactly 1 instance of node');
    return reset($matching);
  }

  /**
   * Assert that our validation prevents saving a page-specific alert without any pages.
   *
   * Since validation is form based, we post a form in this test.
   */
  public function testPageSpecificAlert() {
    $user = User::load(1)->set('status', 1);
    $user->save();
    $this->drupalLogin($user);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $page->fillField('field_alert_display', 'specific_target_pages');
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->findButton('Save')->press();
    $this->assertContains('must show on at least one page', $page->getText());
  }

  /**
   * Assert that our validation prevents saving a organization-specific alert without any pages.
   *
   * Since validation is form based, we post a form in this test.
   */
  public function testOrganizationSpecificAlert() {
    $user = User::load(1)->set('status', 1);
    $user->save();
    $this->drupalLogin($user);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $page->fillField('field_alert_display', 'by_organization');
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->findButton('Save')->press();
    $this->assertContains('must show on at least one organization', $page->getText());
  }

  /**
   * Assert that our validation prevents saving multiple sitewide alerts.
   */
  public function testSitewideAlert() {
    $user = User::load(1)->set('status', 1);
    $user->save();
    $this->drupalLogin($user);

    // Save 1 sitewide alert to start with.
    $this->createNode([
      'type' => 'alert',
      'field_alert_display' => 'site_wide',
      'moderation_state' => 'published',
      'status' => 1,
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => 'test',
      ]),
    ]);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $page->fillField('field_alert_display', 'site_wide');
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->findButton('Save')->press();
    $this->assertContains('This sitewide alert cannot be published because another sitewide alert is currently active:', $page->getText());
  }

  /**
   * Assert specific page alert can be created by a user with the editor role.
   */
  public function testEditorRolePageSpecificAlert() {
    $page_title = $this->randomMachineName();
    $this->drupalLogin($this->editor);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $page->fillField('edit-title-0-value', $page_title);
    $page->fillField('field_alert_display', 'specific_target_pages');
    $page->fillField('edit-field-target-page-0-target-id', $this->orgNode->getTitle());
    $page->fillField('edit-field-alert-0-subform-field-emergency-alert-message-0-value', 'Message text');
    $page->fillField('edit-field-alert-0-subform-field-emergency-alert-link-0-uri', 'https://www.google.com');
    $page->fillField('edit-field-organizations-0-target-id', $this->orgNode->getTitle());
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->pressButton('Save');
    $this->assertContains($page_title, $page->getText());
  }

  /**
   * Assert organization alert can be created by a user with the editor role.
   */
  public function testEditorRoleOrganizationAlert() {
    $page_title = $this->randomMachineName();
    $this->drupalLogin($this->editor);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $page->fillField('edit-title-0-value', $page_title);
    $page->fillField('field_alert_display', 'by_organization');
    $page->fillField('edit-field-target-organization-0-target-id', $this->orgNode->getTitle());
    $page->fillField('edit-field-alert-0-subform-field-emergency-alert-message-0-value', 'Message text');
    $page->fillField('edit-field-alert-0-subform-field-emergency-alert-link-0-uri', 'https://www.google.com');
    $page->fillField('edit-field-organizations-0-target-id', $this->orgNode->getTitle());
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->pressButton('Save');
    $this->assertContains($page_title, $page->getText());
  }

  /**
   * Assert site-wide alert can be created by a user with the emergency_alert_publisher role.
   */
  public function testEmergencyAlertPublisherRoleSiteWideAlert() {
    $this->unPublishExistingSiteWideAlert();
    $page_title = $this->randomMachineName();
    $this->drupalLogin($this->emergencyAlertPublisher);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $page->fillField('edit-title-0-value', $page_title);
    $page->fillField('field_alert_display', 'site_wide');
    $page->fillField('edit-field-alert-0-subform-field-emergency-alert-message-0-value', 'Message text');
    $page->fillField('edit-field-alert-0-subform-field-emergency-alert-link-0-uri', 'https://www.google.com');
    $page->fillField('edit-field-organizations-0-target-id', $this->orgNode->getTitle());
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->pressButton('Save');
    $this->assertContains($page_title, $page->getText());
  }

  /**
   * Assert site-wide alert cannot be created by a user with only the editor role.
   */
  public function testEditorRoleSiteWideAlert() {
    $this->drupalLogin($this->editor);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $this->assertNotContains('Sitewide on all Mass.gov pages', $page->getText());
    $this->assertSession()->fieldNotExists('edit-field-alert-display-site-wide');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    parent::tearDown();
    $this->editor = NULL;
    $this->orgNode = NULL;
    $this->emergencyAlertPublisher = NULL;
  }

  /**
   * Unpublish pre-existing site-wide alert, if any.
   */
  public function unPublishExistingSiteWideAlert() {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'alert')
      ->condition('status', 1)
      ->condition('field_alert_display', 'site_wide')
      ->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      $node->moderation_state = MassModeration::UNPUBLISHED;
      $node->save();
    }
  }

}
