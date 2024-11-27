<?php

namespace Drupal\mass_utility;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy\Storage\EntityTreeNodeMapperInterface;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\entity_hierarchy_breadcrumb\HierarchyBasedBreadcrumbBuilder;
use Drupal\node\Entity\Node;

/**
 * Entity hierarchy breadcrumb alterations for Mass Utility.
 */
class MassUtilityBreadcrumb extends HierarchyBasedBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    NestedSetStorageFactory $storage_factory,
    NestedSetNodeKeyFactory $node_key_factory,
    EntityTreeNodeMapperInterface $mapper,
    EntityFieldManagerInterface $entity_field_manager,
    AdminContext $admin_context,
  ) {
    parent::__construct($storage_factory, $node_key_factory, $mapper, $entity_field_manager, $admin_context);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($this->adminContext->isAdminRoute($route_match->getRouteObject()) && $route_match->getRouteName() !== 'entity.node.edit_form') {
      return FALSE;
    }
    $route_entity = $this->getEntityFromRouteMatch($route_match);
    if (!$route_entity || !$route_entity instanceof ContentEntityInterface || !$this->getHierarchyFieldFromEntity($route_entity)) {
      return FALSE;
    }

    return TRUE;
  }

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
        if ($entity instanceof Node && $entity->hasField('field_short_title') && !empty($entity->field_short_title->value)) {
          $links[] = Link::createFromRoute($entity->field_short_title->value, '<none>');
        }
        else {
          $links[] = Link::createFromRoute($entity->label(), '<none>');
        }
      }
      elseif ($entity instanceof Node && $entity->hasField('field_short_title') && !empty($entity->field_short_title->value)) {
        $links[] = Link::createFromRoute($entity->field_short_title->value, 'entity.node.canonical', ['node' => $entity->id()]);
      }
      else {
        $links[] = $entity->toLink();
      }
    }

    array_unshift($links, Link::createFromRoute(new TranslatableMarkup('Home'), '<front>'));
    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

}
