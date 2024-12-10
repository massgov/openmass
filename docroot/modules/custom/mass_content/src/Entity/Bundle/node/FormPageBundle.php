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
   * @return \Drupal\Core\Field\FieldItemListInterface|null
   *   The form embed field, or NULL if it's not set.
   */
  public function getFormEmbed(): ?FieldItemListInterface {
    return $this->get('field_form_embed');
  }

}
