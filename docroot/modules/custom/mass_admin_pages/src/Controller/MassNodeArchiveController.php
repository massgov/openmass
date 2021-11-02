<?php

namespace Drupal\mass_admin_pages\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Class MassNodeArchiveController.
 *
 * @package Drupal\mass_admin_pages\Controller
 */
class MassNodeArchiveController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function build(NodeInterface $node) {

    $link = $node->toUrl()->toString();
    return [
    '#theme' => 'mass__nodes_archive',
      '#link' => $link,
    ];
  }

}
