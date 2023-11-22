<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Ensures GTM is generated properly.
 */
class GoogleTagManagerTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Tests GTM on Homepage.
   */
  public function testGoogleTagManagerOnHomepage() {

    $this->drupalGet('<front>');
    $this->assertSession()->elementExists('css', 'script[src="https://www.googletagmanager.com/gtm.js?id=GTM-MPHNMQ"]');
    $data_layer = $this->getSession()->evaluateScript('window.dataLayer.length > 0');
    $this->assertTrue($data_layer, 'DataLayer is missing on the Homepage.');

  }

  /**
   * Tests GTM on Info details published page.
   */
  public function testGoogleTagManagerOnRandomPage() {
    // Adding one node, to show one result.
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => $this->randomMachineName(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->drupalGet($node->toUrl()->toString());
    $this->assertSession()->elementExists('css', 'script[src="https://www.googletagmanager.com/gtm.js?id=GTM-MPHNMQ"]');
    $data_layer = $this->getSession()->evaluateScript('window.dataLayer.length > 0');
    $this->assertTrue($data_layer, 'DataLayer is missing on the Page.');
  }

}
