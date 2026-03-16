<?php

namespace Drupal\Tests\mass_fields\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests decorative checkbox behavior on the News image widget.
 *
 * @group mass_fields
 */
class DecorativeImageWidgetTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Creates and returns an admin user.
   */
  private function createAdmin() {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    return $admin;
  }

  /**
   * Creates and returns a news node with an image.
   */
  private function createNewsWithImage() {
    $image = File::create([
      'uri' => 'public://test_news_image.jpg',
    ]);
    $this->markEntityForCleanup($image);
    $image->save();

    $news_node = $this->createNode([
      'type' => 'news',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
      'field_news_image' => [
        'target_id' => $image->id(),
      ],
    ]);
    $news_node->save();

    return $news_node;
  }

  /**
   * Verifies that decorative_image_widget adds the checkbox to field_news_image.
   */
  public function testNewsImageHasDecorativeCheckbox() {
    $this->drupalLogin($this->createAdmin());

    $news = $this->createNewsWithImage();

    // Edit the News node and inspect the image widget.
    $this->drupalGet($news->toUrl('edit-form')->toString());
    $page = $this->getSession()->getPage();

    $widget = $page->find('css', '.field--name-field-news-image .image-widget');
    $this->assertNotNull($widget, 'Image widget container found for field_news_image.');

    // Decorative checkbox should be present when decorative_image_widget is enabled
    // and configured for this field.
    $decorative_checkbox = $widget->find('css', 'input.decorative-checkbox[type="checkbox"]');
    $this->assertNotNull($decorative_checkbox, 'Decorative checkbox present on field_news_image widget.');
  }

}

