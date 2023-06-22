<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Url;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "update_references_service_details"
 * )
 */
class UpdateReferencesServiceDetails extends SqlBase {

  /**
   * Use entity usage to get all nodes that reference a service_detail page.
   */
  public function query(): SelectInterface {
    $query = $this->select('entity_usage', 'eu')
      ->fields('eu', ['source_id', 'source_type'])
      ->condition('eu.source_type', 'node')
      ->condition('eu.target_type', 'node')
      ->groupBy('eu.source_id')
      ->groupBy('eu.source_type');
    $query->addField('mmsd', 'destid1');
    $query->innerJoin('migrate_map_service_details', 'mmsd', 'eu.target_id=mmsd.sourceid1');
    // @todo Limit to just the most revision in the entity_usage table
    // $query->innerJoin('node', 'nt', 'eu.target_id=nt.nid');
    $query->innerJoin('node', 'ns', 'eu.source_id=ns.nid');
    $query->condition('ns.type', 'service_details');
    $query->fields('ns', ['type']);

    $query->addExpression('COUNT(eu.field_name)', 'count');
    $query->addExpression('MAX(eu.source_vid)', 'source_vid_max');
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
      'source_type' => [
        'type' => 'string',
        'alias' => 'eu',
      ],
      'destid1' => [
        'type' => 'integer',
        'alias' => 'mmsd',
      ],
    ];
  }

  public function prepareRow(Row $row) {
    $changed = FALSE;
    $options = ['absolute' => TRUE];

    $storage = \Drupal::entityTypeManager()->getStorage($row->getSourceProperty('source_type'));
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if (!$entity = $storage->load($row->getSourceProperty('source_id'))) {
      // Not a valid source anymore. Maybe it got deleted.
      $this->migration->getIdMap()->saveMessage(['source_id' => $row->getSourceProperty('source_type'), 'sourceid2' => $row->getSourceProperty('source_type')], 'Cannot load, so skipping ');
      return FALSE;
    }
    // Get all the Fields that we need to change in this source entity.
    $query = $this->select('entity_usage', 'eu')
      ->fields('eu', ['method', 'field_name', 'source_type'])
      ->fields('mmsd', ['sourceid1'])
      ->condition('eu.target_type', 'node')
      ->condition('eu.source_id', $row->getSourceProperty('source_id'))
      ->condition('eu.source_type', 'node');
    // MW: This is eliminating entities that we want to update.
    // ->condition('eu.source_vid', $entity->getLoadedRevisionId());
    $query->addField('eu', 'target_id', 'reference_value_old');
    $query->addField('mmsd', 'destid1', 'reference_value_new');
    // $query->addField('n', 'type', 'content_type');
    $query->innerJoin('migrate_map_service_details', 'mmsd', "eu.target_id=mmsd.sourceid1 AND eu.target_type = 'node'");
    // $query->innerJoin('node', 'n', 'mmsd.sourceid1=n.nid');
    $refs = $query->execute()->fetchAll();

    // Now update those fields. Different field types have
    // different approach for updating.
    foreach ($refs as $ref) {
      $values = [];
      $field_name = $ref['field_name'];
      $list = $entity->get($field_name);
      $uri_new = 'entity:node/' . $ref['reference_value_new'];
      $uri_old = 'entity:node/' . $ref['reference_value_old'];
      foreach ($list as $delta => $item) {
        switch (get_class($item)) {
          case DynamicLinkItem::class:
            $values[$delta] = $item->getValue();
            // Only update the delta that was migrated
            // (when there are multiple values).
            // Each if() is a different type of DynamicLinkItem
            $item_uri = $item->get('uri')->getString();
            $item_uri_path = parse_url($item_uri, PHP_URL_PATH);
            if ($item_uri == $uri_old) {
              $values[$delta]['uri'] = $uri_new;
              $changed = TRUE;
            }
            elseif ($item_uri_path == Url::fromUri($uri_old)->toString()) {
              $values[$delta]['uri'] = Url::fromUri($uri_new, $options)->toString();
              $changed = TRUE;
            }
            break;
          case EntityReferenceItem::class:
            $values[$delta] = $item->getValue();
            if ($item->get('target_id')->getString() == $ref['reference_value_old']) {
              $values[$delta]['target_id'] = $ref['reference_value_new'];
              $changed = TRUE;
            }
            break;
          case TextLongItem::class:
          case TextWithSummaryItem::class:
            $values[$delta] = $item->getValue();
            // First check for the entity ID
            if (str_contains($item->getString(), $ref['reference_value_old'])) {
              $replaced = str_replace($ref['reference_value_old'], $ref['reference_value_new'], $item->getString());
              $values[$delta]['value'] = $replaced;
              $changed = TRUE;
            }
            // Next check for the link. We want relative links not
            // absolute so domain mismatch isn't an issue.
            if (str_contains($item->getString(), Url::fromUri($uri_old)->toString())) {
              $replaced = str_replace(Url::fromUri($uri_old)->toString(), Url::fromUri($uri_new)->toString(), $item->getString());
              $values[$delta]['value'] = $replaced;
              $changed = TRUE;
            }
            break;
          default:
            throw new MigrateSkipRowException('Unhandled item');
        }
      }
    }
    if ($changed) {
      // In case this is a service_details node,
      // we need to update the migrated version.
      $field_name = ($field_name == 'field_service_detail_links_5') ? 'field_info_details_related' : $field_name;
      $row->setDestinationProperty($field_name, $values);
    }
    else {
      // We don't need to process further since we already
      // saved the source (paragraph or node).
      // Get the unique identifier for the current item
      $unique_id = $row->getSourceProperty('source_id');
      $this->migration->getIdMap()->saveIdMapping($row, [], ['source_id' => $unique_id, 'source_type' => 'node']);
      return FALSE;
    }
  }

}
