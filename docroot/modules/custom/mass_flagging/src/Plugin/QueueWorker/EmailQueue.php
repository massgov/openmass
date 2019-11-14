<?php

namespace Drupal\mass_flagging\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes Tasks for mass_flagging.
 *
 * @QueueWorker(
 *   id = "mass_flagging_email_queue",
 *   title = @Translation("Watching task worker: email queue"),
 *   cron = {"time" = 60}
 * )
 */
class EmailQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $mailManager->mail('mass_flagging', 'email_queue', $data['user']->getEmail(), 'en', $data['params'], $send = TRUE);
  }

}
