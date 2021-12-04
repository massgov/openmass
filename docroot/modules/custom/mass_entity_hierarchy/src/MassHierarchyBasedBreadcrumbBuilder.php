<?php

namespace Drupal\mass_entity_hierarchy;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy_breadcrumb\HierarchyBasedBreadcrumbBuilder;

/**
 * Entity hierarchy based breadcrumb builder.
 */
class MassHierarchyBasedBreadcrumbBuilder extends HierarchyBasedBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $route_entity */
    $route_entity = $this->getEntityFromRouteMatch($route_match);

    $entity_type = $route_entity->getEntityTypeId();
    $storage = $this->storageFactory->get($this->getHierarchyFieldFromEntity($route_entity), $entity_type);
    $ancestors = $storage->findAncestors($this->nodeKeyFactory->fromEntity($route_entity));
    // Pass in the breadcrumb object for caching.
    $ancestor_entities = $this->mapper->loadAndAccessCheckEntitysForTreeNodes($entity_type, $ancestors, $breadcrumb);

    $links = [];
    foreach ($ancestor_entities as $ancestor_entity) {
      if (!$ancestor_entities->contains($ancestor_entity)) {
        // Doesn't exist or is access hidden.
        continue;
      }
      $entity = $ancestor_entities->offsetGet($ancestor_entity);
      // Show just the label for the entity from the route.
      if ($entity->id() == $route_entity->id()) {
        // Override: Use field_short_title if it's set.
        $text = $entity->label();
        if ($entity->hasField('field_short_title') && !$entity->get('field_short_title')->isEmpty()) {
          $text = $entity->get('field_short_title')->value;
        }
        $links[] = Link::createFromRoute($text, '<none>');
      }
      else {
        if ($entity->hasField('field_short_title') && !$entity->get('field_short_title')->isEmpty()) {
          $text = $entity->get('field_short_title')->value;
          $links[] = Link::fromTextAndUrl($text, $entity->toUrl());
        }
        else {
          $links[] = $entity->toLink();
        }
        // End override.
      }
    }

    array_unshift($links, Link::createFromRoute(new TranslatableMarkup('Home'), '<front>'));
    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

}
