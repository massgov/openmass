<?php

namespace Drupal\Tests\mass_alerts\ExistingSite;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\file\Entity\File;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests our extended purging functionality.
 */
class AutomatedPurgingTest extends ExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    /** @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $queue */
    $queue = \Drupal::service('purge.queue');
    $queue->emptyQueue();
  }

  /**
   * Test file URL purging.
   */
  public function testFileCreationResultsInPurge() {
    // Create a "Llama" media item.
    file_put_contents('public://llama-43.txt', 'Test');
    file_put_contents('public://llama-44.txt', 'Test');
    $file = File::create([
      'uri' => 'public://llama-43.txt',
    ]);
    $file->save();
    $this->markEntityForCleanup($file);
    $this->assertCount(1, $this->getInvalidations('url', $file->url()));
    $file->set('uri', 'public://llama-44.txt');
    $file->save();
    $this->assertCount(1, $this->getInvalidations('url', $file->url()));
  }

  /**
   * Test that purge is skipped for private files.
   */
  public function testFileCreationPrivateResultsInNoPurge() {
    $dir = PrivateStream::basePath();
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    // Create a "Llama" media item  - private.
    file_put_contents('private://llama-45.txt', 'Test');
    $file = File::create([
      'uri' => 'private://llama-45.txt',
    ]);
    $file->save();
    $this->markEntityForCleanup($file);
    $this->assertCount(0, $this->getInvalidations('url', $file->url()));
  }

  /**
   * Test alias purging.
   */
  public function testAliasCreationResultsInPurge() {
    $node = $this->createNode([
      'type' => 'page',
      'path' => [
        'alias' => '/foo-foo',
      ],
    ]);
    $this->assertCount(1, $this->getInvalidations('url', 'http://mass.local/foo-foo'));
    $node->path->alias = '/foo-bar';
    $node->save();
    $this->assertCount(1, $this->getInvalidations('url', 'http://mass.local/foo-bar'));
  }

  /**
   * Get the invalidations matching a type and expression.
   */
  private function getInvalidations($type, $expression) {
    /** @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $queue */
    $queue = \Drupal::service('purge.queue');

    $invalidations = $queue->claim(100);

    $matching = array_filter($invalidations, function ($invalidation) use ($type, $expression) {
      return $invalidation->getType() === $type && $invalidation->getExpression() === $expression;
    });
    $queue->release($invalidations);

    return $matching;

  }

}
