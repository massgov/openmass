<?php

namespace Drupal\Tests\mass_alerts\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Test the client side of alert display.
 */
class EmergencyAlertsClientSideTest extends ExistingSiteWebDriverTestBase {

  const DURATION = 60000;

  /**
   * Test the client side of alert display.
   */
  public function testAlertDisplay() {
    $this->markTestSkipped('Fails when DB already has a sitewide alert showing.');

    $related = $this->createNode([
      'type' => 'service_page',
      'title' => 'EmergencyAlertsClientSideTest Service Page',
    ]);
    $body = $this->randomMachineName();
    $message_paragraph = Paragraph::create([
      'type' => 'rich_text',
      'field_body' => $body,
    ]);
    $node = $this->createNode([
      'type' => 'alert',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'field_alert_display' => 'site_wide',
    // 'State 911 Department (6416)'.
      'field_alert_ref_contact' => ['target_id' => 6416],
      'field_alert_severity' => 'emergency_alert',
      'field_alert' => $message_paragraph,
      'field_alert_related_links_5' => [
        'uri' => 'entity:node/' . $related->id(),
        'title' => $related->getTitle(),
      ],
      'moderation_state' => 'published',
    ]);

    // Make sure homepage shows the alert.
    $this->visit('');
    $locator = '.ma__emergency-alerts h2';
    $jsWebAssert = $this->assertSession();
    $jsWebAssert->waitForElementVisible('css', $locator, self::DURATION);

    // These lines are left here as examples of how to debug requests.
    // file_put_contents('public://screenshot.png', $this->getSession()->getScreenshot());
    // file_put_contents('public://' . drupal_basename($this->getSession()->getCurrentUrl()) . '.html', $this->getCurrentPageContent());

    $jsWebAssert->statusCodeEquals(200);
    $jsWebAssert->pageTextContains($node->getTitle());

    // Visit an arbitrary page and make sure the alert appears.
    $this->visit('/orgs/office-of-the-governor');
    $jsWebAssert->waitForElementVisible('css', $locator, self::DURATION);
    $jsWebAssert->statusCodeEquals(200);
    $jsWebAssert->pageTextContains($node->getTitle());

    $this->visit('/alerts');
    $jsWebAssert->pageTextContains($node->getTitle());
    $jsWebAssert->pageTextContains($body);
    // A related link.
    $jsWebAssert->pageTextContains($related->getTitle());
    // A contact.
    $jsWebAssert->pageTextContains('State 911 Department');

    // Archive and assert that alert is removed from homepage.
    $node->set('moderation_state', MassModeration::TRASH);
    $node->setUnpublished();
    $node->save();
    // Dump browser cache.
    $this->getSession()->restart();
    $this->visit('');
    // Give time for element to appear (we don't expect it to ever do so).
    sleep(5);
    $jsWebAssert->statusCodeEquals(200);
    $jsWebAssert->pageTextNotContains($node->getTitle());
  }

}
