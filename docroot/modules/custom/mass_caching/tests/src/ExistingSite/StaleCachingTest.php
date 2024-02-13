<?php

namespace Drupal\Tests\mass_caching\ExistingSite;

use Drupal\mass_caching\EventSubscriber\StaleResponseSubscriber;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests the stale caching functionality.
 */
class StaleCachingTest extends MassExistingSiteBase {

  use LoginTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Checks for the existence of 'stale' headers for anonymous users.
   *
   * If a user is authenticated the Cache-Control header should be customized
   * and setResponseCacheable should not be run, so no stale headers should be
   * set.
   *
   * @see Drupal\Core\EventSubscriber::onRespond()
   */
  public function testStaleHeadersPresentForAnonymous() {
    $node = $this->createNode([
      'type' => 'page',
      'path' => [
        'alias' => '/foo-foo',
      ],
    ]);
    $node->save();
    $this->drupalGet('foo-foo');
    $headers = $this->getSession()->getResponseHeaders();

    // Test that anonymous users have stale headers and the value is correct.
    $duration = StaleResponseSubscriber::DURATION;
    $this->assertStringContainsString("stale-if-error=$duration", $headers['Cache-Control'][0]);
    $this->assertStringContainsString("stale-while-revalidate=$duration", $headers['Cache-Control'][0]);

    // Test that authenticated users do not have stale headers of any kind.
    $account = $this->createUser();
    $this->drupalLogin($account);

    $this->drupalGet('foo-foo');
    $headers = $this->getSession()->getResponseHeaders();
    $this->assertStringNotContainsString('stale-if-error', $headers['Cache-Control'][0]);
    $this->assertStringNotContainsString('stale-while-revalidate', $headers['Cache-Control'][0]);
  }

}
