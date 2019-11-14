<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Context\DrupalContext;
use Drupal\DrupalExtension\Context\MinkContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class MassXssContext extends RawDrupalContext {

  /**
   * @var MinkContext
   */
  private $minkContext;

  /**
   * @var DrupalContext
   */
  private $drupalContext;

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope)
  {
    $environment = $scope->getEnvironment();
    $this->drupalContext = $environment->getContext(DrupalContext::class);
    $this->minkContext = $environment->getContext(MinkContext::class);
  }

  /**
   * @BeforeFeature @xss
   */
  public static function setUp() {
    \Drupal::service('module_installer')->install(['mass_xss']);
  }

  /**
   * @AfterFeature @xss
   */
  public static function tearDown() {
    \Drupal::service('module_installer')->uninstall(['mass_xss']);
  }

  /**
   * @Then I should not see any vulnerabilities
   */
  public function assertNoVulnerabilities() {
    if($vulnerableNodes = $this->getSession()->getPage()->findAll('css', 'xss')) {
      $messages = [];
      foreach($vulnerableNodes as $node) {
        $messages[] = $node->getText();
      }
      $url = $this->getSession()->getCurrentUrl();
      $message = sprintf('Vulnerabilities were discovered on %s: %s', $url, implode(', ', $messages));
      throw new \Exception($message);
    }
  }
}
