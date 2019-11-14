<?php

namespace Drupal\mass_controller_override\Controller;

/**
* @file
* Contains MassControllerOverrideNodeController.php.
*/

use Drupal;
use Drupal\node\Controller\NodeController;

/**
 * Returns customizations needed for Node routes.
 *
 * @package Drupal\mass_controller_override\Controller
 */
class MassControllerOverrideNodeController extends NodeController {

  /**
   * {@inheritdoc}
   */
  public function addPage() {
    $build = [
      '#theme' => 'node_add_list',
      '#cache' => [
        'tags' => Drupal::entityTypeManager()
          ->getDefinition('node_type')
          ->getListCacheTags(),
      ],
    ];

    $content = [];

    // Only use node types the user has access to.
    $node_types = Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $type) {
      $access = Drupal::entityTypeManager()->getAccessControlHandler('node')->createAccess($type->id(), NULL, [], TRUE);
      if ($access->isAllowed()) {
        $content[$type->id()] = $type;
      }
      $this->renderer->addCacheableDependency($build, $access);
    }

    // Add Media Documents to node/add page.
    $document_type = Drupal::entityTypeManager()->getStorage('media_bundle')->load('document');
    $access = Drupal::entityTypeManager()->getAccessControlHandler('media')->createAccess($document_type->id(), NULL, [], TRUE);
    if ($access->isAllowed()) {
      $content[$document_type->id()] = $document_type;
    }
    $this->renderer->addCacheableDependency($build, $access);

    // Bypass the node/add listing if only one content type is available.
    if (count($content) == 1) {
      $type = array_shift($content);
      return $this->redirect('node.add', ['node_type' => $type->id()]);
    }

    ksort($content);
    $build['#content'] = $content;

    return $build;
  }

}
