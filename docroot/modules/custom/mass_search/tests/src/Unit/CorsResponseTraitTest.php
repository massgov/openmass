<?php

namespace Drupal\Tests\mass_search\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\mass_search\Controller\CorsResponseTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the origin allowlist behind the CORS header on /api/v1/*.
 *
 * The allowlist used to be interpolated into a regular expression, so every
 * dot in an allowed host acted as a wildcard and a lookalike origin such as
 * https://searchXmassYgov was answered with Access-Control-Allow-Origin. That
 * let an attacker controlled site read those responses cross-origin, so the
 * lookalike cases below are pinned here.
 *
 * @coversDefaultClass \Drupal\mass_search\Controller\CorsResponseTrait
 * @group mass_search
 */
class CorsResponseTraitTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // CacheableMetadata::addCacheContexts() validates the context tokens
    // through the container.
    $cache_contexts_manager = $this->createMock(CacheContextsManager::class);
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Returns the response produced for a request with the given Origin.
   */
  private function respondTo(?string $origin): Response {
    $controller = new class() {
      use CorsResponseTrait;
    };
    $request = new Request();
    if ($origin !== NULL) {
      $request->headers->set('Origin', $origin);
    }
    $response = new Response();
    $controller->addCorsHeaderToResponse(new CacheableMetadata(), $response, $request);
    return $response;
  }

  /**
   * Allowed origins are echoed back verbatim.
   *
   * @dataProvider allowedOrigins
   */
  public function testAllowedOrigin(string $origin): void {
    $response = $this->respondTo($origin);
    $this->assertSame($origin, $response->headers->get('Access-Control-Allow-Origin'));
  }

  /**
   * Origins outside the allowlist never receive the header.
   *
   * @dataProvider deniedOrigins
   */
  public function testDeniedOrigin(?string $origin): void {
    $response = $this->respondTo($origin);
    $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
  }

  /**
   * The response varies on Origin whether or not the header is added.
   */
  public function testResponseVariesOnOrigin(): void {
    $this->assertSame(['Origin'], $this->respondTo('https://search.mass.gov')->getVary());
    $this->assertSame(['Origin'], $this->respondTo('https://evil.com')->getVary());
  }

  /**
   * The cache metadata records the Origin context.
   */
  public function testCacheContext(): void {
    $controller = new class() {
      use CorsResponseTrait;
    };
    $cache_metadata = new CacheableMetadata();
    $controller->addCorsHeaderToResponse($cache_metadata, new Response(), new Request());
    $this->assertContains('headers:Origin', $cache_metadata->getCacheContexts());
  }

  /**
   * Data provider of origins that must be allowed.
   */
  public static function allowedOrigins(): array {
    return [
      'production search' => ['https://search.mass.gov'],
      'plain http' => ['http://search.mass.gov'],
      'uppercase host' => ['https://SEARCH.MASS.GOV'],
      'digital subdomain' => ['https://search.digital.mass.gov'],
      'dev search' => ['https://devsearch.digital.mass.gov'],
      'stage search' => ['https://stagesearch.digital.mass.gov'],
      'local dev with port' => ['http://localhost:3000'],
      'local site' => ['https://mass.local'],
      'backstop container' => ['http://web'],
      'backstop host' => ['http://host.docker.internal:8080'],
    ];
  }

  /**
   * Data provider of origins that must be refused.
   */
  public static function deniedOrigins(): array {
    return [
      'no origin header' => [NULL],
      'empty origin' => [''],
      'opaque origin' => ['null'],
      'dots treated as wildcards' => ['https://searchXmassYgov'],
      'wildcard with port' => ['https://searchAmassBgov:1234'],
      'allowed host as a suffix' => ['https://search.mass.gov.evil.com'],
      'allowed host as a prefix' => ['https://evil.com/search.mass.gov'],
      'allowed host as a subdomain' => ['https://evil.search.mass.gov'],
      'sibling of an allowed host' => ['https://search.mass.gov.au'],
      'userinfo smuggling' => ['https://search.mass.gov@evil.com'],
      'unrelated host' => ['https://evil.com'],
      'unsupported scheme' => ['ftp://search.mass.gov'],
      'scheme relative' => ['//search.mass.gov'],
      'bare host' => ['search.mass.gov'],
    ];
  }

}
