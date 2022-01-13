<?php

namespace Drupal\mass_content\Entity\Bundle;

use Drupal\user\Entity\User;

/**
 * A bundle class for user entities.
 */
class UserBundle extends User {
  public function getOrg() {
    return $this->get('field_user_org');
  }
}
