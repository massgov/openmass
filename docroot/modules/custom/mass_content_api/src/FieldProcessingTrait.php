<?php

namespace Drupal\mass_content_api;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\LinkItemInterface;
use Drupal\node\Entity\Node;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;

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
   * character. This will signify the traversal of the first field UNTIL we
   * reach the second. Upon reaching the second we collect it and only it.
   * In the second use-case we append an asterisk (*) to the '>' character. This
   * Allows us to tell the _fetchRelations() method to traverse the first field
   * entirely. And collect all the link and entity reference fields it finds at
   * the end.
   * A Note: This WILL NOT traverse into node, media, etc. entity types for risk
   * of creating recursive reference loops from an entity to itself. We stop
   * traversal at entity references but allow them to continue through
   * paragraphs.
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
      $this->collected = [];
    }

    return $related;
  }

  /**
   * Used to store result from traverseRelations recursive function.
   *
   * @var array
   */
  private $collected = [];

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

    // If we have a spec left to follow, recurse into children.
    if ($spec) {
      foreach ($search_fields as $field) {
        if ($field instanceof EntityReferenceFieldItemListInterface) {
          foreach ($field->referencedEntities() as $referenced) {
            $this->collected = array_merge_recursive($this->collected, $this->traverseRelations($referenced, $spec));
          }
        }
      }
    }
    else {
      foreach ($search_fields as $field) {
        $this->collected[] = $this->collectFieldEntities($field);
      }
    }
    foreach ($this->collected as $col) {
      foreach ($col as $c) {
        $new[$c['field_name']][$c['id']] = $c;
      }
    }

    if (isset($new)) {
      $this->collected = $new;
    }
    return array_filter($this->collected);
  }

  /**
   * Processes Entity Reference field lists.
   */
  private function processEntityReferenceFieldItemListInterface(&$collected, $field_entity, $field_label, $field_name) {
    // If we can extract entity type and ID without loading up the child,
    // do it. If this results in deleted/unpublished entities ending up in the
    // index, that's ok; we do our filtering on output.
    if ($child_entity_type = $field_entity->getSetting('target_type')) {
      foreach ($field_entity as $item) {
        $child_id = $item->target_id;
        $collected[$child_id] = [
          'id' => $child_id,
          'entity' => $child_entity_type,
          'field_label' => $field_label ?? '',
          'field_name' => $field_name ?? '',
        ];
      }
    }
    else {
      foreach ($field_entity->referencedEntities() as $child) {
        $collected[$child->id()] = [
          'id' => $child->id(),
          'entity' => $child->getEntityTypeId(),
          'field_label' => $field_label ?? '',
          'field_name' => $field_name ?? '',
        ];
      }
    }
  }

  /**
   * Processes Link Item fields.
   */
  private function processLinkItemInterface(&$collected, $ref, $field_label, $field_name) {
    if (empty($ref->uri) || !preg_match('~^entity:node/(\d+)$~', $ref->uri, $matches)) {
      return;
    }
    $collected[$matches[1]] = [
      'id' => $matches[1],
      'entity' => 'node',
      'field_label' => $field_label ?? '',
      'field_name' => $field_name ?? '',
    ];
  }

  /**
   * Processes Text Item fields.
   */
  private function processTextItemBase(&$collected, $ref, $field_label, $field_name) {
    $body = $ref->getValue('value');
    if (!isset($body['value'])) {
      return;
    }

    $matches = [];
    $pattern = '/<a\s[^>]*href=\"([^\"]*)\"[^>]*>(.*)<\/a>/siU';
    preg_match_all($pattern, $body['value'], $matches);
    // $matches[1] contains the array of urls in <a> in this textarea.
    if (!isset($matches[1])) {
      return;
    }

    foreach ($matches[1] as $match) {
      $parsed_match = parse_url($match);
      // Relative urls have no host set, external links need filtered.
      if (!isset($parsed_match['path']) || (isset($parsed_match['host']) && !str_contains($parsed_match['host'], 'mass.gov'))) {
        return;
      }

      $validator = $this->getPathValidator();
      $url = $validator->getUrlIfValid($parsed_match['path']);

      if (empty($url) || $url->isExternal()) {
        return;
      }

      // Confirming the local link is a node, not media or other.
      if ($url->getRouteName() === 'entity.node.canonical') {
        $params = $url->getRouteParameters();
        $nid = $params['node'];
        $collected[$nid] = [
          'id' => $nid,
          'entity' => 'node',
          'field_label' => $field_label ?? '',
          'field_name' => $field_name ?? '',
        ];
      }
      // Documents particularly are linked to, track those.
      elseif ($url->getRouteName() === 'media_entity_download.download') {
        $params = $url->getRouteParameters();
        $id = $params['media'];
        // @todo the undefined need below needs to be fixed.
        $collected[$id] = [
          'id' => $id,
          'entity' => 'media',
          'field_label' => $field_label ?? '',
          'field_name' => $field_name ?? '',
        ];
      }
    }
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

    $field_label = !empty($field_entity->getFieldDefinition()) ?
      $field_entity->getFieldDefinition()->getLabel() : '';

    $field_name = $field_entity->getName();

    if ($field_entity instanceof EntityReferenceFieldItemListInterface) {
      $this->processEntityReferenceFieldItemListInterface($collected, $field_entity, $field_label, $field_name);
    }
    elseif ($field_entity instanceof FieldItemListInterface) {
      foreach ($field_entity as $ref) {
        if ($ref instanceof LinkItemInterface) {
          $this->processLinkItemInterface($collected, $ref, $field_label, $field_name);
        }
        // Extract local linked content from text areas.
        elseif ($ref instanceof TextItemBase) {
          $this->processTextItemBase($collected, $ref, $field_label, $field_name);
        }
      }
    }
    return $collected;
  }

  /**
   * Gets the path validator service.
   *
   * @return \Drupal\Core\Path\PathValidatorInterface
   *   The Path Validator Service.
   */
  protected function getPathValidator() {
    if (!$this->pathValidator) {
      $this->pathValidator = \Drupal::service('path.validator');
    }
    return $this->pathValidator;
  }

}
