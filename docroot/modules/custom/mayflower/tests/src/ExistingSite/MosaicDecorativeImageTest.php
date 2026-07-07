<?php

namespace Drupal\Tests\mayflower\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests that mosaic featured item images render as decorative.
 *
 * Mosaic images must render with an empty alt attribute even when an alt
 * value is stored in the database, because authors can no longer see or edit
 * alt text for these fields and the link text carries the content.
 */
class MosaicDecorativeImageTest extends ExistingSiteBase {

  /**
   * Creates an image file entity from a core fixture.
   */
  private function createImageFile(): File {
    $destination = 'public://' . $this->randomMachineName(12) . '.jpg';
    \Drupal::service('file_system')->copy('core/tests/fixtures/files/image-2.jpg', $destination, TRUE);
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $this->markEntityForCleanup($file);
    $file->save();
    return $file;
  }

  /**
   * Mosaic images render with an empty alt despite a stored alt value.
   */
  public function testMosaicImagesRenderEmptyAlt(): void {
    $highlight = $this->createImageFile();
    $image = $this->createImageFile();

    $featured_item = Paragraph::create([
      'type' => 'featured_item',
      'field_featured_item_highlight' => [
        'target_id' => $highlight->id(),
        'alt' => 'Legacy highlight alt text',
      ],
      'field_featured_item_image' => [
        'target_id' => $image->id(),
        'alt' => 'Legacy image alt text',
      ],
      'field_featured_item_link' => [
        'uri' => 'https://www.example.com',
        'title' => 'Example link text',
      ],
    ]);
    $featured_item->save();

    $featured_item_mosaic = Paragraph::create([
      'type' => 'featured_item_mosaic',
      'field_mosaic_heading' => 'Example Mosaic Heading',
      'field_featured_item_mosaic_items' => [$featured_item],
    ]);
    $featured_item_mosaic->save();

    $organization_section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [$featured_item_mosaic],
    ]);
    $organization_section->save();

    $org_page = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page ' . $this->randomMachineName(),
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'field_organization_sections' => [$organization_section],
    ]);
    $org_page->save();

    $this->drupalGet($org_page->toUrl()->toString());
    $this->assertEquals(200, $this->getSession()->getStatusCode());

    $images = $this->getSession()->getPage()->findAll('css', '.ma__featured-item img');
    $this->assertNotEmpty($images, 'Mosaic featured item images are rendered.');
    foreach ($images as $img) {
      $this->assertSame('', $img->getAttribute('alt'), 'Mosaic image renders with an empty alt attribute.');
    }
    $this->assertStringNotContainsString('Legacy highlight alt text', $this->getSession()->getPage()->getContent());
    $this->assertStringNotContainsString('Legacy image alt text', $this->getSession()->getPage()->getContent());
  }

}
