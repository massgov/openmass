<?php

namespace Drupal\mass_views\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * The style plugin for serialized output formats with pager.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "serializer_with_pager",
 *   title = @Translation("Serializer with pager"),
 *   help = @Translation("Serializes views row data using the Serializer component with pager."),
 *   display_types = {"data"}
 * )
 */
class SerializerWithPager extends Serializer {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }

    $results = [
      'results' => $rows,
    ];

    // Add pager information to the rows.
    $pager = $this->view->pager;
    $results['pager'] = [
      'current_page' => $pager->getCurrentPage(),
      'items_per_page' => $pager->getItemsPerPage(),
      'total_items' => $pager->getTotalItems(),
    ];

    return $this->serializer->serialize($results, $content_type, ['views_style_plugin' => $this]);
  }

}
