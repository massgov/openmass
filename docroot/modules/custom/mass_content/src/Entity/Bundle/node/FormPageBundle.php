<?php

namespace Drupal\mass_content\Entity\Bundle\node;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * A bundle class for node entities.
 */
class FormPageBundle extends NodeBundle {

  // Define constants for platform values.
  public const PLATFORM_FORMSTACK = 'formstack';
  public const PLATFORM_GRAVITY_FORMS = 'gravity_forms';

  /**
   * Get platform.
   */
  public function getPlatform(): FieldItemListInterface {
    return $this->get('field_form_platform');
  }

  /**
   * Check if platform is FormStack.
   *
   * @return bool
   *   TRUE if the platform is FormStack and field is set, FALSE otherwise.
   */
  public function isFormStack(): bool {
    $platform = $this->getPlatform();
    return !empty($platform->getString()) && $platform->getString() === self::PLATFORM_FORMSTACK;
  }

  /**
   * Check if platform is Gravity Forms.
   *
   * @return bool
   *   TRUE if the platform is Gravity Forms and field is set, FALSE otherwise.
   */
  public function isGravityForms(): bool {
    $platform = $this->getPlatform();
    return !empty($platform->getString()) && $platform->getString() === self::PLATFORM_GRAVITY_FORMS;
  }

  /**
   * Get form embed field.
   *
   * @return string|null
   *   The value of the form embed field, or NULL if it's empty.
   */
  public function getFormEmbed(): ?string {
    $embed_field = $this->get('field_form_embed');
    return !empty($embed_field->getString()) ? $embed_field->getString() : null;
  }

  /**
   * Check if form embed field is set and not empty.
   *
   * @return bool
   *   TRUE if form embed field has a value, FALSE otherwise.
   */
  public function hasFormEmbed(): bool {
    return !empty($this->getFormEmbed());
  }

}
