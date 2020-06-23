<?php

namespace Drupal\mass_unpublish_reminders\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;

/**
 * Processes Tasks for mass_unpublish_reminders.
 *
 * @QueueWorker(
 *   id = "mass_unpublish_reminders_queue",
 *   title = @Translation("Node Unpublish Reminders: email queue"),
 *   cron = {"time" = 60}
 * )
 */
class UnpublishReminderQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($nid) {
    $node = Node::load($nid);
    $author = $node->getOwner();
    if (!empty($author)) {
      $author_mail = $author->getEmail();
      if (!empty($author->get('field_user_org'))) {
        if ($org = $author->get('field_user_org')->getValue()) {
          $org_id = $org[0]['target_id'];
          $result = \Drupal::entityQuery('user')
            ->condition('uid', $author->id(), '!=')
            ->condition('status', 1)
            ->condition('field_user_org', $org_id);
          $uids = $result->execute();
          if (!empty($uids)) {
            $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids);
            if (!empty($users)) {
              foreach ($users as $user) {
                if (!empty($user)) {
                  if ($user->hasRole('author') || $user->hasRole('editor')) {
                    $cc_mails[] = $user->getEmail();
                  }
                }
              }
            }
          }
        }
      }

      if (isset($cc_mails)) {
        $params['headers']['cc'] = implode(',', $cc_mails);
      }
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE]);

      if ($node->hasField('unpublish_on')) {
        if (!empty($node->unpublish_on->value)) {
          $unpublish_timestamp = $node->unpublish_on->value;
          $unpublish_date = \Drupal::service('date.formatter')->format($unpublish_timestamp, 'custom', 'F d, Y h:i a');
        }
      }
      $renderable = [
        '#theme' => 'mass_reminder_mail_template',
        '#page_url' => $url,
        '#unpublish_date' => $unpublish_date ?? '',
      ];
      $params['message'] = \Drupal::service('renderer')->renderPlain($renderable);

      $mailManager = \Drupal::service('plugin.manager.mail');
      $result = $mailManager->mail('mass_unpublish_reminders', 'unpublish_reminder', $author_mail, 'en', $params, TRUE);
      if ($result['result'] == TRUE) {
        $database = \Drupal::database();
        $query = $database->insert('mass_unpublish_reminders');
        $query->fields([
          'nid' => $nid,
          'reminder_sent' => \Drupal::time()->getRequestTime(),
        ])->execute();
      }
    }
  }

}
