<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\Core\Field\FieldItemListInterface;
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
  public function getPrimaryParent(): ?FieldItemListInterface {
    return $this->hasField(self::PRIMARY_PARENT) ? $this->get(self::PRIMARY_PARENT) : NULL;
  }

  public function isPrimaryParentRequired(): bool {
    return $this->hasField(self::PRIMARY_PARENT) && $this->getFieldDefinition(self::PRIMARY_PARENT)->isRequired();
  }

}
