<?php

namespace Drupal\mass_unpublish_reminders\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Url;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;

/**
 * Processes Tasks for mass_unpublish_reminders.
 *
 * @QueueWorker(
 *   id = "mass_unpublish_reminders_queue",
 *   title = @Translation("Node Unpublish Reminders: email queue")
 * )
 */
class UnpublishReminderQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($nid) {
    if (!$node = Node::load($nid)) {
      // Just mark this item as processed. The node is eligible in next cron run.
      return;
    }
    $author = $node->getOwner();
    if (!empty($author)) {
      $author_mail = $author->getEmail();
      if (!empty($author->getOrg())) {
        if ($org = $author->getOrg()->getValue()) {
          $org_id = $org[0]['target_id'];
          $result = \Drupal::entityQuery('user')
            ->accessCheck(FALSE)
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
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid])->setAbsolute()->toString();

      $transitions = mass_scheduled_transitions_load_by_host_entity($node, FALSE, MassModeration::UNPUBLISHED);
      if (empty($transitions)) {
        // This node became published or something. Just mark as processed.
        return;
      }
      // Just pick the first one since multiple unpublishes is super rare.
      $transition = array_shift($transitions);
      $unpublish_date = $transition->getTransitionDate()->format('F d, Y h:i a');

      $params['message'] = t("A Promotional page or Alert that you or someone in your organization authored has an unpublish date that will arrive soon. At that time, the content will be unpublished.\n\nPage: :page_url\nUnpublish date: @unpublish_date\n\nIf you want to keep the page or alert, please review it, check its performance, and update it if necessary. You can then update the unpublish date.\n\nIf you no longer need a Promotional page or Alert, you can let it unpublish automatically or you can unpublish it manually now. If you think there could still be traffic to the Promotional page, please make a ServiceNow ticket to redirect that traffic to an appropriate page.\n\nIf you have any questions, please make a ServiceNow request.\n\nThank you.",
        [
          '@unpublish_date' => $unpublish_date,
          ':page_url' => $url,
        ]
      );

      // Log the email so we don't resend. We log first, because we really don't want
      // to re-send these emails. Its better if they never get sent.
      try {
        $database = \Drupal::database();
        $query = $database->insert('mass_unpublish_reminders');
        $query->fields([
          'nid' => $nid,
          'reminder_sent' => \Drupal::time()->getRequestTime(),
        ])->execute();
      }
      catch (\Exception $e) {
        // It shouldn't happen, but we are seeing an already existing nid being
        // reprocessed. Rather than rewrite_mass_unpublish_reminders_cron_helper(),
        // lets mark this item as processed, and keep processing the queue. See
        // https://jira.mass.gov/browse/DP-19494.
        return;
      }

      $mailManager = \Drupal::service('plugin.manager.mail');
      if (!$mailManager->mail('mass_unpublish_reminders', 'unpublish_reminder', $author_mail, 'en', $params, TRUE)) {
        // Something is really wrong with mail enqueue.
        throw new SuspendQueueException('Unable to send mass_unpublish_reminder email');
      }
    }
  }

}
