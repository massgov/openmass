<?php

namespace Drupal\mass_utility\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;

/**
 * Process Organization Sync queue items.
 *
 * @QueueWorker(
 *   id = "mass_utility_organization_sync",
 *   title = @Translation("Syncronize organization related fields"),
 *   cron = {"time" = 120}
 * )
 */
class OrganizationSync extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $types = [
      'person' => 'field_person_ref_org',
    ];
    // Allow batching items to queue for faster initial processing.
    if (isset($data->ids)) {
      $result = Node::loadMultiple($data->ids);
      foreach ($result as $node) {
        $type = $node->bundle();
        if (!$node->field_organizations->equals($node->{$types[$type]})) {
          if ($node->{$types[$type]}->count() > 0) {
            $message = 'Syncing from ' . $types[$type] . ' to field_organizations. ';
            $message .= 'Organizations had the following value: ';
            foreach ($node->field_organizations->getValue() as $delta => $value) {
              if ($delta > 0) {
                $message .= ', ';
              }
              $message .= '"' . $node->field_organizations[$delta]->entity->title->value . '"';
            }
            $message .= '. Organizations now has the following value: ';
            foreach ($node->{$types[$type]}->getValue() as $delta => $value) {
              if ($delta > 0) {
                $message .= ', ';
              }
              $message .= '"' . $node->{$types[$type]}[$delta]->entity->title->value . '"';
            }
            $message .= '.';
            $node->field_organizations = $node->{$types[$type]}->getValue();
          }
          else {
            $message = 'Syncing from field_organizations to ' . $types[$type] . '. ';
            $message .= $types[$type] . ' was not populated. ';
            $message .= $types[$type] . ' now has the following value: ';
            foreach ($node->field_organizations->getValue() as $delta => $value) {
              if ($delta > 0) {
                $message .= ', ';
              }
              $message .= $node->field_organizations[$delta]->entity->title->value;
            }
            $message .= '.';
            $node->{$types[$type]} = $node->field_organizations->getValue();
          }
          $this->saveNodeWithRevision($node, $message);
        }
      }
    }
  }

  /**
   * Saves a node with a new revision.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to save.
   * @param string $message
   *   The message to save with the revision.
   */
  private function saveNodeWithRevision(Node $node, $message) {
    $node->setNewRevision();
    $node->setRevisionUserId(1);
    $node->setRevisionLogMessage($message);
    $node->setRevisionCreationTime(REQUEST_TIME);
    $node->save();
  }

}
