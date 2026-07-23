<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\file\Entity\File;
use Drupal\mass_content\Drush\Commands\MassContentCommands;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests the ma:mosaic-alt-report drush command.
 *
 * The report must find mosaic images with stored alt text through every
 * placement chain, attribute them to the node owner, skip whitespace-only
 * alt values, and skip paragraphs that only belong to old node revisions.
 * The site DB contains real mosaic rows, so all assertions filter the
 * report down to the fixtures created here.
 */
class MosaicAltReportCommandTest extends MassExistingSiteBase {

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
   * Creates a featured_item_mosaic paragraph with a single item.
   */
  private function createMosaic(string $image_alt): Paragraph {
    $item = Paragraph::create([
      'type' => 'featured_item',
      'field_featured_item_highlight' => [
        'target_id' => $this->createImageFile()->id(),
        'alt' => '',
      ],
      'field_featured_item_image' => [
        'target_id' => $this->createImageFile()->id(),
        'alt' => $image_alt,
      ],
      'field_featured_item_link' => [
        'uri' => 'https://www.example.com',
        'title' => 'Example link text',
      ],
    ]);
    $item->save();

    $mosaic = Paragraph::create([
      'type' => 'featured_item_mosaic',
      'field_mosaic_heading' => 'Report fixture mosaic',
      'field_featured_item_mosaic_items' => [$item],
    ]);
    $mosaic->save();
    return $mosaic;
  }

  /**
   * Creates an org_page hosting a mosaic through org_section_long_form.
   */
  private function createOrgPageWithMosaic(string $image_alt, int $uid): NodeInterface {
    $section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [$this->createMosaic($image_alt)],
    ]);
    $section->save();

    return $this->createNode([
      'type' => 'org_page',
      'title' => 'Report fixture org page ' . $this->randomMachineName(),
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'uid' => $uid,
      'field_organization_sections' => [$section],
    ]);
  }

  /**
   * Runs the command and returns the report rows for the given nids.
   */
  private function reportRowsForNodes(array $nids): array {
    $command = new MassContentCommands(
      \Drupal::entityTypeManager(),
      \Drupal::service('logger.factory'),
      \Drupal::service('entity_field.manager'),
      \Drupal::database()
    );
    $result = $command->mosaicAltReport();
    $this->assertInstanceOf(RowsOfFields::class, $result);
    return array_values(array_filter(
      $result->getArrayCopy(),
      fn(array $row) => in_array((int) $row['nid'], $nids, TRUE)
    ));
  }

  /**
   * The report covers every mosaic chain and attributes rows to the owner.
   */
  public function testReportFindsAllChainsForCurrentRevisions(): void {
    $owner = $this->createUser();
    $uid = (int) $owner->id();

    // Chain: org_page -> org_section_long_form -> mosaic.
    $org_page = $this->createOrgPageWithMosaic('Fixture org alt', $uid);

    // Chain: service_page -> mosaic (direct).
    $direct_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Report fixture direct ' . $this->randomMachineName(),
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'uid' => $uid,
      'field_service_sections' => [$this->createMosaic('Fixture direct alt')],
    ]);

    // Chain: service_page -> service_section -> mosaic.
    $service_section = Paragraph::create([
      'type' => 'service_section',
      'field_service_section_content' => [$this->createMosaic('Fixture nested alt')],
    ]);
    $service_section->save();
    $nested_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Report fixture nested ' . $this->randomMachineName(),
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'uid' => $uid,
      'field_service_sections' => [$service_section],
    ]);

    $rows = $this->reportRowsForNodes([
      (int) $org_page->id(),
      (int) $direct_page->id(),
      (int) $nested_page->id(),
    ]);

    $by_alt = array_column($rows, 'section_paragraph_type', 'alt_text');
    ksort($by_alt);
    $this->assertSame([
      'Fixture direct alt' => 'featured_item_mosaic (direct)',
      'Fixture nested alt' => 'service_section',
      'Fixture org alt' => 'org_section_long_form',
    ], $by_alt);

    foreach ($rows as $row) {
      $this->assertSame($owner->getAccountName(), $row['user_name']);
      $this->assertSame($owner->getEmail(), $row['user_email']);
      $this->assertSame('field_featured_item_image', $row['image_field']);
      $this->assertSame('published', $row['node_status']);
    }
  }

  /**
   * Whitespace-only alt values are not reported.
   */
  public function testReportSkipsWhitespaceOnlyAlt(): void {
    $node = $this->createOrgPageWithMosaic('   ', (int) $this->createUser()->id());
    $this->assertSame([], $this->reportRowsForNodes([(int) $node->id()]));
  }

  /**
   * Alt values that only exist in an old node revision are not reported.
   */
  public function testReportSkipsOldRevisionOnlyAlt(): void {
    $node = $this->createOrgPageWithMosaic('Fixture old revision alt', (int) $this->createUser()->id());
    $this->assertCount(1, $this->reportRowsForNodes([(int) $node->id()]));

    // Drop the mosaic from a new default revision; the paragraph with the
    // alt text still exists, but only the old revision references it.
    $node->set('field_organization_sections', []);
    $node->setNewRevision(TRUE);
    $node->save();

    $this->assertSame([], $this->reportRowsForNodes([(int) $node->id()]));
  }

}
