<?php

namespace Drupal\Tests\mass_alerts\ExistingSite;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\file\Entity\File;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests our extended purging functionality.
 */
class AutomatedPurgingTest extends MassExistingSiteBase {

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
    $schemas = Settings::get('mass_caching.schemes', [
      parse_url(\Drupal::request()->getUri(), PHP_URL_SCHEME),
    ]);
    $hosts = Settings::get('mass_caching.hosts');
    $invalidations = [];
    foreach ($schemas as $schema) {
      foreach ($hosts as $host) {
        $absolute = sprintf('%s://%s%s', $schema, $host, $relative);
        $invalidations[] = $this->getInvalidations('uri', $absolute);
      }
    }

    // We expect this to be 2 for each host,
    // both edit.stage.mass.gov and stage.mass.gov
    $this->assertCount(2, $invalidations);
    $file->set('uri', 'public://llama-44.txt');
    $file->save();
    $relative = $file->createFileUrl(TRUE);
    $invalidations = [];
    foreach ($schemas as $schema) {
      foreach ($hosts as $host) {
        $absolute = sprintf('%s://%s%s', $schema, $host, $relative);
        $invalidations[] = $this->getInvalidations('uri', $absolute);
      }
    }
    // We expect this to be 2 for each host,
    // both edit.stage.mass.gov and stage.mass.gov
    $this->assertCount(2, $invalidations);
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
    $schemas = Settings::get('mass_caching.schemes', [
      parse_url(\Drupal::request()->getUri(), PHP_URL_SCHEME),
    ]);
    $hosts = Settings::get('mass_caching.hosts');
    $invalidations = [];
    foreach ($schemas as $schema) {
      foreach ($hosts as $host) {
        $absolute = $node->toUrl('canonical', ['base_url' => "$schema://$host", 'absolute' => TRUE])->toString();
        $invalidations[] = $this->getInvalidations('uri', $absolute);
      }
    }
    // We expect this to be 2 for each host,
    // both edit.stage.mass.gov and stage.mass.gov
    $this->assertCount(2, $invalidations);
    $node->path->alias = '/foo-bar';
    $node->save();

    $invalidations = [];
    foreach ($schemas as $schema) {
      foreach ($hosts as $host) {
        $absolute = $node->toUrl('canonical', ['base_url' => "$schema://$host", 'absolute' => TRUE])->toString();
        $invalidations[] = $this->getInvalidations('uri', $absolute);
      }
    }
    // We expect this to be 2 for each host,
    // both edit.stage.mass.gov and stage.mass.gov
    $this->assertCount(2, $invalidations);
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
