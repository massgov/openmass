<?php

namespace Drupal\Tests\mass_caching\ExistingSite;

use Drupal\akamai\Event\AkamaiHeaderEvents;
use MassGov\Dtt\MassExistingSiteBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use weitzman\DrupalTestTraits\ConfigTrait;

/**
 * Tests Mass.gov's Akamai Edge-Cache-Tag header behavior.
 *
 * @group mass_caching
 */
class AkamaiCacheableResponseSubscriberTest extends MassExistingSiteBase {

  use ConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertTrue(
      \Drupal::moduleHandler()->moduleExists('akamai'),
      'The akamai module must be enabled for this test.'
    );
    $this->assertTrue(
      \Drupal::moduleHandler()->moduleExists('mass_caching'),
      'The mass_caching module must be enabled for this test.'
    );

    $this->setConfigValues([
      'akamai.settings' => [
        'edge_cache_tag_header' => FALSE,
        'edge_cache_tag_header_blacklist' => [],
      ],
    ]);
    $this->container->get('config.factory')->clearStaticCache();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->restoreConfigValues();
    parent::tearDown();
  }

  /**
   * Tests Edge-Cache-Tag values are hashed and comma delimited.
   */
  public function testHeaderValue(): void {
    $request = Request::create('/system/401');

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::getContainer()->get('http_kernel');
    $response = $kernel->handle($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertFalse($response->headers->has('Edge-Cache-Tag'));

    $this->setConfigValues([
      'akamai.settings' => [
        'edge_cache_tag_header' => TRUE,
        'edge_cache_tag_header_blacklist' => [],
      ],
    ]);
    $this->container->get('config.factory')->clearStaticCache();

    $response = $kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertTrue($response->headers->has('Edge-Cache-Tag'));
    $tags = $this->getEdgeCacheTags($response);
    $this->assertContains('l2u0', $tags);
    $this->assertContains('80b8', $tags);
    $this->assertContains('1tle', $tags);
    $this->assertStringNotContainsString(' ', $response->headers->get('Edge-Cache-Tag'));
    $this->assertStringNotContainsString('rendered', $response->headers->get('Edge-Cache-Tag'));

    $this->setConfigValues([
      'akamai.settings' => [
        'edge_cache_tag_header' => TRUE,
        'edge_cache_tag_header_blacklist' => ['config:'],
      ],
    ]);
    $this->container->get('config.factory')->clearStaticCache();

    $response = $kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertTrue($response->headers->has('Edge-Cache-Tag'));
    $tags = $this->getEdgeCacheTags($response);
    $this->assertContains('l2u0', $tags);
    $this->assertContains('80b8', $tags);
    $this->assertNotContains('1tle', $tags);

    $event_dispatcher = \Drupal::getContainer()->get('event_dispatcher');
    $event_dispatcher->addListener(AkamaiHeaderEvents::HEADER_CREATION, function ($event): void {
      $event->data[] = 'helloworld';
    });

    $response = $kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertTrue($response->headers->has('Edge-Cache-Tag'));
    $tags = $this->getEdgeCacheTags($response);
    $this->assertContains('l2u0', $tags);
    $this->assertContains('80b8', $tags);
    $this->assertContains('vhf0', $tags);
    $this->assertNotContains('helloworld', $tags);
  }

  /**
   * Returns the comma-delimited Edge-Cache-Tag header values.
   */
  private function getEdgeCacheTags(Response $response): array {
    return explode(',', $response->headers->get('Edge-Cache-Tag'));
  }

}
