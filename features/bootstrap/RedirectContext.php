<?php

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Behat\Context\Step;

class RedirectContext extends RawMinkContext
{
  /**
   * @AfterScenario
   */
  public function resetInterception() {
    $driver = $this->getSession()->getDriver();
    if($driver instanceof GoutteDriver) {
      // Reset goutte driver to follow redirects again.
      $driver->getClient()->followRedirects(TRUE);
    }
  }

  public function canInterceptRedirects() {
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof GoutteDriver) {
      throw new UnsupportedDriverActionException(
        'You need to tag the scenario with '.
        '"@mink:goutte" or "@mink:symfony2". '.
        'Intercepting the redirections is not '.
        'supported by %s', $driver
      );
    }
  }

  /**
   * @Given I do not follow redirects
   */
  public function theRedirectionsAreIntercepted() {
    $this->canInterceptRedirects();
    $this->getSession()->getDriver()->getClient()->followRedirects(FALSE);
  }

  /**
   * @Then I should be redirected to :expectedRedirect
   */
  public function assertRedirected($expectedRedirect) {
    $this->assertSession()->responseHeaderEquals('Location', $expectedRedirect);
  }




}
