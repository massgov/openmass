<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field: shows the top-level organization for a Media row entity.
 *
 * Organization(s) come from media.field_organizations (org_page nodes).
 * The "top-level" org is found by walking org_page.field_parent upward.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("mass_views_media_org_top_parent_field")
 */
class OrgTopParentMediaField extends FieldPluginBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Computed/rendered field: no query changes (avoids row duplication).
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity ?? NULL;
    if (!$entity instanceof MediaInterface) {
      return '';
    }

    if (!$entity->hasField('field_organizations') || $entity->get('field_organizations')->isEmpty()) {
      return '';
    }

    $orgs = $entity->get('field_organizations')->referencedEntities();
    if (empty($orgs)) {
      return '';
    }

    $top_labels = [];
    foreach ($orgs as $org) {
      if (!$org instanceof NodeInterface || $org->bundle() !== 'org_page') {
        continue;
      }
      $top = $this->loadTopLevelOrg($org);
      if ($top) {
        $top_labels[$top->id()] = $top->label();
      }
    }

    return $top_labels ? implode(', ', $top_labels) : '';
  }

  /**
   * Walk up field_parent to find the top-level org_page.
   *
   * @param \Drupal\node\NodeInterface $org
   *   Starting org_page node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The top-level org_page node, or NULL if not resolvable.
   */
  private function loadTopLevelOrg(NodeInterface $org): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');

    $current = $org;
    $seen = [];

    // Hard safety limit: prevents infinite traversal even if data is cyclic.
    $maxDepth = 50;

    for ($i = 0; $i < $maxDepth; $i++) {
      $current_id = (int) $current->id();
      if ($current_id > 0) {
        if (isset($seen[$current_id])) {
          // Cycle protection.
          return $current;
        }
        $seen[$current_id] = TRUE;
      }

      if (!$current->hasField('field_parent') || $current->get('field_parent')->isEmpty()) {
        return $current;
      }

      $parent_id = (int) $current->get('field_parent')->target_id;
      if ($parent_id <= 0) {
        return $current;
      }

      $parent = $storage->load($parent_id);
      if (!$parent instanceof NodeInterface || $parent->bundle() !== 'org_page') {
        return $current;
      }

      $current = $parent;
    }

    // If we hit the depth limit, return the last org we could resolve.
    return $current;
  }

}
