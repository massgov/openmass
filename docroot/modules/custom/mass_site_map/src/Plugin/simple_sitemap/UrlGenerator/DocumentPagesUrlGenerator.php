<?php

namespace Drupal\mass_site_map\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Url;
use Drupal\Core\Entity\Entity;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;

/**
 * Class DocumentPagesUrlGenerator.
 *
 * @package Drupal\mass_site_map\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "mass_documents",
 *   title = @Translation("Document Pages"),
 *   description = @Translation("Generates URLs for Document media entities."),
 * )
 */
class DocumentPagesUrlGenerator extends UrlGeneratorBase {

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
    /** @var \Drupal\media\Entity\Media $entity */
    $data = NULL;

    /** @var \Drupal\mass_metatag\Service\MassMetatagUtilities $utility_service */
    $utility_service = \Drupal::service('mass_metatag.utilities');

    $files = $entity->get('field_upload_file')->referencedEntities();
    foreach ($files as $file) {
      // NOTE: We must pass URL object of the file url in our dataset, so that simple_sitemap module can apply baseurl
      // config settings to it.
      $file_url = $file->url();
      $file_url_object = Url::fromUri($file_url);
      if ($file_url) {
        $data = [
          'url' => $file_url_object,
          'lastmod' => date_iso8601($entity->getChangedTime()),
          'priority' => 0.5,
          'changefreq' => 'daily',
        ];
        if ($start = $entity->get('field_start_date')->date) {
          $data['pagemap']['metatags'][] = [
            'name' => 'mg_date',
            'value' => $start->format('Ymd'),
          ];
        }
        // Add document category term name if present.
        if ($category = $entity->get('field_category')) {
          if ($term = $category->entity) {
            $data['pagemap']['metatags'][] = [
              'name' => 'category',
              'value' => $term->label(),
            ];
          }
        }
        if (isset($entity->field_organizations)) {
          /** @var \Drupal\node\Entity\Node[] $org_nodes */
          $org_nodes = $entity->field_organizations->referencedEntities();
          $org_slugs = [];
          foreach ($org_nodes as $org) {
            $org_slugs += str_replace("-", "", $utility_service->getAllOrgsFromNode($org));
          }
          if (!empty($org_slugs)) {
            $data['pagemap']['metatags'][] = [
              'name' => 'mg_organization',
              'value' => implode(',', $org_slugs),
            ];
          }
        }
        // Associate a file with its corresponding Media Entity title.
        if ($field_title = $entity->get('field_title')) {
          if ($title = $field_title->getValue()) {
            $data['pagemap']['metatags'][] = [
              'name' => 'mg_title',
              'value' => $title[0]['value'],
            ];
          }
        }
      }
    }

    if (isset($entity->field_document_type)) {
      /** @var \Drupal\taxonomy\Entity\Term $type_term */
      $type_terms = $entity->field_document_type->referencedEntities();
      $term_slugs = [];
      foreach ($type_terms as $term) {
        $term_slugs[] = $utility_service->slugify($term->getName());
      }
      if (!empty($term_slugs)) {
        $data['pagemap']['metatags'][] = [
          'name' => 'mg_type',
          'value' => implode(',', $term_slugs),
        ];
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBatchIterationElements($info) {
    $query = \Drupal::database()->select('media_field_data', 'm');
    $query->fields('m', ['mid']);
    $query->join('descendant_relations', 'r', "m.mid = r.destination_id AND r.destination_type = 'media'");
    $query->join('node_field_data', 'n', "r.source_id = n.nid AND r.source_type = 'node'");
    $query->condition('r.relationship', 'links_to');
    $query->condition('m.status', 1);
    $query->condition('n.status', 1);
    $query->condition('m.bundle', 'document');
    $query->groupBy('m.mid');
    $query->orderBy('m.mid');

    if ($this->needsInitialization()) {
      $count_query = clone $query;
      $this->initializeBatch($count_query->countQuery()->execute()->fetchField());
    }

    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchSettings['batch_process_limit']);
    }

    $refined_ids = $query->execute()->fetchCol();

    return $this->entityTypeManager->getStorage('media')
      ->loadMultiple($refined_ids);
  }

}
