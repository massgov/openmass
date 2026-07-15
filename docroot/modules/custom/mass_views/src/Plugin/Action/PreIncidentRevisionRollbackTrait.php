<?php

namespace Drupal\mass_views\Plugin\Action;

use Drupal\Core\Session\AccountInterface;

/**
 * Shared logic for rolling back to the revision before an incident window.
 */
trait PreIncidentRevisionRollbackTrait {

  /**
   * Exposed filter values from the view running the bulk action.
   *
   * VBO rebuilds the view query without exposed filters during batch
   * processing, but stores the original filter values on the action context.
   */
  protected function getExposedFilterInput(): array {
    if (!empty($this->context['exposed_input']) && is_array($this->context['exposed_input'])) {
      return $this->context['exposed_input'];
    }

    return $this->view ? $this->view->getExposedInput() : [];
  }

  /**
   * User IDs from the revision author exposed filter.
   *
   * @return int[]
   *   Revision author user IDs from the exposed filter.
   */
  protected function parseIncidentUserIds(): array {
    $input = $this->getExposedFilterInput();
    if (empty($input['revision_uid'])) {
      return [];
    }

    $raw = is_array($input['revision_uid']) ? $input['revision_uid'] : [$input['revision_uid']];
    $uids = [];
    foreach ($raw as $value) {
      if (is_numeric($value)) {
        $uids[] = (int) $value;
        continue;
      }
      if (preg_match('/\((\d+)\)$/', (string) $value, $matches)) {
        $uids[] = (int) $matches[1];
      }
    }

    return array_values(array_unique($uids));
  }

  /**
   * Incident window start timestamp from exposed filters.
   */
  protected function getIncidentStartTimestamp(): ?int {
    $input = $this->getExposedFilterInput();
    if (empty($input['changed_from'])) {
      return NULL;
    }
    $timestamp = strtotime($input['changed_from']);
    return $timestamp === FALSE ? NULL : $timestamp;
  }

  /**
   * Incident window end timestamp from exposed filters.
   */
  protected function getIncidentEndTimestamp(): ?int {
    $input = $this->getExposedFilterInput();
    if (empty($input['changed_to'])) {
      return NULL;
    }
    $timestamp = strtotime($input['changed_to'] . ' 23:59:59');
    return $timestamp === FALSE ? NULL : $timestamp;
  }

  /**
   * Whether the account may use compromised account rollback.
   */
  protected function canRollbackCompromisedAccountRevisions(?AccountInterface $account = NULL): bool {
    $account = $account ?: \Drupal::currentUser();
    return $account->hasPermission('rollback compromised account revisions');
  }

  /**
   * Builds the revision log message for a rollback revision.
   */
  protected function buildRollbackRevisionLogMessage(int $target_revision_id, ?string $target_revision_log): string {
    $prefix = 'Rollback to revision #' . $target_revision_id;
    $message = trim((string) $target_revision_log);
    if ($message === '') {
      return $prefix;
    }
    return $prefix . ': ' . $message;
  }

  /**
   * Finds the revision immediately before the earliest match in the window.
   */
  protected function findPreviousRevisionId(
    int $entity_id,
    int $fallback_vid,
    string $field_revision_table,
    string $revision_table,
    string $id_field,
    string $revision_user_field,
    string $changed_field = 'changed',
  ): ?int {
    $connection = \Drupal::database();
    $query = $connection->select($field_revision_table, 'r');
    $query->join($revision_table, 'nr', 'r.vid = nr.vid');
    $query->fields('r', ['vid']);
    $query->condition("r.$id_field", $entity_id);
    $query->orderBy('r.vid', 'ASC');

    $uids = $this->parseIncidentUserIds();
    if ($uids) {
      $query->condition("nr.$revision_user_field", $uids, 'IN');
    }
    if ($start = $this->getIncidentStartTimestamp()) {
      $query->condition("r.$changed_field", $start, '>=');
    }
    if ($end = $this->getIncidentEndTimestamp()) {
      $query->condition("r.$changed_field", $end, '<=');
    }

    $earliest_compromised_vid = $query->range(0, 1)->execute()->fetchField();
    if (!$earliest_compromised_vid) {
      $earliest_compromised_vid = $fallback_vid;
    }

    $previous_vid = $connection->select($revision_table, 'nr')
      ->fields('nr', ['vid'])
      ->condition("nr.$id_field", $entity_id)
      ->condition('nr.vid', $earliest_compromised_vid, '<')
      ->orderBy('nr.vid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $previous_vid ? (int) $previous_vid : NULL;
  }

}
