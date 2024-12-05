<?php

namespace Drupal\Tests\mass_fields\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests image alt text descriptions across multiple content types and fields.
 *
 * This test class verifies the correct behavior of image alt text descriptions
 * across various content types, ensuring that the correct alt text description
 * is displayed and that fields are properly configured regarding the alt field
 * being required or not.
 *
 * The tests included cover the following:
 *
 * 1. **Org Page Alt Text Descriptions**:
 *    - Verifies the alt text descriptions for `field_featured_item_highlight`
 *      and `field_featured_item_image` within the `Org Page` content type.
 *    - Ensures that the alt text descriptions match the expected values for accessibility.
 *    - Asserts that the `alt_field_required` setting is false for both fields.
 *
 * 2. **Event Alt Text Descriptions**:
 *    - Verifies the alt text descriptions for `field_event_image` and `field_event_logo`
 *      in the `Event` content type.
 *    - Ensures that the alt text descriptions reflect proper accessibility guidelines.
 *    - Checks that the `alt_field_required` setting is false for both fields.
 *
 * 3. **News Alt Text Description**:
 *    - Verifies that the `field_news_image` in the `News` content type does not
 *      get overridden by descriptions for other fields and retains its default description.
 *    - Ensures the alt text description is correct for accessibility, specifically:
 *      "Short description of the image used by screen readers and displayed when the image
 *      is not loaded. This is important for accessibility."
 */
class ImageAltDescriptionTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Creates an admin, saves it, and returns it.
   */
  private function createAdmin() {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    return $admin;
  }

  /**
   * Creates and returns an org page with a featured_item_mosaic.
   */
  private function createOrgPage() {
    // Create image for field_featured_item_highlight.
    $image_highlight = File::create([
      'uri' => 'public://test_highlight.jpg',
    ]);
    $this->markEntityForCleanup($image_highlight);
    $image_highlight->save();

    // Create image for field_featured_item_image.
    $image_item = File::create([
      'uri' => 'public://test_item.jpg',
    ]);
    $this->markEntityForCleanup($image_item);
    $image_item->save();

    // Create the featured_item paragraph with two images.
    $featured_item = Paragraph::create([
      'type' => 'featured_item',
      'field_featured_item_highlight' => [
        'target_id' => $image_highlight->id(),
      ],
      'field_featured_item_image' => [
        'target_id' => $image_item->id(),
      ],
    ]);
    $featured_item->save();

    // Create the featured_item_mosaic paragraph with a heading and reference to the featured_item.
    $featured_item_mosaic = Paragraph::create([
      'type' => 'featured_item_mosaic',
      'field_mosaic_heading' => 'Example Mosaic Heading',
      'field_featured_item_mosaic_items' => [
        $featured_item,
      ],
    ]);
    $featured_item_mosaic->save();

    // Create the organization_section paragraph to include featured_item_mosaic.
    $organization_section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [
        $featured_item_mosaic,
      ],
    ]);
    $organization_section->save();

    // Create the Org Page node and associate the organization_section.
    $org_page = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'field_organization_sections' => [$organization_section],
    ]);
    $org_page->save();

    return $org_page;
  }

  /**
   * Creates and returns an event node with event image and logo.
   */
  private function createEvent() {
    // Create image for field_event_image.
    $event_image = File::create([
      'uri' => 'public://test_event_image.jpg',
    ]);
    $this->markEntityForCleanup($event_image);
    $event_image->save();

    // Create image for field_event_logo.
    $event_logo = File::create([
      'uri' => 'public://test_event_logo.jpg',
    ]);
    $this->markEntityForCleanup($event_logo);
    $event_logo->save();

    // Create the event node.
    $event_node = $this->createNode([
      'type' => 'event',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
      'field_event_image' => [
        'target_id' => $event_image->id(),
      ],
      'field_event_logo' => [
        'target_id' => $event_logo->id(),
      ],
    ]);
    $event_node->save();

    return $event_node;
  }

  /**
   * Creates and returns a news node with an image.
   */
  private function createNews() {
    // Create image for field_news_image.
    $news_image = File::create([
      'uri' => 'public://test_news_image.jpg',
    ]);
    $this->markEntityForCleanup($news_image);
    $news_image->save();

    // Create the news node.
    $news_node = $this->createNode([
      'type' => 'news',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'field_news_image' => [
        'target_id' => $news_image->id(),
      ],
    ]);
    $news_node->save();

    return $news_node;
  }

  /**
   * Tests the alt text description for News images (field_news_image) and ensures it's not overridden.
   */
  public function testNewsImageAltTextDescription() {
    $this->drupalLogin($this->createAdmin());

    // Create the News node.
    $news = $this->createNews();

    // Edit the News node to verify the alt text description for field_news_image.
    $this->drupalGet($news->toUrl('edit-form')->toString());

    // Wait for the page to load and ensure we are on the right edit form.
    $page = $this->getSession()->getPage();

    // Verify alt text description for `field_news_image`.
    $news_image_alt_description = $page->find('css', '#edit-field-news-image-0-alt--description')->getHtml();
    // Trim the HTML content to remove extra whitespace.
    $news_image_alt_description = trim($news_image_alt_description);

    // Assert that the description is the expected default one.
    $this->assertEquals(
      'Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.',
      $news_image_alt_description,
      'Alt text description for field_news_image is correct and has not been overridden.'
    );

    // Ensure that the overridden descriptions are not applied.
    $this->assertNotEquals(
      'If the image conveys information that is not part of the link text, describe it here. If the image is purely decorative, leave it blank. Screen readers will read the alt text first, then the link text.',
      $news_image_alt_description,
      'Alt text description for field_news_image is not overridden by other field descriptions.'
    );

    $this->assertNotEquals(
      'Enter a short description of the image to be used by screen readers ONLY if the image is not decorative. If the image is decorative (only adds visual decoration to the page, rather than to convey information that is important to understanding the page) leave this field blank.',
      $news_image_alt_description,
      'Alt text description for field_news_image is not overridden by event image descriptions.'
    );
  }

  /**
   * Verifies image alt text descriptions.
   */
  public function testOrgPageImageAltTextDescriptions() {
    $this->drupalLogin($this->createAdmin());

    // Create the Org Page.
    $org_page = $this->createOrgPage();

    // Edit the Org Page to verify the alt text description for images.
    $this->drupalGet($org_page->toUrl('edit-form')->toString());

    // Wait for the page to load and ensure we are on the right edit form.
    $page = $this->getSession()->getPage();

    // Verify alt text description for `field_featured_item_highlight`.
    $highlight_alt_description = $page->find('css', '#edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-featured-item-mosaic-items-0-subform-field-featured-item-highlight-0-alt--description')->getHtml();
    // Trim the HTML content to remove extra whitespace.
    $highlight_alt_description = trim($highlight_alt_description);
    $this->assertEquals(
      'If the image conveys information that is not part of the link text, describe it here. If the image is purely decorative, leave it blank. Screen readers will read the alt text first, then the link text.',
      $highlight_alt_description,
      'Alt text description for field_featured_item_highlight is correct.'
    );

    // Verify alt text description for `field_featured_item_image`.
    $item_alt_description = $page->find('css', '#edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-featured-item-mosaic-items-0-subform-field-featured-item-image-0-alt--description')->getHtml();
    $item_alt_description = trim($item_alt_description);
    $this->assertEquals(
      'If the image conveys information that is not part of the link text, describe it here. If the image is purely decorative, leave it blank. Screen readers will read the alt text first, then the link text.',
      $item_alt_description,
      'Alt text description for field_featured_item_image is correct.'
    );

    // Check field settings for alt_field_required.
    $field_definition = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'featured_item');
    $highlight_settings = $field_definition['field_featured_item_highlight']->getSettings();
    $image_settings = $field_definition['field_featured_item_image']->getSettings();

    // Assert that alt_field_required is false for both fields.
    $this->assertFalse($highlight_settings['alt_field_required'], 'Alt field for field_featured_item_highlight is not required.');
    $this->assertFalse($image_settings['alt_field_required'], 'Alt field for field_featured_item_image is not required.');
  }

  /**
   * Tests the alt text description for Event images (field_event_image and field_event_logo).
   */
  public function testEventImageAltTextDescriptions() {
    $this->drupalLogin($this->createAdmin());

    // Create the Event node.
    $event = $this->createEvent();

    // Edit the Event node to verify the alt text description for images.
    $this->drupalGet($event->toUrl('edit-form')->toString());

    // Wait for the page to load and ensure we are on the right edit form.
    $page = $this->getSession()->getPage();

    // Verify alt text description for `field_event_image`.
    $event_image_alt_description = $page->find('css', '#edit-field-event-image-0-alt--description')->getHtml();
    // Trim the HTML content to remove extra whitespace.
    $event_image_alt_description = trim($event_image_alt_description);
    $this->assertEquals(
      'Enter a short description of the image to be used by screen readers ONLY if the image is not decorative. If the image is decorative (only adds visual decoration to the page, rather than to convey information that is important to understanding the page) leave this field blank.',
      $event_image_alt_description,
      'Alt text description for field_event_image is correct.'
    );

    // Verify alt text description for `field_event_logo`.
    $event_logo_alt_description = $page->find('css', '#edit-field-event-logo-0-alt--description')->getHtml();
    $event_logo_alt_description = trim($event_logo_alt_description);
    $this->assertEquals(
      'Enter a short description of the image to be used by screen readers ONLY if the image is not decorative. If the image is decorative (only adds visual decoration to the page, rather than to convey information that is important to understanding the page) leave this field blank.',
      $event_logo_alt_description,
      'Alt text description for field_event_logo is correct.'
    );

    // Check field settings for alt_field_required.
    $field_definition = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'event');
    $image_settings = $field_definition['field_event_image']->getSettings();
    $logo_settings = $field_definition['field_event_logo']->getSettings();

    // Assert that alt_field_required is false for both fields.
    $this->assertFalse($image_settings['alt_field_required'], 'Alt field for field_event_image is not required.');
    $this->assertFalse($logo_settings['alt_field_required'], 'Alt field for field_event_logo is not required.');
  }

}
