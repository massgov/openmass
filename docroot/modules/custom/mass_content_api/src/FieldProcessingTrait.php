<?php

namespace Drupal\mass_content_api;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\LinkItemInterface;

/**
 * Trait FieldProcessingTrait.
 */
trait FieldProcessingTrait {

  /**
   * Fetch the third party settings of a given content entity.
   *
   * There are 2 nodes in the database that are associated with
   * non-existent content types. We want to have this method fail
   * gracefully if and when we process those nodes.
   *
   * @param \Drupal\node\Entity\Node $node
   *   A node object from which to retrieve any third party settings.
   *
   * @return mixed
   *   If third party settings are present we return them otherwise we return
   *   nothing.
   */
  public function fetchNodeTypeConfig(Node $node) {
    $type = $node->type->entity;
    if (!$type) {
      \Drupal::logger('mass_content_api')->warning('Node "@id" does not have a type', ['@id' => $node->id()]);
      return [];
    }
    $node_settings = $type->getThirdPartySettings('mass_content_api');
    $config = array_filter($node_settings);
    $related = [];
    foreach ($config as $dependency_status => $specs) {
      $related[$dependency_status] = [];
      foreach ($specs as $spec) {
        $related[$dependency_status][] = explode('>', $spec);
      }
    }
    return $related;
  }

  /**
   * Fetch IDs and types of related entities given a content entity and config.
   *
   * Spec should be taken from the config array in the content entity's .yml
   * file. In all cases we are generating this config manually using the
   * following guidelines:
   * mass_content_api:
   *   dependency_status_parent:
   *     - field_no_specified_dependants
   *     - field_parent_field>field_child_field
   *   dependency_status_child:
   *     - field_child_field>field_parent_field
   *   dependency_status_link_page:
   *     - field_linking_page_field>*
   *
   * Each section indicates those array elements eventual position in the
   * mass_content_api_descendants database table. In all cases, the first field
   * before the '>' in each array item should be a field on the entity you are
   * configuring.
   * When there is no '>' present it is assumed there is no traversal on the
   * listed field and any values collected are collected at face-value.
   * The presence of the '>' character presents two (2) use-cases. In the first
   * use-case we may append a second field immediately following the '>'
   * character. This will signify the traversal of the first field UNTIL we reach
   * the second. Upon reaching the second we collect it and only it.
   * In the second use-case we append an asterisk (*) to the '>' character. This
   * Allows us to tell the _fetchRelations() method to traverse the first field
   * entirely. And collect all the link and entity reference fields it finds at
   * the end.
   * A Note: This WILL NOT traverse into node, media, etc. entity types for risk
   * of creating recursive reference loops from an entity to itself. We stop
   * traversal at entity references but allow them to continue through paragraphs.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to execute traversal on.
   * @param array $spec
   *   An array containing the entity's mass_content_api config.
   *
   * @return array
   *   An array of IDs and types of related entities.
   */
  public function fetchRelations(ContentEntityInterface $entity, array $spec) {
    $related = [];
    foreach ($spec as $dependency_status => $field_sets) {
      $related[$dependency_status] = [];
      foreach ($field_sets as $fields) {
        $related[$dependency_status] = array_merge($related[$dependency_status], $this->traverseRelations($entity, $fields));
      }
    }
    return $related;
  }

  /**
   * Given a single entity and a single spec, follow the spec and fetch relations.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to execute traversal on.
   * @param array $spec
   *   An array containing the entity's mass_content_api config.
   *
   * @return array
   *   An array of IDs and types of related entities.
   */
  private function traverseRelations(ContentEntityInterface $entity, array $spec) {
    $active_spec = array_shift($spec);

    if ($entity->hasField($active_spec)) {
      $search_fields = [$entity->get($active_spec)];
    }
    elseif ($active_spec === '*') {
      $search_fields = array_filter($entity->getFields(FALSE), function (FieldItemListInterface $field) use ($entity) {
        $definition = $entity->getFieldDefinition($field->getName());
        return !$definition->getFieldStorageDefinition()->isBaseField();
      });
    }
    else {
      $search_fields = [];
    }

    $collected = [];

    // If we have a spec left to follow, recurse into children.
    if ($spec) {
      foreach ($search_fields as $field) {
        if ($field instanceof EntityReferenceFieldItemListInterface) {
          foreach ($field->referencedEntities() as $referenced) {
            $collected = array_merge_recursive($collected, $this->traverseRelations($referenced, $spec));
          }
        }
      }
    }
    else {
      foreach ($search_fields as $field) {
        $collected[$field->getName()] = $this->collectFieldEntities($field);
      }
    }

    return array_filter($collected);
  }

  /**
   * Collect field entity values based on their class.
   *
   * Used primarily as a helper method for fetchRelations(). Because we're
   * collecting field values in a couple places detaching this functionality
   * from the parent method means we can add field values to the $collected
   * array based on more conditions than just their type. For example, the
   * condition that we've matched certain field search criteria.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field_entity
   *   The field entity to act on.
   *
   * @return array
   *   An array of field names, their values and corresponding entity types.
   *
   * @see fetchChildren()
   */
  private function collectFieldEntities(FieldItemListInterface $field_entity) {
    $collected = [];
    if ($field_entity instanceof EntityReferenceFieldItemListInterface) {
      // If we can extract entity type and ID without loading up the child,
      // do it. If this results in deleted/unpublished entities ending up in the
      // index, that's ok; we do our filtering on output.
      if ($child_entity_type = $field_entity->getSetting('target_type')) {
        foreach ($field_entity as $item) {
          $child_id = $item->target_id;
          $collected[$child_id] = [
            'id' => $child_id,
            'entity' => $child_entity_type
          ];
        }
      }
      else {
        foreach ($field_entity->referencedEntities() as $child) {
          $collected[$child->id()] = [
            'id' => $child->id(),
            'entity' => $child->getEntityTypeId(),
          ];
        }
      }

    }
    elseif ($field_entity instanceof FieldItemListInterface) {
      foreach ($field_entity as $ref) {
        if ($ref instanceof LinkItemInterface) {
          if (preg_match('~^entity:node/(\d+)$~', $ref->uri, $matches)) {
            $collected[$matches[1]] = [
              'id' => $matches[1],
              'entity' => 'node',
            ];
          }
        }
      }
    }
    return $collected;
  }

}
