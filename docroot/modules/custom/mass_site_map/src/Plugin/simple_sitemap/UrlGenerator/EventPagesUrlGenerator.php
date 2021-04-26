<?php

namespace Drupal\mass_site_map\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Url;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;

/**
 * Class EventPagesUrlGenerator.
 *
 * @package Drupal\mass_site_map\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "mass_map_events",
 *   title = @Translation("Event Listing Pages"),
 *   description = @Translation("Generates URLs for event listing pages."),
 * )
 */
class EventPagesUrlGenerator extends UrlGeneratorBase {

  /**
   * {@inheritdoc}
   */
  public function getDataSets() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function processDataSet($entity) {
    $url_object = Url::fromRoute('view.event_listing.page_1', ['arg_0' => $entity->id()]);
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
    $alias = 'parents';
    // Get every node that's listed as a parent of an event.
    $query = $this->entityTypeManager->getStorage('node')->getAggregateQuery();
    $query->condition('field_event_ref_parents.entity.status', 1);
    $query->aggregate('field_event_ref_parents', 'DISTINCT', NULL, $alias);
    $query->exists('field_event_ref_parents');
    $query->condition('type', 'event');

    if ($this->needsInitialization()) {
      $count_query = $this->entityTypeManager->getStorage('node')->getAggregateQuery();
      $count_query->condition('field_event_ref_parents.entity.status', 1);
      $count_query->exists('field_event_ref_parents');
      $count_query->groupBy('field_event_ref_parents');
      $this->initializeBatch($count_query->count()->execute());
    }

    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchSettings['batch_process_limit']);
    }

    $parents = array_map(function ($row) use ($alias) {
      return $row[$alias];
    }, $query->execute());

    return $this->entityTypeManager->getStorage('node')
      ->loadMultiple($parents);
  }

}
