<?php

namespace Drupal\mass_unpublish_reminders\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;
use Drupal\Core\Link;

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
      $params = [];
      $cc_mails = [];
      if (!empty($author->get('field_user_org'))) {
        if ($org = $author->get('field_user_org')->getValue()) {
          $org_id = $org[0]['target_id'];
          $result = \Drupal::entityQuery('user')
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

      if (!empty($cc_mails)) {
        $params['headers']['Cc'] = implode(',', $cc_mails);
      }
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE]);
      $link = Link::fromTextAndUrl($node->label(), $url);
      $renderable = [
        '#theme' => 'mass_reminder_mail_template',
        '#page_url' => $link,
      ];
      $params['message'] = \Drupal::service('renderer')->renderPlain($renderable);

      $mailManager = \Drupal::service('plugin.manager.mail');
      $mailManager->mail('mass_unpublish_reminders', 'unpublish_reminder', $author_mail, 'en', $params, TRUE);
    }
  }

}
