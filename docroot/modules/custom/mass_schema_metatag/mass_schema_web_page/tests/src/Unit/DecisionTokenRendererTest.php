<?php

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\mass_schema_web_page\DecisionTokenRenderer;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test rendering tokens for the Decision content type.
 *
 * @group mass_schema_web_page
 */
class DecisionTokenRendererTest extends TestCase {

  /**
   * Test that we accept rendering of a Decision node.
   */
  public function testIsRenderable(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $renderer = new DecisionTokenRenderer($entityTypeManager, $fileUrlGenerator);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('decision');
    $this->assertTrue($renderer->isRenderable($node));
  }

  /**
   * Test that we reject rendering of a non-decision nodes.
   */
  public function testIsNotRenderable(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $renderer = new DecisionTokenRenderer($entityTypeManager, $fileUrlGenerator);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('not_decision');
    $this->assertFalse($renderer->isRenderable($node));
  }

  /**
   * Test that we throw an exception when trying to render a non-decision node.
   */
  public function testNotCheckingBundle(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $renderer = new DecisionTokenRenderer($entityTypeManager, $fileUrlGenerator);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('not_decision');
    $this->expectException(\LogicException::class);
    $renderer->getTokens(['title' => 'node:title'], $node);
  }

  /**
   * Test that we return an array even when the media item has been deleted.
   */
  public function testMediaHasBeenDeleted(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);
    $storage->expects($this->once())->method('load')
      ->willReturn(FALSE);

    $this->deletedEntityTest($entityTypeManager);
  }

  /**
   * Test that we return an array even when the file item has been deleted.
   */
  public function testFileHasBeenDeleted(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);
    $media = $this->createMock(MediaInterface::class);
    $ref = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $ref->method('getValue')
      ->willReturn([['target_id' => 1234]]);
    $media->method('get')->with('field_upload_file')->willReturn($ref);
    $storage->expects($this->exactly(2))->method('load')
      ->willReturn($media, FALSE);

    $this->deletedEntityTest($entityTypeManager);
  }

  /**
   * Common code for testing both deleted media and file entities.
   */
  private function deletedEntityTest($entityTypeManager): void {
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $renderer = new DecisionTokenRenderer($entityTypeManager, $fileUrlGenerator);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('decision');
    $node->method('hasField')->willReturn(TRUE);

    $ref = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $ref->method('getValue')
      ->willReturn([['target_id' => 1234]]);

    $mockFieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $mockFieldDefinition->expects($this->exactly(2))
      ->method('getSetting')
      ->willReturnOnConsecutiveCalls('media', ['target_bundles' => ['document' => 'Document']]);

    $ref->method('getFieldDefinition')->willReturn($mockFieldDefinition);
    $node->method('get')->with('field_decision_download')->willReturn($ref);
    $this->assertIsArray($renderer->getTokens(['field_decision_download' => '[node:field_decision_download]'], $node));
  }

}
