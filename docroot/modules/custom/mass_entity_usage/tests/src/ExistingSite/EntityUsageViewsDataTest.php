<?php

namespace Drupal\Tests\mass_entity_usage\ExistingSite;

use Drupal\views\Views;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies Views integration for entity_usage.
 */
class EntityUsageViewsDataTest extends MassExistingSiteBase {

  /**
   * Ensures entity_usage is available as a Views base table.
   */
  public function testEntityUsageViewsData() : void {
    $views_data = Views::viewsData();
    $base_tables = $views_data->fetchBaseTables();
    $data = $views_data->get('entity_usage');
    $wizards = \Drupal::service('plugin.manager.views.wizard')->getDefinitions();

    $this->assertArrayHasKey('entity_usage', $base_tables);
    $this->assertSame('target_id', $data['table']['base']['field']);
    $this->assertSame('mass_entity_usage_bundle', $views_data->get('node_field_data')['type']['filter']['id']);

    $this->assertArrayHasKey('source_node', $data);
    $this->assertSame('node_field_data', $data['source_node']['relationship']['base']);
    $this->assertSame('source_id', $data['source_node']['relationship']['relationship field']);

    $this->assertArrayHasKey('target_media', $data);
    $this->assertSame('media_field_data', $data['target_media']['relationship']['base']);
    $this->assertSame('target_id', $data['target_media']['relationship']['relationship field']);

    $this->assertArrayHasKey('standard:entity_usage', $wizards);
    $this->assertSame('entity_usage', $wizards['standard:entity_usage']['base_table']);
  }

  /**
   * Ensures a newly added content-type filter does not brick the view editor.
   */
  public function testBundleFilterCanExistBeforeRelationshipSelection() : void {
    $view = \Drupal\views\Entity\View::load('pages_linking_to_trashed_pages');
    $displays = $view->get('display');
    $displays['default']['display_options']['filters']['type'] = [
      'id' => 'type',
      'table' => 'node_field_data',
      'field' => 'type',
      'relationship' => 'none',
      'plugin_id' => 'bundle',
      'value' => [
        'org_page' => 'org_page',
      ],
    ];
    $view->set('display', $displays);

    $executable = Views::executableFactory()->get($view);
    $this->assertSame([], $executable->validate());
  }

}
