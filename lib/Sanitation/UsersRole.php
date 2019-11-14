<?php


namespace MassGov\Sanitation;


use Drupal\Core\Database\Database;

class UsersRole {

  public function sanitize() {
    $this->removeUserRole();
    $this->removeUserName();
  }

  public function removeUserRole() {
    // Remove all authors roles from the database.
    Database::getConnection()->truncate('user_roles')->execute();

  }

  public function removeUserName() {
    // User data table updated.
    Database::getConnection()->truncate('users_data table')->execute();
  }

}
