<?php

namespace Drupal\mass_redirect_normalizer;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;

/**
 * Resolver class: pure link rewrite logic.
 *
 * This class only answers "what should this link become?".
 * It does not loop entity fields and does not save entities.
 */
class RedirectLinkResolver {

  /**
   * Creates the resolver.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AliasManagerInterface $pathAliasManager,
    protected RequestStack $requestStack,
    protected RequestContext $requestContext,
  ) {
  }

  /**
   * Rewrites redirect-based links in rich text.
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
   * Rewrites redirect-based links in link fields.
   */
  public function normalizeRedirectLinkUri(string $uri): array {
    $resolved = $this->resolveRedirectTarget($uri);
    if (!$resolved['changed']) {
      return [
        'changed' => FALSE,
        'uri' => $uri,
      ];
    }

    $query = (string) parse_url((string) $resolved['target_path'], PHP_URL_QUERY);
    $fragment = (string) parse_url((string) $resolved['target_path'], PHP_URL_FRAGMENT);
    if (!empty($resolved['entity']) && $query === '' && $fragment === '') {
      return [
        'changed' => TRUE,
        'uri' => 'entity:' . $resolved['entity']->getEntityTypeId() . '/' . $resolved['entity']->id(),
      ];
    }

    return [
      'changed' => TRUE,
      'uri' => 'internal:' . $resolved['target_path'],
    ];
  }

  /**
   * Rewrites a node/media entity reference target when redirect says so.
   */
  public function normalizeEntityReferenceTarget(string $targetType, int $targetId, int $maxDepth = 10): array {
    if (!in_array($targetType, ['node', 'media'], TRUE) || $targetId <= 0) {
      return ['changed' => FALSE, 'reason' => 'unsupported_target'];
    }
    $entity = $this->entityTypeManager->getStorage($targetType)->load($targetId);
    if (!$entity instanceof EntityInterface) {
      return ['changed' => FALSE, 'reason' => 'missing_entity'];
    }

    $matches = [];
    foreach ($this->buildReferenceSourcePaths($targetType, $targetId) as $sourcePath) {
      // Guard: if this non-canonical source path currently resolves to the same
      // referenced entity, treat it as an active alias and do not remap refs
      // from redirects that might be stale/inactive for route resolution.
      if (
        !$this->isCanonicalEntityPath($targetType, $targetId, $sourcePath) &&
        $this->pathResolvesToEntity($sourcePath, $targetType, $targetId)
      ) {
        continue;
      }
      $resolved = $this->resolveStrictRedirectEntityTarget($sourcePath, $targetType, $maxDepth);
      if (!$resolved['changed']) {
        continue;
      }
      $matches[$resolved['target_entity_id']] = $resolved;
    }

    if (count($matches) !== 1) {
      return ['changed' => FALSE, 'reason' => count($matches) > 1 ? 'ambiguous_target' : 'no_match'];
    }

    $resolved = reset($matches);
    if (!$resolved || (int) $resolved['target_entity_id'] === $targetId) {
      return ['changed' => FALSE, 'reason' => 'same_target'];
    }

    return [
      'changed' => TRUE,
      'target_entity_type' => $targetType,
      'target_entity_id' => (int) $resolved['target_entity_id'],
      'source_path' => (string) $resolved['source_path'],
      'target_path' => (string) $resolved['target_path'],
    ];
  }

