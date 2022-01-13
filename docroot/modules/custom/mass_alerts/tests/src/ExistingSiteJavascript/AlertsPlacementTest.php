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

  /**
   * Creates an specific alerts with specific target ids.
   */
  private function createSpecificAlert($targets) {

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
   * Test the placement of specific alerts is correct.
   */
  public function testSpecificAlertPlacement() {

    $this->createAndLoginUser('administrator');

    // @todo: Still having issues to tests campaign_landing.
    $content_types = [
      'person' => '#main-content > div.ma__bio__content > div > div > div.mass-alerts-block > div > section > button',
      'service_page' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'advisory' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'service_details' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'news' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'org_page' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'info_details' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'guide_page' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'how_to_page' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'rules' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'curated_list' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'location' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'topic_page' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'binder' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'event' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'location_details' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'regulation' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'form_page' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'executive_order' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'decision' => '#main-content > div.pre-content > div.mass-alerts-block > div > section > button',
      'decision_tree' => '#main-content > div.pre-content > div.decision-tree > div > div > section > button',
    ];

    $targets = [];
    /** @var \Drupal\node\Entity\Node[] */
    $nodes = [];

    foreach ($content_types as $content_type => $selector) {
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
      $targets[] = ['target_id' => $node->id()];
      $nodes[] = $node;
    }

    $this->createSpecificAlert($targets);

    foreach ($nodes as $node) {
      $this->drupalGet($node->toUrl()->toString());
      $selector = $content_types[$node->bundle()];
      $this->assertSession()->elementExists('css', $selector);
    }

  }

}
