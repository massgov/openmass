<?php

namespace Drupal\mass_redirect_normalizer\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mass_redirect_normalizer\RedirectLinkNormalizationManager;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Drush commands for redirect link normalization.
 */
final class MassRedirectNormalizerCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RedirectLinkNormalizationManager $normalizerManager,
  ) {
    parent::__construct();
  }

  /**
   * Normalizes redirected internal links in node and paragraph content.
   *
   * @command mass-redirect-normalizer:normalize-links
   * @field-labels
   *   status: Status
   *   entity_type: Entity Type
   *   entity_id: Entity ID
   *   bundle: Bundle
   *   details: Details
   * @default-fields status,entity_type,entity_id,bundle,details
   * @aliases mnrl
   * @option limit
   * @option entity-type
   * @option bundle
   * @option show-unchanged
   */
  public function normalizeRedirectLinks($options = ['limit' => 0, 'entity-type' => 'all', 'bundle' => NULL, 'show-unchanged' => FALSE]): RowsOfFields {
    $_ENV['MASS_FLAGGING_BYPASS'] = TRUE;
    $entityTypes = $options['entity-type'] === 'all' ? ['node', 'paragraph'] : [(string) $options['entity-type']];
    $limit = max(0, (int) $options['limit']);
    $showUnchanged = !empty($options['show-unchanged']);
    try {
      $simulate = Drush::simulate();
    }
    catch (\RuntimeException) {
      // Allow direct invocation in PHPUnit without Drush bootstrap.
      $simulate = FALSE;
    }
    $rows = [];
    $processed = 0;
    $changed = 0;
    $skipped = 0;

    foreach ($entityTypes as $entityType) {
      if (!in_array($entityType, ['node', 'paragraph'], TRUE)) {
        $rows[] = [
          'status' => 'unsupported',
          'entity_type' => $entityType,
          'entity_id' => 'N/A',
          'bundle' => 'N/A',
          'details' => 'Unsupported entity type',
        ];
        continue;
      }

      $idField = $entityType === 'node' ? 'nid' : 'id';
      $query = $this->entityTypeManager->getStorage($entityType)->getQuery()
        ->accessCheck(FALSE)
        ->sort($idField);
      if (!empty($options['bundle'])) {
        $query->condition('type', $options['bundle']);
      }
      if ($limit > 0) {
        $query->range(0, $limit);
      }
      $ids = $query->execute();

      foreach ($ids as $id) {
        $entity = $this->entityTypeManager->getStorage($entityType)->load($id);
        if (!$entity) {
          continue;
        }

        $result = $this->normalizerManager->normalizeEntity($entity, !$simulate);
        $processed++;
        if (!empty($result['changed'])) {
          $changed++;
          $rows[] = [
            'status' => $simulate ? 'would_update' : 'updated',
            'entity_type' => $entityType,
            'entity_id' => $id,
            'bundle' => $entity->bundle(),
            'details' => 'Redirect-based links normalized',
          ];
        }
        elseif (!empty($result['skipped'])) {
          $skipped++;
          $rows[] = [
            'status' => 'skipped',
            'entity_type' => $entityType,
            'entity_id' => $id,
            'bundle' => $entity->bundle(),
            'details' => 'Orphan paragraph skipped',
          ];
        }
        elseif ($showUnchanged) {
          $rows[] = [
            'status' => 'unchanged',
            'entity_type' => $entityType,
            'entity_id' => $id,
            'bundle' => $entity->bundle(),
            'details' => 'No redirect-based links found',
          ];
        }
      }
    }

    $mode = $simulate ? 'SIMULATION' : 'EXECUTION';
    $rows[] = [
      'status' => 'summary',
      'entity_type' => 'all',
      'entity_id' => (string) $processed,
      'bundle' => 'N/A',
      'details' => "{$mode} complete. changed={$changed}; skipped={$skipped}",
    ];

    return new RowsOfFields($rows);
  }

}
