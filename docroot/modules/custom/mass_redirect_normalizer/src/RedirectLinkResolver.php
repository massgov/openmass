<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;

/**
 * Resolves and rewrites redirect-based internal links.
 */
class RedirectLinkResolver {

  /**
   * Creates a resolver instance.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AliasManagerInterface $pathAliasManager,
    protected RequestStack $requestStack,
    protected RequestContext $requestContext,
  ) {
  }

  /**
   * Normalizes redirected internal links in rich text.
   */
  public function normalizeRedirectLinksInText(string $text): array {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $changed = FALSE;

    foreach ($xpath->query('//a[@href]') as $anchor) {
      if (!$anchor instanceof \DOMElement) {
        continue;
      }
      $href = (string) $anchor->getAttribute('href');
      $resolved = $this->resolveRedirectTarget($href);
      if (!$resolved['changed']) {
        continue;
      }

      $anchor->setAttribute('href', $resolved['target_path']);
      if (!empty($resolved['node'])) {
        $anchor->setAttribute('data-entity-uuid', $resolved['node']->uuid());
        $anchor->setAttribute('data-entity-substitution', 'canonical');
        $anchor->setAttribute('data-entity-type', 'node');
      }
      $changed = TRUE;
    }

    return [
      'changed' => $changed,
      'text' => Html::serialize($dom),
    ];
  }

  /**
   * Normalizes redirected internal links in link fields.
   */
  public function normalizeRedirectLinkUri(string $uri): array {
    $resolved = $this->resolveRedirectTarget($uri);
    if (!$resolved['changed']) {
      return [
        'changed' => FALSE,
        'uri' => $uri,
      ];
    }

    return [
      'changed' => TRUE,
      'uri' => 'internal:' . $resolved['target_path'],
    ];
  }

  /**
   * Resolves an internal URL/path through redirect chains.
   */
  public function resolveRedirectTarget(string $url, int $maxDepth = 10): array {
    $parsed = parse_url($url) ?: [];
    $sourcePath = $this->extractLocalPath($url);
    if (!$sourcePath) {
      return ['changed' => FALSE];
    }

    $query = empty($parsed['query']) ? '' : '?' . $parsed['query'];
    $fragment = empty($parsed['fragment']) ? '' : '#' . $parsed['fragment'];

    $current = ltrim($sourcePath, '/');
    $visited = [];

    for ($i = 0; $i < $maxDepth; $i++) {
      if (isset($visited[$current])) {
        break;
      }
      $visited[$current] = TRUE;

      $redirect = $this->loadRedirectBySourcePath($current);
      if (!$redirect instanceof Redirect) {
        break;
      }

      $next = $this->extractLocalPath($redirect->getRedirectUrl()->toString());
      if (!$next) {
        break;
      }
      $current = ltrim($next, '/');
    }

    $finalPath = '/' . ltrim($current, '/');
    $targetPath = $finalPath . $query . $fragment;
    $sourceNormalized = '/' . ltrim($sourcePath, '/') . $query . $fragment;
    if ($targetPath === $sourceNormalized) {
      return ['changed' => FALSE];
    }

    $node = NULL;
    $internalPath = $this->pathAliasManager->getPathByAlias($finalPath);
    if (preg_match('/^\/node\/(\d+)$/', $internalPath, $matches)) {
      $node = $this->entityTypeManager->getStorage('node')->load((int) $matches[1]);
    }

    return [
      'changed' => TRUE,
      'target_path' => $targetPath,
      'node' => $node,
    ];
  }

  /**
   * Extracts local path from URL/URI; returns NULL for non-local hosts.
   */
  private function extractLocalPath(string $url): ?string {
    if (str_starts_with($url, 'internal:')) {
      $path = (string) parse_url(substr($url, strlen('internal:')), PHP_URL_PATH);
      return '/' . ltrim($path, '/');
    }

    if (str_starts_with($url, '/')) {
      $path = (string) parse_url($url, PHP_URL_PATH);
      return '/' . ltrim($path, '/');
    }

    if (!UrlHelper::isExternal($url)) {
      $path = (string) parse_url($url, PHP_URL_PATH);
      return '/' . ltrim($path, '/');
    }

    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $knownHosts = ['mass.gov', 'www.mass.gov'];
    if ($this->requestStack->getCurrentRequest()) {
      $knownHosts[] = strtolower((string) $this->requestStack->getCurrentRequest()->getHost());
    }
    $requestContextHost = strtolower((string) $this->requestContext->getHost());
    if ($requestContextHost !== '') {
      $knownHosts[] = $requestContextHost;
    }
    if (!in_array($host, array_filter($knownHosts), TRUE)) {
      return NULL;
    }

    $path = $parts['path'] ?? '/';
    return '/' . ltrim((string) $path, '/');
  }

  /**
   * Loads redirect by source path, tolerating leading slash differences.
   */
  private function loadRedirectBySourcePath(string $sourcePath): ?Redirect {
    $sourcePath = trim($sourcePath);
    if ($sourcePath === '') {
      return NULL;
    }

    $candidates = [
      ltrim($sourcePath, '/'),
      '/' . ltrim($sourcePath, '/'),
    ];

    $storage = $this->entityTypeManager->getStorage('redirect');
    foreach ($candidates as $candidate) {
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->range(0, 1);
      $group = $query->orConditionGroup()
        ->condition('redirect_source.path', $candidate)
        ->condition('redirect_source__path', $candidate);
      $ids = $query->condition($group)->execute();
      if (!$ids) {
        continue;
      }

      $redirect = $storage->load((int) reset($ids));
      if ($redirect instanceof Redirect) {
        return $redirect;
      }
    }

    return NULL;
  }

}