  /**
   * Returns TRUE when this path is the canonical entity route.
   */
  private function isCanonicalEntityPath(string $targetType, int $targetId, string $path): bool {
    $normalized = '/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    $canonical = match ($targetType) {
      'node' => '/node/' . $targetId,
      'media' => '/media/' . $targetId,
      default => '',
    };
    return $normalized === $canonical;
  }

  /**
   * Returns TRUE if path resolves to the supplied entity.
   */
  private function pathResolvesToEntity(string $path, string $targetType, int $targetId): bool {
    $entity = $this->resolvePathToEntity($path);
    return $entity instanceof EntityInterface
      && $entity->getEntityTypeId() === $targetType
      && (int) $entity->id() === $targetId;
  }

  /**
   * Follows redirect chain and returns the final local path.
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

    $entity = $this->resolvePathToEntity($finalPath);
    $node = $entity && $entity->getEntityTypeId() === 'node' ? $entity : NULL;

    return [
      'changed' => TRUE,
      'target_path' => $targetPath,
      'entity' => $entity,
      'node' => $node,
    ];
  }

  /**
   * Resolves one source path to exactly one node/media target.
   */
  private function resolveStrictRedirectEntityTarget(string $sourcePath, string $targetType, int $maxDepth): array {
    $current = ltrim($sourcePath, '/');
    $visited = [];
    $hops = 0;

    for ($i = 0; $i < $maxDepth; $i++) {
      if (isset($visited[$current])) {
        return ['changed' => FALSE, 'reason' => 'loop_detected'];
      }
      $visited[$current] = TRUE;

      $redirects = $this->loadRedirectsBySourcePath($current, 2);
      if (count($redirects) > 1) {
        return ['changed' => FALSE, 'reason' => 'ambiguous_redirects'];
      }
      if ($redirects === []) {
        break;
      }
      $hops++;
      $next = $this->extractLocalPath($redirects[0]->getRedirectUrl()->toString());
      if (!$next) {
        return ['changed' => FALSE, 'reason' => 'non_local_target'];
      }
      $current = ltrim($next, '/');
    }

    if ($hops === 0) {
      return ['changed' => FALSE, 'reason' => 'no_redirect'];
    }

    $finalPath = '/' . ltrim($current, '/');
    $entity = $this->resolvePathToEntity($finalPath);
    if (!$entity || $entity->getEntityTypeId() !== $targetType) {
      return ['changed' => FALSE, 'reason' => 'unresolved_or_wrong_type'];
    }

    return [
      'changed' => TRUE,
      'target_entity_id' => (int) $entity->id(),
      'source_path' => '/' . ltrim($sourcePath, '/'),
      'target_path' => $finalPath,
    ];
  }

  /**
   * Builds candidate local source paths for entity-reference resolution.
   *
   * @return string[]
   *   De-duplicated local paths, leading slash prefixed.
   */
  private function buildReferenceSourcePaths(string $targetType, int $targetId): array {
    $paths = [];
    if ($targetType === 'node') {
      $canonical = '/node/' . $targetId;
      $paths[] = $canonical;
      $paths[] = $this->pathAliasManager->getAliasByPath($canonical);
    }
    elseif ($targetType === 'media') {
      $canonical = '/media/' . $targetId;
      $paths[] = $canonical;
      $paths[] = $this->pathAliasManager->getAliasByPath($canonical);
    }

    $normalized = [];
    foreach ($paths as $path) {
      if (!is_string($path) || $path === '') {
        continue;
      }
      $normalizedPath = '/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/');
      $normalized[$normalizedPath] = $normalizedPath;
    }
    return array_values($normalized);
  }

  /**
   * Resolves a local path to node/media entity when possible.
   */
  private function resolvePathToEntity(string $path): ?EntityInterface {
    $candidatePaths = [
      '/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/'),
    ];
    $internal = $this->pathAliasManager->getPathByAlias($candidatePaths[0]);
    if ($internal !== '') {
      $candidatePaths[] = '/' . ltrim((string) parse_url($internal, PHP_URL_PATH), '/');
    }

    $candidatePaths = array_unique($candidatePaths);
    foreach ($candidatePaths as $candidate) {
      if (preg_match('/^\/node\/(\d+)$/', $candidate, $matches)) {
        $node = $this->entityTypeManager->getStorage('node')->load((int) $matches[1]);
        if ($node instanceof EntityInterface) {
          return $node;
        }
      }
      if (preg_match('/^\/media\/(\d+)(?:\/download)?$/', $candidate, $matches)) {
        $media = $this->entityTypeManager->getStorage('media')->load((int) $matches[1]);
        if ($media instanceof EntityInterface) {
          return $media;
        }
      }
    }
    return NULL;
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

  /**
   * Loads redirects by source path, tolerating slash differences.
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   Matched redirects (possibly empty), up to $limit.
   */
  private function loadRedirectsBySourcePath(string $sourcePath, int $limit = 10): array {
    $sourcePath = trim($sourcePath);
    if ($sourcePath === '') {
      return [];
    }
    $limit = max(1, $limit);
    $candidates = [
      ltrim($sourcePath, '/'),
      '/' . ltrim($sourcePath, '/'),
    ];

    $storage = $this->entityTypeManager->getStorage('redirect');
    foreach ($candidates as $candidate) {
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->range(0, $limit);
      $group = $query->orConditionGroup()
        ->condition('redirect_source.path', $candidate)
        ->condition('redirect_source__path', $candidate);
      $ids = $query->condition($group)->execute();
      if (!$ids) {
        continue;
      }
      $results = [];
      foreach ($ids as $id) {
        $redirect = $storage->load((int) $id);
        if ($redirect instanceof Redirect) {
          $results[] = $redirect;
        }
      }
      return $results;
    }
    return [];
  }

}
