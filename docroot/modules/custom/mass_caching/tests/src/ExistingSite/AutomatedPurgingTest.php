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
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $queue */
    $queue = \Drupal::service('purge.queue');
    $queue->emptyQueue();
    // Ensure that mass_caching_purge_purgers_alter() executes during the phpunit run.
    $manager = \Drupal::service('plugin.cache_clearer');
    $manager->clearCachedDefinitions();
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
    $relative = $file->createFileUrl(TRUE);
    // Make URL the same way that ManualPurger::purgePath() does it.
    $absolute = sprintf('%s://%s%s', 'http', 'stage.mass.gov', $relative);
    $this->assertCount(1, $this->getInvalidations('url', $absolute));
    $file->set('uri', 'public://llama-44.txt');
    $file->save();
    $relative = $file->createFileUrl(TRUE);
    $absolute = sprintf('%s://%s%s', 'http', 'stage.mass.gov', $relative);
    $this->assertCount(1, $this->getInvalidations('url', $absolute));
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
    $relative = $file->createFileUrl(TRUE);
    $absolute = sprintf('%s://%s%s', 'http', 'stage.mass.gov', $relative);
    $this->assertCount(0, $this->getInvalidations('url', $absolute));
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
    $absolute = $node->toUrl('canonical', ['base_url' => 'http://stage.mass.gov', 'absolute' => TRUE])->toString();
    $this->assertCount(1, $this->getInvalidations('url', $absolute));
    $node->path->alias = '/foo-bar';
    $node->save();
    $absolute = $node->toUrl('canonical', ['base_url' => 'http://stage.mass.gov', 'absolute' => TRUE])->toString();
    $this->assertCount(1, $this->getInvalidations('url', $absolute));
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
