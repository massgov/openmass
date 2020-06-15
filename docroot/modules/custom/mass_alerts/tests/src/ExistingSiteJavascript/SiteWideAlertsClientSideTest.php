<?php

namespace Drupal\Tests\mass_alerts\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Test the client side of alert display.
 */
class SiteWideAlertsClientSideTest extends ExistingSiteWebDriverTestBase {

  const DURATION = 60000;

  /**
   * Test the client side of alert display.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSiteWideAlertDisplay() {
    // $this->markTestSkipped('Fails when DB already has a sitewide alert showing.');.

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'alert')
      ->condition('status', 1)
      ->condition('field_alert_display', 'site_wide')
      ->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      $node->moderation_state = MassModeration::UNPUBLISHED;
      $node->save();x
    }

    $related = $this->createNode([
      'type' => 'service_page',
      'title' => 'EmergencyAlertsClientSideTest Service Page',
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

    $jsWebAssert = $this->assertSession();

    // Make sure homepage shows the alert.
    $this->drupalGet('');
    $locator = '.ma__emergency-alerts h2';
    $jsWebAssert->waitForElementVisible('css', $locator, self::DURATION);

    // These lines are left here as examples of how to debug requests.
    // file_put_contents('public://screenshot.png', $this->getSession()->getScreenshot());
    // file_put_contents('public://' . drupal_basename($this->getSession()->getCurrentUrl()) . '.html', $this->getCurrentPageContent());

    $jsWebAssert->statusCodeEquals(200);
    $jsWebAssert->pageTextContains($node->getTitle());

    // Visit an arbitrary page and make sure the alert appears.
    $this->drupalGet('/orgs/office-of-the-governor');
    $jsWebAssert->waitForElementVisible('css', $locator, self::DURATION);
    $jsWebAssert->statusCodeEquals(200);
    $jsWebAssert->pageTextContains($node->getTitle());

    $this->drupalGet('/alerts');
    $jsWebAssert->pageTextContains($node->getTitle());
    $jsWebAssert->pageTextContains($alert_message_text);
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
    $this->drupalGet('');
    // Give time for element to appear (we don't expect it to ever do so).
    sleep(5);
    $jsWebAssert->statusCodeEquals(200);
    $jsWebAssert->pageTextNotContains($node->getTitle());
  }

}
