<?php

namespace Drupal\Tests\mass_alerts\ExistingSite;

use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\mass_caching\EventSubscriber\StaleResponseSubscriber;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_utility\DebugCachability;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests basic alert functionality, including JSONAPI customizations.
 */
class EmergencyAlertsTest extends MassExistingSiteBase {

  use LoginTrait;

  private $editor;
  private $orgNode;
  private $emergencyAlertPublisher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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

    (new DebugCachability())->requestDebugCachabilityHeaders($this->getSession());
  }

  /**
   * Check that the Alerts response contains the correct data.
   */
  public function testEmergencyAlertResponseSitewide() {
    $this->unPublishExistingSiteWideAlert();

    $related = $this->createNode([
      'type' => 'service_page',
      'title' => 'EmergencyAlertsTest Service Page',
      'status' => Node::PUBLISHED,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $alert_message_text = $this->randomMachineName();
    $node = $this->createNode([
      'type' => 'sitewide_alert',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      // 'State 911 Department (6416)'.
      'field_alert_ref_contact' => ['target_id' => 6416],
      'field_sitewide_alert_severity' => 'emergency_alert',
      'field_sitewide_alert' => Paragraph::create([
        'type' => 'sitewide_alert_message',
        'field_sitewide_alert_message' => $alert_message_text,
      ]),
      'field_alert_related_links_5' => [
        'uri' => 'entity:node/' . $related->id(),
        'title' => $related->getTitle(),
      ],
    ]);

    $session = $this->getSession();
    $session->visit('/alerts/sitewide');
    $page = $session->getPage();
    $this->assertStringContainsString($alert_message_text, $page->getText());

    $headers = $session->getResponseHeaders();
    $this->assertStringContainsString('max-age=60', $headers['Cache-Control'][0]);
    $duration = StaleResponseSubscriber::DURATION;
    $this->assertStringContainsString("stale-if-error=$duration", $headers['Cache-Control'][0]);
    $this->assertStringContainsString("stale-while-revalidate=$duration", $headers['Cache-Control'][0]);
    $this->assertStringContainsString('max-age=60', $headers['Cache-Control'][0]);

    $this->assertStringContainsString(MASS_ALERTS_TAG_SITEWIDE . ':list', $headers['X-Drupal-Cache-Tags'][0]);
    $this->assertStringContainsString('node:' . $node->id(), $headers['X-Drupal-Cache-Tags'][0]);
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
    $this->assertStringContainsString($alert_message_text, $page->getText());
    $headers = $session->getResponseHeaders();
    $this->assertStringContainsString('max-age=900', $headers['Cache-Control'][0]);
    $this->assertStringContainsString(MASS_ALERTS_TAG_PAGE . ':' . $org_node->id(), $headers['X-Drupal-Cache-Tags'][0]);
    $this->assertStringContainsString('node:' . $node->id(), $headers['X-Drupal-Cache-Tags'][0]);
    $this->assertStringContainsString('MISS', $headers[DynamicPageCacheSubscriber::HEADER][0]);
    $duration = StaleResponseSubscriber::DURATION;
    $this->assertStringContainsString("stale-if-error=$duration", $headers['Cache-Control'][0]);
    $this->assertStringContainsString("stale-while-revalidate=$duration", $headers['Cache-Control'][0]);

    $this->drupalGet('/alerts/page/' . $org_node->id());
    $headers = $session->getResponseHeaders();
    $this->assertStringContainsString('HIT', $headers[DynamicPageCacheSubscriber::HEADER][0]);

    // Test that the alert details page has the field content.
    $crawler = $this->getRenderedEntityCrawler($node);
    $this->assertStringContainsString($node->label(), $crawler->text());
    $this->assertStringContainsString($alert_message_text, $crawler->text());
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
    $this->assertStringContainsString('must show on at least one page', $page->getText());
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
    $this->assertStringContainsString('must show on at least one organization', $page->getText());
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
      'type' => 'sitewide_alert',
      'moderation_state' => 'published',
      'status' => 1,
      'field_sitewide_alert' => Paragraph::create([
        'type' => 'sitewide_alert_message',
        'field_sitewide_alert_message' => 'test',
      ]),
    ]);
    $session = $this->getSession();
    $session->visit('/node/add/sitewide_alert');
    $page = $session->getPage();
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->findButton('Save')->press();
    $this->assertStringContainsString('This sitewide alert cannot be published because another sitewide alert is currently active:', $page->getText());
  }

  /**
   * Assert site-wide alert cannot be created by a user with only the editor role.
   */
  public function testEditorRoleSiteWideAlert() {
    $this->drupalLogin($this->editor);
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $this->assertStringNotContainsString('Sitewide on all Mass.gov pages', $page->getText());
    $this->assertSession()->fieldNotExists('edit-field-alert-display-site-wide');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
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
      ->accessCheck(FALSE)
      ->condition('type', 'sitewide_alert')
      ->condition('status', 1)
      ->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      $node->moderation_state = MassModeration::UNPUBLISHED;
      $node->save();
    }
  }

}
