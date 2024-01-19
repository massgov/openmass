<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_hierarchy\Plugin\Field\FieldType\EntityReferenceHierarchyFieldItemList;
use Drupal\mass_content_moderation\MassModerationTrait;
use Drupal\mass_fields\MassCollectionTrait;
use Drupal\mass_fields\MassOrganizationsTrait;
use Drupal\mass_fields\MassSearchTrait;
use Drupal\mass_fields\MassTranslationsTrait;
use Drupal\node\Entity\Node;

/**
 * A base bundle class for node entities.
 */
abstract class NodeBundle extends Node {
  use MassSearchTrait;
  use MassCollectionTrait;
  use MassOrganizationsTrait;
  use MassModerationTrait;
  use MassTranslationsTrait;

  const FIELD_NAME_ENGLISH_VERSION = 'field_english_version';
  const PRIMARY_PARENT = 'field_primary_parent';

  /**
   * Get search nosnippet value. Media doesn't have this field.
   */
  public function getSearchNoSnippet(): FieldItemListInterface {
    return $this->get('search_nosnippet');
  }

  /**
   * Get field_primary_parent value. Media doesn't have this field.
   */
  public function getPrimaryParent(): ?EntityReferenceHierarchyFieldItemList {
    return $this->hasField(self::PRIMARY_PARENT) ? $this->get(self::PRIMARY_PARENT) : NULL;
  }

  public function isPrimaryParentRequired(): bool {
    if (!$this->hasField(self::PRIMARY_PARENT)) {
      return FALSE;
    }
    $field_config = $this->getFieldDefinition(self::PRIMARY_PARENT);
    // This code comes from RequireOnPublishValidator.
    $required = $field_config->getThirdPartySetting('require_on_publish', 'require_on_publish', FALSE);
    return $required;
  }

}
