<?php

namespace Drupal\Tests\mass_caching\ExistingSite;

use Drupal\mass_caching\EventSubscriber\StaleResponseSubscriber;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests the stale and general cache headers.
 */
class CachingTest extends MassExistingSiteBase {

  use MediaCreationTrait;

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
      'type' => 'org_page',
      'title' => 'Test Organization',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->drupalGet($node->toUrl()->toString());
    // $headers = $this->getSession()->getResponseHeaders();

    // Test that anonymous users have stale headers and the value is correct.
    $duration = StaleResponseSubscriber::DURATION;
    $this->assertSession()->responseHeaderContains('Cache-Control', "stale-if-error=$duration");
    $this->assertSession()->responseHeaderContains('Cache-Control', "stale-while-revalidate=$duration");

    // Test that authenticated users do not have stale headers of any kind.
    $account = $this->createUser();
    $this->drupalLogin($account);

    $this->drupalGet($node->toUrl()->toString());
    $this->assertSession()->responseHeaderNotContains('Cache-Control', 'stale-if-error');
    $this->assertSession()->responseHeaderNotContains('Cache-Control', 'stale-while-revalidate');
  }

  public function testMaxAge() {
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Organization',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->drupalGet($node->toUrl());
    $this->assertSession()->responseHeaderContains('Cache-Control', "max-age=" . MASS_CACHING_MAX_AGE_DEFAULT);
  }

}
