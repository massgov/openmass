<?php

namespace MassGov\Behat;

use Drupal\DrupalDriverManager;
use Drupal\DrupalExtension\Manager\DrupalAuthenticationManager;
use Behat\Mink\Exception\DriverException;
use MassGov\Behat\Driver\EnhancedDriverInterface;

class DirectAuthenticationManager extends DrupalAuthenticationManager {

  /**
   * @var DrupalDriverManager
   */
  private $driverManager;

  public function setDriver(DrupalDriverManager $driverManager) {
    $this->driverManager = $driverManager;
  }

  public function getDriver() {
    if(!$this->driverManager) {
      throw new \Exception('Driver has not been set');
    }
    return $this->driverManager->getDriver();
  }

  public function loggedIn() {
    $session = $this->getSession();

    // If the session has not been started yet, or no page has yet been loaded,
    // then this is a brand new test session and the user is not logged in.
    if (!$session->isStarted() || !$page = $session->getPage()) {
      return false;
    }

    // Look for a css selector to determine if a user is logged in.
    // Default is the logged-in class on the body tag.
    // Which should work with almost any theme.
    try {
      if ($page->has('css', $this->getDrupalSelector('logged_in_selector'))) {
        return true;
      }
    } catch (DriverException $e) {
      // This test may fail if the driver did not load any site yet. If this is
      // the case, we can assume we're not logged in.
      return false;
    }

    // Visit the homepage and check again.
    $session->visit('/');
    if ($page->has('css', $this->getDrupalSelector('logged_in_selector'))) {
      return true;
    }
    return false;
  }

  public function logIn(\stdClass $user) {
    // Ensure we aren't already logged in.
    $this->fastLogout();

    $driver = $this->getDriver();
    if(!$driver instanceof EnhancedDriverInterface) {
      throw new \Exception('Direct login is only supported by the EnhancedDriverInterface');
    }
    $login = $driver->getLoginLink($user);

    $this->getSession()->visit($login);

    if (!$this->loggedIn()) {
      if (isset($user->role)) {
        throw new \Exception(sprintf("Unable to determine if logged in because 'log_out' link cannot be found for user '%s' with role '%s'", $user->name, $user->role));
      } else {
        throw new \Exception(sprintf("Unable to determine if logged in because 'log_out' link cannot be found for user '%s'", $user->name));
      }
    }

    $this->userManager->setCurrentUser($user);
  }
}
