<?php

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Imbo\BehatApiExtension\Context\ApiContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Behat\Context\Context;


class MassAPIContext implements Context {
  /**
   * @var ApiContext
   */
  private $apiContext;

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope)
  {
    $environment = $scope->getEnvironment();
    $this->apiContext = $environment->getContext(ApiContext::class);
  }

  /**
   * @Then the response body does not contain JSON:
   *
   * Note: This is the inverse of the APIContext's assertion.
   */
  public function assertResponseBodyNotContainsJson(PyStringNode $contains) {
    try {
      $this->apiContext->assertResponseBodyContainsJson($contains);
    }
    catch(Exception $e) {
      // In this case we want an exception.
      return;
    }
    throw new \Exception(sprintf('%s was found in response body.', (string) $contains));
  }

}
