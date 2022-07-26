<?php

namespace Drupal\mass_metatag\Service;

use Drupal\node\Entity\Node;

/**
 * Class MassMetatagUtilities.
 *
 * @package Drupal\mass_metatag\Service
 */
class MassMetatagUtilities {

  /**
   * Slugifies a string by making it lowercase and separating words with hyphens.
   *
   * @param string $string
   *   The string to slugify.
   *
   * @return string
   *   A slugified version of the string.
   */
  public function slugify($string) {
    // Replace one or more consecutive whitespace characters with a hyphen.
    $without_whitespace = preg_replace('/[\s_]+/', '-', $string);

    // Lowercase and remove characters which aren't alphanumeric or hyphens.
    return preg_replace('/[^a-z\d-]/', '', strtolower($without_whitespace));
  }

  /**
   * Gets all Organization names for the passed node, including all parent Orgs.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to get Orgs and parent Orgs from.
   *
   * @return string[]
   *   The array of slugified Org names related to this node.
   */
  public function getAllOrgsFromNode(Node $node) {
    $result = [];

    // The array that will hold all the orgs to check for parents.
    $orgs = [];
    // Add the current node for checking.
    $orgs[] = $node;
    $checked_orgs = [];
    // While there are organizations in the array, check for parent orgs.
    while (!empty($orgs)) {
      // Pop an org node from the array.
      $node = array_shift($orgs);
      // If the current node is an org page, add the title to the values
      // and if there is a parent org, add it to the array for checking.
      if ($node->bundle() === 'org_page') {
        // If it is an unchecked org, add the slugified title to values.
        if (!in_array($node->id(), $checked_orgs)) {
          $result[] = $this->slugify(trim($node->label()));
        }
        // If there is a parent org, add it to the array to check.
        if (!$node->field_parent->isEmpty() && !is_null($node->field_parent->entity) && !in_array($node->field_parent->entity->id(), $checked_orgs)) {
          $orgs[] = $node->field_parent->entity;
        }
        if ($node->hasField('field_organizations') && !$node->field_organizations->isEmpty()) {
          /** @var \Drupal\node\Entity\Node[] $org_pages */
          $org_pages = $node->field_organizations->referencedEntities();
          foreach ($org_pages as $org_page) {
            if (!in_array($org_page->id(), $checked_orgs)) {
              // Only add the referenced orgs if they have not already been
              // checked.
              if (!in_array($org_page->id(), $checked_orgs)) {
                $orgs[] = $org_page;
              }
            }
          }
        }
      }
      // For all other nodes, get all the organizations referenced
      // and add it to the orgs array so they can be checked for parents.
      elseif ($node->hasField('field_organizations')) {
        /** @var \Drupal\node\Entity\Node[] $org_pages */
        $org_pages = $node->field_organizations->referencedEntities();
        foreach ($org_pages as $org_page) {
          // Only add the referenced orgs if they have not already been
          // checked.
          if (!in_array($org_page->id(), $checked_orgs)) {
            $orgs[] = $org_page;
          }
        }
      }
      // Add the current org node to the checked array to keep track.
      $checked_orgs[] = $node->id();
    }

    return array_unique($result);
  }

  /**
   * Gets all Labels for the passed node, including all parent Orgs.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to get Labels from.
   *
   * @return string[]
   *   The array of slugified Labels names related to this node.
   */
  public function getAllLabelsFromNode(Node $node) {
    $result = [];

    if (!empty($node)) {
      if ($node->hasField('field_reusable_label')) {
        $labels = $node->field_reusable_label->referencedEntities();
        foreach ($labels as $label) {
          $result[] = $this->slugify(trim($label->label()));
        }
      }
    }

    return $result;
  }

}
