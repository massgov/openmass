<?php

namespace Drupal\mass_content\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "update_references"
 * )
 */
class UpdateReferences extends SqlBase {

  /**
   * Get all nodes that reference a service_detail page.
   */
  public function query(): SelectInterface {
    $query = $this->select('entity_usage', 'eu')
      ->fields('eu', ['source_id', 'source_type'])
      ->condition('eu.source_type', 'node')
      ->condition('eu.target_type', 'node')
      ->groupBy('eu.source_id');
    $query->innerJoin('migrate_map_service_details', 'mmsd', 'eu.target_id=mmsd.sourceid1');
    // Limit to just the most revision in the entity_usage table
    $query->innerJoin('node', 'nt', 'eu.target_id=nt.nid');
    // Don't care about service detail nodes, as those are not in use.
    $query->innerJoin('node', 'ns', 'eu.source_id=ns.nid');
    $query->condition('ns.type', 'service_details', '!=');
    $query->fields('ns', ['type']);

    $query->addExpression('COUNT(eu.field_name)', 'count');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'source_id' => [
        'type' => 'integer',
        'alias' => 'eu',
      ],
    ];
  }

  public function prepareRow(Row $row) {
    $changed = FALSE;
    $storage = \Drupal::entityTypeManager()->getStorage($row->getSourceProperty('source_type'));
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if (!$entity = $storage->load($row->getSourceProperty('source_id'))) {
      // Not a valid source anymore. Maybe it got deleted.
      $this->migration->getIdMap()->saveMessage(['source_id' => $row->getSourceProperty('source_type'), 'sourceid2' => $row->getSourceProperty('source_type')], 'Cannot load, so skipping ');
      return FALSE;
    }
    // Get all the fields that we need to change in this sourceid.
    $query = $this->select('entity_usage', 'eu')
      ->fields('eu', ['method', 'field_name'])
      ->fields('mmsd', ['sourceid1'])
      ->condition('eu.target_type', 'node')
      ->condition('eu.source_id', $row->getSourceProperty('source_id'))
      ->condition('eu.source_vid', $entity->getLoadedRevisionId());
    $query->addField('eu', 'target_id', 'reference_value_old');
    $query->addField('mmsd', 'destid1', 'reference_value_new');
    $query->addField('n', 'type', 'content_type');
    $query->innerJoin('migrate_map_service_details', 'mmsd', 'eu.target_id=mmsd.sourceid1');
    $query->innerJoin('node', 'n', 'mmsd.sourceid1=n.nid');
    $refs = $query->execute()->fetchAll();
    foreach ($refs as $ref) {
      $field_name = $ref['field_name'];
      $list = $entity->get($field_name);
      foreach ($list as $delta => $item) {
        switch (get_class($item)) {
          case DynamicLinkItem::class:
            // Only update the delta that was migrated (when there are multiple values).
            if ($item->get('uri')->getString() == 'entity:node/' . $ref['reference_value_old']) {
              $prop_name = "$field_name/$delta/uri";
              $row->setDestinationProperty($prop_name, 'entity:node/' . $ref['reference_value_new']);
              $changed = TRUE;
            }
            break;
          case EntityReferenceItem::class:
            if ($item->get('target_id')->getString() == $ref['reference_value_old']) {
              $prop_name = "$field_name/$delta/target_id";
              $row->setDestinationProperty($prop_name, 'entity:node/' . $ref['reference_value_new']);
              // $entity->get($field_name)[$delta]->set('target_id', $ref['reference_value_new']);
              $changed = TRUE;
            }
            break;
          case TextLongItem::class:
          case TextWithSummaryItem::class:
            if (str_contains($item->getString(), $ref['reference_value_old'])) {
              $replaced = str_replace($ref['reference_value_old'], $ref['reference_value_new'], $item->getString());
              $prop_name = "$field_name/$delta/value";
              $row->setDestinationProperty($prop_name, $replaced);
              // $entity->get($field_name)[$delta]->set('value', $replaced);
              $changed = TRUE;
            }
            break;
          default:
            throw new MigrateSkipRowException('Unhandled item');
        }
      }
    }
    if (!$changed) {
      // $entity->save();
      // We don't need to process further since we already saved the source (paragraph or node).
      return FALSE;
    }
  }

}
