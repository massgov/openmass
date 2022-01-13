<?php

namespace Drupal\Tests\mass_alerts\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Test Alerts Placement.
 */
class AlertsPlacementTest extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * Unpublishes any alerts.
   */
  private function unpublishAlerts() {
    // Unpublish any existing sitewide alerts so our slate is clean.
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'sitewide_alert')
      ->condition('status', 1)
      ->execute();

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      $node->moderation_state = MassModeration::UNPUBLISHED;
      $node->save();
    }
  }

  private function createSiteWideAlert() {
    $related = $this->createNode([
      'type' => 'service_page',
      'title' => 'EmergencyAlertsClientSideTest Service Page',
    ]);
    $alert_message_text = $this->randomMachineName();
    $node = $this->createNode([
      'type' => 'sitewide_alert',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
      // 'State 911 Department (6416)'.
      'field_alert_ref_contact' => ['target_id' => 6416],
      'field_alert_severity' => 'emergency_alert',
      'field_sitewide_alert' => Paragraph::create([
        'type' => 'sitewide_alert_message',
        'field_sitewide_alert_message' => $alert_message_text,
      ]),
      'field_alert_related_links_5' => [
        'uri' => 'entity:node/' . $related->id(),
        'title' => $related->getTitle(),
      ],
    ]);
  }

  /**
   * Creates an specific alerts with specific target ids.
   */
  private function createSpecificAlert(array $nodes) {

    $targets = [];
    foreach ($nodes as $node) {
      $targets[] = $node->id();
    }

    $this->createNode([
      'type' => 'alert',
      'title' => $this->randomString(20),
      'field_alert_display' => 'specific_target_pages',
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => $this->randomMachineName(),
      ]),
      'field_target_page' => $targets,
    ]);

  }

  /**
   * Creates and logs in a user with a specific role.
   */
  private function createAndLoginUser($role) {
    $user = $this->createUser();
    $user->addRole($role);
    $user->save();
    $this->drupalLogin($user);
    $this->unpublishAlerts();
  }

  /**
   * Get the content types and the selectors for wide alerts.
   */
  private function getContentTypesAndSelectorForWideAlerts() {
    $bundles = [
      'campaign_landing',
      'person',
      'service_page',
      'advisory',
      'service_details',
      'news',
      'org_page',
      'info_details',
      'guide_page',
      'how_to_page',
      'rules',
      'curated_list',
      'location',
      'topic_page',
      'binder',
      'event',
      'location_details',
      'regulation',
      'form_page',
      'executive_order',
      'decision',
      'decision_tree',
    ];

    $content_types_and_selectors = [];
    foreach ($bundles as $bundle) {
      $content_types_and_selectors[$bundle] = 'body > div.dialog-off-canvas-main-canvas > div.mass-alerts-block > section > div.ma__emergency-alerts__content.js-accordion-content > div > p > span';
    }

    return $content_types_and_selectors;
  }

  /**
   * Get the content types and the selectors for wide alerts.
   */
  private function getContentTypesAndSelectorForSpecificAlerts() {
    $bundles = [
      'service_page',
      'advisory',
      'service_details',
      'news',
      'org_page',
      'info_details',
      'guide_page',
      'how_to_page',
      'rules',
      'curated_list',
      'location',
      'topic_page',
      'binder',
      'event',
      'location_details',
      'regulation',
      'form_page',
      'executive_order',
      'decision',
    ];

    $content_types_and_selectors = [];
    foreach ($bundles as $bundle) {
      $content_types_and_selectors[$bundle] = '#main-content > div.pre-content > div.mass-alerts-block > div > section > button';
    }

    $irregular_selectors = [
      'decision_tree' => '#main-content > div.pre-content > div.decision-tree > div > div > section > button',
      'person' => '#main-content > div.ma__bio__content > div > div > div.mass-alerts-block > div > section > button',
    ];

    return $irregular_selectors + $content_types_and_selectors;
  }

  /**
   * Creates the nodes needed to test an Alert.
   */
  private function createNodesToTestAlert($content_types_and_selectors) {
    $nodes = [];

    foreach ($content_types_and_selectors as $content_type => $selector) {
      $node_data = [
        'type' => $content_type,
        'title' => $this->randomMachineName(),
        'status' => 1,
        'moderation_state' => MassModeration::PUBLISHED,
      ];

      if ($content_type == 'person') {
        $node_data += [
          'field_person_first_name' => $this->randomString(10),
          'field_person_last_name' => $this->randomString(10),
        ];
      }

      $node = $this->createNode($node_data);
      $nodes[] = $node;
    }

    return $nodes;
  }

  /**
   * Tests the placement for Site Wide Alerts.
   */
  public function testWideAlertPlacement() {

    $this->createAndLoginUser('administrator');

    $content_types_and_selectors = $this->getContentTypesAndSelectorForWideAlerts();

    /** @var \Drupal\node\Entity\Node[] */
    $nodes = $this->createNodesToTestAlert($content_types_and_selectors);

    $this->createSiteWideAlert();

    foreach ($nodes as $node) {
      $this->drupalGet($node->toUrl()->toString());
      $selector = $content_types_and_selectors[$node->bundle()];
      $this->assertSession()->elementExists('css', $selector);
    }

  }

  /**
   * Test the placement of specific alerts is correct.
   */
  public function testSpecificAlertPlacement() {

    $this->createAndLoginUser('administrator');

    // @todo: Still having issues to tests campaign_landing.
    $content_types_and_selectors = $this->getContentTypesAndSelectorForSpecificAlerts();

    /** @var \Drupal\node\Entity\Node[] */
    $nodes = $this->createNodesToTestAlert($content_types_and_selectors);

    $this->createSpecificAlert($nodes);

    foreach ($nodes as $node) {
      $this->drupalGet($node->toUrl()->toString());
      $selector = $content_types_and_selectors[$node->bundle()];
      $this->assertSession()->elementExists('css', $selector);
    }

  }

}
