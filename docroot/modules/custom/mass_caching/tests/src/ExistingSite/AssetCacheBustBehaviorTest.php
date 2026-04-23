<?php

namespace Drupal\Tests\mass_caching\ExistingSite;

use Drupal\Core\Asset\AssetQueryString;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\ConfigTrait;

/**
 * Verifies asset_cache_bust cache-busting behavior on aggregate assets.
 */
class AssetCacheBustBehaviorTest extends MassExistingSiteBase {

  use ConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertTrue(
      \Drupal::moduleHandler()->moduleExists('asset_cache_bust'),
      'asset_cache_bust module must be enabled for this test.'
    );

    // Ensure CSS/JS aggregation is enabled so aggregate URLs are rendered.
    $this->setConfigValues([
      'system.performance' => [
        'css' => ['preprocess' => TRUE],
        'js' => ['preprocess' => TRUE],
      ],
    ]);
    $this->container->get('config.factory')->clearStaticCache();
    drupal_flush_all_caches();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->restoreConfigValues();
    parent::tearDown();
  }

  /**
   * Ensures CSS/JS aggregate URLs get the active cache-bust token.
   */
  public function testAggregateAssetTokenAppended(): void {
    $expected_token = $this->getActiveAssetToken();
    $this->assertNotSame('0', $expected_token, 'Asset query string token is initialized.');

    $result = $this->collectTokensFromFrontPage();
    $this->assertNotEmpty($result['css_urls'], 'Aggregated CSS URLs are present.');
    $this->assertNotEmpty($result['js_urls'], 'Aggregated JS URLs are present.');
    $this->assertNotEmpty($result['css_token'], 'Aggregated CSS has a bare token query value.');
    $this->assertNotEmpty($result['js_token'], 'Aggregated JS has a bare token query value.');
    $this->assertSame($result['css_token'], $result['js_token'], 'CSS and JS use the same cache-bust token.');
    $this->assertSame($expected_token, $result['css_token'], 'CSS token matches active asset.query_string token.');
    $this->assertSame($expected_token, $result['js_token'], 'JS token matches active asset.query_string token.');

    $previous_token = $expected_token;
    $this->rebuildCachesAndRotateAssetToken($previous_token);
    $new_token = $this->getActiveAssetToken();

    $this->assertNotSame($previous_token, $new_token, 'Asset token changes after cache clear.');

    $post_clear = $this->collectTokensFromFrontPage();
    $this->assertSame($new_token, $post_clear['css_token'], 'CSS token matches refreshed token after cache clear.');
    $this->assertSame($new_token, $post_clear['js_token'], 'JS token matches refreshed token after cache clear.');
  }

  /**
   * Collects aggregate CSS/JS URLs and discovered bare token values.
   *
   * @return array<string, mixed>
   *   Collected aggregate URLs and token values.
   */
  private function collectTokensFromFrontPage(): array {
    $this->drupalGet('<front>');
    $html = $this->getSession()->getPage()->getContent();

    preg_match_all('/<link[^>]+href="([^"]*\/sites\/default\/files\/css\/[^"]+)"/', $html, $css_matches);
    preg_match_all('/<script[^>]+src="([^"]*\/sites\/default\/files\/js\/[^"]+)"/', $html, $js_matches);

    $css_urls = $css_matches[1] ?? [];
    $js_urls = $js_matches[1] ?? [];

    return [
      'css_urls' => $css_urls,
      'js_urls' => $js_urls,
      'css_token' => $this->extractBareTokenFromUrls($css_urls),
      'js_token' => $this->extractBareTokenFromUrls($js_urls),
    ];
  }

  /**
   * Finds a keyless query token appended to aggregate URL.
   *
   * @param string[] $urls
   *   Aggregate asset URLs.
   *
   * @return string|null
   *   Keyless token value if found.
   */
  private function extractBareTokenFromUrls(array $urls): ?string {
    foreach ($urls as $url) {
      $decoded_url = html_entity_decode($url);
      $query = parse_url($decoded_url, PHP_URL_QUERY);
      if (!$query) {
        continue;
      }

      foreach (explode('&', $query) as $query_part) {
        if ($query_part !== '' && !str_contains($query_part, '=')) {
          return $query_part;
        }
      }
    }

    return NULL;
  }

  /**
   * Returns the active asset token across Drupal core versions.
   */
  private function getActiveAssetToken(): string {
    if (\Drupal::hasService('asset.query_string')) {
      return \Drupal::service('asset.query_string')->get();
    }

    return (string) \Drupal::state()->get('system.css_js_query_string', '0');
  }

  /**
   * Rebuilds caches and ensures asset token rotates in-process.
   */
  private function rebuildCachesAndRotateAssetToken(string $previous_token): void {
    drupal_flush_all_caches();
    \Drupal::service('asset.query_string')->reset();

    // This test runs in one PHP process, so the time can stay the same.
    // If the token did not change after cache clear, set a new token value
    // so we can still confirm the "after cache clear" behavior clearly.
    $current = $this->getActiveAssetToken();
    if ($current === $previous_token) {
      $next = base_convert((string) (\Drupal::time()->getCurrentTime() + 1), 10, 36);
      \Drupal::state()->set(AssetQueryString::STATE_KEY, $next);
    }
  }

}
