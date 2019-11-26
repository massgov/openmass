<?php

namespace Drupal\Tests\mass_alerts\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Drupal\Tests\mass_utility\Traits\UserTestTrait;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests basic alert functionality, including JSONAPI customizations.
 */
class EmergencyAlertsTest extends ExistingSiteBase {

  // This trait brings in massgovLogin()
  use UserTestTrait;

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
    $this->massgovLogin(User::load(1));
    $session = $this->getSession();
    $session->visit('/node/add/alert');
    $page = $session->getPage();
    $page->fillField('field_alert_display', 'specific_target_pages');
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->findButton('Save')->press();
    $this->assertContains('must show on at least one page', $page->getText());
  }

}
