<?php

namespace Drupal\mass_entity_usage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mass_entity_usage\Controller\LocalTaskUsageController;

/**
 * Extends the entity usage base class.
 */
class MassEntityUsage implements MassEntityUsageInterface {

  public function __construct(
    protected Connection $connection,
    protected ConfigFactoryInterface $config,
    protected string $tableName = 'entity_usage',
  ) {}

  /**
   * {@inheritdoc}
   */
  public function listSourcesPage(EntityInterface $target_entity, $offset, $nest_results = TRUE) {
    $target_id_column = $this->isInt($target_entity->id()) ? 'target_id' : 'target_id_string';

    // SubQuery for offset results.
    $sub_query = $this->connection->select($this->tableName, 'p');
    $sub_query->fields('p', [
      'source_id',
      'source_type',
    ]);
    $sub_query->addExpression("concat(source_type, '-', source_id)", 'type_id_key');
    $sub_query->condition($target_id_column, $target_entity->id());
    $sub_query->condition('target_type', $target_entity->getEntityTypeId());
    $sub_query->condition('count', 0, '>');
    $sub_query->orderBy('source_type');
    $sub_query->orderBy('source_id', 'DESC');
    $sub_query->distinct();

    // Entities can have string IDs. We support that by using different columns
    // on each case.
    $query = $this->connection->select($this->tableName, 'e');
    $query->fields('e', [
      'source_id',
      'source_id_string',
      'source_type',
      'source_langcode',
      'source_vid',
      'method',
      'field_name',
      'count',
    ]);

    // Set a range and restrict usage records to unique sources.
    $items_per_page = (int) ($this->config
      ->get('entity_usage.settings')
      ->get('usage_controller_items_per_page') ?? LocalTaskUsageController::ITEMS_PER_PAGE_DEFAULT);
    $sub_query->range($offset, $items_per_page);
    $sub_query_results = $sub_query->execute()->fetchAllAssoc('type_id_key');
    $sub_query_keys = array_keys($sub_query_results);
    $query->addExpression("concat(source_type, '-', source_id)", 'type_id_key');
    $query->where("concat(source_type, '-', source_id) in (:keys[])", [':keys[]' => $sub_query_keys]);

    $query->condition($target_id_column, $target_entity->id());
    $query->condition('target_type', $target_entity->getEntityTypeId());
    $query->condition('count', 0, '>');
    $query->orderBy('source_type');
    $query->orderBy('source_id', 'DESC');
    $query->orderBy('source_vid', 'DESC');
    $query->orderBy('source_langcode');

    $result = $query->execute();
    return $this->prepareListSources($result, $nest_results);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareListSources($result, $nest_results) {
    $references = [];
    foreach ($result as $usage) {
      $source_id_value = !empty($usage->source_id) ? (string) $usage->source_id : (string) $usage->source_id_string;
      if ($nest_results) {
        $references[$usage->source_type][$source_id_value][] = [
          'source_langcode' => $usage->source_langcode,
          'source_vid' => $usage->source_vid,
          'method' => $usage->method,
          'field_name' => $usage->field_name,
          'count' => $usage->count,
        ];
      }
      else {
        $references[] = [
          'source_type' => $usage->source_type,
          'source_id' => $source_id_value,
          'source_langcode' => $usage->source_langcode,
          'source_vid' => $usage->source_vid,
          'method' => $usage->method,
          'field_name' => $usage->field_name,
          'count' => $usage->count,
        ];
      }
    }

    return $references;
  }

  /**
   * {@inheritdoc}
   */
  public function listUniqueSourcesCount(EntityInterface $target_entity) {
    $target_id_column = $this->isInt($target_entity->id()) ? 'target_id' : 'target_id_string';
    $query = $this->connection->select($this->tableName, 'p');
    $query->fields('p', [
      'source_id',
      'source_type',
    ]);
    $query->addExpression("concat(source_type, '-', source_id)", 'type_id_key');
    $query->condition($target_id_column, $target_entity->id());
    $query->condition('target_type', $target_entity->getEntityTypeId());
    $query->condition('count', 0, '>');
    $query->orderBy('source_type');
    $query->orderBy('source_id', 'DESC');
    $query->distinct();

    // Return the unique number of sources.
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Check if a value is an integer, or an integer string.
   *
   * Core doesn't support big integers (bigint) for entity reference fields.
   * Therefore we consider integers with more than 10 digits (big integer) to be
   * strings.
   *
   * @param int|string $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if the value is a numeric integer or a string containing an integer,
   *   FALSE otherwise.
   *
   * @todo Fix bigint support once fixed in core. More info on #2680571 and
   *   #2989033.
   */
  protected function isInt($value) {
    return ((string) (int) $value === (string) $value) && strlen($value) < 11;
  }

}
