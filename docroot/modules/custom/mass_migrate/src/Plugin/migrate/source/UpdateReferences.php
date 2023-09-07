<?php

namespace Drupal\mass_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Url;
use Drupal\mass_content\Field\FieldType\DynamicLinkItem;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;

class UpdateReferences extends SqlBase {

  const SOURCE_TYPE = '';
  const SOURCE_BUNDLE = '';

  private function baseQuery(): SelectInterface {
    $query = $this->select('entity_usage', 'eu');
    $query->fields('eu', ['source_id', 'source_type']);
    $query->innerJoin('migrate_map_service_details', 'mmsd', 'eu.target_id=mmsd.sourceid1');
    $query->condition('eu.source_type', static::SOURCE_TYPE);
    $query->condition('eu.target_type', 'node');

    if (static::SOURCE_TYPE == 'node') {
      $query->innerJoin('node', 'ns', 'eu.source_id=ns.nid');
      $query->condition('ns.type', 'service_details', '!=');
    }

    $query->groupBy('eu.source_id');
    $query->groupBy('eu.source_type');

    return $query;
  }

  /**
   * Use entity usage to get all nodes that reference a service_detail page.
   */
  public function query(): SelectInterface {
    $query = $this->baseQuery();
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
    $ref_query = $this->baseQuery();
    $ref_query->addField('eu', 'field_name');
    $ref_query->addField('eu', 'target_id', 'reference_value_old');
    $ref_query->addField('mmsd', 'destid1', 'reference_value_new');
    $ref_query->condition('eu.source_id', $row->getSourceProperty('source_id'));
    $ref_query->groupBy('eu.field_name');
    $ref_query->groupBy('reference_value_old');
    $ref_query->groupBy('reference_value_new');

    $refs = $ref_query->execute()->fetchAll();

    // Now update those fields. Different field types have
    // different approach for updating.
    $values = [];
    foreach ($refs as $ref) {
      $field_name = $ref['field_name'];
      if (!isset($values[$field_name])) {
        $values[$field_name] = [];
      }
      $field_items = $entity->get($field_name);
      $uri_old = 'entity:node/' . $ref['reference_value_old'];
      $uri_new = 'entity:node/' . $ref['reference_value_new'];
      if (isset($ref['reference_value_old']) && isset($ref['reference_value_new'])) {
        foreach ($field_items as $delta => $item) {
          if (!isset($values[$field_name][$delta])) {
            $values[$field_name][$delta] = $item->getValue();
          }
          switch (get_class($item)) {
            case DynamicLinkItem::class:
              // Only update the delta that was migrated
              // (when there are multiple values).
              // Each if() is a different type of DynamicLinkItem
              $item_uri = $item->get('uri')->getString();
              $item_uri_path = parse_url($item_uri, PHP_URL_PATH);
              if ($item_uri == $uri_old) {
                $values[$field_name][$delta]['uri'] = $uri_new;
                $changed = TRUE;
              }
              elseif ($item_uri_path == Url::fromUri($uri_old)->toString()) {
                $values[$field_name][$delta]['uri'] = Url::fromUri($uri_new, $options)
                  ->toString();
                $changed = TRUE;
              }
              break;
            case EntityReferenceItem::class:
              if ($item->get('target_id')->getString() == $ref['reference_value_old']) {
                $values[$field_name][$delta]['target_id'] = $ref['reference_value_new'];
                $changed = TRUE;
              }
              break;
            case TextLongItem::class:
            case TextWithSummaryItem::class:
              $value = $values[$field_name][$delta]['value'];
              // First check for the entity ID
              if (str_contains($value, $ref['reference_value_old'])) {
                $replaced = str_replace($ref['reference_value_old'], $ref['reference_value_new'], $value);
                $value = $replaced;
                $values[$field_name][$delta]['value'] = $replaced;
                $changed = TRUE;
              }

              // Check for the linkit values.
              if (str_contains($value, 'data-entity-uuid')) {
                $node_storage = \Drupal::entityTypeManager()
                  ->getStorage('node');
                $entity_old = $node_storage->load($ref['reference_value_old']);
                if ($entity_old) {
                  if (str_contains($value, $entity_old->uuid())) {
                    $dom = Html::load($value);
                    $xpath = new \DOMXPath($dom);
                    foreach ($xpath->query('//a[@data-entity-type and @data-entity-uuid]') as $element) {
                      if ($element->getAttribute('data-entity-uuid') == $entity_old->uuid()) {
                        // Parse link href as url,
                        // extract query and fragment from it.
                        $href_url = parse_url($element->getAttribute('href'));
                        $anchor = empty($href_url["fragment"]) ? '' : '#' . $href_url["fragment"];
                        $query = empty($href_url["query"]) ? '' : '?' . $href_url["query"];
                        $entity_new = $node_storage->load($ref['reference_value_new']);
                        if ($entity_new) {
                          $substitution = \Drupal::service('plugin.manager.linkit.substitution');
                          $url = $substitution
                            ->createInstance('canonical')
                            ->getUrl($entity_new);
                          $element->setAttribute('data-entity-uuid', $entity_new->uuid());
                          $element->setAttribute('href', $url->getGeneratedUrl() . $query . $anchor);
                          $changed = TRUE;
                        }
                      }
                    }
                    if ($changed) {
                      $replaced = Html::serialize($dom);
                      $value = $replaced;
                      $values[$field_name][$delta]['value'] = $replaced;
                    }
                  }
                }
              }

              // Next check for the link. We want relative links not
              // absolute so domain mismatch isn't an issue.
              if (str_contains($value, Url::fromUri($uri_old)->toString())) {
                $replaced = str_replace(Url::fromUri($uri_old)
                  ->toString(), Url::fromUri($uri_new)->toString(), $value);
                $values[$field_name][$delta]['value'] = $replaced;
                $changed = TRUE;
              }
              break;
            default:
              throw new MigrateSkipRowException('Unhandled item');
          }
        }
      }
    }
    if ($changed) {
      foreach ($values as $field_name => $field_values) {
        $row->setDestinationProperty($field_name, $field_values);
      }
    }
    else {
      // We don't need to process further since we already
      // saved the source (paragraph or node).
      // Get the unique identifier for the current item
      $unique_id = $row->getSourceProperty('source_id');
      $this->migration->getIdMap()->saveIdMapping($row, [], ['source_id' => $unique_id, 'source_type' => static::SOURCE_TYPE]);
      return FALSE;
    }
  }

}
