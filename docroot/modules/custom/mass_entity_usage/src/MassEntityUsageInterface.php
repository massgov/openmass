<?php

namespace Drupal\mass_entity_usage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_usage\EntityUsageInterface;

/**
 * Entity usage interface.
 */
interface MassEntityUsageInterface extends EntityUsageInterface {

  /**
   * Provide a list of all referencing source entities for a target entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $target_entity
   *   A target entity.
   * @param int $offset
   *   (optional) Controls the range of records returned.
   *   Defaults to NULL.
   * @param bool $nest_results
   *   (optional) Whether the results should be returned in a nested structure.
   *   Defaults to TRUE.
   *
   * @return array
   *   A nested array with usage data. The first level is keyed by the type of
   *   the source entities, the second by the source id. The value of the second
   *   level contains all other information like the method used by the source
   *   to reference the target, the field name and the source language code. If
   *   $nest_results is FALSE, the returned array will be an indexed array where
   *   values are arrays containing all DB columns for the records. Offset will
   *   be the start integer for the query range.
   */
  public function listSourcesPage(EntityInterface $target_entity, $offset, $nest_results = TRUE);

  /**
   * Prepares the list of all referencing source entities.
   *
   * Examples:
   *  - Return example 1:
   *  [
   *    'node' => [
   *      123 => [
   *        'source_langcode' => 'en',
   *        'source_vid' => '128',
   *        'method' => 'entity_reference',
   *        'field_name' => 'field_related_items',
   *        'count' => 1,
   *      ],
   *      124 => [
   *        'source_langcode' => 'en',
   *        'source_vid' => '129',
   *        'method' => 'entity_reference',
   *        'field_name' => 'Related items',
   *        'count' => 1,
   *      ],
   *    ],
   *    'user' => [
   *      2 => [
   *        'source_langcode' => 'en',
   *        'source_vid' => '2',
   *        'method' => 'entity_reference',
   *        'field_name' => 'field_author',
   *        'count' => 1,
   *      ],
   *    ],
   *  ]
   *  - Return example 2:
   *  [
   *    'entity_reference' => [
   *      'node' => [...],
   *      'user' => [...],
   *    ]
   *  ]
   *
   * @param \Drupal\Core\Database\StatementWrapper $results
   *   Query results to be prepared into an array.
   * @param bool $nest_results
   *   Whether the results should be returned in a nested structure.
   *
   * @return array
   *   A nested array with usage data. The first level is keyed by the type of
   *   the source entities, the second by the source id. The value of the second
   *   level contains all other information like the method used by the source
   *   to reference the target, the field name and the source language code. If
   *   $nest_results is FALSE, the returned array will be an indexed array where
   *   values are arrays containing all DB columns for the records.
   */
  public function prepareListSources($results, $nest_results);

  /**
   * Provide a count of unique referencing source entities for a target entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $target_entity
   *   A target entity.
   *
   * @return int
   *   The return value will be the total number of unique sources.
   */
  public function listUniqueSourcesCount(EntityInterface $target_entity);

}
