<?php

namespace Drupal\mass_views\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field: shows the top-level organization for the row entity.
 *
 * For org_page rows: the org is the node itself.
 * For other node rows: org(s) come from field_organizations.
 * The "top-level" org is found by walking org_page.field_parent upward.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("mass_views_org_top_parent_field")
 */
class OrgTopParentField extends FieldPluginBase {

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
    // Intentionally empty: this is a computed/rendered field.
    // (No SQL fragment added; avoids duplicating rows via joins.)
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity ?? NULL;
    if (!$entity instanceof NodeInterface) {
      return '';
    }

    $orgs = [];

    // If this is an org page, treat the node itself as the org.
    if ($entity->bundle() === 'org_page') {
      $orgs = [$entity];
    }
    // Otherwise, pull orgs from field_organizations if present.
    elseif ($entity->hasField('field_organizations') && !$entity->get('field_organizations')->isEmpty()) {
      $orgs = $entity->get('field_organizations')->referencedEntities();
    }

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

    if (empty($top_labels)) {
      return '';
    }

    return implode(', ', $top_labels);
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
