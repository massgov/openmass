<?php

namespace Drupal\Tests\mass_feedback_loop\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\mass_feedback_loop\Service\MassFeedbackLoopContentFetcher;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests enriching API CSV rows with current node owner metadata.
 *
 * @group mass_feedback_loop
 */
class FeedbackOwnerCsvTest extends TestCase {

  /**
   * Existing columns, multiline feedback and multiple owners survive export.
   */
  public function testExport(): void {
    $terms = [];
    foreach (['Smith, Jane (DPH)', 'Alex "AJ" Lee'] as $name) {
      $term = $this->createMock(TermInterface::class);
      $term->method('label')->willReturn($name);
      $terms[] = $term;
    }
    $field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $field->method('access')->with('view')->willReturn(TRUE);
    $field->method('referencedEntities')->willReturn($terms);
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(11);
    $node->method('access')->with('view')->willReturn(TRUE);
    $node->method('hasField')->with('field_sme_content_owner')->willReturn(TRUE);
    $node->method('get')->with('field_sme_content_owner')->willReturn($field);
    $restricted_node = $this->createMock(NodeInterface::class);
    $restricted_node->method('access')->with('view')->willReturn(FALSE);
    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->expects($this->once())->method('loadMultiple')->with([11, 12, 99])
      ->willReturn([11 => $node, 12 => $restricted_node]);
    $storage->expects($this->once())->method('resetCache')->with([11, 12, 99]);
    $service = $this->service($storage);

    $original = [
      [
        'id', 'author_id', 'node_id', 'node_path', 'node_title', 'submit_date',
        'info_found', 'text', 'flagged_inappropriate', 'organization',
      ],
      [
        '1', '3', '11', '/example', 'Title', '2026-10-01', 'false',
        "First line\nSecond, \"quoted\" line", 'false', 'DPH',
      ],
      ['2', '3', '11', '/example', 'Title', '2026-10-02', 'true', 'More feedback', 'false', 'DPH'],
      ['3', '3', '12', '/restricted', 'Restricted', '2026-10-02', 'true', 'Text', 'false', 'DPH'],
      ['4', '3', '99', '/deleted', 'Deleted', '2026-10-02', 'true', 'Text', 'false', 'DPH'],
    ];
    $input = fopen('php://temp', 'r+');
    foreach ($original as $row) {
      fputcsv($input, $row, ',', '"', '');
    }
    rewind($input);
    $csv = $service->addOwnersToCsv(stream_get_contents($input));
    fclose($input);
    $output = fopen('php://temp', 'r+');
    fwrite($output, $csv);
    rewind($output);
    foreach ($original as $index => $row) {
      $exported = fgetcsv($output, 0, ',', '"', '');
      $this->assertSame($row, array_slice($exported, 0, count($row)));
      $expected = $index === 0 ? 'Content Owner / SME' : ($index < 3 ? 'Smith, Jane (DPH); Alex "AJ" Lee' : '');
      $this->assertSame($expected, end($exported));
    }
    $this->assertFalse(fgetcsv($output, 0, ',', '"', ''));
    fclose($output);
  }

  /**
   * Missing identifiers fail explicitly instead of producing incorrect owners.
   */
  public function testMissingNodeId(): void {
    $service = $this->service($this->createMock(NodeStorageInterface::class));
    $this->expectException(\UnexpectedValueException::class);
    $service->addOwnersToCsv("id,text\n1,Feedback\n");
  }

  /**
   * Creates a service using only the dependencies needed by the CSV helper.
   */
  private function service(NodeStorageInterface $storage): MassFeedbackLoopContentFetcher {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('node')->willReturn($storage);
    return new class($manager) extends MassFeedbackLoopContentFetcher {

      public function __construct(EntityTypeManagerInterface $manager) {
        $this->entityTypeManager = $manager;
      }

    };
  }

}
