<?php

namespace Drupal\mass_content\Entity\Bundle;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\user\Entity\User;

/**
 * A bundle class for user entities.
 */
class UserBundle extends User {

  /**
   * Get user's organization.
   */
  public function getOrg(): FieldItemListInterface {
    return $this->get('field_user_org');
  }

}
