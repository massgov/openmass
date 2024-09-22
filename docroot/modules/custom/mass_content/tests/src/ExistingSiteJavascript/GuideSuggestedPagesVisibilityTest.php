<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests the visibility of the suggested pages block on a guide page.
 */
class GuideSuggestedPagesVisibilityTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Tests the visibility of the suggested pages block based on related guides.
   */
  public function testSuggestedPagesVisibility() {
    // Create a related guide page node.
    $related = $this->createNode([
      'type' => 'guide_page',
      'title' => 'Test Related',
      'moderation_state' => 'published',
    ]);

    // Create a guide page node with no related guides.
    $node = $this->createNode([
      'type' => 'guide_page',
      'title' => 'Test Guide',
      'field_guide_page_related_guides' => [],
      'moderation_state' => 'published',
    ]);

    // Visit the guide page and verify that
    // the suggested pages block is not rendered.
    $this->drupalGet($node->toUrl()->toString());
    $this->assertSession()->elementNotExists('css', '.post-content .ma__suggested-pages');

    // Update the guide page node to add a related guide.
    $node->set('field_guide_page_related_guides', [$related]);
    $node->save();

    // Visit the guide page again and verify that
    // the suggested pages block is now rendered.
    $this->drupalGet($node->toUrl()->toString());
    $this->assertSession()->elementExists('css', '.post-content .ma__suggested-pages');

    // Unpublish the related guide and verify that
    // the suggested pages block is not rendered.
    $related->setUnpublished()->set('moderation_state', 'unpublished')->save();

    $this->drupalGet($node->toUrl()->toString());
    $this->assertSession()->elementNotExists('css', '.post-content .ma__suggested-pages');
  }

}
