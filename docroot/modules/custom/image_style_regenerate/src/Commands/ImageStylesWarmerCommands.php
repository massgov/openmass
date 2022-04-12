<?php

namespace Drupal\image_style_regenerate\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Commands\DrushCommands;

class ImageStylesWarmerCommands extends DrushCommands {

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * Warms up all the existing images by using the default configuration.
   *
   * @command image-style-regenerate:warm-up
   * @aliases isw:wu
   */
  public function warmUp() {
    $this->logger()->notice("Loading files.");
    $this->loggerChannelFactory->get('image_style_regenerate')->info('Image styles warmer loading files.');

    $files = \Drupal::entityQuery('file')
      ->condition('filemime', ['image/jpeg', 'image/jpg', 'image/gif', 'image/png'], 'IN')
      ->condition('status', \Drupal\file\FileInterface::STATUS_PERMANENT)
      ->execute();
    if (!empty($files)) {
      $count = count($files);
      $numOperations = 0;
      $operations = [];

      $this->loggerChannelFactory->get('image_style_regenerate')->info('Image styles warmer batch operations start');
      foreach ($files as $fid) {
        $operations[] = [
          '\Drupal\image_style_regenerate\BatchService::warmUpFileProcess',
          [$fid, $count],
        ];
        $numOperations++;
      }

      $batch = [
        'title' => t('Warming up image styles for @num file(s)', ['@num' => $numOperations]),
        'operations' => $operations,
        'finished' => '\Drupal\image_style_regenerate\BatchService::warmUpFileFinished',
      ];
      batch_set($batch);
      drush_backend_batch_process();

      $this->logger()->notice("Batch operations end.");
      $this->loggerChannelFactory->get('image_style_regenerate')->info('Image styles warmer batch operations end.');
    }
    else {
      $this->logger()->notice("No files found.");
      $this->logger()->warning('No files found to warm up.');
      $this->loggerChannelFactory->get('image_style_regenerate')->info('No files found to warm up.');
    }
  }

}
