<?php

namespace Drupal\Tests\mass_caching\Kernel;

use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Mass.gov's Akamai Edge-Cache-Tag header behavior.
 *
 * @group mass_caching
 */
#[RunTestsInSeparateProcesses]
class AkamaiCacheableResponseSubscriberTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'akamai',
    'acquia_purge',
    'purge',
    'purge_queuer_coretags',
    'path_alias',
    'mass_caching',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['akamai']);
    $this->installEntitySchema('path_alias');
  }

  /**
   * Tests Edge-Cache-Tag values are hashed and comma delimited.
   */
  public function testHeaderValue(): void {
    $request = Request::create('/system/401');
    $config = $this->config('akamai.settings');

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::getContainer()->get('http_kernel');
    $response = $kernel->handle($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertFalse($response->headers->has('Edge-Cache-Tag'));

    $config->set('edge_cache_tag_header', TRUE)->save();
    $response = $kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertTrue($response->headers->has('Edge-Cache-Tag'));
    $this->assertEquals('l2u0,80b8,us5v', $response->headers->get('Edge-Cache-Tag'));

    $config->set('edge_cache_tag_header_blacklist', ['config:'])->save();
    $response = $kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertTrue($response->headers->has('Edge-Cache-Tag'));
    $this->assertEquals('l2u0,80b8', $response->headers->get('Edge-Cache-Tag'));

    $event_dispatcher = \Drupal::getContainer()->get('event_dispatcher');
    $event_dispatcher->addListener(AkamaiHeaderEvents::HEADER_CREATION, function ($event): void {
      $event->data[] = 'helloworld';
    });

    $response = $kernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertTrue($response->headers->has('Edge-Cache-Tag'));
    $this->assertEquals('l2u0,80b8,vhf0', $response->headers->get('Edge-Cache-Tag'));
  }

}
