<?php

namespace Drupal\Tests\mass_inline_message\Traits;

use Drupal\user\UserInterface;

/**
 * Creates common test users for Message box browser tests.
 */
trait InlineMessageTestUserTrait {

  /**
   * Creates an active administrator test user.
   */
  protected function createAdministrator(): UserInterface {
    $user = $this->createUser();
    $user->addRole('administrator');
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * Creates an active editor with content_team + editor roles.
   */
  protected function createContentEditor(): UserInterface {
    $user = $this->createUser();
    $user->addRole('content_team');
    $user->addRole('editor');
    $user->activate();
    $user->save();
    return $user;
  }

}
