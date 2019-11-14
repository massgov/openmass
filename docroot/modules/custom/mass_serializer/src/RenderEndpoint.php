<?php

namespace Drupal\mass_serializer;

use Drupal\views\Views;

/**
 * Class RenderEndpoint.
 *
 * @package Drupal\mass_serializer
 */
class RenderEndpoint {

  /**
   * The batch size of cache operations.
   *
   * @var int
   */
  protected $itemsPerPage = 1000;

  /**
   * Renders a view for output or saving to disk.
   *
   * @param string $api
   *   Name of the endpoint you are saving.
   * @param string $display
   *   Display ID of the view to use.
   * @param array $args
   *   Arguments to supply to the view.
   *
   * @return string
   *   String data of the view to return to user.
   */
  public function render($api, $display, array $args) {
    $view = Views::getView($api);
    if (!$view) {
      throw new \Exception('No view returned by this api machine name: ' . $api);
    }

    $preview = $view->preview($display, $args);
    return strval($preview['#markup']);
  }

}
