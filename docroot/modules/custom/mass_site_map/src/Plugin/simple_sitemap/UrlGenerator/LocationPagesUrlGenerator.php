<?php

namespace Drupal\mass_site_map\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Url;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;

/**
 * Class LocationPagesUrlGenerator.
 *
 * @package Drupal\mass_site_map\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "mass_map_locations",
 *   title = @Translation("Location Listing Pages"),
 *   description = @Translation("Generates URLs for locations pages."),
 *   settings = {
 *     "instantiate_for_each_data_set" = true,
 *   },
 * )
 */
class LocationPagesUrlGenerator extends UrlGeneratorBase {

  /**
   * {@inheritdoc}
   */
  public function getDataSets() {
    $data_sets = [];
    $data_sets[] = [
      'bundle' => 'org_page',
      'fields' => ['field_org_ref_locations'],
    ];
    $data_sets[] = [
      'bundle' => 'location',
      'fields' => ['field_related_locations'],
    ];
    $data_sets[] = [
      'bundle' => 'service_page',
      'fields' => ['field_service_ref_locations'],
    ];
    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  public function processDataSet($entity) {
    $url_object = Url::fromRoute('mass_map.map_page', ['node' => $entity->id()]);
    $url_object->setOption('absolute', TRUE);

    return [
      'url' => $url_object,
      'lastmod' => method_exists($entity, 'getChangedTime') ? date('c', $entity->getChangedTime()) : NULL,
      'priority' => 0.5,
      'changefreq' => 'daily',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBatchIterationElements($info) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $condition = $query->orConditionGroup();
    foreach ($info['fields'] as $field) {
      $condition->exists($field);
    }
    $query->condition('type', $info['bundle']);
    $query->condition($condition);

    if ($this->needsInitialization()) {
      $count_query = clone $query;
      $this->initializeBatch($count_query->count()->execute());
    }

    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchSettings['batch_process_limit']);
    }

    return $this->entityTypeManager->getStorage('node')
      ->loadMultiple($query->execute());
  }

}
