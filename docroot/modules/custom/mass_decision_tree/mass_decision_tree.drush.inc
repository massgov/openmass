<?php

/**
 * @file
 * Drush commands for mass_decision_tree.
 */

use Drupal\node\Entity\Node;

/**
 * Implements hook_drush_command().
 */
function mass_decision_tree_drush_command() {
  $items['mass-decision-tree-parent-populate'] = [
    'aliases' => ['mdtpp'],
    'description' => 'Populates field_decision_root_ref for Decision Tree Branch/Conclusions',
  ];

  return $items;
}

/**
 * Populate the decision_root_ref field for nodes in Decision Trees.
 */
function drush_mass_decision_tree_parent_populate() {

  // Get all Decision Tree nodes.
  $query = Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'decision_tree');
  $tree_ids = $query->execute();

  /** @var \Drupal\mass_content_api\DescendantManagerInterface $descendant_manager */
  $descendant_manager = \Drupal::getContainer()->get('descendant_manager');
  $allowed_bundles = ['decision_tree_branch', 'decision_tree_conclusion'];
  foreach ($tree_ids as $tree_id) {

    // Find all descendants which are reachable from the root node.
    $descendant_ids = $descendant_manager->getChildrenFlat($tree_id);
    foreach ($descendant_ids as $descendant_id) {
      $descendant_node = Node::load($descendant_id);

      // Only update proper nodes of the correct types.
      if (empty($descendant_node) || !($descendant_node instanceof Node)
        || array_search($descendant_node->bundle(), $allowed_bundles) === FALSE) {
        continue;
      }

      // Update node's decision root ref to point to the tree root.
      $descendant_node->set('field_decision_root_ref', $tree_id);
      $descendant_node->save();
    }
  }
}
