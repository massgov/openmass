<?php

namespace Drupal\image_style_regenerate;

use Drupal\file\Entity\File;

/**
 * Class BatchService.
 *
 * @package Drupal\image_style_regenerate
 */
class BatchService {

  /**
   * Batch process callback.
   */
  public function warmUpFileProcess($fid, $count, &$context) {
    $image_styles_warmer = \Drupal::service('image_style_regenerate.warmer');
    $file = File::load($fid);
    $uri = $file->getFileUri();
    if (!file_exists($uri)) {
      return;
    }
    $image_styles_warmer->warmUp($file, [
      'action_banner_large',
      'hero1600x400_fp'
    ]);
    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, batch_example_finished()).
    $context['results'][] = $fid;
    $i = count($context['results']);
    // Optional message displayed under the progressbar.
    $context['message'] = t('Warming up styles for file @fid (@i/@count)',
      ['@fid' => $fid, '@i' => $i, '@count' => $count]
    );

  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public function warmUpFileFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('@count files warmed up.', ['@count' => count($results)]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
