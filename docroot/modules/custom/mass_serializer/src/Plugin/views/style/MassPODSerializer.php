<?php

namespace Drupal\mass_serializer\Plugin\views\style;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\rest\Plugin\views\style\Serializer;

/**
 * Plugin for serialized output formats using Project Open Data v1.1 format.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "mass_pod_serializer",
 *   title = @Translation("Mass POD v1.1 Data.json Serializer"),
 *   help = @Translation("Serializes views row data using the Serializer component."),
 *   display_types = {"data"}
 * )
 */
class MassPODSerializer extends Serializer implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->view;
    $rows = [];
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($view->result as $row_index => $row) {
      $view->row_index = $row_index;
      $rows[] = $view->rowPlugin->render($row);
    }
    $view->row_index = NULL;

    // Get the format configured in the display or fallback to the default.
    $format = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    if (empty($view->live_preview)) {
      $format = $this->displayHandler->getContentType();
    }

    global $base_root;
    /** @var \Drupal\views\ViewExecutable $view_executable */
    $view_executable = $this->view->storage->getExecutable();

    switch ($view->id()) {
      case 'documents_by_filter':
        $path = str_replace('%taxonomy_term', $view_executable->args[0], $view->displayHandlers->getConfiguration()['rest_export_documents_by_contributor']['display_options']['path']);
        break;

      case 'data_json_summary':
        $path = $view->displayHandlers->getConfiguration()['rest_export_1']['display_options']['path'];
        break;

      default:
        $path = '';
    }

    $data = [
      '@context' => 'https://project-open-data.cio.gov/v1.1/schema/catalog.jsonld',
      '@id' => $base_root . '/' . $path,
      '@type' => 'dcat:Catalog',
      'conformsTo' => 'https://project-open-data.cio.gov/v1.1/schema',
      'describedBy' => 'https://project-open-data.cio.gov/v1.1/schema/catalog.json',
    ];

    // Add a count to the results.
    if (isset($view->pager)) {
      $data['count'] = $view->query->query()->countQuery()->execute()->fetchField();
    }

    $data['dataset'] = $rows;

    return $this->serializer->serialize($data, $format, $this->getContext());
  }

  /**
   * Return the context with all fields needed in the normalizer.
   *
   * @return array
   *   The context values.
   */
  private function getContext() {
    return [
      'views_style_plugin' => $this,
      'included_fields' => [
        'field_title',
        'field_description',
        'field_contributing_organization',
        'field_contact_name',
        'field_contact_information',
        'field_publishing_frequency',
        'field_license',
        // @deprecated DP-42778: field_start_date is deprecated and hidden from the document form.
        'field_start_date',
        'field_end_date',
        'field_geographic_place',
        'field_language',
        'field_subjects',
        'field_tags',
        'field_rights',
        'field_data_dictionary',
        'field_conform',
        'field_system_of_records',
        'field_data_quality',
        'changed',
        'created',
        'uuid',
        'field_alternative_title',
        'field_creator',
        'field_content_type',
        'field_additional_info',
        'field_link_related_content',
        'field_internal_notes',
        'field_part_of',
        'field_oclc_number',
        'field_upload_file',
        'field_link_classic_massgov',
        'field_file_migration_id',
        'field_checksum',
        'field_other_license_url',
      ],
    ];
  }

}
