<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

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
   * Tests GTM on random published page.
   */
  public function testGoogleTagManagerOnRandomPage() {
    $result = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->addTag('sort_by_random')
      ->range(0, 1)
      ->execute();
    $nid = reset($result);
    $this->drupalGet('node/' . $nid);
    $this->assertSession()->elementExists('css', 'script[src="https://www.googletagmanager.com/gtm.js?id=GTM-MPHNMQ"]');
    $data_layer = $this->getSession()->evaluateScript('window.dataLayer.length > 0');
    $this->assertTrue($data_layer, 'DataLayer is missing on the Page.');

  }

}
